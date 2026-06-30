<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LicenseTransferRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference', 'type', 'status',
        'license_id', 'requested_by', 'approved_by', 'approved_at', 'cancelled_by',
        'source_info',
        'target_customer_id', 'target_user_id',
        'target_device_id', 'target_device_fingerprint', 'target_device_name',
        'reason', 'admin_notes',
        'verification_token', 'verification_expires_at',
        'request_ip', 'audit_log',
    ];

    protected function casts(): array
    {
        return [
            'source_info' => 'array',
            'audit_log' => 'array',
            'approved_at' => 'datetime',
            'verification_expires_at' => 'datetime',
        ];
    }

    const TYPES = [
        'device_transfer' => '设备转移',
        'customer_transfer' => '客户转移',
        'user_transfer' => '用户转移',
    ];

    const STATUSES = ['pending', 'approved', 'rejected', 'cancelled', 'completed', 'expired'];

    public function license()
    {
        return $this->belongsTo(License::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function targetCustomer()
    {
        return $this->belongsTo(Customer::class, 'target_customer_id');
    }

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function targetDevice()
    {
        return $this->belongsTo(Device::class, 'target_device_id');
    }

    public function isProcessable(): bool
    {
        return in_array($this->status, ['pending']);
    }
}
