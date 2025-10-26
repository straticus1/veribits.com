<?php
namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\RateLimit;

/**
 * Controller for providing anonymous user rate limit information
 */
class AnonymousLimitsController {

    public function getLimits(): void {
        $clientIp = RateLimit::getClientIp();
        $limits = RateLimit::getAnonymousLimits($clientIp);

        Response::success([
            'type' => 'anonymous_trial_status',
            'trial' => [
                'free_scans' => $limits['free_scans'],
                'scans_used' => $limits['scans_used'],
                'scans_remaining' => $limits['scans_remaining'],
                'max_file_size_mb' => $limits['max_file_size_mb'],
                'trial_period_days' => $limits['trial_period_days']
            ],
            'status' => $limits['upgrade_required'] ? 'trial_expired' : 'active',
            'message' => $limits['message'],
            'pricing' => [
                'pay_per_scan' => [
                    'price' => '$0.10 per scan',
                    'description' => 'No monthly fees, pay only for what you use'
                ],
                'monthly' => [
                    'price' => '$9.99/month',
                    'includes' => '500 scans/month',
                    'overage' => '$0.08 per additional scan'
                ],
                'annual' => [
                    'price' => '$99/year',
                    'includes' => '5,000 scans/year',
                    'overage' => '$0.06 per additional scan',
                    'savings' => 'Save 17%'
                ],
                'enterprise' => [
                    'price' => 'Custom pricing',
                    'includes' => 'Unlimited scans, dedicated support, SLA',
                    'contact' => 'sales@veribits.com'
                ]
            ],
            'payment_methods' => ['credit_card', 'paypal', 'invoice'],
            'register_url' => '/api/v1/auth/register',
            'upgrade_url' => '/api/v1/billing/plans'
        ]);
    }
}
