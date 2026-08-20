<?php
/**
 * app/models/Reconciliation.php
 * -----------------------------------------------------------------------
 * Backs the "Transaction Record & End of Day Reconciliation" page: the
 * day's transaction list (a register tape), a cash-drawer expectation
 * built from actual SalePayments rows (not just Sales.grand_total,
 * which would over-count a split cash+card sale), and the saved
 * counted-cash / variance record in CashReconciliation.
 */

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class Reconciliation
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /** Every transaction on a given business date - the "Transaction Record" register tape. Newest first. */
    public function transactionsForDate(string $date): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.sale_id, s.invoice_no, s.created_at, s.grand_total, s.amount_paid, s.change_due,
                    s.payment_method, s.status,
                    ISNULL(c.full_name, 'Walk-in customer') AS customer_name,
                    u.full_name AS cashier_name,
                    (SELECT COUNT(*) FROM SaleDetails sd WHERE sd.sale_id = s.sale_id) AS item_count,
                    (SELECT ISNULL(SUM(sd.quantity), 0) FROM SaleDetails sd WHERE sd.sale_id = s.sale_id) AS quantity_sold
             FROM Sales s
             LEFT JOIN Customers c ON c.customer_id = s.customer_id
             INNER JOIN Users u ON u.user_id = s.user_id
             WHERE CAST(s.created_at AS DATE) = :business_date
             ORDER BY s.created_at DESC"
        );
        $stmt->bindValue(':business_date', $date, PDO::PARAM_STR);
        $stmt->execute();

        return array_map(function ($row) {
            return [
                'sale_id'       => (int) $row['sale_id'],
                'invoice_no'    => $row['invoice_no'],
                'created_at'    => $row['created_at'],
                'customer_name' => $row['customer_name'],
                'cashier_name'  => $row['cashier_name'],
                'item_count'    => (int) $row['item_count'],
                'quantity_sold' => (int) $row['quantity_sold'],
                'payment_method'=> $row['payment_method'],
                'grand_total'   => round((float) $row['grand_total'], 2),
                'amount_paid'   => round((float) $row['amount_paid'], 2),
                'change_due'    => round((float) $row['change_due'], 2),
                'status'        => $row['status'],
            ];
        }, $stmt->fetchAll());
    }

    /**
     * Cash actually collected and change actually paid out on a date,
     * read from SalePayments (the per-method breakdown), not
     * Sales.grand_total - a split cash+card sale must only count its
     * cash portion toward the drawer, and 'multiple' as a
     * Sales.payment_method value would otherwise be invisible to a
     * naive group-by. Voided and held sales never touch the drawer.
     */
    public function cashMovementForDate(string $date): array
    {
        $stmt = $this->db->prepare(
            "SELECT ISNULL(SUM(sp.amount), 0) AS cash_collected
             FROM SalePayments sp
             INNER JOIN Sales s ON s.sale_id = sp.sale_id
             WHERE sp.payment_method = 'cash' AND s.status = 'completed'
               AND CAST(s.created_at AS DATE) = :business_date"
        );
        $stmt->bindValue(':business_date', $date, PDO::PARAM_STR);
        $stmt->execute();
        $cashCollected = (float) $stmt->fetch()['cash_collected'];

        // Change is only paid from the drawer on sales that included a
        // cash payment - a pure-card sale's change_due is always 0 anyway.
        $stmt = $this->db->prepare(
            "SELECT ISNULL(SUM(s.change_due), 0) AS change_given
             FROM Sales s
             WHERE s.status = 'completed' AND s.change_due > 0
               AND CAST(s.created_at AS DATE) = :business_date"
        );
        $stmt->bindValue(':business_date', $date, PDO::PARAM_STR);
        $stmt->execute();
        $changeGiven = (float) $stmt->fetch()['change_given'];

        return [
            'cash_collected' => round($cashCollected, 2),
            'change_given'   => round($changeGiven, 2),
        ];
    }

    /** Revenue grouped by payment method for the date (all methods, not just cash) - shown alongside the drawer math for context. */
    public function paymentBreakdownForDate(string $date): array
    {
        $stmt = $this->db->prepare(
            "SELECT sp.payment_method, COUNT(*) AS payment_count, ISNULL(SUM(sp.amount), 0) AS total
             FROM SalePayments sp
             INNER JOIN Sales s ON s.sale_id = sp.sale_id
             WHERE s.status = 'completed' AND CAST(s.created_at AS DATE) = :business_date
             GROUP BY sp.payment_method
             ORDER BY total DESC"
        );
        $stmt->bindValue(':business_date', $date, PDO::PARAM_STR);
        $stmt->execute();

        return array_map(function ($row) {
            return [
                'payment_method' => $row['payment_method'],
                'payment_count'  => (int) $row['payment_count'],
                'total'          => round((float) $row['total'], 2),
            ];
        }, $stmt->fetchAll());
    }

    /** Existing saved reconciliation for a date, if the day was already closed out. */
    public function getForDate(string $date): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT cr.*, u.full_name AS closed_by
             FROM CashReconciliation cr
             INNER JOIN Users u ON u.user_id = cr.user_id
             WHERE cr.business_date = :business_date"
        );
        $stmt->bindValue(':business_date', $date, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch();
        if (!$row) return null;

        return [
            'business_date' => $row['business_date'],
            'opening_float' => round((float) $row['opening_float'], 2),
            'expected_cash' => round((float) $row['expected_cash'], 2),
            'counted_cash'  => round((float) $row['counted_cash'], 2),
            'variance'      => round((float) $row['variance'], 2),
            'notes'         => $row['notes'],
            'closed_by'     => $row['closed_by'],
            'updated_at'    => $row['updated_at'],
        ];
    }

    /**
     * The most recent counted_cash before this date - used to suggest
     * tomorrow's (or an un-closed today's) opening float automatically,
     * so a cashier isn't guessing what's already sitting in the drawer.
     */
    public function suggestedOpeningFloat(string $date): float
    {
        $stmt = $this->db->prepare(
            "SELECT TOP 1 counted_cash FROM CashReconciliation
             WHERE business_date < :business_date
             ORDER BY business_date DESC"
        );
        $stmt->bindValue(':business_date', $date, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ? round((float) $row['counted_cash'], 2) : 0.0;
    }

    /** Upsert - a day can be recounted/corrected before shift truly closes without creating duplicate rows. */
    public function save(string $date, int $userId, float $openingFloat, float $countedCash, ?string $notes): array
    {
        $movement = $this->cashMovementForDate($date);
        $expectedCash = round($openingFloat + $movement['cash_collected'] - $movement['change_given'], 2);
        $variance = round($countedCash - $expectedCash, 2);

        $exists = $this->getForDate($date);
        if ($exists) {
            $stmt = $this->db->prepare(
                "UPDATE CashReconciliation
                 SET user_id = :user_id, opening_float = :opening_float, expected_cash = :expected_cash,
                     counted_cash = :counted_cash, variance = :variance, notes = :notes, updated_at = GETDATE()
                 WHERE business_date = :business_date"
            );
        } else {
            $stmt = $this->db->prepare(
                "INSERT INTO CashReconciliation
                    (business_date, user_id, opening_float, expected_cash, counted_cash, variance, notes)
                 VALUES (:business_date, :user_id, :opening_float, :expected_cash, :counted_cash, :variance, :notes)"
            );
        }
        $stmt->bindValue(':business_date', $date, PDO::PARAM_STR);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':opening_float', $openingFloat);
        $stmt->bindValue(':expected_cash', $expectedCash);
        $stmt->bindValue(':counted_cash', $countedCash);
        $stmt->bindValue(':variance', $variance);
        $stmt->bindValue(':notes', $notes !== '' ? $notes : null, $notes !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->execute();

        return ['expected_cash' => $expectedCash, 'variance' => $variance];
    }
}
