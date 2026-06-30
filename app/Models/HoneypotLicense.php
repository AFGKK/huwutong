<?php

namespace App\Models;

use App\Enums\LicenseStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * M2-03 主动蜜罐防御 - 蜜罐License
 *
 * 用于生成假License密钥，当被激活尝试时触发实时告警。
 *
 * @property int $id
 * @property string $license_key
 * @property string|null $label
 * @property string $status active|triggered|disabled
 * @property string|null $notes
 * @property Carbon|null $triggered_at
 * @property string|null $triggered_ip
 * @property array|null $triggered_info
 * @property int $trigger_count
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class HoneypotLicense extends Model
{
    protected $fillable = [
        'license_key',
        'label',
        'status',
        'notes',
        'triggered_at',
        'triggered_ip',
        'triggered_info',
        'trigger_count',
        'created_by',
    ];

    protected $casts = [
        'triggered_info' => 'array',
        'triggered_at' => 'datetime',
        'trigger_count' => 'integer',
    ];

    /**
     * 生成随机蜜罐 License Key
     */
    public static function generateKey(): string
    {
        $prefix = config('honeypot.generation.prefix', 'HWT-HNY-');
        $length = config('honeypot.generation.key_length', 16);
        $charset = config('honeypot.charset', 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789');
        $random = '';
        $charLen = strlen($charset);
        for ($i = 0; $i < $length; $i++) {
            $random .= $charset[random_int(0, $charLen - 1)];
        }
        $checksum = strtoupper(substr(md5($prefix . $random . config('app.key')), 0, 4));
        return $prefix . $random . $checksum;
    }

    /**
     * 判断是否可触发
     */
    public function isTriggerable(): bool
    {
        return $this->status === 'active';
    }

    /**
     * 记录触发
     */
    public function recordTrigger(string $ip, array $info = []): void
    {
        $this->update([
            'status' => 'triggered',
            'triggered_at' => $this->triggered_at ?? now(),
            'triggered_ip' => $this->triggered_ip ?? $ip,
            'triggered_info' => $this->triggered_info ?? $info,
            'trigger_count' => $this->trigger_count + 1,
        ]);
    }
}
