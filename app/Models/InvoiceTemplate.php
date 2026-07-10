<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperInvoiceTemplate
 */
class InvoiceTemplate extends Model
{
    use HasFactory;

    protected $table = 'invoice_templates';

    protected $fillable = [
        'tenant_id', 'name', 'code', 'is_default',
        'header', 'footer', 'color_scheme', 'locale', 'currency',
        'line_item_fields', 'show_fields', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'header' => 'array',
            'footer' => 'array',
            'line_item_fields' => 'array',
            'show_fields' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
