<?php

namespace App\Events;

use App\Models\License;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LicenseStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public License $license,
        public string  $oldStatus,
        public string  $newStatus,
        public ?string $reason = null,
    ) {}
}
