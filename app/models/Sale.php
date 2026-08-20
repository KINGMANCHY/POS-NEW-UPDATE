<?php
/**
 * app/models/Sale.php
 * -----------------------------------------------------------------------
 * Data access for the POS Screen: product/customer lookups used while
 * building a cart, checkout (Sales + SaleDetails + Inventory decrement
 * in one transaction), held/resumed sales, and receipt retrieval.
 *
 * Security note: line prices, tax, and discount are ALWAYS recomputed
 * here from the authoritative Products row - the client only ever sends
 * product_id + quantity. This mirrors the "never trust client input"
 * approach used throughout the rest of the app (see ProductController).
 */

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class Sale
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    // -------------------------------------------------------------
    // Product / customer lookups for the cart-building UI
    // -------------------------------------------------------------

    /**
     * Active, in-stock-aware product list for the POS grid.
     */
    /**
     * @param string $searchBy Restricts which column `$search` matches against:
     *   'barcode' -> p.barcode only, 'code' -> p.product_code (product number) only,
     *   'name' -> p.product_name only. Any other value (default '') searches
     *   name/code/barcode/brand together, as before - used by the plain
     *   search box and the keyboard-wedge scanner input.
     */
    public function searchProducts(string $search, ?int $categoryId, int $page = 1, int $perPage = 48, string $searchBy = ''): array
    {
        // POS only lists sellable stock; out-of-stock items remain visible in Product management.
        $conditions = ['p.is_active = 1', 'ISNULL(i.quantity_on_hand, 0) > 0'];
        $params = [];

        if ($search !== '' && $searchBy === 'barcode') {
            $conditions[] = 'p.barcode LIKE :search_barcode';
            $params[':search_barcode'] = '%' . $search . '%';
        } elseif ($search !== '' && $searchBy === 'code') {
            $conditions[] = 'p.product_code LIKE :search_code';
            $params[':search_code'] = '%' . $search . '%';
        } elseif ($search !== '' && $searchBy === 'name') {
            $conditions[] = 'p.product_name LIKE :search_name';
            $params[':search_name'] = '%' . $search . '%';
        } elseif ($search !== '') {
            $conditions[] = '(p.product_name LIKE :search_name OR p.product_code LIKE :search_code OR p.barcode LIKE :search_barcode OR p.brand LIKE :search_brand)';
            $like = '%' . $search . '%';
            $params[':search_name'] = $like;
            $params[':search_code'] = $like;
            $params[':search_barcode'] = $like;
            $params[':search_brand'] = $like;
        }
        if ($categoryId) {
            $conditions[] = 'p.category_id = :category_id';
            $params[':category_id'] = $categoryId;
        }
        $where = 'WHERE ' . implode(' AND ', $conditions);

        $page = max(1, $page); $perPage = max(12, min(100, $perPage));
        $countStmt = $this->db->prepare("SELECT COUNT(*) AS total FROM Products p LEFT JOIN Inventory i ON i.product_id = p.product_id {$where}");
        foreach ($params as $key => $value) $countStmt->bindValue($key, $value, PDO::PARAM_STR);
        $countStmt->execute(); $total = (int) $countStmt->fetch()['total'];
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT p.product_id, p.product_code, p.barcode, p.product_name, p.brand,
                       p.image_path, p.selling_price, p.tax_rate, p.discount_rate, p.unit,
                       p.category_id, c.category_name,
                       ISNULL(i.quantity_on_hand, 0) AS quantity_on_hand
                FROM Products p
                LEFT JOIN Categories c ON c.category_id = p.category_id
                LEFT JOIN Inventory i ON i.product_id = p.product_id
                {$where}
                ORDER BY p.product_name ASC OFFSET :offset ROWS FETCH NEXT :per_page ROWS ONLY";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':per_page', $perPage, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['image_url'] = $row['image_path'] ? UPLOAD_URL . $row['image_path'] : null;
        }
        unset($row);
        return ['rows' => $rows, 'page' => $page, 'total_pages' => (int) ceil($total / $perPage)];
    }

    public function findByBarcode(string $barcode): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT p.product_id, p.product_code, p.barcode, p.product_name, p.brand,
                    p.image_path, p.selling_price, p.tax_rate, p.discount_rate, p.unit, p.is_active,
                    ISNULL(i.quantity_on_hand, 0) AS quantity_on_hand
             FROM Products p
             LEFT JOIN Inventory i ON i.product_id = p.product_id
             WHERE p.barcode = :barcode"
        );
        $stmt->bindValue(':barcode', $barcode, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row) {
            $row['image_url'] = $row['image_path'] ? UPLOAD_URL . $row['image_path'] : null;
        }
        return $row ?: null;
    }

    public function searchCustomers(string $search, int $limit = 10): array
    {
        $sql = "SELECT TOP {$limit} customer_id, full_name, phone, loyalty_points
                FROM Customers
                WHERE is_active = 1 AND (full_name LIKE :search_name OR phone LIKE :search_phone)
                ORDER BY full_name ASC";
        $stmt = $this->db->prepare($sql);
        $like = '%' . $search . '%';
        $stmt->bindValue(':search_name', $like, PDO::PARAM_STR);
        $stmt->bindValue(':search_phone', $like, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // -------------------------------------------------------------
    // Pricing (shared by hold + checkout)
    // -------------------------------------------------------------

    /**
     * Re-prices a client-submitted cart against live Products data.
     * $items is an array of ['product_id' => int, 'quantity' => int].
     * Returns [priced_items, totals, error]. On error, priced_items/
     * totals are empty and $error explains what went wrong.
     */
    private function priceCart(array $items, float $manualDiscount): array
    {
        if (empty($items)) {
            return [[], [], 'Cart is empty.'];
        }

        $priced = [];
        $subtotal = 0.0;
        $taxTotal = 0.0;
        $lineDiscountTotal = 0.0;

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $qty = (int) ($item['quantity'] ?? 0);

            if ($productId <= 0 || $qty <= 0 || $qty > 10000) {
                return [[], [], 'Invalid item in cart.'];
            }

            $stmt = $this->db->prepare(
                "SELECT p.product_id, p.product_name, p.selling_price, p.tax_rate, p.discount_rate,
                        p.is_active, ISNULL(i.quantity_on_hand, 0) AS quantity_on_hand
                 FROM Products p
                 LEFT JOIN Inventory i ON i.product_id = p.product_id
                 WHERE p.product_id = :id"
            );
            $stmt->bindValue(':id', $productId, PDO::PARAM_INT);
            $stmt->execute();
            $product = $stmt->fetch();

            if (!$product || !$product['is_active']) {
                return [[], [], "One of the items in the cart is no longer available."];
            }
            if ($qty > (int) $product['quantity_on_hand']) {
                return [[], [], "Not enough stock for \"{$product['product_name']}\" (only {$product['quantity_on_hand']} left)."];
            }

            $unitPrice = (float) $product['selling_price'];
            $lineSubtotal = $unitPrice * $qty;
            $lineTax = round($lineSubtotal * ((float) $product['tax_rate'] / 100), 2);
            $lineDiscount = round($lineSubtotal * ((float) $product['discount_rate'] / 100), 2);
            $lineTotal = $lineSubtotal + $lineTax - $lineDiscount;

            $subtotal += $lineSubtotal;
            $taxTotal += $lineTax;
            $lineDiscountTotal += $lineDiscount;

            $priced[] = [
                'product_id'    => $productId,
                'product_name'  => $product['product_name'],
                'quantity'      => $qty,
                'unit_price'    => $unitPrice,
                'tax_amount'    => $lineTax,
                'discount_amount' => $lineDiscount,
                'line_total'    => $lineTotal,
            ];
        }

        $manualDiscount = max(0.0, $manualDiscount);
        $discountTotal = $lineDiscountTotal + $manualDiscount;
        $grandTotal = max(0.0, $subtotal + $taxTotal - $discountTotal);

        $totals = [
            'subtotal'        => round($subtotal, 2),
            'tax_total'       => round($taxTotal, 2),
            'discount_total'  => round($discountTotal, 2),
            'grand_total'     => round($grandTotal, 2),
        ];

        return [$priced, $totals, null];
    }

    public function generateInvoiceNumber(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $candidate = Helper::generateCode('INV');
            $stmt = $this->db->prepare("SELECT COUNT(*) AS c FROM Sales WHERE invoice_no = :inv");
            $stmt->bindValue(':inv', $candidate, PDO::PARAM_STR);
            $stmt->execute();
            if ((int) $stmt->fetch()['c'] === 0) {
                return $candidate;
            }
        }
        // Astronomically unlikely fallback
        return Helper::generateCode('INV') . '-' . random_int(1000, 9999);
    }

    // -------------------------------------------------------------
    // Checkout (completed sale)
    // -------------------------------------------------------------

    /**
     * Prices, validates stock, and commits a completed sale in one
     * transaction: Sales header, SaleDetails rows, Inventory decrement.
     * Returns [saleId|null, error|null].
     */
    public function checkout(array $items, array $header, int $userId): array
    {
        $manualDiscount = max(0, (float) ($header['manual_discount'] ?? 0));
        $redeemedPoints = max(0, (int) ($header['loyalty_points_redeemed'] ?? 0));
        $settings = (new Setting())->getAll();
        $pointValue = ($settings['loyalty_point_value'] ?? '') === '' ? 1.0 : (float) $settings['loyalty_point_value'];

        if ($redeemedPoints > 0 && empty($header['customer_id'])) {
            return [null, 'Choose a customer before redeeming loyalty points.'];
        }
        if ($redeemedPoints > 0 && $pointValue <= 0) {
            return [null, 'Loyalty point redemption is disabled in Settings.'];
        }

        [$basePriced, $baseTotals, $error] = $this->priceCart($items, $manualDiscount);
        if ($error) {
            return [null, $error];
        }
        // Compare in centavos so a valid decimal value such as 0.10 x 10
        // cannot be rejected because of binary floating-point precision.
        $pointValueCents = max(0, (int) round($pointValue * 100));
        $loyaltyDiscountCents = $redeemedPoints * $pointValueCents;
        $baseGrandTotalCents = (int) round($baseTotals['grand_total'] * 100);
        if ($loyaltyDiscountCents > $baseGrandTotalCents) {
            return [null, 'The selected loyalty points exceed the amount due.'];
        }
        $loyaltyDiscount = $loyaltyDiscountCents / 100;
        [$priced, $totals, $error] = $this->priceCart($items, $manualDiscount + $loyaltyDiscount);
        if ($error) {
            return [null, $error];
        }

        $allowedPaymentMethods = ['cash', 'gcash', 'maya', 'card', 'check'];
        $payments = is_array($header['payments'] ?? null) ? $header['payments'] : [];
        if (!$payments) {
            $payments[] = [
                'method' => $header['payment_method'] ?? 'cash',
                'amount' => $header['amount_paid'] ?? $totals['grand_total'],
                'reference' => $header['payment_reference'] ?? '',
            ];
        }
        $normalisedPayments = [];
        $amountPaid = 0.0;
        $hasCash = false;
        $seenReferences = [];
        foreach ($payments as $payment) {
            $method = strtolower((string) ($payment['method'] ?? ''));
            $amount = round((float) ($payment['amount'] ?? 0), 2);
            $reference = trim((string) ($payment['reference'] ?? ''));
            if (!in_array($method, $allowedPaymentMethods, true) || $amount <= 0) {
                return [null, 'Each payment must have a valid method and amount.'];
            }
            if ($method !== 'cash' && $reference === '') {
                return [null, 'Please enter reference details for every non-cash payment.'];
            }
            $referenceKey = strtolower($reference);
            if ($method !== 'cash' && isset($seenReferences[$referenceKey])) {
                return [null, 'The same payment reference was entered more than once.'];
            }
            if ($method !== 'cash') $seenReferences[$referenceKey] = true;
            $normalisedPayments[] = ['method' => $method, 'amount' => $amount, 'reference' => $reference];
            $amountPaid += $amount;
            $hasCash = $hasCash || $method === 'cash';
        }
        $amountPaid = round($amountPaid, 2);
        if ($amountPaid < $totals['grand_total']) {
            return [null, 'The combined payments are less than the total due.'];
        }
        if (!$hasCash && $amountPaid > $totals['grand_total']) {
            return [null, 'A non-cash payment cannot be more than the total due.'];
        }
        $paymentMethod = count($normalisedPayments) === 1 ? $normalisedPayments[0]['method'] : 'multiple';
        $paymentReference = count($normalisedPayments) === 1 ? ($normalisedPayments[0]['reference'] ?: null) : null;
        $changeDue = $hasCash ? round($amountPaid - $totals['grand_total'], 2) : 0.0;
        $spendAmount = (float) ($settings['loyalty_spend_amount'] ?? 1000);
        $pointsPerSpend = (int) ($settings['loyalty_points_awarded'] ?? 10);
        $earnedPoints = !empty($header['customer_id']) && $spendAmount > 0 && $pointsPerSpend > 0
            ? (int) floor($totals['grand_total'] / $spendAmount) * $pointsPerSpend : 0;

        $this->db->beginTransaction();
        try {
            // A reference number identifies a completed non-cash payment.
            // Refuse a duplicate before inventory or points are changed.
            foreach ($normalisedPayments as $payment) {
                if ($payment['method'] === 'cash') continue;
                $referenceStmt = $this->db->prepare(
                    "SELECT TOP 1 invoice_no FROM Sales WHERE payment_reference = :reference
                     UNION ALL
                     SELECT TOP 1 s.invoice_no FROM SalePayments sp
                     INNER JOIN Sales s ON s.sale_id = sp.sale_id
                     WHERE sp.payment_reference = :reference2"
                );
                $referenceStmt->execute([':reference' => $payment['reference'], ':reference2' => $payment['reference']]);
                $existing = $referenceStmt->fetch();
                if ($existing) {
                    throw new Exception("Payment reference already exists on invoice {$existing['invoice_no']}.");
                }
            }
            // Lock the customer's balance until the sale commits so the same
            // points cannot be redeemed by two POS terminals at once.
            if ($redeemedPoints > 0) {
                $customerStmt = $this->db->prepare("SELECT loyalty_points FROM Customers WITH (UPDLOCK, HOLDLOCK) WHERE customer_id = :id AND is_active = 1");
                $customerStmt->execute([':id' => $header['customer_id']]);
                $customer = $customerStmt->fetch();
                if (!$customer) {
                    throw new Exception('The selected customer is no longer active.');
                }
                if ((int) $customer['loyalty_points'] < $redeemedPoints) {
                    throw new Exception('The customer no longer has enough loyalty points.');
                }
            }
            $invoiceNo = $this->generateInvoiceNumber();

            $stmt = $this->db->prepare(
                "INSERT INTO Sales
                    (invoice_no, customer_id, user_id, branch_id, subtotal, tax_total, discount_total,
                     grand_total, amount_paid, change_due, payment_method, payment_reference,
                     loyalty_points_earned, loyalty_points_redeemed, status, created_at)
                 OUTPUT INSERTED.sale_id
                 VALUES
                    (:invoice_no, :customer_id, :user_id, :branch_id, :subtotal, :tax_total, :discount_total,
                     :grand_total, :amount_paid, :change_due, :payment_method, :payment_reference,
                     :loyalty_points_earned, :loyalty_points_redeemed, 'completed', GETDATE())"
            );
            $stmt->bindValue(':invoice_no', $invoiceNo, PDO::PARAM_STR);
            $stmt->bindValue(':customer_id', $header['customer_id'] ?: null, $header['customer_id'] ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $branchId = $header['branch_id'] ?? null;
            $stmt->bindValue(':branch_id', $branchId, $branchId ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':subtotal', $totals['subtotal']);
            $stmt->bindValue(':tax_total', $totals['tax_total']);
            $stmt->bindValue(':discount_total', $totals['discount_total']);
            $stmt->bindValue(':grand_total', $totals['grand_total']);
            $stmt->bindValue(':amount_paid', $amountPaid);
            $stmt->bindValue(':change_due', $changeDue);
            $stmt->bindValue(':payment_method', $paymentMethod, PDO::PARAM_STR);
            $stmt->bindValue(':payment_reference', $paymentReference, $paymentReference === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':loyalty_points_earned', $earnedPoints, PDO::PARAM_INT);
            $stmt->bindValue(':loyalty_points_redeemed', $redeemedPoints, PDO::PARAM_INT);
            $stmt->execute();
            $saleId = (int) $stmt->fetch()['sale_id'];

            $paymentStmt = $this->db->prepare(
                'INSERT INTO SalePayments (sale_id, payment_method, amount, payment_reference) VALUES (:sale_id, :method, :amount, :reference)'
            );
            foreach ($normalisedPayments as $payment) {
                $paymentStmt->execute([
                    ':sale_id' => $saleId,
                    ':method' => $payment['method'],
                    ':amount' => $payment['amount'],
                    ':reference' => $payment['reference'] ?: null,
                ]);
            }

            $this->insertSaleDetails($saleId, $priced);

            foreach ($priced as $line) {
                $upd = $this->db->prepare(
                    "UPDATE Inventory SET quantity_on_hand = quantity_on_hand - :qty, updated_at = GETDATE()
                     WHERE product_id = :pid AND quantity_on_hand >= :qty2"
                );
                $upd->bindValue(':qty', $line['quantity'], PDO::PARAM_INT);
                $upd->bindValue(':qty2', $line['quantity'], PDO::PARAM_INT);
                $upd->bindValue(':pid', $line['product_id'], PDO::PARAM_INT);
                $upd->execute();
                if ($upd->rowCount() !== 1) {
                    throw new Exception("Not enough stock for \"{$line['product_name']}\".");
                }

                InventoryMovement::log(
                    $line['product_id'], 'sale', -$line['quantity'],
                    'sale', $saleId, $invoiceNo, null, $userId
                );
            }

            // Configurable loyalty earning; only named customers participate.
            if (!empty($header['customer_id'])) {
                if ($redeemedPoints > 0) {
                    $redeemStmt = $this->db->prepare("UPDATE Customers SET loyalty_points = loyalty_points - :points WHERE customer_id = :id");
                    $redeemStmt->execute([':points' => $redeemedPoints, ':id' => $header['customer_id']]);
                }
                if ($earnedPoints > 0) {
                    $pointStmt = $this->db->prepare("UPDATE Customers SET loyalty_points = loyalty_points + :points WHERE customer_id = :id");
                    $pointStmt->execute([':points' => $earnedPoints, ':id' => $header['customer_id']]);
                }
            }

            $this->db->commit();
            return [$saleId, null];
        } catch (Exception $e) {
            $this->db->rollBack();
            return [null, $e->getMessage() ?: 'Checkout failed. Please try again.'];
        }
    }

    // -------------------------------------------------------------
    // Held sales
    // -------------------------------------------------------------

    /**
     * Saves the current cart as a held sale (no inventory change).
     * Returns [saleId|null, error|null].
     */
    public function hold(array $items, array $header, int $userId): array
    {
        [$priced, $totals, $error] = $this->priceCart($items, (float) ($header['manual_discount'] ?? 0));
        if ($error) {
            return [null, $error];
        }

        $this->db->beginTransaction();
        try {
            $invoiceNo = $this->generateInvoiceNumber();

            $stmt = $this->db->prepare(
                "INSERT INTO Sales
                    (invoice_no, customer_id, user_id, subtotal, tax_total, discount_total,
                     grand_total, amount_paid, change_due, payment_method, status, created_at)
                 OUTPUT INSERTED.sale_id
                 VALUES
                    (:invoice_no, :customer_id, :user_id, :subtotal, :tax_total, :discount_total,
                     :grand_total, 0, 0, 'cash', 'held', GETDATE())"
            );
            $stmt->bindValue(':invoice_no', $invoiceNo, PDO::PARAM_STR);
            $stmt->bindValue(':customer_id', $header['customer_id'] ?: null, $header['customer_id'] ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':subtotal', $totals['subtotal']);
            $stmt->bindValue(':tax_total', $totals['tax_total']);
            $stmt->bindValue(':discount_total', $totals['discount_total']);
            $stmt->bindValue(':grand_total', $totals['grand_total']);
            $stmt->execute();
            $saleId = (int) $stmt->fetch()['sale_id'];

            $this->insertSaleDetails($saleId, $priced);

            $this->db->commit();
            return [$saleId, null];
        } catch (Exception $e) {
            $this->db->rollBack();
            return [null, 'Could not hold this sale. Please try again.'];
        }
    }

    public function listHeld(): array
    {
        $sql = "SELECT s.sale_id, s.invoice_no, s.grand_total, s.created_at,
                       c.full_name AS customer_name, u.full_name AS cashier_name,
                       (SELECT COUNT(*) FROM SaleDetails sd WHERE sd.sale_id = s.sale_id) AS item_count
                FROM Sales s
                LEFT JOIN Customers c ON c.customer_id = s.customer_id
                INNER JOIN Users u ON u.user_id = s.user_id
                WHERE s.status = 'held'
                ORDER BY s.created_at DESC";
        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Loads a held sale's items back into cart shape (for the client to
     * repopulate its cart) but does NOT delete it - the caller deletes
     * only after the client confirms the cart was restored, via delete().
     */
    public function getHeldWithItems(int $saleId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT s.sale_id, s.invoice_no, s.customer_id, s.discount_total, c.full_name AS customer_name
             FROM Sales s
             LEFT JOIN Customers c ON c.customer_id = s.customer_id
             WHERE s.sale_id = :id AND s.status = 'held'"
        );
        $stmt->bindValue(':id', $saleId, PDO::PARAM_INT);
        $stmt->execute();
        $sale = $stmt->fetch();
        if (!$sale) {
            return null;
        }

        $itemStmt = $this->db->prepare(
            "SELECT sd.product_id, sd.quantity, p.product_name, p.product_code, p.unit,
                    p.selling_price, ISNULL(i.quantity_on_hand, 0) AS quantity_on_hand
             FROM SaleDetails sd
             INNER JOIN Products p ON p.product_id = sd.product_id
             LEFT JOIN Inventory i ON i.product_id = sd.product_id
             WHERE sd.sale_id = :id"
        );
        $itemStmt->bindValue(':id', $saleId, PDO::PARAM_INT);
        $itemStmt->execute();
        $sale['items'] = $itemStmt->fetchAll();

        return $sale;
    }

    public function delete(int $saleId): bool
    {
        // SaleDetails cascades via FK ON DELETE CASCADE.
        $stmt = $this->db->prepare("DELETE FROM Sales WHERE sale_id = :id AND status = 'held'");
        $stmt->bindValue(':id', $saleId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    // -------------------------------------------------------------
    // Sales history (Phase 5)
    // -------------------------------------------------------------

    private const SORTABLE = ['created_at', 'invoice_no', 'grand_total', 'status'];

    /** Adds branch scoping to a WHERE clause when a branch filter is set. */
    private function applyBranchFilter(array &$conditions, array &$params, ?int $branchId): void
    {
        if ($branchId === null) {
            return;
        }
        $conditions[] = 's.branch_id = :branch_id';
        $params[':branch_id'] = $branchId;
    }

    public function paginate(array $filters, string $sortBy, string $sortDir, int $page, int $perPage): array
    {
        $sortBy  = in_array($sortBy, self::SORTABLE, true) ? $sortBy : 'created_at';
        $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';
        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset  = ($page - 1) * $perPage;

        $conditions = [];
        $params = [];

        if (!empty($filters['search'])) {
            $conditions[] = '(s.invoice_no LIKE :search_invoice OR c.full_name LIKE :search_customer OR u.full_name LIKE :search_cashier)';
            $like = '%' . $filters['search'] . '%';
            $params[':search_invoice'] = $like;
            $params[':search_customer'] = $like;
            $params[':search_cashier'] = $like;
        }
        if (!empty($filters['status'])) {
            $conditions[] = 's.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $conditions[] = 's.created_at >= :date_from';
            $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $conditions[] = 's.created_at <= :date_to';
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }
        $this->applyBranchFilter($conditions, $params, $filters['branch_id'] ?? null);
        $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

        $base = "FROM Sales s
                 LEFT JOIN Customers c ON c.customer_id = s.customer_id
                 INNER JOIN Users u ON u.user_id = s.user_id
                 LEFT JOIN Branches b ON b.branch_id = s.branch_id
                 {$where}";

        $countStmt = $this->db->prepare("SELECT COUNT(*) AS total {$base}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];

        $sql = "SELECT s.sale_id, s.invoice_no, s.grand_total, s.payment_method, s.status, s.created_at,
                       s.branch_id, c.full_name AS customer_name, u.full_name AS cashier_name,
                       b.branch_code, b.branch_name,
                       (SELECT COUNT(*) FROM SaleDetails sd WHERE sd.sale_id = s.sale_id) AS item_count
                {$base}
                ORDER BY s.{$sortBy} {$sortDir}
                OFFSET :offset ROWS FETCH NEXT :perPage ROWS ONLY";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'rows' => $stmt->fetchAll(), 'total' => $total, 'page' => $page,
            'per_page' => $perPage, 'total_pages' => (int) ceil($total / $perPage),
        ];
    }

    /** Complete filtered rows for an export (not restricted by the UI page size). */
    public function exportRows(array $filters): array
    {
        $conditions = []; $params = [];
        if (!empty($filters['search'])) { $conditions[] = '(s.invoice_no LIKE :search OR c.full_name LIKE :search OR u.full_name LIKE :search)'; $params[':search'] = '%' . $filters['search'] . '%'; }
        if (!empty($filters['status'])) { $conditions[] = 's.status = :status'; $params[':status'] = $filters['status']; }
        if (!empty($filters['date_from'])) { $conditions[] = 's.created_at >= :date_from'; $params[':date_from'] = $filters['date_from'] . ' 00:00:00'; }
        if (!empty($filters['date_to'])) { $conditions[] = 's.created_at <= :date_to'; $params[':date_to'] = $filters['date_to'] . ' 23:59:59'; }
        $this->applyBranchFilter($conditions, $params, $filters['branch_id'] ?? null);
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $stmt = $this->db->prepare("SELECT s.invoice_no, s.created_at, s.grand_total, s.payment_method, s.status, c.full_name AS customer_name, u.full_name AS cashier_name, b.branch_name, (SELECT COUNT(*) FROM SaleDetails sd WHERE sd.sale_id = s.sale_id) AS item_count FROM Sales s LEFT JOIN Customers c ON c.customer_id = s.customer_id INNER JOIN Users u ON u.user_id = s.user_id LEFT JOIN Branches b ON b.branch_id = s.branch_id {$where} ORDER BY s.created_at DESC");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Voids a completed sale and restores the stock it deducted.
     * Refuses to void anything that isn't currently 'completed' (a held
     * sale isn't checked out yet, and an already-voided sale can't be
     * voided twice). Returns an error string on failure, null on success.
     */
    public function void(int $saleId, ?int $userId = null): ?string
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT status, invoice_no FROM Sales WHERE sale_id = :id");
            $stmt->bindValue(':id', $saleId, PDO::PARAM_INT);
            $stmt->execute();
            $sale = $stmt->fetch();

            if (!$sale) {
                $this->db->rollBack();
                return 'Sale not found.';
            }
            if ($sale['status'] !== 'completed') {
                $this->db->rollBack();
                return 'Only completed sales can be voided.';
            }

            $itemStmt = $this->db->prepare("SELECT product_id, quantity FROM SaleDetails WHERE sale_id = :id");
            $itemStmt->bindValue(':id', $saleId, PDO::PARAM_INT);
            $itemStmt->execute();

            $restoreStmt = $this->db->prepare(
                "UPDATE Inventory SET quantity_on_hand = quantity_on_hand + :qty, updated_at = GETDATE() WHERE product_id = :pid"
            );
            foreach ($itemStmt->fetchAll() as $line) {
                $restoreStmt->bindValue(':qty', $line['quantity'], PDO::PARAM_INT);
                $restoreStmt->bindValue(':pid', $line['product_id'], PDO::PARAM_INT);
                $restoreStmt->execute();

                InventoryMovement::log(
                    $line['product_id'], 'sale_void', (int) $line['quantity'],
                    'sale', $saleId, $sale['invoice_no'], null, $userId
                );
            }

            $update = $this->db->prepare("UPDATE Sales SET status = 'voided' WHERE sale_id = :id");
            $update->bindValue(':id', $saleId, PDO::PARAM_INT);
            $update->execute();

            $this->db->commit();
            return null;
        } catch (Exception $e) {
            $this->db->rollBack();
            return 'Could not void this sale. Please try again.';
        }
    }

    // -------------------------------------------------------------
    // Receipt
    // -------------------------------------------------------------

    /** Lightweight lookup used right after checkout() - just the invoice number for a confirmation screen. */
    public function getInvoiceNumber(int $saleId): ?string
    {
        $stmt = $this->db->prepare('SELECT invoice_no FROM Sales WHERE sale_id = :id');
        $stmt->bindValue(':id', $saleId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ? $row['invoice_no'] : null;
    }

    public function getReceipt(int $saleId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT s.*, c.full_name AS customer_name, c.loyalty_points AS customer_loyalty_points, u.full_name AS cashier_name
             FROM Sales s
             LEFT JOIN Customers c ON c.customer_id = s.customer_id
             INNER JOIN Users u ON u.user_id = s.user_id
             WHERE s.sale_id = :id"
        );
        $stmt->bindValue(':id', $saleId, PDO::PARAM_INT);
        $stmt->execute();
        $sale = $stmt->fetch();
        if (!$sale) {
            return null;
        }

        $itemStmt = $this->db->prepare(
            "SELECT sd.quantity, sd.unit_price, sd.tax_amount, sd.discount_amount, sd.line_total,
                    p.product_name, p.unit
             FROM SaleDetails sd
             INNER JOIN Products p ON p.product_id = sd.product_id
             WHERE sd.sale_id = :id"
        );
        $itemStmt->bindValue(':id', $saleId, PDO::PARAM_INT);
        $itemStmt->execute();
        $sale['items'] = $itemStmt->fetchAll();

        $paymentStmt = $this->db->prepare('SELECT payment_method, amount, payment_reference FROM SalePayments WHERE sale_id = :id ORDER BY sale_payment_id ASC');
        $paymentStmt->execute([':id' => $saleId]);
        $sale['payments'] = $paymentStmt->fetchAll();
        // Sales created before split payments were introduced still have a
        // complete receipt using their original header fields.
        if (!$sale['payments']) {
            $sale['payments'] = [[
                'payment_method' => $sale['payment_method'],
                'amount' => $sale['amount_paid'],
                'payment_reference' => $sale['payment_reference'],
            ]];
        }

        return $sale;
    }

    private function insertSaleDetails(int $saleId, array $priced): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO SaleDetails (sale_id, product_id, quantity, unit_price, tax_amount, discount_amount, line_total)
             VALUES (:sale_id, :product_id, :quantity, :unit_price, :tax_amount, :discount_amount, :line_total)"
        );
        foreach ($priced as $line) {
            $stmt->execute([
                ':sale_id'          => $saleId,
                ':product_id'       => $line['product_id'],
                ':quantity'         => $line['quantity'],
                ':unit_price'       => $line['unit_price'],
                ':tax_amount'       => $line['tax_amount'],
                ':discount_amount'  => $line['discount_amount'],
                ':line_total'       => $line['line_total'],
            ]);
        }
    }
}
