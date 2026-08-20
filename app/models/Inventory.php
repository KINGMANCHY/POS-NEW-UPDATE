<?php
/**
 * app/models/Inventory.php
 * -----------------------------------------------------------------------
 * Data access for the Inventory page: a stock-focused view over
 * Products + Inventory (not a product editor - that's ProductController).
 * Adjustments here are manual counts/corrections, distinct from the
 * automatic increments/decrements Sale.php and Purchase.php already do
 * at checkout and receiving time.
 */

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class Inventory
{
    private PDO $db;
    private const SORTABLE = ['product_name', 'quantity_on_hand', 'stock_alert_qty', 'updated_at'];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function paginate(array $filters, string $sortBy, string $sortDir, int $page, int $perPage): array
    {
        $sortBy  = in_array($sortBy, self::SORTABLE, true) ? $sortBy : 'product_name';
        $sortDir = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';
        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset  = ($page - 1) * $perPage;

        $conditions = ['p.is_active = 1'];
        $params = [];

        if (!empty($filters['search'])) {
            $conditions[] = '(p.product_name LIKE :search_name OR p.product_code LIKE :search_code OR p.barcode LIKE :search_barcode)';
            $like = '%' . $filters['search'] . '%';
            $params[':search_name'] = $like;
            $params[':search_code'] = $like;
            $params[':search_barcode'] = $like;
        }
        if (!empty($filters['category_id'])) {
            $conditions[] = 'p.category_id = :category_id';
            $params[':category_id'] = $filters['category_id'];
        }
        if (!empty($filters['low_stock_only'])) {
            $conditions[] = 'ISNULL(i.quantity_on_hand, 0) <= p.stock_alert_qty';
        }
        $where = 'WHERE ' . implode(' AND ', $conditions);

        $base = "FROM Products p
                 LEFT JOIN Inventory i ON i.product_id = p.product_id
                 LEFT JOIN Categories c ON c.category_id = p.category_id
                 {$where}";

        $countStmt = $this->db->prepare("SELECT COUNT(*) AS total {$base}");
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetch()['total'];

        // quantity_on_hand/updated_at live on Inventory but are exposed here as if
        // they belonged to the product row, so the sortable whitelist stays simple.
        $orderCol = in_array($sortBy, ['quantity_on_hand', 'updated_at'], true) ? "i.{$sortBy}" : "p.{$sortBy}";

        $sql = "SELECT p.product_id, p.product_code, p.product_name, p.unit, p.stock_alert_qty,
                       c.category_name, ISNULL(i.quantity_on_hand, 0) AS quantity_on_hand,
                       i.last_counted_at, i.updated_at
                {$base}
                ORDER BY {$orderCol} {$sortDir}
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

    public function lowStockCount(): int
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) AS c FROM Products p
             LEFT JOIN Inventory i ON i.product_id = p.product_id
             WHERE p.is_active = 1 AND ISNULL(i.quantity_on_hand, 0) <= p.stock_alert_qty"
        );
        return (int) $stmt->fetch()['c'];
    }

    /**
     * The actual low-stock items (not just the count), for the navbar
     * notification bell. Ranked by how far past the reorder point they
     * are - furthest deficit (or fully out of stock) first, so the
     * most urgent restocks surface at the top of a short dropdown.
     */
    public function lowStockList(int $limit = 8): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = $this->db->prepare(
            "SELECT TOP {$limit} p.product_id, p.product_name, p.unit, p.stock_alert_qty,
                    ISNULL(i.quantity_on_hand, 0) AS quantity_on_hand
             FROM Products p
             LEFT JOIN Inventory i ON i.product_id = p.product_id
             WHERE p.is_active = 1 AND ISNULL(i.quantity_on_hand, 0) <= p.stock_alert_qty
             ORDER BY (ISNULL(i.quantity_on_hand, 0) - p.stock_alert_qty) ASC, p.product_name ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Sets the on-hand quantity to an exact counted value (not a delta -
     * a physical count is what the person in front of the shelf actually
     * saw, so "set to X" avoids compounding an earlier mistake). Creates
     * the Inventory row if this product has never had one.
     * Returns [oldQuantity|null, error|null].
     */
    public function setQuantity(int $productId, int $newQuantity, ?string $reason = null, ?int $userId = null): array
    {
        if ($newQuantity < 0) {
            return [null, 'Quantity cannot be negative.'];
        }

        $stmt = $this->db->prepare("SELECT product_id FROM Products WHERE product_id = :id");
        $stmt->bindValue(':id', $productId, PDO::PARAM_INT);
        $stmt->execute();
        if (!$stmt->fetch()) {
            return [null, 'Product not found.'];
        }

        $existing = $this->db->prepare("SELECT quantity_on_hand FROM Inventory WHERE product_id = :id");
        $existing->bindValue(':id', $productId, PDO::PARAM_INT);
        $existing->execute();
        $row = $existing->fetch();

        if ($row) {
            $oldQuantity = (int) $row['quantity_on_hand'];
            $update = $this->db->prepare(
                "UPDATE Inventory SET quantity_on_hand = :qty, last_counted_at = GETDATE(), updated_at = GETDATE()
                 WHERE product_id = :id"
            );
            $update->bindValue(':qty', $newQuantity, PDO::PARAM_INT);
            $update->bindValue(':id', $productId, PDO::PARAM_INT);
            $update->execute();
        } else {
            $oldQuantity = 0;
            $insert = $this->db->prepare(
                "INSERT INTO Inventory (product_id, quantity_on_hand, last_counted_at, updated_at)
                 VALUES (:id, :qty, GETDATE(), GETDATE())"
            );
            $insert->bindValue(':id', $productId, PDO::PARAM_INT);
            $insert->bindValue(':qty', $newQuantity, PDO::PARAM_INT);
            $insert->execute();
        }

        $delta = $newQuantity - $oldQuantity;
        if ($delta !== 0) {
            InventoryMovement::log($productId, 'adjustment', $delta, 'manual', null, null, $reason, $userId);
        }

        return [$oldQuantity, null];
    }
}
