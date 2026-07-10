<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperExamQuestion
 */
class ExamQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'certification_level_id', 'question', 'type',
        'options', 'explanation', 'points', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_active' => 'boolean',
            'points' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function certificationLevel(): BelongsTo
    {
        return $this->belongsTo(CertificationLevel::class, 'certification_level_id');
    }

    /**
     * 获取正确的答案ID列表
     */
    public function getCorrectAnswerIds(): array
    {
        $options = $this->options ?? [];
        return collect($options)
            ->where('is_correct', true)
            ->pluck('id')
            ->values()
            ->toArray();
    }

    /**
     * 检查答案是否正确（不含顺序敏感性）
     */
    public function checkAnswer(array $selectedIds): bool
    {
        $correctIds = $this->getCorrectAnswerIds();
        sort($selectedIds);
        sort($correctIds);
        return $selectedIds === $correctIds;
    }
}
