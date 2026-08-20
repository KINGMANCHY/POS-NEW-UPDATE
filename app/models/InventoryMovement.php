<?php
/**
 * app/models/InventoryMovement.php
 * -----------------------------------------------------------------------
 * Shared stock-movement logger, called by Sale.php (checkout/void),
 * Purchase.php (create/cancel), and Inventory.php (manual adjustments) -
 * anywhere Inventory.quantity_on_hand changes. Also serves the Item
 * Ledger page's read queries.
 *
 * log() must be called AFTER the Inventory UPDATE for that product, in
 * the same transaction, so balance_after reflects the change.
 */

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class InventoryMovement
{
    public const TYPES = ['purchase', 'purchase_cancel', 'sale', 'sale_void', 'adjustment'];

    public static function log(
        int $productId,
        string $type,
        int $quantityChange,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $referenceCode = null,
        ?string $notes = null,
        ?int $userId = null
    ): void {
        $db = Database::getConnection();

        $balanceStmt = $db->prepare("SELECT quantity_on_hand FROM Inventory WHERE product_id = :id");
        $balanceStmt->bindValue(':id', $productId, PDO::PARAM_INT);
        $balanceStmt->execute();
        $balanceAfter = (int) ($balanceStmt->fetch()['quantity_on_hand'] ?? 0);

        $stmt = $db->prepare(
            "INSERT INTO InventoryMovements
                (product_id, movement_type, quantity_change, balance_after,
                 reference_type, reference_id, reference_code, notes, user_id, created_at)
             VALUES
                (:product_id, :type, :qty, :balance, :ref_type, :ref_id, :ref_code, :notes, :user_id, GETDATE())"
        );
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->bindValue(':type', $type, PDO::PARAM_STR);
        $stmt->bindValue(':qty', $quantityChange, PDO::PARAM_INT);
        $stmt->bindValue(':balance', $balanceAfter, PDO::PARAM_INT);
        $stmt->bindValue(':ref_type', $referenceType, $referenceType === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':ref_id', $referenceId, $referenceId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':ref_code', $referenceCode, $referenceCode === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':notes', $notes, $notes === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':user_id', $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Full in/out history for one product in a date range, plus the
     * opening balance (balance_after of the last movement before the
     * range, or 0 if the product has no earlier history) - so the ledger
     * reads like a real accounting ledger: opening balance, running
     * balance per line, closing balance.
     */
    public function ledger(int $productId, string $dateFrom, string $dateTo): array
    {
        $db = Database::getConnection();

        $openingStmt = $db->prepare(
            "SELECT TOP 1 balance_after FROM InventoryMovements
             WHERE product_id = :pid AND created_at < :date_from
             ORDER BY created_at DESC, movement_id DESC"
        );
        $openingStmt->bindValue(':pid', $productId, PDO::PARAM_INT);
        $openingStmt->bindValue(':date_from', $dateFrom . ' 00:00:00', PDO::PARAM_STR);
        $openingStmt->execute();
        $openingRow = $openingStmt->fetch();
        $opening = $openingRow ? (int) $openingRow['balance_after'] : 0;

        $stmt = $db->prepare(
            "SELECT m.movement_id, m.movement_type, m.quantity_change, m.balance_after,
                    m.reference_type, m.reference_id, m.reference_code, m.notes, m.created_at,
                    u.full_name AS user_name
             FROM InventoryMovements m
             LEFT JOIN Users u ON u.user_id = m.user_id
             WHERE m.product_id = :pid AND m.created_at BETWEEN :date_from AND :date_to
             ORDER BY m.created_at ASC, m.movement_id ASC"
        );
        $stmt->bindValue(':pid', $productId, PDO::PARAM_INT);
        $stmt->bindValue(':date_from', $dateFrom . ' 00:00:00', PDO::PARAM_STR);
        $stmt->bindValue(':date_to', $dateTo . ' 23:59:59', PDO::PARAM_STR);
        $stmt->execute();
        $movements = $stmt->fetchAll();

        $totalIn = 0;
        $totalOut = 0;
        foreach ($movements as $m) {
            if ($m['quantity_change'] > 0) { $totalIn += $m['quantity_change']; }
            else { $totalOut += abs($m['quantity_change']); }
        }

        $closing = $movements ? (int) end($movements)['balance_after'] : $opening;

        return [
            'opening_balance' => $opening,
            'closing_balance' => $closing,
            'total_in'        => $totalIn,
            'total_out'       => $totalOut,
            'movements'       => $movements,
        ];
    }
}
