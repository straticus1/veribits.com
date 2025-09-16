<?php
namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;
use VeriBits\Utils\Validator;
use VeriBits\Utils\Logger;
use VeriBits\Utils\Config;
use VeriBits\Services\BillingService;

class BillingController {
    private BillingService $billingService;

    public function __construct() {
        $this->billingService = new BillingService();
    }

    public function getAccount(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        try {
            $account = $this->billingService->getUserBillingAccount($userId);

            if (!$account) {
                Response::error('Billing account not found', 404);
                return;
            }

            Response::success($account);
        } catch (\Exception $e) {
            Logger::error('Failed to get billing account', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to retrieve billing account', 500);
        }
    }

    public function getPlans(): void {
        try {
            $plans = $this->billingService->getAllPlans();
            Response::success(['plans' => $plans]);
        } catch (\Exception $e) {
            Logger::error('Failed to get plans', ['error' => $e->getMessage()]);
            Response::error('Failed to retrieve plans', 500);
        }
    }

    public function upgradePlan(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $validator = new Validator($body);

        $validator->required('plan')->in('plan', ['free', 'pro', 'enterprise']);

        if (!$validator->isValid()) {
            Response::validationError($validator->getErrors());
            return;
        }

        $newPlan = $validator->sanitize('plan');

        try {
            $success = $this->billingService->upgradePlan($userId, $newPlan);

            if ($success) {
                Logger::info('Plan upgrade successful', [
                    'user_id' => $userId,
                    'new_plan' => $newPlan
                ]);

                Response::success([
                    'plan' => $newPlan,
                    'message' => 'Plan upgraded successfully'
                ]);
            } else {
                Response::error('Failed to upgrade plan', 500);
            }
        } catch (\Exception $e) {
            Logger::error('Plan upgrade failed', [
                'user_id' => $userId,
                'new_plan' => $newPlan,
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to upgrade plan: ' . $e->getMessage(), 500);
        }
    }

    public function cancelSubscription(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        try {
            $success = $this->billingService->cancelSubscription($userId);

            if ($success) {
                Logger::info('Subscription cancelled', ['user_id' => $userId]);
                Response::success([], 'Subscription cancelled successfully');
            } else {
                Response::error('Failed to cancel subscription', 500);
            }
        } catch (\Exception $e) {
            Logger::error('Subscription cancellation failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to cancel subscription', 500);
        }
    }

    public function getUsage(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        try {
            $usage = $this->billingService->getUsageStats($userId);
            Response::success($usage);
        } catch (\Exception $e) {
            Logger::error('Failed to get usage stats', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to retrieve usage statistics', 500);
        }
    }

    public function getInvoices(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        try {
            $invoices = $this->billingService->getInvoiceHistory($userId);
            Response::success(['invoices' => $invoices]);
        } catch (\Exception $e) {
            Logger::error('Failed to get invoice history', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to retrieve invoice history', 500);
        }
    }

    public function processPayment(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $validator = new Validator($body);

        $validator->required('invoice_id')->string('invoice_id')
                  ->required('payment_method')->string('payment_method')
                  ->string('card_token');

        if (!$validator->isValid()) {
            Response::validationError($validator->getErrors());
            return;
        }

        $invoiceId = $validator->sanitize('invoice_id');
        $paymentData = [
            'payment_method' => $validator->sanitize('payment_method'),
            'card_token' => $validator->sanitize('card_token')
        ];

        try {
            $success = $this->billingService->processPayment($invoiceId, $paymentData);

            if ($success) {
                Logger::info('Payment processed successfully', [
                    'user_id' => $userId,
                    'invoice_id' => $invoiceId
                ]);
                Response::success([], 'Payment processed successfully');
            } else {
                Response::error('Payment processing failed', 400);
            }
        } catch (\Exception $e) {
            Logger::error('Payment processing failed', [
                'user_id' => $userId,
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage()
            ]);
            Response::error('Payment processing failed', 500);
        }
    }

    public function getPlanRecommendation(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        try {
            $recommendation = $this->billingService->calculatePlanRecommendation($userId);
            Response::success($recommendation);
        } catch (\Exception $e) {
            Logger::error('Failed to get plan recommendation', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to generate plan recommendation', 500);
        }
    }

    public function webhookStripe(): void {
        $payload = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

        try {
            $event = $this->verifyStripeWebhook($payload, $signature);

            switch ($event['type']) {
                case 'invoice.payment_succeeded':
                    $this->handlePaymentSucceeded($event['data']['object']);
                    break;
                case 'invoice.payment_failed':
                    $this->handlePaymentFailed($event['data']['object']);
                    break;
                case 'customer.subscription.deleted':
                    $this->handleSubscriptionCancelled($event['data']['object']);
                    break;
            }

            Response::success([], 'Webhook processed');
        } catch (\Exception $e) {
            Logger::error('Stripe webhook processing failed', [
                'error' => $e->getMessage(),
                'payload' => substr($payload, 0, 500)
            ]);
            Response::error('Webhook processing failed', 400);
        }
    }

    private function verifyStripeWebhook(string $payload, string $signature): array {
        $webhookSecret = Config::get('STRIPE_WEBHOOK_SECRET');

        if (!$webhookSecret) {
            throw new \RuntimeException('Stripe webhook secret not configured');
        }

        return json_decode($payload, true);
    }

    private function handlePaymentSucceeded(array $invoice): void {
        Logger::info('Payment succeeded webhook', [
            'invoice_id' => $invoice['id'],
            'amount' => $invoice['amount_paid']
        ]);
    }

    private function handlePaymentFailed(array $invoice): void {
        Logger::warning('Payment failed webhook', [
            'invoice_id' => $invoice['id'],
            'amount' => $invoice['amount_due']
        ]);
    }

    private function handleSubscriptionCancelled(array $subscription): void {
        Logger::info('Subscription cancelled webhook', [
            'subscription_id' => $subscription['id'],
            'customer' => $subscription['customer']
        ]);
    }
}