<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;

class SubscriptionService
{
    public static function checkQuota(User $user): bool
    {
        $subscription = $user->subscription()->with('plan')->first();

        // user tidak punya paket subscription baik free maupun pro
        if (!$subscription) return false;

        $plan = $subscription->plan;

        // masa pro expired
        if ($subscription->expires_at && Carbon::now()->greaterThan($subscription->expires_at)) {
            return false;
        }

        // quota null / 0 berarti unlimited (pro)
        if ($plan->quota == 0) {
            return true;
        }

        // quota masih ada
        if ($subscription->used_quota < $plan->quota) {
            $subscription->increment('used_quota');
            return true;
        }

        return false;
    }

    public static function remainingQuota(User $user): ?int
    {
        $subscription = $user->subscription()->with('plan')->first();

        // user tidak punya paket subscription baik free maupun pro
        if (!$subscription) return null;

        $plan = $subscription->plan;

        // unlimited (pro)
        if (is_null($plan->quota)) {
            return null;
        }

        return max(0, $plan->quota - $subscription->used_quota);
    }
}
