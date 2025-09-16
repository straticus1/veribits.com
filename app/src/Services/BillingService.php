<?php
namespace VeriBits\Services;

use VeriBits\Utils\Database;
use VeriBits\Utils\Logger;
use VeriBits\Utils\Config;

class BillingService {
    private const PLANS = [
        'free' => [
            'name' => 'Free',
            'price' => 0,
            'quota' => 1000,
            'features' => ['Basic verification', 'Community support']
        ],
        'pro' => [
            'name' => 'Pro',
            'price' => 2900, // $29.00 in cents
            'quota' => 10000,
            'features' => ['Advanced verification', 'Webhooks', 'Priority support', 'Custom badges']
        ],
        'enterprise' => [
            'name' => 'Enterprise',
            'price' => 29900, // $299.00 in cents
            'quota' => 100000,
            'features' => ['Unlimited verification', 'White-label', 'SLA', 'Custom integration']
        ]
    ];

    public function getPlan(string $planId): ?array {
        return self::PLANS[$planId] ?? null;
    }

    public function getAllPlans(): array {
        return self::PLANS;
    }

    public function getUserBillingAccount(string $userId): ?array {
        try {
            $sql = "SELECT ba.*, p.name as plan_name, p.price, p.quota, p.features
                    FROM billing_accounts ba
                    LEFT JOIN plans p ON ba.plan = p.id
                    WHERE ba.user_id = :user_id";

            $account = Database::fetch($sql, ['user_id' => $userId]);

            if ($account) {
                $account['plan_details'] = self::PLANS[$account['plan']] ?? self::PLANS['free'];
                $account['usage'] = $this->getCurrentUsage($userId);
                $account['next_billing_date'] = $this->getNextBillingDate($account['id']);
            }

            return $account;
        } catch (\Exception $e) {
            Logger::error('Failed to get billing account', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function createBillingAccount(string $userId, string $plan = 'free'): string {
        if (!isset(self::PLANS[$plan])) {
            throw new \InvalidArgumentException("Invalid plan: $plan");
        }

        try {
            Database::beginTransaction();

            $accountId = Database::insert('billing_accounts', [
                'user_id' => $userId,
                'plan' => $plan,
                'currency' => 'USD'
            ]);

            $this->updateUserQuota($userId, $plan);

            Database::commit();

            Logger::info('Billing account created', [
                'user_id' => $userId,
                'account_id' => $accountId,
                'plan' => $plan
            ]);

            return $accountId;
        } catch (\Exception $e) {
            Database::rollback();
            Logger::error('Failed to create billing account', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function upgradePlan(string $userId, string $newPlan): bool {
        if (!isset(self::PLANS[$newPlan])) {
            throw new \InvalidArgumentException("Invalid plan: $newPlan");
        }

        try {
            Database::beginTransaction();

            $account = Database::fetch(
                "SELECT * FROM billing_accounts WHERE user_id = :user_id",
                ['user_id' => $userId]
            );

            if (!$account) {
                throw new \RuntimeException('Billing account not found');
            }

            $oldPlan = $account['plan'];
            if ($oldPlan === $newPlan) {
                Database::rollback();
                return true;
            }

            Database::update('billing_accounts', [
                'plan' => $newPlan
            ], ['user_id' => $userId]);

            $this->updateUserQuota($userId, $newPlan);

            if ($newPlan !== 'free') {
                $this->createInvoice($account['id'], $newPlan);
            }

            Database::commit();

            Logger::info('Plan upgraded', [
                'user_id' => $userId,
                'old_plan' => $oldPlan,
                'new_plan' => $newPlan
            ]);

            return true;
        } catch (\Exception $e) {
            Database::rollback();
            Logger::error('Failed to upgrade plan', [
                'user_id' => $userId,
                'new_plan' => $newPlan,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function cancelSubscription(string $userId): bool {
        try {
            Database::beginTransaction();

            Database::update('billing_accounts', [
                'plan' => 'free'
            ], ['user_id' => $userId]);

            $this->updateUserQuota($userId, 'free');

            Database::commit();

            Logger::info('Subscription cancelled', ['user_id' => $userId]);

            return true;
        } catch (\Exception $e) {
            Database::rollback();
            Logger::error('Failed to cancel subscription', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function createInvoice(string $billingAccountId, string $plan): string {
        $planDetails = self::PLANS[$plan];

        try {
            $invoiceId = Database::insert('invoices', [
                'billing_account_id' => $billingAccountId,
                'period' => date('Y-m'),
                'amount_cents' => $planDetails['price'],
                'status' => 'open'
            ]);

            Logger::info('Invoice created', [
                'billing_account_id' => $billingAccountId,
                'invoice_id' => $invoiceId,
                'amount' => $planDetails['price']
            ]);

            return $invoiceId;
        } catch (\Exception $e) {
            Logger::error('Failed to create invoice', [
                'billing_account_id' => $billingAccountId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function processPayment(string $invoiceId, array $paymentData): bool {
        try {
            Database::beginTransaction();

            $invoice = Database::fetch(
                "SELECT * FROM invoices WHERE id = :id",
                ['id' => $invoiceId]
            );

            if (!$invoice || $invoice['status'] !== 'open') {
                throw new \RuntimeException('Invoice not found or already paid');
            }

            $paymentResult = $this->processStripePayment($paymentData, $invoice['amount_cents']);

            if ($paymentResult['success']) {
                Database::update('invoices', [
                    'status' => 'paid',
                    'paid_at' => date('Y-m-d H:i:s'),
                    'payment_reference' => $paymentResult['payment_id']
                ], ['id' => $invoiceId]);

                Database::commit();

                Logger::info('Payment processed successfully', [
                    'invoice_id' => $invoiceId,
                    'payment_id' => $paymentResult['payment_id']
                ]);

                return true;
            } else {
                Database::rollback();
                Logger::warning('Payment failed', [
                    'invoice_id' => $invoiceId,
                    'error' => $paymentResult['error']
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Database::rollback();
            Logger::error('Payment processing failed', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getUsageStats(string $userId): array {
        try {
            $sql = "SELECT
                        COUNT(*) as total_verifications,
                        COUNT(CASE WHEN created_at >= date_trunc('month', CURRENT_DATE) THEN 1 END) as monthly_verifications,
                        COUNT(CASE WHEN created_at >= CURRENT_DATE THEN 1 END) as daily_verifications
                    FROM verifications
                    WHERE user_id = :user_id";

            $usage = Database::fetch($sql, ['user_id' => $userId]);

            $quota = Database::fetch(
                "SELECT allowance, used FROM quotas WHERE user_id = :user_id AND period = 'monthly'",
                ['user_id' => $userId]
            );

            return [
                'total_verifications' => (int)$usage['total_verifications'],
                'monthly_verifications' => (int)$usage['monthly_verifications'],
                'daily_verifications' => (int)$usage['daily_verifications'],
                'monthly_quota' => $quota ? [
                    'used' => (int)$quota['used'],
                    'allowance' => (int)$quota['allowance'],
                    'percentage' => round(($quota['used'] / $quota['allowance']) * 100, 1)
                ] : null
            ];
        } catch (\Exception $e) {
            Logger::error('Failed to get usage stats', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    public function getInvoiceHistory(string $userId): array {
        try {
            $sql = "SELECT i.*, ba.plan
                    FROM invoices i
                    JOIN billing_accounts ba ON i.billing_account_id = ba.id
                    WHERE ba.user_id = :user_id
                    ORDER BY i.created_at DESC
                    LIMIT 50";

            return Database::fetchAll($sql, ['user_id' => $userId]);
        } catch (\Exception $e) {
            Logger::error('Failed to get invoice history', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    private function updateUserQuota(string $userId, string $plan): void {
        $planDetails = self::PLANS[$plan];

        Database::query(
            "INSERT INTO quotas (user_id, period, allowance, used)
             VALUES (:user_id, 'monthly', :allowance, 0)
             ON CONFLICT (user_id, period)
             DO UPDATE SET allowance = EXCLUDED.allowance",
            [
                'user_id' => $userId,
                'allowance' => $planDetails['quota']
            ]
        );
    }

    private function getCurrentUsage(string $userId): array {
        $sql = "SELECT period, allowance, used
                FROM quotas
                WHERE user_id = :user_id";

        return Database::fetchAll($sql, ['user_id' => $userId]);
    }

    private function getNextBillingDate(string $billingAccountId): ?string {
        $sql = "SELECT created_at FROM billing_accounts WHERE id = :id";
        $account = Database::fetch($sql, ['id' => $billingAccountId]);

        if ($account) {
            $created = new \DateTime($account['created_at']);
            $nextBilling = $created->modify('+1 month');
            return $nextBilling->format('Y-m-d');
        }

        return null;
    }

    private function processStripePayment(array $paymentData, int $amountCents): array {
        try {
            $stripeSecretKey = Config::get('STRIPE_SECRET_KEY');

            if (!$stripeSecretKey) {
                return ['success' => false, 'error' => 'Payment processing not configured'];
            }

            $mockPaymentId = 'pi_' . bin2hex(random_bytes(12));

            Logger::info('Mock payment processed', [
                'payment_id' => $mockPaymentId,
                'amount' => $amountCents
            ]);

            return [
                'success' => true,
                'payment_id' => $mockPaymentId
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function calculatePlanRecommendation(string $userId): array {
        $usage = $this->getUsageStats($userId);
        $monthlyUsage = $usage['monthly_verifications'] ?? 0;

        if ($monthlyUsage < 500) {
            $recommended = 'free';
            $reason = 'Your usage fits well within the free plan limits.';
        } elseif ($monthlyUsage < 5000) {
            $recommended = 'pro';
            $reason = 'Pro plan offers better value for your usage level with advanced features.';
        } else {
            $recommended = 'enterprise';
            $reason = 'Enterprise plan provides unlimited usage and premium support for high-volume needs.';
        }

        return [
            'recommended_plan' => $recommended,
            'reason' => $reason,
            'current_usage' => $monthlyUsage,
            'savings' => $this->calculatePotentialSavings($monthlyUsage, $recommended)
        ];
    }

    private function calculatePotentialSavings(int $monthlyUsage, string $recommendedPlan): ?array {
        if ($recommendedPlan === 'free') {
            return null;
        }

        $overage = max(0, $monthlyUsage - 1000); // Free plan limit
        $overageCost = $overage * 0.01; // $0.01 per verification over limit

        $planCost = self::PLANS[$recommendedPlan]['price'] / 100; // Convert cents to dollars

        if ($overageCost > $planCost) {
            return [
                'monthly_savings' => round($overageCost - $planCost, 2),
                'annual_savings' => round(($overageCost - $planCost) * 12, 2)
            ];
        }

        return null;
    }
}