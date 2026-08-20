<?php
/**
 * app/models/Supplier.php
 * -----------------------------------------------------------------------
 * Data access for the Suppliers table.
 */

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class Supplier
{
    private PDO $db;
    private const SORTABLE = ['supplier_name', 'contact_person', 'created_at', 'is_active'];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function paginate(string $search, string $sortBy, string $sortDir, int $page, int $perPage): array
    {
        $sortBy  = in_array($sortBy, self::SORTABLE, true) ? $sortBy : 'supplier_name';
        $sortDir = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';
        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset  = ($page - 1) * $perPage;

        $where = '';
        $params = [];
        if ($search !== '') {
            $where = 'WHERE supplier_name LIKE :search_name OR contact_person LIKE :search_contact OR phone LIKE :search_phone OR email LIKE :search_email';
            $like = '%' . $search . '%';
            $params[':search_name'] = $like;
            $params[':search_contact'] = $like;
            $params[':search_phone'] = $like;
            $params[':search_email'] = $like;
        }

        $countStmt = $this->db->prepare("SELECT COUNT(*) AS total FROM Suppliers {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];

        $sql = "SELECT supplier_id, supplier_name, contact_person, phone, email, address, is_active, created_at
                FROM Suppliers {$where}
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
            'rows' => $stmt->fetchAll(), 'total' => $total, 'page' => $page,
            'per_page' => $perPage, 'total_pages' => (int) ceil($total / $perPage),
        ];
    }

    public function allActive(): array
    {
        $stmt = $this->db->query("SELECT supplier_id, supplier_name FROM Suppliers WHERE is_active = 1 ORDER BY supplier_name ASC");
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM Suppliers WHERE supplier_id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO Suppliers (supplier_name, contact_person, phone, email, address, is_active, created_at)
                OUTPUT INSERTED.supplier_id
                VALUES (:name, :contact, :phone, :email, :address, :is_active, GETDATE())";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':name', $data['supplier_name'], PDO::PARAM_STR);
        $stmt->bindValue(':contact', $data['contact_person'], PDO::PARAM_STR);
        $stmt->bindValue(':phone', $data['phone'], PDO::PARAM_STR);
        $stmt->bindValue(':email', $data['email'], PDO::PARAM_STR);
        $stmt->bindValue(':address', $data['address'], PDO::PARAM_STR);
        $stmt->bindValue(':is_active', $data['is_active'] ? 1 : 0, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetch()['supplier_id'];
    }

    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE Suppliers SET supplier_name = :name, contact_person = :contact, phone = :phone,
                email = :email, address = :address, is_active = :is_active WHERE supplier_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':name', $data['supplier_name'], PDO::PARAM_STR);
        $stmt->bindValue(':contact', $data['contact_person'], PDO::PARAM_STR);
        $stmt->bindValue(':phone', $data['phone'], PDO::PARAM_STR);
        $stmt->bindValue(':email', $data['email'], PDO::PARAM_STR);
        $stmt->bindValue(':address', $data['address'], PDO::PARAM_STR);
        $stmt->bindValue(':is_active', $data['is_active'] ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function isInUse(int $id): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) AS c FROM Products WHERE supplier_id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetch()['c'] > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM Suppliers WHERE supplier_id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
