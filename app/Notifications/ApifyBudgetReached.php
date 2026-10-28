<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Number;

class ApifyBudgetReached extends Notification
{
    use Queueable;

    public function __construct(public float $spent, public float $limit) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Apify budget reached: Instagram, TikTok and X syncs are paused')
            ->line('Apify spending this month is '.Number::currency($this->spent).' of the '.Number::currency($this->limit).' budget.')
            ->line('Instagram, TikTok and X will not sync, and new creators on those platforms cannot be looked up, until next month.')
            ->line('To resume now, raise APIFY_MONTHLY_BUDGET_USD in .env.');
    }
}
