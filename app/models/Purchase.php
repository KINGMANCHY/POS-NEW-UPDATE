<?php
/**
 * app/models/Purchase.php
 * -----------------------------------------------------------------------
 * Data access for the Purchases module: recording stock received from a
 * supplier. Mirrors Sale.php's shape - a header row + line items - but
 * increments Inventory instead of decrementing it, and (like Sale.php)
 * always recomputes line/total amounts server-side from the submitted
 * quantity/unit_cost pairs rather than trusting a client-sent total.
 */

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class Purchase
{
    private PDO $db;
    private const SORTABLE = ['purchased_at', 'reference_no', 'total_amount', 'status'];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function paginate(array $filters, string $sortBy, string $sortDir, int $page, int $perPage): array
    {
        $sortBy  = in_array($sortBy, self::SORTABLE, true) ? $sortBy : 'purchased_at';
        $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';
        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset  = ($page - 1) * $perPage;

        $conditions = [];
        $params = [];

        if (!empty($filters['search'])) {
            $conditions[] = '(p.reference_no LIKE :search_reference OR s.supplier_name LIKE :search_supplier)';
            $like = '%' . $filters['search'] . '%';
            $params[':search_reference'] = $like;
            $params[':search_supplier'] = $like;
        }
        if (!empty($filters['status'])) {
            $conditions[] = 'p.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['supplier_id'])) {
            $conditions[] = 'p.supplier_id = :supplier_id';
            $params[':supplier_id'] = $filters['supplier_id'];
        }
        if (!empty($filters['date_from'])) {
            $conditions[] = 'p.purchased_at >= :date_from';
            $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $conditions[] = 'p.purchased_at <= :date_to';
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }
        $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

        $base = "FROM Purchases p
                 INNER JOIN Suppliers s ON s.supplier_id = p.supplier_id
                 INNER JOIN Users u ON u.user_id = p.user_id
                 {$where}";

        $countStmt = $this->db->prepare("SELECT COUNT(*) AS total {$base}");
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetch()['total'];

        $sql = "SELECT p.purchase_id, p.reference_no, p.total_amount, p.status, p.purchased_at,
                       s.supplier_name, u.full_name AS recorded_by,
                       (SELECT COUNT(*) FROM PurchaseDetails pd WHERE pd.purchase_id = p.purchase_id) AS item_count
                {$base}
                ORDER BY p.{$sortBy} {$sortDir}
                OFFSET :offset ROWS FETCH NEXT :perPage ROWS ONLY";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'rows' => $stmt->fetchAll(), 'total' => $total, 'page' => $page,
            'per_page' => $perPage, 'total_pages' => (int) ceil($total / $perPage),
        ];
    }

    public function find(int $purchaseId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT p.*, s.supplier_name, u.full_name AS recorded_by
             FROM Purchases p
             INNER JOIN Suppliers s ON s.supplier_id = p.supplier_id
             INNER JOIN Users u ON u.user_id = p.user_id
             WHERE p.purchase_id = :id"
        );
        $stmt->bindValue(':id', $purchaseId, PDO::PARAM_INT);
        $stmt->execute();
        $purchase = $stmt->fetch();
        if (!$purchase) {
            return null;
        }

        $itemStmt = $this->db->prepare(
            "SELECT pd.*, pr.product_name, pr.unit
             FROM PurchaseDetails pd
             INNER JOIN Products pr ON pr.product_id = pd.product_id
             WHERE pd.purchase_id = :id"
        );
        $itemStmt->bindValue(':id', $purchaseId, PDO::PARAM_INT);
        $itemStmt->execute();
        $purchase['items'] = $itemStmt->fetchAll();

        return $purchase;
    }

    /**
     * Records a received purchase: Purchases + PurchaseDetails, bumps
     * Inventory up for every line, and refreshes each product's
     * cost_price to what was just paid (so the next sale's margin
     * reflects the latest cost). All in one transaction.
     *
     * $items is [['product_id' => int, 'quantity' => int, 'unit_cost' => float], ...].
     * Returns [purchaseId|null, error|null].
     */
    public function create(int $supplierId, array $items, int $userId, string $invoiceReceipt = ''): array
    {
        if ($supplierId <= 0) {
            return [null, 'Please select a supplier.'];
        }

        $lines = [];
        $totalAmount = 0.0;

        $productStmt = $this->db->prepare("SELECT product_id, product_name, is_active FROM Products WHERE product_id = :id");

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $quantity  = (int) ($item['quantity'] ?? 0);
            $unitCost  = (float) ($item['unit_cost'] ?? 0);

            if ($productId <= 0 || $quantity <= 0 || $unitCost < 0) {
                return [null, 'Every line needs a product, a positive quantity, and a non-negative unit cost.'];
            }

            $productStmt->bindValue(':id', $productId, PDO::PARAM_INT);
            $productStmt->execute();
            $product = $productStmt->fetch();
            if (!$product) {
                return [null, 'One of the selected products no longer exists.'];
            }

            $lineTotal = round($quantity * $unitCost, 2);
            $totalAmount += $lineTotal;

            $lines[] = [
                'product_id'   => $productId,
                'product_name' => $product['product_name'],
                'quantity'     => $quantity,
                'unit_cost'    => $unitCost,
                'line_total'   => $lineTotal,
            ];
        }

        if (empty($lines)) {
            return [null, 'Add at least one product line.'];
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO Purchases (reference_no, invoice_receipt, supplier_id, user_id, total_amount, status, purchased_at)
                 OUTPUT INSERTED.purchase_id
                 VALUES (:ref, :invoice_receipt, :supplier_id, :user_id, :total, 'received', GETDATE())"
            );
            $stmt->bindValue(':ref', $referenceNo = $this->generateReferenceNo(), PDO::PARAM_STR);
            $stmt->bindValue(':invoice_receipt', $invoiceReceipt ?: null, $invoiceReceipt ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':supplier_id', $supplierId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':total', round($totalAmount, 2), PDO::PARAM_STR);
            $stmt->execute();
            $purchaseId = (int) $stmt->fetch()['purchase_id'];

            $detailStmt = $this->db->prepare(
                "INSERT INTO PurchaseDetails (purchase_id, product_id, quantity, unit_cost, line_total)
                 VALUES (:purchase_id, :product_id, :quantity, :unit_cost, :line_total)"
            );
            $stockStmt = $this->db->prepare(
                "UPDATE Inventory SET quantity_on_hand = quantity_on_hand + :qty, updated_at = GETDATE() WHERE product_id = :pid"
            );
            $costStmt = $this->db->prepare("UPDATE Products SET cost_price = :cost, updated_at = GETDATE() WHERE product_id = :pid");

            foreach ($lines as $line) {
                $detailStmt->bindValue(':purchase_id', $purchaseId, PDO::PARAM_INT);
                $detailStmt->bindValue(':product_id', $line['product_id'], PDO::PARAM_INT);
                $detailStmt->bindValue(':quantity', $line['quantity'], PDO::PARAM_INT);
                $detailStmt->bindValue(':unit_cost', $line['unit_cost'], PDO::PARAM_STR);
                $detailStmt->bindValue(':line_total', $line['line_total'], PDO::PARAM_STR);
                $detailStmt->execute();

                $stockStmt->bindValue(':qty', $line['quantity'], PDO::PARAM_INT);
                $stockStmt->bindValue(':pid', $line['product_id'], PDO::PARAM_INT);
                $stockStmt->execute();

                $costStmt->bindValue(':cost', $line['unit_cost'], PDO::PARAM_STR);
                $costStmt->bindValue(':pid', $line['product_id'], PDO::PARAM_INT);
                $costStmt->execute();

                InventoryMovement::log(
                    $line['product_id'], 'purchase', $line['quantity'],
                    'purchase', $purchaseId, $referenceNo, null, $userId
                );
            }

            $this->db->commit();
            return [$purchaseId, null];
        } catch (Exception $e) {
            $this->db->rollBack();
            return [null, 'Could not record this purchase. Please try again.'];
        }
    }

    /**
     * Cancels a received purchase and reverses the stock it added.
     * Refuses if that would take any product's on-hand quantity negative
     * (i.e. some of that stock has already been sold) - cancel the
     * offsetting sale first, or adjust inventory manually.
     */
    public function cancel(int $purchaseId, ?int $userId = null): ?string
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT status, reference_no FROM Purchases WHERE purchase_id = :id");
            $stmt->bindValue(':id', $purchaseId, PDO::PARAM_INT);
            $stmt->execute();
            $purchase = $stmt->fetch();

            if (!$purchase) {
                $this->db->rollBack();
                return 'Purchase not found.';
            }
            if ($purchase['status'] !== 'received') {
                $this->db->rollBack();
                return 'Only received purchases can be cancelled.';
            }

            $itemStmt = $this->db->prepare("SELECT product_id, quantity FROM PurchaseDetails WHERE purchase_id = :id");
            $itemStmt->bindValue(':id', $purchaseId, PDO::PARAM_INT);
            $itemStmt->execute();
            $lines = $itemStmt->fetchAll();

            $reverseStmt = $this->db->prepare(
                "UPDATE Inventory SET quantity_on_hand = quantity_on_hand - :qty, updated_at = GETDATE()
                 WHERE product_id = :pid AND quantity_on_hand >= :qty2"
            );
            foreach ($lines as $line) {
                $reverseStmt->bindValue(':qty', $line['quantity'], PDO::PARAM_INT);
                $reverseStmt->bindValue(':qty2', $line['quantity'], PDO::PARAM_INT);
                $reverseStmt->bindValue(':pid', $line['product_id'], PDO::PARAM_INT);
                $reverseStmt->execute();
                if ($reverseStmt->rowCount() === 0) {
                    $this->db->rollBack();
                    return 'Cannot cancel - some of this stock has already been sold.';
                }

                InventoryMovement::log(
                    $line['product_id'], 'purchase_cancel', -(int) $line['quantity'],
                    'purchase', $purchaseId, $purchase['reference_no'], null, $userId
                );
            }

            $update = $this->db->prepare("UPDATE Purchases SET status = 'cancelled' WHERE purchase_id = :id");
            $update->bindValue(':id', $purchaseId, PDO::PARAM_INT);
            $update->execute();

            $this->db->commit();
            return null;
        } catch (Exception $e) {
            $this->db->rollBack();
            return 'Could not cancel this purchase. Please try again.';
        }
    }

    private function generateReferenceNo(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $candidate = Helper::generateCode('PO');
            $stmt = $this->db->prepare("SELECT COUNT(*) AS c FROM Purchases WHERE reference_no = :ref");
            $stmt->bindValue(':ref', $candidate, PDO::PARAM_STR);
            $stmt->execute();
            if ((int) $stmt->fetch()['c'] === 0) {
                return $candidate;
            }
        }
        return Helper::generateCode('PO') . '-' . random_int(1000, 9999);
    }
}
