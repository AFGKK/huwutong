<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/**
 * @mixin IdeHelperChinaTaxReport
 */
class ChinaTaxReport extends Model {
    protected $table = 'china_tax_reports';
    protected $fillable = ['tenant_id','period','report_type','total_sales','total_tax','deductible_tax','payable_tax','breakdown','status','submitted_at'];
    protected function casts(): array { return ['breakdown'=>'array','submitted_at'=>'datetime']; }
    const STATUSES = ['draft'=>'草稿','submitted'=>'已申报','approved'=>'已完成'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
