<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    use HasFactory;

    protected $table = 'user_preferences';

    protected $fillable = [
        'tenant_id', 'user_id', 'customer_id',
        'preference_key', 'preference_value', 'preference_type',
    ];

    const KEYS = [
        'preferred_layout' => '偏好布局',
        'content_focus' => '内容焦点',
        'notification_freq' => '通知频率',
        'dashboard_widgets' => '仪表盘组件',
        'language' => '语言偏好',
        'theme' => '主题偏好',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
}
