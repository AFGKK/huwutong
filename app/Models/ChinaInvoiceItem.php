<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/**
 * @mixin IdeHelperChinaInvoiceItem
 */
class ChinaInvoiceItem extends Model {
    protected $table = 'china_invoice_items';
    protected $fillable = ['invoice_id','item_name','specification','unit','quantity','unit_price','amount','tax_rate','tax_amount','tax_code','tax_code_name','is_discount'];
    protected function casts(): array { return ['quantity'=>'integer','unit_price'=>'decimal:6','tax_rate'=>'decimal:2']; }
    public function invoice(): BelongsTo { return $this->belongsTo(ChinaInvoice::class, 'invoice_id'); }
}
