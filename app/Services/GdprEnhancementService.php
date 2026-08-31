<?php

namespace App\Services;

use App\Models\AutomatedDecisionRecord;
use App\Models\DataBreachNotification;
use App\Models\DpiaRecord;
use App\Models\ProcessingActivityRecord;
use App\Models\SubProcessorAssessment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GdprEnhancementService
{
    // ─── DPIA ───

    public function listDpias(array $filters = [], int $perPage = 20)
    {
        $query = DpiaRecord::with(['creator:id,name'])->orderBy('created_at', 'desc');
        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['processing_type'])) $query->where('processing_type', $filters['processing_type']);
        return $query->paginate($perPage);
    }

    public function createDpia(array $data): DpiaRecord
    {
        $data['reference'] = $this->generateReference('DPIA');
        $data['created_by'] = auth()->id();
        return DpiaRecord::create($data);
    }

    public function updateDpia(DpiaRecord $dpia, array $data): DpiaRecord
    {
        if (in_array($dpia->status, ['approved', 'rejected']) && empty($data['reviewed_by'])) {
            throw new \RuntimeException(__("app.gdpr_enhancement.dpia_reviewed_cannot_edit"));
        }
        $dpia->update($data);
        return $dpia->fresh();
    }

    public function reviewDpia(DpiaRecord $dpia, string $status, ?string $notes = null): DpiaRecord
    {
        if (!in_array($status, ['approved', 'rejected'])) {
            throw new \RuntimeException(__("app.gdpr_enhancement.invalid_review_status"));
        }
        $dpia->update([
            'status' => $status,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);
        return $dpia->fresh();
    }

    public function getDpiaStats(): array
    {
        return [
            'total' => DpiaRecord::count(),
            'draft' => DpiaRecord::where('status', 'draft')->count(),
            'in_review' => DpiaRecord::where('status', 'in_review')->count(),
            'approved' => DpiaRecord::where('status', 'approved')->count(),
            'rejected' => DpiaRecord::where('status', 'rejected')->count(),
        ];
    }

    // ─── 数据泄露通知 ───

    public function listBreaches(array $filters = [], int $perPage = 20)
    {
        $query = DataBreachNotification::with(['reporter:id,name', 'assignee:id,name'])
            ->orderBy('detected_at', 'desc');
        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['severity'])) $query->where('severity', $filters['severity']);
        return $query->paginate($perPage);
    }

    public function createBreach(array $data): DataBreachNotification
    {
        $data['reference'] = $this->generateReference('BR');
        $data['reported_by'] = auth()->id();
        return DataBreachNotification::create($data);
    }

    public function updateBreach(DataBreachNotification $breach, array $data): DataBreachNotification
    {
        $breach->update($data);
        return $breach->fresh();
    }

    public function getBreachStats(): array
    {
        return [
            'total' => DataBreachNotification::count(),
            'detected' => DataBreachNotification::whereIn('status', ['detected', 'assessing'])->count(),
            'resolved' => DataBreachNotification::whereIn('status', ['resolved', 'closed'])->count(),
            'critical' => DataBreachNotification::where('severity', 'critical')->count(),
            'by_severity' => DataBreachNotification::selectRaw('severity, count(*) as count')
                ->groupBy('severity')->pluck('count', 'severity'),
        ];
    }

    // ─── ROPA ───

    public function listRopas(array $filters = [], int $perPage = 20)
    {
        $query = ProcessingActivityRecord::with('dpia')->orderBy('created_at', 'desc');
        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['processing_type'])) $query->where('processing_type', $filters['processing_type']);
        return $query->paginate($perPage);
    }

    public function createRopa(array $data): ProcessingActivityRecord
    {
        $data['reference'] = $this->generateReference('ROPA');
        return ProcessingActivityRecord::create($data);
    }

    public function updateRopa(ProcessingActivityRecord $ropa, array $data): ProcessingActivityRecord
    {
        $ropa->update($data);
        return $ropa->fresh();
    }

    public function getRopaStats(): array
    {
        return [
            'total' => ProcessingActivityRecord::count(),
            'active' => ProcessingActivityRecord::where('status', 'active')->count(),
            'with_dpia' => ProcessingActivityRecord::where('has_dpia', true)->count(),
        ];
    }

    // ─── 子处理商 ───

    public function listSubProcessors(array $filters = [], int $perPage = 20)
    {
        $query = SubProcessorAssessment::orderBy('created_at', 'desc');
        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        return $query->paginate($perPage);
    }

    public function createSubProcessor(array $data): SubProcessorAssessment
    {
        return SubProcessorAssessment::create($data);
    }

    public function updateSubProcessor(SubProcessorAssessment $sp, array $data): SubProcessorAssessment
    {
        $sp->update($data);
        return $sp->fresh();
    }

    // ─── 自动决策 ───

    public function listAutoDecisions(array $filters = [], int $perPage = 20)
    {
        $query = AutomatedDecisionRecord::orderBy('created_at', 'desc');
        if (!empty($filters['type'])) $query->where('type', $filters['type']);
        if (isset($filters['is_active'])) $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        return $query->paginate($perPage);
    }

    public function createAutoDecision(array $data): AutomatedDecisionRecord
    {
        return AutomatedDecisionRecord::create($data);
    }

    public function updateAutoDecision(AutomatedDecisionRecord $record, array $data): AutomatedDecisionRecord
    {
        $record->update($data);
        return $record->fresh();
    }

    // ─── 辅助 ───

    protected function generateReference(string $prefix): string
    {
        $year = now()->format('Y');
        $last = DB::table(match ($prefix) {
            'DPIA' => 'dpia_records',
            'BR' => 'data_breach_notifications',
            'ROPA' => 'processing_activity_records',
        })->where('reference', 'like', "{$prefix}-{$year}-%")
            ->orderBy('id', 'desc')
            ->value('reference');

        $seq = $last ? (int) Str::afterLast($last, '-') + 1 : 1;
        return "{$prefix}-{$year}-" . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }
}
