<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
/**
 * @mixin IdeHelperChinaInvoiceTemplate
 */
class ChinaInvoiceTemplate extends Model {
    protected $table = 'china_invoice_templates';
    protected $fillable = ['tenant_id','name','invoice_type','is_electronic','title','tax_calculation','line_item_defaults','metadata','is_active'];
    protected function casts(): array { return ['is_electronic'=>'boolean','is_active'=>'boolean','line_item_defaults'=>'array','metadata'=>'array']; }
    const INVOICE_TYPES = ['vat_special'=>'增值税专用发票','vat_normal'=>'增值税普通发票','fiscal_bill'=>'财政电子票据','receipt'=>'收款收据'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function invoices(): HasMany { return $this->hasMany(ChinaInvoice::class, 'template_id'); }
}
