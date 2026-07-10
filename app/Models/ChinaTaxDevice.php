<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
/**
 * @mixin IdeHelperChinaTaxDevice
 */
class ChinaTaxDevice extends Model {
    protected $table = 'china_tax_devices';
    protected $fillable = ['tenant_id','name','device_type','tax_authority','taxpayer_id','company_name','registered_address','phone','bank_name','bank_account','certificate','is_active'];
    protected function casts(): array { return ['certificate' => 'encrypted','is_active' => 'boolean']; }
    const DEVICE_TYPES = ['ukey' => '税务UKey','tax_disk' => '税控盘','cloud' => '云开票'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function invoices(): HasMany { return $this->hasMany(ChinaInvoice::class, 'tax_device_id'); }
}
