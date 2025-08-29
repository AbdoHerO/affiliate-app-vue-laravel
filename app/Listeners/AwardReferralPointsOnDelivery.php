<?php

namespace App\Listeners;

use App\Events\OrderDelivered;
use App\Models\ReferralDispensation;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AwardReferralPointsOnDelivery
{
    /**
     * Points awarded when a referred user has a delivered order.
     */
    const POINTS_PER_DELIVERED_ORDER = 10;

    /**
     * Handle the event.
     */
    public function handle(OrderDelivered $event): void
    {
        try {
            $order = $event->order;
            
            // Get the user who placed the order
            $orderUser = $order->user;
            if (!$orderUser) {
                Log::info('Order has no associated user, skipping referral points', [
                    'order_id' => $order->id,
                ]);
                return;
            }

            // Check if this user was referred by an affiliate
            if (!$orderUser->affiliate_parrained_by) {
                Log::info('User was not referred by an affiliate, skipping referral points', [
                    'order_id' => $order->id,
                    'user_id' => $orderUser->id,
                ]);
                return;
            }

            // Check if we've already awarded points for this order
            $existingDispensation = ReferralDispensation::where('reference', 'ORDER-' . $order->id)
                ->where('referrer_affiliate_id', $orderUser->affiliate_parrained_by)
                ->first();

            if ($existingDispensation) {
                Log::info('Referral points already awarded for this order', [
                    'order_id' => $order->id,
                    'dispensation_id' => $existingDispensation->id,
                ]);
                return;
            }

            // Award points to the referring affiliate
            $dispensation = ReferralDispensation::create([
                'referrer_affiliate_id' => $orderUser->affiliate_parrained_by,
                'points' => self::POINTS_PER_DELIVERED_ORDER,
                'comment' => "10 points pour commande livrée d'un utilisateur parrainé (Commande #{$order->id})",
                'reference' => 'ORDER-' . $order->id,
                'created_by_admin_id' => $this->getSystemAdminId(),
                'metadata' => [
                    'order_id' => $order->id,
                    'referred_user_id' => $orderUser->id,
                    'delivery_trigger' => $event->trigger,
                    'delivery_metadata' => $event->metadata,
                    'order_total' => $order->total_ttc,
                    'order_currency' => $order->devise,
                ],
            ]);

            // Update the affiliate's points balance
            $affiliate = $dispensation->referrerAffiliate;
            if ($affiliate) {
                $affiliate->increment('points', self::POINTS_PER_DELIVERED_ORDER);
            }

            Log::info('Referral points awarded for delivered order', [
                'order_id' => $order->id,
                'referred_user_id' => $orderUser->id,
                'referring_affiliate_id' => $orderUser->affiliate_parrained_by,
                'points_awarded' => self::POINTS_PER_DELIVERED_ORDER,
                'dispensation_id' => $dispensation->id,
                'trigger' => $event->trigger,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to award referral points on delivery', [
                'order_id' => $event->order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Get the system admin ID for automated dispensations.
     */
    private function getSystemAdminId(): ?string
    {
        $systemAdmin = User::role('admin')->first();
        return $systemAdmin?->id;
    }
}
