<?php

namespace App\Services;

class DeviceLimiterResult
{
    public function __construct(
        public readonly bool   $allowed,
        public readonly int    $currentCount,
        public readonly int    $maxDevices,
        public readonly bool   $isExistingDevice = false,
        public readonly string $reason = '',
    ) {}
}
