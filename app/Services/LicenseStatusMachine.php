<?php

namespace App\Services;

use App\Enums\LicenseStatus;
use App\Events\LicenseStatusChanged;
use App\Models\License;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class LicenseStatusMachine
{
    private const TRANSITIONS = [
        'pending' => ['active', 'revoked', 'blacklisted'],
        'active' => ['suspended', 'frozen', 'expired', 'revoked', 'refunded', 'blacklisted'],
        'suspended' => ['active', 'frozen', 'expired', 'revoked', 'refunded', 'blacklisted'],
        'frozen' => ['active', 'suspended', 'expired', 'revoked', 'refunded', 'blacklisted'],
        'expired' => ['active', 'revoked', 'refunded', 'blacklisted'],
        'revoked' => ['blacklisted'],
        'refunded' => ['blacklisted'],
        'blacklisted' => [],
    ];

    private static function reasonTemplates(): array
    {
        $t = fn(string $k) => __('app.license_status_machine.' . $k);
        return [
            'pending->active' => $t('transition_pending_active'),
            'pending->revoked' => $t('transition_pending_revoked'),
            'pending->blacklisted' => $t('transition_pending_blacklisted'),
            'active->suspended' => $t('transition_active_suspended'),
            'active->frozen' => $t('transition_active_frozen'),
            'active->expired' => $t('transition_active_expired'),
            'active->revoked' => $t('transition_active_revoked'),
            'active->refunded' => $t('transition_active_refunded'),
            'active->blacklisted' => $t('transition_active_blacklisted'),
            'suspended->active' => $t('transition_suspended_active'),
            'suspended->frozen' => $t('transition_suspended_frozen'),
            'suspended->expired' => $t('transition_suspended_expired'),
            'suspended->revoked' => $t('transition_suspended_revoked'),
            'suspended->refunded' => $t('transition_suspended_refunded'),
            'suspended->blacklisted' => $t('transition_suspended_blacklisted'),
            'frozen->active' => $t('transition_frozen_active'),
            'frozen->suspended' => $t('transition_frozen_suspended'),
            'frozen->expired' => $t('transition_frozen_expired'),
            'frozen->revoked' => $t('transition_frozen_revoked'),
            'frozen->refunded' => $t('transition_frozen_refunded'),
            'frozen->blacklisted' => $t('transition_frozen_blacklisted'),
            'expired->active' => $t('transition_expired_active'),
            'expired->revoked' => $t('transition_expired_revoked'),
            'expired->refunded' => $t('transition_expired_refunded'),
            'expired->blacklisted' => $t('transition_expired_blacklisted'),
            'revoked->blacklisted' => $t('transition_revoked_blacklisted'),
            'refunded->blacklisted' => $t('transition_refunded_blacklisted'),
        ];
    }

    public function canTransition(string|LicenseStatus $from, string|LicenseStatus $to): array
    {
        $fromStr = $from instanceof LicenseStatus ? $from->value : $from;
        $toStr = $to instanceof LicenseStatus ? $to->value : $to;

        $allowed = self::TRANSITIONS[$fromStr] ?? [];

        if (in_array($toStr, $allowed, true)) {
            $templateKey = "{$fromStr}->{$toStr}";
            return [
                'allowed' => true,
                'reason' => self::reasonTemplates()[$templateKey] ?? __('app.license_status_machine.status_change', ['from' => $fromStr, 'to' => $toStr]),
            ];
        }

        if (LicenseStatus::tryFrom($toStr) === null) {
            return ['allowed' => false, 'reason' => __('app.license_status_machine.invalid_target_status', ['status' => $toStr])];
        }

        if ($fromStr === $toStr) {
            return ['allowed' => false, 'reason' => __('app.license_status_machine.status_unchanged', ['status' => $fromStr])];
        }

        if (($fromStr === 'blacklisted')) {
            return ['allowed' => false, 'reason' => __('app.license_status_machine.blacklisted_terminal')];
        }

        $available = array_map(fn($s) => self::reasonTemplates()["{$fromStr}->{$s}"] ?? $s, $allowed);
        return [
            'allowed' => false,
            'reason' => __('app.license_status_machine.transition_not_allowed', ['from' => $fromStr, 'to' => $toStr, 'available' => implode(', ', $available)]),
        ];
    }

    public function transition(License $license, string|LicenseStatus $to, ?User $operator = null, ?string $reason = null): License
    {
        $from = $license->status;
        $check = $this->canTransition($from, $to);

        if (!$check['allowed']) {
            throw new \RuntimeException($check['reason']);
        }

        $toStr = $to instanceof LicenseStatus ? $to->value : $to;
        $finalReason = $reason ?: $check['reason'];

        return DB::transaction(function () use ($license, $from, $toStr, $operator, $finalReason) {
            $this->beforeTransition($license, $toStr, $operator, $finalReason);

            $oldStatus = $license->status;
            $license->update(['status' => $toStr]);

            $this->logTransition($license, $oldStatus, $toStr, $operator, $finalReason);

            Event::dispatch(new LicenseStatusChanged($license, $oldStatus, $toStr, $operator));

            $this->afterTransition($license, $oldStatus, $toStr, $operator);

            Log::info("License status change: {$license->license_key} {$oldStatus} -> {$toStr}", [
                'license_id' => $license->id,
                'operator' => $operator?->id,
                'reason' => $finalReason,
            ]);

            return $license->fresh();
        });
    }

    public function batchTransition(
        iterable $licenses,
        string|LicenseStatus $to,
        ?User $operator = null,
        ?string $reason = null
    ): array {
        $result = ['success' => [], 'failed' => []];

        foreach ($licenses as $license) {
            try {
                $this->transition($license, $to, $operator, $reason);
                $result['success'][] = $license->id;
            } catch (\Exception $e) {
                $result['failed'][] = [
                    'id' => $license->id,
                    'license_key' => $license->license_key,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $result;
    }

    public function getAllowedTransitions(string|LicenseStatus $from): array
    {
        $fromStr = $from instanceof LicenseStatus ? $from->value : $from;
        $allowed = self::TRANSITIONS[$fromStr] ?? [];

        return array_map(function ($status) use ($fromStr) {
            $key = "{$fromStr}->{$status}";
            return [
                'to' => $status,
                'label' => self::reasonTemplates()[$key] ?? $status,
            ];
        }, $allowed);
    }

    public function getTransitionMatrix(): array
    {
        $matrix = [];
        foreach (self::TRANSITIONS as $from => $toList) {
            $matrix[$from] = array_map(function ($to) use ($from) {
                $key = "{$from}->{$to}";
                return [
                    'to' => $to,
                    'label' => self::reasonTemplates()[$key] ?? $to,
                ];
            }, $toList);
        }
        return $matrix;
    }

    protected function beforeTransition(License $license, string $toStatus, ?User $operator, string $reason): void
    {
    }

    protected function afterTransition(License $license, string $oldStatus, string $newStatus, ?User $operator): void
    {
    }

    protected function logTransition(License $license, string $from, string $to, ?User $operator, string $reason): void
    {
        if (method_exists($license, 'auditLogs')) {
            $license->auditLogs()->create([
                'action' => 'status_changed',
                'description' => __('app.license_status_machine.status_change', ['from' => $from, 'to' => $to]),
                'old_value' => $from,
                'new_value' => $to,
                'reason' => $reason,
                'operator_id' => $operator?->id,
                'operator_name' => $operator?->name,
            ]);
        }

        Log::info("LicenseStatusMachine: {$license->license_key} {$from} -> {$to}", [
            'license_id' => $license->id,
            'operator' => $operator?->id,
            'reason' => $reason,
        ]);
    }
}