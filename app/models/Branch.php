<?php
/**
 * app/models/Branch.php
 * -----------------------------------------------------------------------
 * Data access for the Branches table (multi-branch POS).
 */

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class Branch
{
    private PDO $db;

    private const SORTABLE = ['branch_code', 'branch_name', 'is_active', 'created_at'];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function allActive(): array
    {
        $stmt = $this->db->query(
            "SELECT branch_id, branch_code, branch_name, address, phone, is_active
             FROM Branches WHERE is_active = 1 ORDER BY branch_name ASC"
        );
        return $stmt->fetchAll();
    }

    public function all(): array
    {
        $stmt = $this->db->query(
            "SELECT branch_id, branch_code, branch_name, address, phone, is_active, created_at
             FROM Branches ORDER BY branch_name ASC"
        );
        return $stmt->fetchAll();
    }

    public function find(int $branchId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT branch_id, branch_code, branch_name, address, phone, is_active, created_at
             FROM Branches WHERE branch_id = :id"
        );
        $stmt->bindValue(':id', $branchId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT branch_id, branch_code, branch_name, is_active FROM Branches WHERE branch_code = :code"
        );
        $stmt->bindValue(':code', $code, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function paginate(string $search, string $sortBy, string $sortDir, int $page, int $perPage): array
    {
        $sortBy  = in_array($sortBy, self::SORTABLE, true) ? $sortBy : 'branch_name';
        $sortDir = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';
        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset  = ($page - 1) * $perPage;

        $conditions = [];
        $params = [];
        if ($search !== '') {
            $conditions[] = '(branch_code LIKE :search_code OR branch_name LIKE :search_name OR address LIKE :search_address)';
            $like = '%' . $search . '%';
            $params[':search_code'] = $like;
            $params[':search_name'] = $like;
            $params[':search_address'] = $like;
        }
        $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

        $countStmt = $this->db->prepare("SELECT COUNT(*) AS total FROM Branches {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];

        $sql = "SELECT branch_id, branch_code, branch_name, address, phone, is_active, created_at,
                       (SELECT COUNT(*) FROM Users u WHERE u.branch_id = Branches.branch_id) AS user_count,
                       (SELECT COUNT(*) FROM Sales s WHERE s.branch_id = Branches.branch_id AND s.status = 'completed') AS sale_count
                FROM Branches {$where}
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
            'rows' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ];
    }

    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) AS c FROM Branches WHERE branch_code = :code";
        if ($excludeId) {
            $sql .= ' AND branch_id != :id';
        }
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':code', $code, PDO::PARAM_STR);
        if ($excludeId) {
            $stmt->bindValue(':id', $excludeId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return (int) $stmt->fetch()['c'] > 0;
    }

    public function create(string $code, string $name, string $address, string $phone, bool $isActive): array
    {
        if ($this->codeExists($code)) {
            return [null, 'That branch code is already in use.'];
        }
        if ($name === '') {
            return [null, 'Branch name is required.'];
        }

        $stmt = $this->db->prepare(
            "INSERT INTO Branches (branch_code, branch_name, address, phone, is_active)
             OUTPUT INSERTED.branch_id
             VALUES (:code, :name, :address, :phone, :is_active)"
        );
        $stmt->bindValue(':code', strtoupper($code), PDO::PARAM_STR);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':address', $address !== '' ? $address : null, $address !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':phone', $phone !== '' ? $phone : null, $phone !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':is_active', $isActive ? 1 : 0, PDO::PARAM_INT);
        $stmt->execute();

        return [(int) $stmt->fetch()['branch_id'], null];
    }

    public function update(int $branchId, string $code, string $name, string $address, string $phone, bool $isActive): array
    {
        if (!$this->find($branchId)) {
            return [false, 'Branch not found.'];
        }
        if ($this->codeExists($code, $branchId)) {
            return [false, 'That branch code is already in use.'];
        }
        if ($name === '') {
            return [false, 'Branch name is required.'];
        }

        $stmt = $this->db->prepare(
            "UPDATE Branches SET branch_code = :code, branch_name = :name, address = :address,
             phone = :phone, is_active = :is_active WHERE branch_id = :id"
        );
        $stmt->bindValue(':code', strtoupper($code), PDO::PARAM_STR);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':address', $address !== '' ? $address : null, $address !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':phone', $phone !== '' ? $phone : null, $phone !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':is_active', $isActive ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':id', $branchId, PDO::PARAM_INT);
        $stmt->execute();

        return [true, null];
    }
}
