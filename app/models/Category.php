<?php
/**
 * app/models/Category.php
 * -----------------------------------------------------------------------
 * Data access for the Categories table.
 */

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class Category
{
    private PDO $db;

    /** Columns allowed to be used as an ORDER BY target (whitelist -
     *  never interpolate a raw user-supplied column name into SQL). */
    private const SORTABLE = ['category_name', 'created_at', 'is_active'];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Paginated, searchable, sortable list for the AJAX DataTable.
     */
    public function paginate(string $search, string $sortBy, string $sortDir, int $page, int $perPage): array
    {
        $sortBy  = in_array($sortBy, self::SORTABLE, true) ? $sortBy : 'category_name';
        $sortDir = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';
        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset  = ($page - 1) * $perPage;

        $where = '';
        $params = [];
        if ($search !== '') {
            $where = 'WHERE category_name LIKE :search_name OR description LIKE :search_description';
            $like = '%' . $search . '%';
            $params[':search_name'] = $like;
            $params[':search_description'] = $like;
        }

        $countSql = "SELECT COUNT(*) AS total FROM Categories {$where}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];

        $sql = "SELECT category_id, category_name, description, is_active, created_at
                FROM Categories {$where}
                ORDER BY {$sortBy} {$sortDir}
                OFFSET :offset ROWS FETCH NEXT :perPage ROWS ONLY";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'rows'        => $stmt->fetchAll(),
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ];
    }

    /** All active categories, for populating <select> dropdowns elsewhere. */
    public function allActive(): array
    {
        $stmt = $this->db->query(
            "SELECT category_id, category_name FROM Categories WHERE is_active = 1 ORDER BY category_name ASC"
        );
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM Categories WHERE category_id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) AS c FROM Categories WHERE category_name = :name";
        if ($excludeId !== null) {
            $sql .= " AND category_id != :id";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        if ($excludeId !== null) {
            $stmt->bindValue(':id', $excludeId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return (int) $stmt->fetch()['c'] > 0;
    }

    public function create(string $name, string $description, bool $isActive): int
    {
        $sql = "INSERT INTO Categories (category_name, description, is_active, created_at)
                OUTPUT INSERTED.category_id
                VALUES (:name, :description, :is_active, GETDATE())";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':description', $description, PDO::PARAM_STR);
        $stmt->bindValue(':is_active', $isActive ? 1 : 0, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetch()['category_id'];
    }

    public function update(int $id, string $name, string $description, bool $isActive): bool
    {
        $sql = "UPDATE Categories
                SET category_name = :name, description = :description, is_active = :is_active
                WHERE category_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':description', $description, PDO::PARAM_STR);
        $stmt->bindValue(':is_active', $isActive ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /** Blocks deletion if products still reference this category. */
    public function isInUse(int $id): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) AS c FROM Products WHERE category_id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetch()['c'] > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM Categories WHERE category_id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
