<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
/**
 * @mixin IdeHelperChinaInvoice
 */
class ChinaInvoice extends Model {
    use SoftDeletes;
    protected $table = 'china_invoices';
    protected $fillable = [
        'tenant_id','template_id','tax_device_id','order_id','invoice_type',
        'invoice_code','invoice_no','tax_control_code','qr_code_url','status',
        'buyer_name','buyer_tax_id','buyer_address','buyer_phone','buyer_bank','buyer_bank_account',
        'seller_name','seller_tax_id','seller_address','seller_phone','seller_bank','seller_bank_account',
        'amount','tax_rate','tax_amount','total_amount',
        'drawer','reviewer','payee','remark','red_letter_source','pdf_url','issued_at','voided_at',
    ];
    protected function casts(): array {
        return ['amount'=>'decimal:2','tax_rate'=>'decimal:2','tax_amount'=>'decimal:2','total_amount'=>'decimal:2','issued_at'=>'datetime','voided_at'=>'datetime'];
    }
    const STATUSES = ['pending'=>'待开票','issued'=>'已开票','voided'=>'已作废','red_letter'=>'已红冲'];
    const TYPES = ['vat_special'=>'增值税专用发票','vat_normal'=>'增值税普通发票','fiscal_bill'=>'财政电子票据'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function template(): BelongsTo { return $this->belongsTo(ChinaInvoiceTemplate::class, 'template_id'); }
    public function taxDevice(): BelongsTo { return $this->belongsTo(ChinaTaxDevice::class, 'tax_device_id'); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function items(): HasMany { return $this->hasMany(ChinaInvoiceItem::class, 'invoice_id'); }

    /** 生成发票代码（模拟，生产环境需对接税控） */
    public static function generateInvoiceCode(int $tenantId): string {
        return date('Ymd') . str_pad($tenantId % 10000, 4, '0', STR_PAD_LEFT) . str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
    }
    /** 生成发票号码 */
    public static function generateInvoiceNo(int $tenantId): string {
        $seq = static::where('tenant_id', $tenantId)->whereDate('created_at', today())->count() + 1;
        return str_pad($seq, 8, '0', STR_PAD_LEFT);
    }
    /** 生成税控码（模拟） */
    public static function generateTaxControlCode(string $invoiceNo, float $total): string {
        return strtoupper(substr(md5($invoiceNo . $total . config('app.key')), 0, 20));
    }
}
