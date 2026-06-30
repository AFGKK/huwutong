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

    /**
     * 兼容旧的 previousStatus 属性名
     */
    public function __get(string $name): mixed
    {
        return match ($name) {
            'previousStatus' => $this->oldStatus,
            default => throw new \RuntimeException("Property {$name} does not exist."),
        };
    }
}
