<?php
/**
 * app/models/Report.php
 * -----------------------------------------------------------------------
 * Read-only aggregate queries for the Reports page. Everything here is
 * scoped to completed sales within a date range - held/voided sales
 * never count toward revenue, and a voided sale's stock has already
 * been restored so excluding it keeps inventory and revenue numbers
 * consistent with each other.
 */

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class Report
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /** Headline numbers for the date range: revenue, tax, discount, txn count, avg sale, units sold, gross profit. */
    public function summary(string $dateFrom, string $dateTo): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                ISNULL(COUNT(DISTINCT s.sale_id), 0)      AS transaction_count,
                ISNULL(SUM(s.grand_total), 0)             AS revenue,
                ISNULL(SUM(s.tax_total), 0)                AS tax_total,
                ISNULL(SUM(s.discount_total), 0)           AS discount_total,
                ISNULL(SUM(sd.quantity), 0)                AS units_sold,
                ISNULL(SUM(sd.quantity * p.cost_price), 0) AS cost_total
             FROM Sales s
             INNER JOIN SaleDetails sd ON sd.sale_id = s.sale_id
             INNER JOIN Products p ON p.product_id = sd.product_id
             WHERE s.status = 'completed' AND s.created_at BETWEEN :date_from AND :date_to"
        );
        $stmt->bindValue(':date_from', $dateFrom . ' 00:00:00', PDO::PARAM_STR);
        $stmt->bindValue(':date_to', $dateTo . ' 23:59:59', PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch();

        $revenue = (float) $row['revenue'];
        $cost    = (float) $row['cost_total'];
        $profit  = $revenue - $cost;

        return [
            'transaction_count' => (int) $row['transaction_count'],
            'revenue'           => round($revenue, 2),
            'tax_total'         => round((float) $row['tax_total'], 2),
            'discount_total'    => round((float) $row['discount_total'], 2),
            'units_sold'        => (int) $row['units_sold'],
            'average_sale'      => $row['transaction_count'] > 0 ? round($revenue / $row['transaction_count'], 2) : 0.0,
            'gross_profit'      => round($profit, 2),
            'gross_margin_pct'  => $revenue > 0 ? round(($profit / $revenue) * 100, 1) : 0.0,
        ];
    }

    /** Revenue per calendar day in range - feeds the trend chart. */
    public function salesByDay(string $dateFrom, string $dateTo): array
    {
        $stmt = $this->db->prepare(
            "SELECT CAST(s.created_at AS DATE) AS sale_date, SUM(s.grand_total) AS revenue, COUNT(*) AS transaction_count
             FROM Sales s
             WHERE s.status = 'completed' AND s.created_at BETWEEN :date_from AND :date_to
             GROUP BY CAST(s.created_at AS DATE)
             ORDER BY sale_date ASC"
        );
        $stmt->bindValue(':date_from', $dateFrom . ' 00:00:00', PDO::PARAM_STR);
        $stmt->bindValue(':date_to', $dateTo . ' 23:59:59', PDO::PARAM_STR);
        $stmt->execute();

        return array_map(function ($row) {
            return [
                'date'              => substr((string) $row['sale_date'], 0, 10),
                'revenue'           => round((float) $row['revenue'], 2),
                'transaction_count' => (int) $row['transaction_count'],
            ];
        }, $stmt->fetchAll());
    }

    /** Best sellers by revenue and by quantity, each independently ranked and limited. */
    public function topProducts(string $dateFrom, string $dateTo, int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));

        $sql = "SELECT TOP {$limit} p.product_id, p.product_name,
                       SUM(sd.quantity) AS quantity_sold, SUM(sd.line_total) AS revenue
                FROM SaleDetails sd
                INNER JOIN Sales s ON s.sale_id = sd.sale_id
                INNER JOIN Products p ON p.product_id = sd.product_id
                WHERE s.status = 'completed' AND s.created_at BETWEEN :date_from AND :date_to
                GROUP BY p.product_id, p.product_name
                ORDER BY %s DESC";

        $byRevenue = $this->db->prepare(sprintf($sql, 'revenue'));
        $byRevenue->bindValue(':date_from', $dateFrom . ' 00:00:00', PDO::PARAM_STR);
        $byRevenue->bindValue(':date_to', $dateTo . ' 23:59:59', PDO::PARAM_STR);
        $byRevenue->execute();

        $byQuantity = $this->db->prepare(sprintf($sql, 'quantity_sold'));
        $byQuantity->bindValue(':date_from', $dateFrom . ' 00:00:00', PDO::PARAM_STR);
        $byQuantity->bindValue(':date_to', $dateTo . ' 23:59:59', PDO::PARAM_STR);
        $byQuantity->execute();

        return [
            'by_revenue'  => $byRevenue->fetchAll(),
            'by_quantity' => $byQuantity->fetchAll(),
        ];
    }

    /** How revenue splits across Cash/GCash/Maya/Card. */
    public function paymentBreakdown(string $dateFrom, string $dateTo): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.payment_method, COUNT(*) AS transaction_count, SUM(s.grand_total) AS revenue
             FROM Sales s
             WHERE s.status = 'completed' AND s.created_at BETWEEN :date_from AND :date_to
             GROUP BY s.payment_method
             ORDER BY revenue DESC"
        );
        $stmt->bindValue(':date_from', $dateFrom . ' 00:00:00', PDO::PARAM_STR);
        $stmt->bindValue(':date_to', $dateTo . ' 23:59:59', PDO::PARAM_STR);
        $stmt->execute();

        return array_map(function ($row) {
            return [
                'payment_method'    => $row['payment_method'],
                'transaction_count' => (int) $row['transaction_count'],
                'revenue'           => round((float) $row['revenue'], 2),
            ];
        }, $stmt->fetchAll());
    }

    /** Stock received and spend per supplier in range - the purchasing-side counterpart to sales totals. */
    public function purchaseSummary(string $dateFrom, string $dateTo): array
    {
        $stmt = $this->db->prepare(
            "SELECT ISNULL(COUNT(*), 0) AS purchase_count, ISNULL(SUM(p.total_amount), 0) AS total_spend
             FROM Purchases p
             WHERE p.status = 'received' AND p.purchased_at BETWEEN :date_from AND :date_to"
        );
        $stmt->bindValue(':date_from', $dateFrom . ' 00:00:00', PDO::PARAM_STR);
        $stmt->bindValue(':date_to', $dateTo . ' 23:59:59', PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch();

        return [
            'purchase_count' => (int) $row['purchase_count'],
            'total_spend'    => round((float) $row['total_spend'], 2),
        ];
    }

    /** Expense totals grouped by category, for the Reports page's expense breakdown. */
    public function expensesByCategory(string $dateFrom, string $dateTo): array
    {
        $stmt = $this->db->prepare(
            "SELECT category, SUM(amount) AS total, COUNT(*) AS expense_count
             FROM Expenses
             WHERE expense_date BETWEEN :date_from AND :date_to
             GROUP BY category
             ORDER BY total DESC"
        );
        $stmt->bindValue(':date_from', $dateFrom, PDO::PARAM_STR);
        $stmt->bindValue(':date_to', $dateTo, PDO::PARAM_STR);
        $stmt->execute();

        return array_map(function ($row) {
            return [
                'category'       => $row['category'],
                'total'          => round((float) $row['total'], 2),
                'expense_count'  => (int) $row['expense_count'],
            ];
        }, $stmt->fetchAll());
    }

    /** Voided ("returned/refunded") sales in range - count + amount, for the Returns/Refunds KPI. */
    public function returnsSummary(string $dateFrom, string $dateTo): array
    {
        $stmt = $this->db->prepare(
            "SELECT ISNULL(COUNT(*), 0) AS voided_count, ISNULL(SUM(s.grand_total), 0) AS voided_amount
             FROM Sales s
             WHERE s.status = 'voided' AND s.created_at BETWEEN :date_from AND :date_to"
        );
        $stmt->bindValue(':date_from', $dateFrom . ' 00:00:00', PDO::PARAM_STR);
        $stmt->bindValue(':date_to', $dateTo . ' 23:59:59', PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch();

        return [
            'voided_count'  => (int) $row['voided_count'],
            'voided_amount' => round((float) $row['voided_amount'], 2),
        ];
    }
}
