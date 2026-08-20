<?php
/**
 * app/models/Product.php
 * -----------------------------------------------------------------------
 * Data access for the Products table (joined with Categories/Suppliers
 * for display, and Inventory for on-hand stock).
 */

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class Product
{
    private PDO $db;
    private const SORTABLE = ['product_name', 'product_code', 'selling_price', 'created_at', 'is_active'];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function paginate(string $search, ?int $categoryId, string $sortBy, string $sortDir, int $page, int $perPage): array
    {
        $sortBy  = in_array($sortBy, self::SORTABLE, true) ? $sortBy : 'product_name';
        $sortDir = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';
        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset  = ($page - 1) * $perPage;

        $conditions = [];
        $params = [];

        if ($search !== '') {
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
        $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

        $countStmt = $this->db->prepare("SELECT COUNT(*) AS total FROM Products p {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];

        $sql = "SELECT p.product_id, p.product_code, p.barcode, p.qr_code, p.product_name, p.brand,
                       p.image_path, p.cost_price, p.selling_price, p.tax_rate, p.discount_rate,
                       p.unit, p.stock_alert_qty, p.is_active, p.created_at,
                       p.category_id, c.category_name,
                       p.supplier_id, s.supplier_name,
                       ISNULL(i.quantity_on_hand, 0) AS quantity_on_hand
                FROM Products p
                LEFT JOIN Categories c ON c.category_id = p.category_id
                LEFT JOIN Suppliers s ON s.supplier_id = p.supplier_id
                LEFT JOIN Inventory i ON i.product_id = p.product_id
                {$where}
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

    /**
     * Product lookup for the POS Screen: active products only, matched by
     * exact barcode (fastest path for a scanner gun) OR partial name/code.
     * Exact barcode hits are sorted first so a scan never gets buried
     * under partial text matches.
     */
    public function searchForPos(string $term, int $limit = 20): array
    {
        $limit = max(1, min(50, $limit));
        $term = trim($term);

        $sql = "SELECT TOP {$limit}
                       p.product_id, p.product_code, p.barcode, p.product_name, p.brand,
                       p.image_path, p.cost_price, p.selling_price, p.tax_rate, p.discount_rate, p.unit,
                       ISNULL(i.quantity_on_hand, 0) AS quantity_on_hand
                FROM Products p
                LEFT JOIN Inventory i ON i.product_id = p.product_id
                WHERE p.is_active = 1"
                . ($term !== '' ? " AND (p.barcode = :exact OR p.product_name LIKE :like_name OR p.product_code LIKE :like_code OR p.brand LIKE :like_brand)" : "")
                . " ORDER BY CASE WHEN p.barcode = :exact2 THEN 0 ELSE 1 END, p.product_name ASC";

        $stmt = $this->db->prepare($sql);
        if ($term !== '') {
            $stmt->bindValue(':exact', $term, PDO::PARAM_STR);
            $stmt->bindValue(':exact2', $term, PDO::PARAM_STR);
            $like = '%' . $term . '%';
            $stmt->bindValue(':like_name', $like, PDO::PARAM_STR);
            $stmt->bindValue(':like_code', $like, PDO::PARAM_STR);
            $stmt->bindValue(':like_brand', $like, PDO::PARAM_STR);
        } else {
            $stmt->bindValue(':exact2', '', PDO::PARAM_STR);
        }
        $stmt->execute();

        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['image_url'] = $row['image_path'] ? UPLOAD_URL . $row['image_path'] : null;
        }
        unset($row);

        return $rows;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT p.*, c.category_name, s.supplier_name, ISNULL(i.quantity_on_hand, 0) AS quantity_on_hand
             FROM Products p
             LEFT JOIN Categories c ON c.category_id = p.category_id
             LEFT JOIN Suppliers s ON s.supplier_id = p.supplier_id
             LEFT JOIN Inventory i ON i.product_id = p.product_id
             WHERE p.product_id = :id"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }

    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) AS c FROM Products WHERE product_code = :code";
        if ($excludeId !== null) $sql .= " AND product_id != :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':code', $code, PDO::PARAM_STR);
        if ($excludeId !== null) $stmt->bindValue(':id', $excludeId, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetch()['c'] > 0;
    }

    public function barcodeExists(string $barcode, ?int $excludeId = null): bool
    {
        if ($barcode === '') return false;
        $sql = "SELECT COUNT(*) AS c FROM Products WHERE barcode = :barcode";
        if ($excludeId !== null) $sql .= " AND product_id != :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':barcode', $barcode, PDO::PARAM_STR);
        if ($excludeId !== null) $stmt->bindValue(':id', $excludeId, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetch()['c'] > 0;
    }

    /**
     * Creates the product row AND its zero-stock Inventory row in one
     * transaction, so every product always has a matching inventory record.
     */
    public function create(array $data): int
    {
        $this->db->beginTransaction();
        try {
            $sql = "INSERT INTO Products
                        (category_id, supplier_id, product_code, barcode, qr_code, product_name, brand,
                         image_path, cost_price, selling_price, tax_rate, discount_rate, unit,
                         stock_alert_qty, expiration_date, is_active, created_at, updated_at)
                    OUTPUT INSERTED.product_id
                    VALUES
                        (:category_id, :supplier_id, :code, :barcode, :qr, :name, :brand,
                         :image, :cost, :price, :tax, :discount, :unit,
                         :alert_qty, :expiration_date, :is_active, GETDATE(), GETDATE())";
            $stmt = $this->db->prepare($sql);
            $this->bindProduct($stmt, $data);
            $stmt->execute();
            $productId = (int) $stmt->fetch()['product_id'];

            $invStmt = $this->db->prepare(
                "INSERT INTO Inventory (product_id, quantity_on_hand, updated_at) VALUES (:id, 0, GETDATE())"
            );
            $invStmt->bindValue(':id', $productId, PDO::PARAM_INT);
            $invStmt->execute();

            $this->db->commit();
            return $productId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE Products SET
                    category_id = :category_id, supplier_id = :supplier_id, product_code = :code,
                    barcode = :barcode, qr_code = :qr, product_name = :name, brand = :brand,
                    image_path = :image, cost_price = :cost, selling_price = :price, tax_rate = :tax,
                    discount_rate = :discount, unit = :unit, stock_alert_qty = :alert_qty, expiration_date = :expiration_date,
                    is_active = :is_active, updated_at = GETDATE()
                WHERE product_id = :id";
        $stmt = $this->db->prepare($sql);
        $this->bindProduct($stmt, $data);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    private function bindProduct(PDOStatement $stmt, array $data): void
    {
        $stmt->bindValue(':category_id', $data['category_id'] ?: null, $data['category_id'] ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':supplier_id', $data['supplier_id'] ?: null, $data['supplier_id'] ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':code', $data['product_code'], PDO::PARAM_STR);
        $stmt->bindValue(':barcode', $data['barcode'] ?: null, $data['barcode'] ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':qr', $data['qr_code'] ?: null, $data['qr_code'] ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':name', $data['product_name'], PDO::PARAM_STR);
        $stmt->bindValue(':brand', $data['brand'], PDO::PARAM_STR);
        $stmt->bindValue(':image', $data['image_path'] ?: null, $data['image_path'] ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':cost', $data['cost_price'], PDO::PARAM_STR);
        $stmt->bindValue(':price', $data['selling_price'], PDO::PARAM_STR);
        $stmt->bindValue(':tax', $data['tax_rate'], PDO::PARAM_STR);
        $stmt->bindValue(':discount', $data['discount_rate'], PDO::PARAM_STR);
        $stmt->bindValue(':unit', $data['unit'], PDO::PARAM_STR);
        $stmt->bindValue(':alert_qty', $data['stock_alert_qty'], PDO::PARAM_INT);
        $stmt->bindValue(':expiration_date', $data['expiration_date'] ?: null, $data['expiration_date'] ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':is_active', $data['is_active'] ? 1 : 0, PDO::PARAM_INT);
    }

    public function delete(int $id): bool
    {
        // Inventory row cascades via FK ON DELETE CASCADE.
        $stmt = $this->db->prepare("DELETE FROM Products WHERE product_id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function isInUse(int $id): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) AS c FROM SaleDetails WHERE product_id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetch()['c'] > 0;
    }
}
