<?php
/**
 * app/models/Expense.php
 * -----------------------------------------------------------------------
 * Operating expenses (rent, utilities, supplies, etc.) - tracked
 * separately from Purchases, which are strictly inventory coming in.
 * Feeds the Reports page's Net Profit figure alongside Sales/COGS.
 */

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class Expense
{
    private PDO $db;

    public const CATEGORIES = ['Rent', 'Utilities', 'Salaries', 'Supplies', 'Maintenance', 'Marketing', 'Other'];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function create(string $category, string $description, float $amount, string $expenseDate, int $userId): array
    {
        if (!in_array($category, self::CATEGORIES, true)) {
            return [null, 'Please choose a valid expense category.'];
        }
        if ($amount <= 0) {
            return [null, 'Amount must be greater than zero.'];
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expenseDate)) {
            return [null, 'Please provide a valid date.'];
        }

        $stmt = $this->db->prepare(
            "INSERT INTO Expenses (category, description, amount, expense_date, user_id)
             OUTPUT INSERTED.expense_id
             VALUES (:category, :description, :amount, :expense_date, :user_id)"
        );
        $stmt->bindValue(':category', $category, PDO::PARAM_STR);
        $stmt->bindValue(':description', $description !== '' ? $description : null, $description !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':amount', $amount);
        $stmt->bindValue(':expense_date', $expenseDate, PDO::PARAM_STR);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return [(int) $stmt->fetch()['expense_id'], null];
    }

    public function delete(int $expenseId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM Expenses WHERE expense_id = :id");
        $stmt->bindValue(':id', $expenseId, PDO::PARAM_INT);
        return $stmt->execute() && $stmt->rowCount() > 0;
    }

    /** All expenses in range, most recent first - for the Reports page's expense list. */
    public function listForRange(string $dateFrom, string $dateTo): array
    {
        $stmt = $this->db->prepare(
            "SELECT e.expense_id, e.category, e.description, e.amount, e.expense_date, u.full_name AS recorded_by
             FROM Expenses e
             INNER JOIN Users u ON u.user_id = e.user_id
             WHERE e.expense_date BETWEEN :date_from AND :date_to
             ORDER BY e.expense_date DESC, e.expense_id DESC"
        );
        $stmt->bindValue(':date_from', $dateFrom, PDO::PARAM_STR);
        $stmt->bindValue(':date_to', $dateTo, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function totalForRange(string $dateFrom, string $dateTo): float
    {
        $stmt = $this->db->prepare(
            "SELECT ISNULL(SUM(amount), 0) AS total FROM Expenses WHERE expense_date BETWEEN :date_from AND :date_to"
        );
        $stmt->bindValue(':date_from', $dateFrom, PDO::PARAM_STR);
        $stmt->bindValue(':date_to', $dateTo, PDO::PARAM_STR);
        $stmt->execute();
        return round((float) $stmt->fetch()['total'], 2);
    }
}
