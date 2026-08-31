<?php

namespace App\Services;

use App\Models\CertificationLevel;
use App\Models\DeveloperCertification;
use App\Models\ExamAnswer;
use App\Models\ExamQuestion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 开发者认证计划服务
 *
 * M3-58
 * 管理认证等级、考试、证书颁发和徽章
 */
class CertificationService
{
    // ──────────────────────────────────────────────
    //  认证等级管理
    // ──────────────────────────────────────────────

    /**
     * 获取所有认证等级
     */
    public function getLevels(int $tenantId, bool $activeOnly = true): array
    {
        $query = CertificationLevel::withCount(['developerCertifications as total_certified' => function ($q) {
            $q->where('status', DeveloperCertification::STATUS_PASSED);
        }])->where('tenant_id', $tenantId);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('level_order')->get()->toArray();
    }

    /**
     * 创建认证等级
     */
    public function createLevel(int $tenantId, array $data): CertificationLevel
    {
        return CertificationLevel::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'level_order' => $data['level_order'] ?? 0,
            'icon_url' => $data['icon_url'] ?? null,
            'color' => $data['color'] ?? null,
            'passing_score' => $data['passing_score'] ?? 70,
            'requirements' => $data['requirements'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * 更新认证等级
     */
    public function updateLevel(int $levelId, array $data): CertificationLevel
    {
        $level = CertificationLevel::findOrFail($levelId);
        $level->update($data);
        return $level->fresh();
    }

    // ──────────────────────────────────────────────
    //  题库管理
    // ──────────────────────────────────────────────

    /**
     * 获取认证等级的试题
     */
    public function getQuestions(int $levelId, bool $activeOnly = true): array
    {
        $level = CertificationLevel::findOrFail($levelId);
        $query = $level->questions()->orderBy('sort_order');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->get()->toArray();
    }

    /**
     * 添加试题
     */
    public function addQuestion(int $levelId, array $data): ExamQuestion
    {
        $level = CertificationLevel::findOrFail($levelId);

        return ExamQuestion::create([
            'certification_level_id' => $levelId,
            'question' => $data['question'],
            'type' => $data['type'] ?? 'single_choice',
            'options' => $data['options'],
            'explanation' => $data['explanation'] ?? null,
            'points' => $data['points'] ?? 1,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * 批量导入试题
     */
    public function bulkAddQuestions(int $levelId, array $questions): array
    {
        $created = [];
        DB::transaction(function () use ($levelId, $questions, &$created) {
            foreach ($questions as $data) {
                $created[] = $this->addQuestion($levelId, $data);
            }
        });
        return $created;
    }

    // ──────────────────────────────────────────────
    //  考试流程
    // ──────────────────────────────────────────────

    /**
     * 开始考试
     */
    public function startExam(int $userId, int $levelId): DeveloperCertification
    {
        $level = CertificationLevel::findOrFail($levelId);

        // 检查现有认证
        $existing = DeveloperCertification::where('user_id', $userId)
            ->where('certification_level_id', $levelId)
            ->first();

        if ($existing) {
            if ($existing->isPassed() && $existing->isValid()) {
                throw new \RuntimeException(__('app.certification.already_passed'));
            }
            if ($existing->canRetake()) {
                // 重新开始考试
                $existing->update([
                    'status' => DeveloperCertification::STATUS_IN_PROGRESS,
                    'attempts' => $existing->attempts + 1,
                    'score' => null,
                    'exam_started_at' => now(),
                    'exam_completed_at' => null,
                ]);
                // 清空旧答案
                $existing->answers()->delete();
                return $existing->fresh();
            }
            if ($existing->attempts >= $existing->max_attempts) {
                throw new \RuntimeException(__('app.certification.max_retake'));
            }
            if ($existing->status === DeveloperCertification::STATUS_IN_PROGRESS) {
                throw new \RuntimeException(__('app.certification.exam_in_progress'));
            }
        }

        return DeveloperCertification::create([
            'tenant_id' => $level->tenant_id,
            'user_id' => $userId,
            'certification_level_id' => $levelId,
            'certificate_number' => 'CERT-' . strtoupper(Str::random(12)),
            'status' => DeveloperCertification::STATUS_IN_PROGRESS,
            'attempts' => 1,
            'max_attempts' => 3,
            'exam_started_at' => now(),
            'expires_at' => now()->addYear(),
        ]);
    }

    /**
     * 获取考试试题（去除正确答案）
     */
    public function getExamQuestions(int $devCertId): array
    {
        $devCert = DeveloperCertification::with('certificationLevel')
            ->findOrFail($devCertId);

        if ($devCert->status !== DeveloperCertification::STATUS_IN_PROGRESS) {
            throw new \RuntimeException(__('app.certification.exam_not_in_progress'));
        }

        $questions = $devCert->certificationLevel->activeQuestions;

        return $questions->map(function (ExamQuestion $q) {
            $questionData = $q->toArray();
            // 隐藏正确答案
            $questionData['options'] = collect($q->options)->map(function ($opt) {
                unset($opt['is_correct']);
                return $opt;
            })->toArray();
            return $questionData;
        })->toArray();
    }

    /**
     * 提交答案
     */
    public function submitAnswer(int $devCertId, int $questionId, array $selectedAnswers): ExamAnswer
    {
        $devCert = DeveloperCertification::findOrFail($devCertId);

        if ($devCert->status !== DeveloperCertification::STATUS_IN_PROGRESS) {
            throw new \RuntimeException(__('app.certification.exam_not_in_progress'));
        }

        // 检查是否已答过此题
        $existing = ExamAnswer::where('developer_certification_id', $devCertId)
            ->where('question_id', $questionId)
            ->first();

        if ($existing) {
            throw new \RuntimeException(__('app.certification.question_answered'));
        }

        $question = ExamQuestion::findOrFail($questionId);
        $isCorrect = $question->checkAnswer($selectedAnswers);
        $pointsEarned = $isCorrect ? $question->points : 0;

        return ExamAnswer::create([
            'developer_certification_id' => $devCertId,
            'question_id' => $questionId,
            'selected_answers' => $selectedAnswers,
            'is_correct' => $isCorrect,
            'points_earned' => $pointsEarned,
        ]);
    }

    /**
     * 提交考试（计算总分）
     */
    public function submitExam(int $devCertId): DeveloperCertification
    {
        $devCert = DeveloperCertification::with('certificationLevel')
            ->findOrFail($devCertId);

        if ($devCert->status !== DeveloperCertification::STATUS_IN_PROGRESS) {
            throw new \RuntimeException(__('app.certification.exam_not_in_progress'));
        }

        $answers = ExamAnswer::where('developer_certification_id', $devCertId)->get();
        $totalPoints = $answers->sum('points_earned');

        // 计算最大可能分数
        $maxPoints = $devCert->certificationLevel->activeQuestions->sum('points');

        if ($answers->count() === 0) {
            throw new \RuntimeException(__('app.certification.answer_at_least_one'));
        }

        $scorePercentage = $maxPoints > 0 ? round(($totalPoints / $maxPoints) * 100) : 0;
        $passingScore = $devCert->certificationLevel->passing_score;
        $passed = $scorePercentage >= $passingScore;

        $devCert->update([
            'status' => $passed ? DeveloperCertification::STATUS_PASSED : DeveloperCertification::STATUS_FAILED,
            'score' => $scorePercentage,
            'total_points' => $maxPoints,
            'exam_completed_at' => now(),
            'certificate_issued_at' => $passed ? now() : null,
            'badge_issued' => $passed,
        ]);

        if ($passed) {
            $this->issueBadge($devCert);
        }

        return $devCert->fresh(['certificationLevel']);
    }

    // ──────────────────────────────────────────────
    //  徽章与证书
    // ──────────────────────────────────────────────

    /**
     * 颁发徽章（生成SVG徽章URL）
     */
    protected function issueBadge(DeveloperCertification $devCert): void
    {
        $level = $devCert->certificationLevel;
        $color = $level->color ?? '#0f172a';
        $badgeUrl = $this->generateBadgeUrl($devCert, $level, $color);

        $devCert->update([
            'badge_issued' => true,
            'badge_url' => $badgeUrl,
        ]);
    }

    /**
     * 生成SVG徽章（按等级颜色）
     */
    protected function generateBadgeUrl(DeveloperCertification $devCert, $level, string $color): string
    {
        $encoded = base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120">'
            . '<circle cx="60" cy="60" r="55" fill="none" stroke="' . $color . '" stroke-width="4"/>'
            . '<circle cx="60" cy="60" r="40" fill="' . $color . '" opacity="0.15"/>'
            . '<path d="M60 15 L72 45 L105 45 L78 65 L88 98 L60 78 L32 98 L42 65 L15 45 L48 45 Z" fill="' . $color . '" opacity="0.8"/>'
            . '<text x="60" y="105" text-anchor="middle" font-size="10" fill="' . $color . '">' . htmlspecialchars($level->name) . '</text>'
            . '</svg>'
        );
        return 'data:image/svg+xml;base64,' . $encoded;
    }

    /**
     * 获取用户的全部认证
     */
    public function getUserCertifications(int $userId): array
    {
        return DeveloperCertification::with(['certificationLevel'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * 获取用户的认证统计
     */
    public function getUserCertificationStats(int $userId): array
    {
        $all = DeveloperCertification::where('user_id', $userId)->get();

        return [
            'total_attempts' => $all->count(),
            'passed' => $all->where('status', DeveloperCertification::STATUS_PASSED)->count(),
            'failed' => $all->where('status', DeveloperCertification::STATUS_FAILED)->count(),
            'in_progress' => $all->where('status', DeveloperCertification::STATUS_IN_PROGRESS)->count(),
            'active_certifications' => $all->filter(fn ($c) => $c->isValid())->count(),
        ];
    }

    /**
     * 获取所有认证统计（管理用）
     */
    public function getGlobalStats(int $tenantId): array
    {
        $levels = CertificationLevel::where('tenant_id', $tenantId)->get();
        $certifications = DeveloperCertification::where('tenant_id', $tenantId);

        return [
            'total_levels' => $levels->count(),
            'total_certifications' => (clone $certifications)->count(),
            'total_passed' => (clone $certifications)->where('status', DeveloperCertification::STATUS_PASSED)->count(),
            'total_failed' => (clone $certifications)->where('status', DeveloperCertification::STATUS_FAILED)->count(),
            'total_in_progress' => (clone $certifications)->where('status', DeveloperCertification::STATUS_IN_PROGRESS)->count(),
            'levels' => $levels->map(function ($level) {
                $count = DeveloperCertification::where('certification_level_id', $level->id)
                    ->where('status', DeveloperCertification::STATUS_PASSED)->count();
                return [
                    'id' => $level->id,
                    'name' => $level->name,
                    'certified_count' => $count,
                ];
            }),
        ];
    }

    /**
     * 吊销认证
     */
    public function revokeCertification(int $devCertId, ?string $reason = null): DeveloperCertification
    {
        $devCert = DeveloperCertification::findOrFail($devCertId);

        $metadata = $devCert->metadata ?? [];
        $metadata['revoked_at'] = now()->toDateTimeString();
        $metadata['revoke_reason'] = $reason;

        $devCert->update([
            'status' => DeveloperCertification::STATUS_REVOKED,
            'badge_issued' => false,
            'badge_url' => null,
            'metadata' => $metadata,
        ]);

        return $devCert->fresh();
    }

    // ──────────────────────────────────────────────
    //  M3-58 增强: 徽章/目录/验证/权益
    // ──────────────────────────────────────────────

    /**
     * 生成 SVG 徽章 (公开)
     */
    public function generateBadgeSvg(string $levelName, string $levelColor, string $userName = 'Developer'): string
    {
        $star = '<path d="M60 15 L72 45 L105 45 L78 65 L88 98 L60 78 L32 98 L42 65 L15 45 L48 45 Z" fill="' . $levelColor . '" opacity="0.8"/>';

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200">'
            . '<defs><linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">'
            . '<stop offset="0%" stop-color="#1a1a2e"/>'
            . '<stop offset="100%" stop-color="#16213e"/>'
            . '</linearGradient></defs>'
            . '<rect width="200" height="200" rx="20" fill="url(#bg)"/>'
            . '<circle cx="100" cy="85" r="60" fill="none" stroke="' . $levelColor . '" stroke-width="4"/>'
            . '<circle cx="100" cy="85" r="45" fill="' . $levelColor . '" opacity="0.15"/>'
            . str_replace('M60 15', 'M100 30', str_replace('M60 78', 'M100 110', $star))
            . '<text x="100" y="170" text-anchor="middle" font-size="14" fill="' . $levelColor . '" font-weight="bold">' . htmlspecialchars($levelName) . '</text>'
            . '<text x="100" y="188" text-anchor="middle" font-size="10" fill="#909399">' . htmlspecialchars($userName) . '</text>'
            . '</svg>';
    }

    /**
     * 公开验证证书
     */
    public function verifyCertificate(string $certNumber): ?array
    {
        $cert = DeveloperCertification::with(['user', 'certificationLevel'])
            ->where('certificate_number', $certNumber)
            ->first();

        if (!$cert) return null;

        return [
            'valid' => $cert->status === DeveloperCertification::STATUS_PASSED && $cert->isValid(),
            'certificate_number' => $cert->certificate_number,
            'developer_name' => $cert->user?->name ?? 'Unknown',
            'level_name' => $cert->certificationLevel?->name ?? 'N/A',
            'level_color' => $cert->certificationLevel?->color ?? '#0f172a',
            'issued_at' => $cert->certificate_issued_at?->toIso8601String(),
            'expires_at' => $cert->expires_at?->toIso8601String(),
            'status' => $cert->status,
        ];
    }

    /**
     * 公开认证开发者目录
     */
    public function getDirectory(int $perPage = 20): array
    {
        $query = DeveloperCertification::with(['user', 'certificationLevel'])
            ->where('status', DeveloperCertification::STATUS_PASSED)
            ->where('badge_issued', true);

        return $query->paginate($perPage)
            ->through(fn($cert) => [
                'id' => $cert->id,
                'developer_name' => $cert->user?->name ?? 'Anonymous',
                'level_name' => $cert->certificationLevel?->name ?? 'N/A',
                'level_color' => $cert->certificationLevel?->color ?? '#0f172a',
                'certificate_number' => $cert->certificate_number,
                'certified_at' => $cert->certificate_issued_at?->toIso8601String(),
                'badge_url' => $cert->badge_url,
            ])
            ->toArray();
    }

    /**
     * 获取认证权益列表
     */
    public function getBenefits(int $levelId): array
    {
        return \App\Models\CertificationBenefit::where('certification_level_id', $levelId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->toArray();
    }

    /**
     * 添加权益
     */
    public function addBenefit(int $levelId, array $data): \App\Models\CertificationBenefit
    {
        return \App\Models\CertificationBenefit::create([
            'certification_level_id' => $levelId,
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'icon' => $data['icon'] ?? 'medal',
            'type' => $data['type'] ?? 'badge',
            'value' => $data['value'] ?? '',
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * 删除权益
     */
    public function deleteBenefit(int $benefitId): void
    {
        \App\Models\CertificationBenefit::findOrFail($benefitId)->delete();
    }
}
