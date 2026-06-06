<?php

namespace App\Events;

use App\Models\License;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LicenseAboutToExpire
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param License $license         即将过期的 License
     * @param int     $daysRemaining   剩余天数（7/3/1）
     * @param string  $reminderLevel   '7_days' | '3_days' | '1_day'
     */
    public function __construct(
        public License $license,
        public int     $daysRemaining,
        public string  $reminderLevel = '7_days',
    ) {}
}
