<?php
/**
 * app/models/ActivityLog.php
 * -----------------------------------------------------------------------
 * Read access to ActivityLogs. Writing happens via User::logActivity(),
 * called from every other controller that changes something - this
 * model only ever reads.
 */

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class ActivityLog
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $offset  = ($page - 1) * $perPage;

        $conditions = [];
        $params = [];

        if (!empty($filters['search'])) {
            $conditions[] = '(a.action LIKE :search_action OR a.description LIKE :search_description OR u.full_name LIKE :search_user)';
            $like = '%' . $filters['search'] . '%';
            $params[':search_action'] = $like;
            $params[':search_description'] = $like;
            $params[':search_user'] = $like;
        }
        if (!empty($filters['user_id'])) {
            $conditions[] = 'a.user_id = :user_id';
            $params[':user_id'] = $filters['user_id'];
        }
        if (!empty($filters['date_from'])) {
            $conditions[] = 'a.created_at >= :date_from';
            $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $conditions[] = 'a.created_at <= :date_to';
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }
        $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

        $base = "FROM ActivityLogs a INNER JOIN Users u ON u.user_id = a.user_id {$where}";

        $countStmt = $this->db->prepare("SELECT COUNT(*) AS total {$base}");
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetch()['total'];

        $sql = "SELECT a.activity_id, a.action, a.description, a.ip_address, a.created_at, u.full_name AS user_name
                {$base}
                ORDER BY a.created_at DESC
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

    /** Users who have at least one log entry, for the filter dropdown. */
    public function usersWithActivity(): array
    {
        $stmt = $this->db->query(
            "SELECT DISTINCT u.user_id, u.full_name
             FROM Users u INNER JOIN ActivityLogs a ON a.user_id = u.user_id
             ORDER BY u.full_name ASC"
        );
        return $stmt->fetchAll();
    }
}
