<?php
/**
 * app/models/Customer.php
 * -----------------------------------------------------------------------
 * Data access for the Customers table.
 */

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class Customer
{
    private PDO $db;
    private const SORTABLE = ['full_name', 'created_at', 'is_active', 'loyalty_points'];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function paginate(string $search, string $sortBy, string $sortDir, int $page, int $perPage): array
    {
        $sortBy  = in_array($sortBy, self::SORTABLE, true) ? $sortBy : 'full_name';
        $sortDir = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';
        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset  = ($page - 1) * $perPage; 

        $where = '';
        $params = [];
        if ($search !== '') {
            $where = 'WHERE full_name LIKE :search_name OR phone LIKE :search_phone OR email LIKE :search_email';
            $like = '%' . $search . '%';
            $params[':search_name'] = $like;
            $params[':search_phone'] = $like;
            $params[':search_email'] = $like;
        }

        $countStmt = $this->db->prepare("SELECT COUNT(*) AS total FROM Customers {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];

        $sql = "SELECT customer_id, full_name, phone, email, address, loyalty_points, is_active, created_at
                FROM Customers {$where}
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
        $stmt = $this->db->query("SELECT customer_id, full_name FROM Customers WHERE is_active = 1 ORDER BY full_name ASC");
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM Customers WHERE customer_id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $sql = "INSERT INTO Customers (full_name, phone, email, address, loyalty_points, is_active, created_at)
                OUTPUT INSERTED.customer_id
                VALUES (:name, :phone, :email, :address, 0, :is_active, GETDATE())";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':name', $data['full_name'], PDO::PARAM_STR);
        $stmt->bindValue(':phone', $data['phone'], PDO::PARAM_STR);
        $stmt->bindValue(':email', $data['email'], PDO::PARAM_STR);
        $stmt->bindValue(':address', $data['address'], PDO::PARAM_STR);
        $stmt->bindValue(':is_active', $data['is_active'] ? 1 : 0, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetch()['customer_id'];
    }

    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE Customers SET full_name = :name, phone = :phone, email = :email,
                address = :address, is_active = :is_active WHERE customer_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':name', $data['full_name'], PDO::PARAM_STR);
        $stmt->bindValue(':phone', $data['phone'], PDO::PARAM_STR);
        $stmt->bindValue(':email', $data['email'], PDO::PARAM_STR);
        $stmt->bindValue(':address', $data['address'], PDO::PARAM_STR);
        $stmt->bindValue(':is_active', $data['is_active'] ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function isInUse(int $id): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) AS c FROM Sales WHERE customer_id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetch()['c'] > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM Customers WHERE customer_id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
    
    /** Customer sales and loyalty roll-up for the customer report and exports. */
    public function searchForReport(string $search, int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        // PDO_SQLSRV does not support reusing one named placeholder in a
        // statement. Bind a separate value for each searchable column so
        // the customer-report autocomplete can return its matches.
        $stmt = $this->db->prepare("SELECT TOP {$limit} customer_id, full_name, phone, email, loyalty_points FROM Customers WHERE full_name LIKE :search_name OR phone LIKE :search_phone OR email LIKE :search_email ORDER BY full_name ASC");
        $like = '%' . $search . '%';
        $stmt->execute([
            ':search_name' => $like,
            ':search_phone' => $like,
            ':search_email' => $like,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function report(string $search, string $dateFrom, string $dateTo, int $customerId = 0): array
    {
        $where = ['1=1'];
        $params = [];

        if ($search !== '') {
            $where[] = '(
                c.full_name LIKE :search_name
                OR c.phone LIKE :search_phone
                OR c.email LIKE :search_email
            )';

            $searchValue = '%' . $search . '%';

            $params[':search_name']  = $searchValue;
            $params[':search_phone'] = $searchValue;
            $params[':search_email'] = $searchValue;
        }
        if ($customerId > 0) { $where[] = 'c.customer_id = :customer_id'; $params[':customer_id'] = $customerId; }

        $salesRange = '';

        if ($dateFrom !== '') {
            $salesRange .= ' AND s.created_at >= :date_from';
            $params[':date_from'] = $dateFrom . ' 00:00:00';
        }

        if ($dateTo !== '') {
            $salesRange .= ' AND s.created_at <= :date_to';
            $params[':date_to'] = $dateTo . ' 23:59:59';
        }

        $sql = "
            SELECT
                c.customer_id,
                c.full_name,
                c.phone,
                c.email,
                c.loyalty_points,
                c.is_active,
                COUNT(s.sale_id) AS transaction_count,
                ISNULL(SUM(s.grand_total), 0) AS total_spent,
                ISNULL(SUM(s.loyalty_points_earned), 0) AS points_earned,
                ISNULL(SUM(s.loyalty_points_redeemed), 0) AS points_redeemed,
                MAX(s.created_at) AS last_purchase

            FROM Customers AS c

            LEFT JOIN Sales AS s
                ON s.customer_id = c.customer_id
                AND s.status = 'completed'
                {$salesRange}

            WHERE " . implode(' AND ', $where) . "

            GROUP BY
                c.customer_id,
                c.full_name,
                c.phone,
                c.email,
                c.loyalty_points,
                c.is_active

            ORDER BY
                total_spent DESC,
                c.full_name ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function reportDetails(int $customerId, string $dateFrom, string $dateTo): array
    {
        $customer = $this->find($customerId);
        if (!$customer) return [];
        $where = ['s.customer_id = :customer_id', "s.status = 'completed'"];
        $params = [':customer_id' => $customerId];
        if ($dateFrom !== '') { $where[] = 's.created_at >= :date_from'; $params[':date_from'] = $dateFrom . ' 00:00:00'; }
        if ($dateTo !== '') { $where[] = 's.created_at <= :date_to'; $params[':date_to'] = $dateTo . ' 23:59:59'; }
        $stmt = $this->db->prepare('SELECT s.invoice_no, s.created_at, s.grand_total, s.payment_method, s.loyalty_points_earned, s.loyalty_points_redeemed FROM Sales s WHERE ' . implode(' AND ', $where) . ' ORDER BY s.created_at DESC');
        $stmt->execute($params);
        return ['customer' => $customer, 'sales' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    }


}
