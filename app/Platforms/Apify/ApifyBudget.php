<?php

namespace App\Platforms\Apify;

use App\Models\Admin;
use App\Models\ApifyRun;
use App\Notifications\ApifyBudgetReached;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * The monthly Apify spending cap (APIFY_MONTHLY_BUDGET_USD). Spend is the sum of logged runs.
 */
class ApifyBudget
{
    public function limit(): float
    {
        return (float) config('services.apify.monthly_budget_usd');
    }

    public function spent(): float
    {
        return ApifyRun::spentThisMonth();
    }

    public function reached(): bool
    {
        return $this->limit() > 0 && $this->spent() >= $this->limit();
    }

    /**
     * Email every admin, once per month.
     */
    public function alert(): void
    {
        if (Cache::add('platforms:apify:budget-alert:'.now()->format('Y-m'), true, now()->endOfMonth())) {
            Notification::send(Admin::all(), new ApifyBudgetReached($this->spent(), $this->limit()));
        }
    }
}
