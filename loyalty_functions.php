<?php
/**
 * Loyalty Points & Coupon System — core functions.
 * Include this after db.php ($pdo must already exist).
require_once __DIR__ . '/../loyalty_functions.php';
 */

function getLoyaltySettings(PDO $pdo): array {
    $stmt = $pdo->query("SELECT * FROM loyalty_settings WHERE setting_id = 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$settings) {
        // Fallback defaults if the settings row was somehow deleted
        return [
            'points_per_rm'           => 1.00,
            'redemption_threshold'    => 100,
            'reward_discount_percent' => 10.00,
            'reward_validity_days'    => 30,
            'reward_min_order'        => 0.00,
            'is_enabled'              => 1,
        ];
    }
    return $settings;
}

function getLoyaltyBalance(PDO $pdo, int $customer_id): int {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(points_change), 0)
        FROM loyalty_points
        WHERE customer_id = ?
    ");
    $stmt->execute([$customer_id]);
    return (int) $stmt->fetchColumn();
}

function generateUniqueCouponCode(PDO $pdo): string {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    do {
        $code  = 'LOYAL' . substr(str_shuffle($chars), 0, 5);
        $check = $pdo->prepare("SELECT COUNT(*) FROM coupon WHERE code = ?");
        $check->execute([$code]);
    } while ($check->fetchColumn() > 0);
    return $code;
}

/**
 * Award points for a completed order, and auto-generate a reward coupon
 * if the customer's balance crosses the redemption threshold.
 *
 * Call this AFTER the order + ORDER_MENU rows are committed and the cart
 * is cleared (i.e. right where order_receipt.php currently sits).
 *
 * @param float $orderSubtotal  Pre-tax subtotal of the order (matches $total in order_receipt.php)
 * @return array|null  ['points_earned' => int, 'new_balance' => int, 'reward_coupon' => array|null]
 */
function processLoyaltyForOrder(PDO $pdo, int $customer_id, int $order_id, float $orderSubtotal): ?array {
    $settings = getLoyaltySettings($pdo);

    if (!$settings['is_enabled']) {
        return null;
    }

    $pointsEarned = (int) floor($orderSubtotal * (float) $settings['points_per_rm']);
    if ($pointsEarned <= 0) {
        return null;
    }

    try {
        $pdo->beginTransaction();

        // 1. Award points for this order
        $pdo->prepare("
            INSERT INTO loyalty_points (customer_id, order_id, points_change, reason)
            VALUES (?, ?, ?, 'Order earn')
        ")->execute([$customer_id, $order_id, $pointsEarned]);

        // 2. Recalculate balance
        $balanceStmt = $pdo->prepare("
            SELECT COALESCE(SUM(points_change), 0)
            FROM loyalty_points
            WHERE customer_id = ?
        ");
        $balanceStmt->execute([$customer_id]);
        $balance = (int) $balanceStmt->fetchColumn();

        $rewardCoupon  = null;
        $threshold     = (int) $settings['redemption_threshold'];

        // 3. Threshold crossed? Generate a personal reward coupon.
        if ($threshold > 0 && $balance >= $threshold) {
            $code   = generateUniqueCouponCode($pdo);
            $expiry = date('Y-m-d', strtotime('+' . (int)$settings['reward_validity_days'] . ' days'));

            $pdo->prepare("
                INSERT INTO coupon
                    (code, discount, min_order, expiry_date, is_active, customer_id, is_auto_generated)
                VALUES (?, ?, ?, ?, 1, ?, 1)
            ")->execute([
                $code,
                $settings['reward_discount_percent'],
                $settings['reward_min_order'],
                $expiry,
                $customer_id,
            ]);

            // Deduct the threshold (points roll over rather than resetting to 0)
            $pdo->prepare("
                INSERT INTO loyalty_points (customer_id, order_id, points_change, reason)
                VALUES (?, ?, ?, ?)
            ")->execute([
                $customer_id,
                $order_id,
                -$threshold,
                'Redeemed for coupon ' . $code,
            ]);

            $rewardCoupon = [
                'code'             => $code,
                'discount_percent' => $settings['reward_discount_percent'],
                'expiry_date'      => $expiry,
            ];
            $balance -= $threshold;
        }

        $pdo->commit();

        return [
            'points_earned' => $pointsEarned,
            'new_balance'   => $balance,
            'reward_coupon' => $rewardCoupon,
        ];
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Loyalty processing failed: ' . $e->getMessage());
        return null;
    }
}