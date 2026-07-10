<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperExamAnswer
 */
class ExamAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'developer_certification_id', 'question_id',
        'selected_answers', 'is_correct', 'points_earned',
    ];

    protected function casts(): array
    {
        return [
            'selected_answers' => 'array',
            'is_correct' => 'boolean',
            'points_earned' => 'integer',
        ];
    }

    public function developerCertification(): BelongsTo
    {
        return $this->belongsTo(DeveloperCertification::class, 'developer_certification_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ExamQuestion::class, 'question_id');
    }
}
