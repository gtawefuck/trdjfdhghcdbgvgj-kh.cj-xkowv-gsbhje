<?php
declare(strict_types=1);

namespace App;

use PDO;
use DateTime;
use Exception;

final class Plan
{
    /**
     * প্ল্যান ক্যাটালগ (LEVEL => [name, days, credits, bonus_xcoin])
     * ওয়েবের Buy Premium কার্ডের সাথে ১:১ মিলিয়ে রাখা হয়েছে।
     */
    public static function catalog(): array
    {
        return [
            1 => ['name'=>'PLAN1', 'days'=>7,   'credits'=>500,   'bonus_xcoin'=>1],
            2 => ['name'=>'PLAN2', 'days'=>15,  'credits'=>1000,  'bonus_xcoin'=>2],
            3 => ['name'=>'PLAN3', 'days'=>30,  'credits'=>2500,  'bonus_xcoin'=>3],
            4 => ['name'=>'PLAN4', 'days'=>90,  'credits'=>8000,  'bonus_xcoin'=>5],
            5 => ['name'=>'PLAN5', 'days'=>180, 'credits'=>20000, 'bonus_xcoin'=>10],
            6 => ['name'=>'PLAN6', 'days'=>365, 'credits'=>50000, 'bonus_xcoin'=>25],
        ];
    }

    public static function get(int $level): ?array
    {
        $cat = self::catalog();
        return $cat[$level] ?? null;
    }

    /**
     * নির্দিষ্ট ইউজারের উপর প্ল্যান অ্যাপ্লাই করে:
     * - status = 'premium'
     * - plan_name = PLAN{n}
     * - credits += plan credits
     * - kcoin   += bonus_xcoin
     * - expiry_date: যদি বর্তমান expiry ভবিষ্যতে থাকে, সেখান থেকে বাড়ায়; না থাকলে আজ থেকে।
     */
    public static function apply(PDO $pdo, int $userId, int $level): array
    {
        $plan = self::get($level);
        if (!$plan) {
            return ['ok'=>false, 'msg'=>'Invalid plan level'];
        }

        $pdo->beginTransaction();
        try {
            // Lock the row
            $stmt = $pdo->prepare("SELECT id, status, plan_name, credits, kcoin, expiry_date FROM users WHERE id=:id FOR UPDATE");
            $stmt->execute([':id'=>$userId]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$u) {
                $pdo->rollBack();
                return ['ok'=>false, 'msg'=>'User not found'];
            }

            // Expiry calculate
            $base = null;
            if (!empty($u['expiry_date'])) {
                $base = new DateTime($u['expiry_date']);
                // যদি পুরনো মেয়াদ শেষ হয়ে যায় তাহলে আজ থেকে
                $today = new DateTime('today');
                if ($base < $today) $base = $today;
            } else {
                $base = new DateTime('today');
            }
            $base->modify('+' . (int)$plan['days'] . ' days');
            $newExpiry = $base->format('Y-m-d');

            // Update
            $upd = $pdo->prepare(
                "UPDATE users
                 SET previous_status = status,
                     status = 'premium',
                     plan_name = :pname,
                     credits = credits + :addc,
                     kcoin = kcoin + :addk,
                     expiry_date = :exp,
                     last_activity = NOW()
                 WHERE id = :id"
            );
            $upd->execute([
                ':pname' => $plan['name'],
                ':addc'  => (int)$plan['credits'],
                ':addk'  => (int)$plan['bonus_xcoin'],
                ':exp'   => $newExpiry,
                ':id'    => $userId,
            ]);

            $pdo->commit();
            return [
                'ok'=>true,
                'user_id'=>$userId,
                'plan'=>$plan['name'],
                'days'=>$plan['days'],
                'added_credits'=>$plan['credits'],
                'added_xcoin'=>$plan['bonus_xcoin'],
                'expiry_date'=>$newExpiry
            ];
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['ok'=>false, 'msg'=>'DB error: '.$e->getMessage()];
        }
    }
}
