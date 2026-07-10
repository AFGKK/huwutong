<?php

namespace Tests\Unit\Services;

use App\Models\CertificationLevel;
use App\Models\DeveloperCertification;
use App\Models\ExamAnswer;
use App\Models\ExamQuestion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CertificationService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class CertificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CertificationService $service;
    protected Tenant $tenant;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CertificationService::class);
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    /** @test */
    public function it_creates_certification_level()
    {
        $level = $this->service->createLevel($this->tenant->id, [
            'name' => '初级开发者',
            'slug' => 'junior-dev',
            'description' => '面向初学者的认证',
            'level_order' => 1,
            'passing_score' => 70,
            'color' => '#52c41a',
        ]);

        $this->assertNotNull($level);
        $this->assertEquals('初级开发者', $level->name);
        $this->assertEquals(70, $level->passing_score);
    }

    /** @test */
    public function it_lists_levels_with_certified_count()
    {
        $level = $this->service->createLevel($this->tenant->id, [
            'name' => '高级开发者',
            'slug' => 'senior-dev',
            'level_order' => 2,
        ]);

        DeveloperCertification::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'certification_level_id' => $level->id,
            'certificate_number' => 'CERT-TEST-001',
            'status' => DeveloperCertification::STATUS_PASSED,
            'badge_issued' => true,
            'certificate_issued_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $levels = $this->service->getLevels($this->tenant->id);

        $this->assertCount(1, $levels);
        $this->assertEquals(1, $levels[0]['total_certified']);
    }

    /** @test */
    public function it_adds_exam_question()
    {
        $level = $this->service->createLevel($this->tenant->id, [
            'name' => '测试等级',
            'slug' => 'test-level',
            'level_order' => 1,
        ]);

        $question = $this->service->addQuestion($level->id, [
            'question' => '什么是REST API？',
            'type' => 'single_choice',
            'options' => [
                ['id' => 'a', 'text' => '一种架构风格', 'is_correct' => true],
                ['id' => 'b', 'text' => '一种数据库', 'is_correct' => false],
                ['id' => 'c', 'text' => '一种编程语言', 'is_correct' => false],
            ],
            'points' => 2,
            'sort_order' => 1,
        ]);

        $this->assertNotNull($question);
        $this->assertEquals('什么是REST API？', $question->question);
        $this->assertEquals(2, $question->points);
        $this->assertEquals(['a'], $question->getCorrectAnswerIds());
    }

    /** @test */
    public function it_bulk_adds_questions()
    {
        $level = $this->service->createLevel($this->tenant->id, [
            'name' => '批量导入测试',
            'slug' => 'bulk-test',
            'level_order' => 1,
        ]);

        $questions = $this->service->bulkAddQuestions($level->id, [
            [
                'question' => '题1',
                'options' => [
                    ['id' => 'a', 'text' => '正确', 'is_correct' => true],
                    ['id' => 'b', 'text' => '错误', 'is_correct' => false],
                ],
                'points' => 1,
            ],
            [
                'question' => '题2',
                'options' => [
                    ['id' => 'a', 'text' => '对', 'is_correct' => true],
                    ['id' => 'b', 'text' => '错', 'is_correct' => false],
                ],
                'points' => 1,
            ],
        ]);

        $this->assertCount(2, $questions);
    }

    /** @test */
    public function it_starts_exam()
    {
        $level = $this->service->createLevel($this->tenant->id, [
            'name' => '入门认证',
            'slug' => 'entry',
            'level_order' => 1,
            'passing_score' => 60,
        ]);

        $devCert = $this->service->startExam($this->user->id, $level->id);

        $this->assertNotNull($devCert);
        $this->assertEquals(DeveloperCertification::STATUS_IN_PROGRESS, $devCert->status);
        $this->assertEquals(1, $devCert->attempts);
        $this->assertStringStartsWith('CERT-', $devCert->certificate_number);
    }

    /** @test */
    public function it_rejects_duplicate_exam()
    {
        $level = $this->service->createLevel($this->tenant->id, [
            'name' => '重考测试',
            'slug' => 'retry-test',
            'level_order' => 1,
        ]);

        $this->service->startExam($this->user->id, $level->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('进行中的考试');

        $this->service->startExam($this->user->id, $level->id);
    }

    /** @test */
    public function it_submits_answer_and_checks_correctness()
    {
        $level = $this->service->createLevel($this->tenant->id, [
            'name' => '答题测试',
            'slug' => 'answer-test',
            'level_order' => 1,
        ]);

        $question = $this->service->addQuestion($level->id, [
            'question' => '1+1=？',
            'type' => 'single_choice',
            'options' => [
                ['id' => 'a', 'text' => '1', 'is_correct' => false],
                ['id' => 'b', 'text' => '2', 'is_correct' => true],
                ['id' => 'c', 'text' => '3', 'is_correct' => false],
            ],
            'points' => 1,
        ]);

        $devCert = $this->service->startExam($this->user->id, $level->id);

        // 正确答案
        $answer = $this->service->submitAnswer($devCert->id, $question->id, ['b']);
        $this->assertTrue($answer->is_correct);
        $this->assertEquals(1, $answer->points_earned);

        // 错误答案
        $question2 = $this->service->addQuestion($level->id, [
            'question' => '2+2=？',
            'type' => 'single_choice',
            'options' => [
                ['id' => 'a', 'text' => '3', 'is_correct' => false],
                ['id' => 'b', 'text' => '4', 'is_correct' => true],
                ['id' => 'c', 'text' => '5', 'is_correct' => false],
            ],
            'points' => 2,
        ]);

        $answer2 = $this->service->submitAnswer($devCert->id, $question2->id, ['a']);
        $this->assertFalse($answer2->is_correct);
        $this->assertEquals(0, $answer2->points_earned);
    }

    /** @test */
    public function it_submits_exam_and_determines_pass_fail()
    {
        $level = $this->service->createLevel($this->tenant->id, [
            'name' => '通过率测试',
            'slug' => 'passfail',
            'level_order' => 1,
            'passing_score' => 50,
        ]);

        // 添加2题，每题1分，满分2分。需要至少1分(50%)才能通过
        $q1 = $this->service->addQuestion($level->id, [
            'question' => '题1',
            'options' => [
                ['id' => 'a', 'text' => '正确', 'is_correct' => true],
                ['id' => 'b', 'text' => '错误', 'is_correct' => false],
            ],
            'points' => 1,
        ]);

        $q2 = $this->service->addQuestion($level->id, [
            'question' => '题2',
            'options' => [
                ['id' => 'a', 'text' => '正确', 'is_correct' => true],
                ['id' => 'b', 'text' => '错误', 'is_correct' => false],
            ],
            'points' => 1,
        ]);

        $devCert = $this->service->startExam($this->user->id, $level->id);

        // 全对
        $this->service->submitAnswer($devCert->id, $q1->id, ['a']);
        $this->service->submitAnswer($devCert->id, $q2->id, ['a']);

        $result = $this->service->submitExam($devCert->id);

        $this->assertEquals(DeveloperCertification::STATUS_PASSED, $result->status);
        $this->assertEquals(100, $result->score);
        $this->assertTrue($result->badge_issued);
        $this->assertNotNull($result->badge_url);
    }

    /** @test */
    public function it_fails_exam_with_low_score()
    {
        $level = $this->service->createLevel($this->tenant->id, [
            'name' => '不通过测试',
            'slug' => 'fail-test',
            'level_order' => 1,
            'passing_score' => 80,
        ]);

        $q1 = $this->service->addQuestion($level->id, [
            'question' => '题1',
            'options' => [
                ['id' => 'a', 'text' => '正确', 'is_correct' => true],
                ['id' => 'b', 'text' => '错误', 'is_correct' => false],
            ],
            'points' => 1,
        ]);

        $q2 = $this->service->addQuestion($level->id, [
            'question' => '题2',
            'options' => [
                ['id' => 'a', 'text' => '正确', 'is_correct' => true],
                ['id' => 'b', 'text' => '错误', 'is_correct' => false],
            ],
            'points' => 1,
        ]);

        $devCert = $this->service->startExam($this->user->id, $level->id);

        // 只答对一题（50%），不够80%
        $this->service->submitAnswer($devCert->id, $q1->id, ['b']); // 错
        $this->service->submitAnswer($devCert->id, $q2->id, ['a']); // 对

        $result = $this->service->submitExam($devCert->id);

        $this->assertEquals(DeveloperCertification::STATUS_FAILED, $result->status);
        $this->assertEquals(50, $result->score);
    }

    /** @test */
    public function it_allows_retake_after_failure()
    {
        $level = $this->service->createLevel($this->tenant->id, [
            'name' => '重考测试',
            'slug' => 'retake-test',
            'level_order' => 1,
            'passing_score' => 90,
        ]);

        $q1 = $this->service->addQuestion($level->id, [
            'question' => '题1',
            'options' => [
                ['id' => 'a', 'text' => '正确', 'is_correct' => true],
                ['id' => 'b', 'text' => '错误', 'is_correct' => false],
            ],
            'points' => 1,
        ]);

        $devCert = $this->service->startExam($this->user->id, $level->id);
        $this->service->submitAnswer($devCert->id, $q1->id, ['b']); // 答错
        $result = $this->service->submitExam($devCert->id);
        $this->assertEquals(DeveloperCertification::STATUS_FAILED, $result->status);

        // 可以重考
        $this->assertTrue($result->canRetake());

        $retry = $this->service->startExam($this->user->id, $level->id);
        $this->assertEquals(2, $retry->attempts);
    }

    /** @test */
    public function it_retrieves_user_certifications()
    {
        $level = $this->service->createLevel($this->tenant->id, [
            'name' => '用户认证测试',
            'slug' => 'user-cert',
            'level_order' => 1,
        ]);

        $this->service->startExam($this->user->id, $level->id);

        $certs = $this->service->getUserCertifications($this->user->id);

        $this->assertCount(1, $certs);
        $this->assertEquals('用户认证测试', $certs[0]['certification_level']['name']);
    }

    /** @test */
    public function it_revokes_certification()
    {
        $level = $this->service->createLevel($this->tenant->id, [
            'name' => '吊销测试',
            'slug' => 'revoke-test',
            'level_order' => 1,
        ]);

        // 直接创建一个"已通过"的认证
        $devCert = DeveloperCertification::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'certification_level_id' => $level->id,
            'certificate_number' => 'CERT-REVOKE-001',
            'status' => DeveloperCertification::STATUS_PASSED,
            'badge_issued' => true,
            'badge_url' => 'data:image/svg+xml;base64,test',
            'certificate_issued_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $revoked = $this->service->revokeCertification($devCert->id, '违反服务条款');

        $this->assertEquals(DeveloperCertification::STATUS_REVOKED, $revoked->status);
        $this->assertFalse($revoked->badge_issued);
        $this->assertNull($revoked->badge_url);
        $this->assertEquals('违反服务条款', $revoked->metadata['revoke_reason']);
    }

    /** @test */
    public function it_gets_exam_questions_without_answers()
    {
        $level = $this->service->createLevel($this->tenant->id, [
            'name' => '考试题目测试',
            'slug' => 'exam-q',
            'level_order' => 1,
        ]);

        $this->service->addQuestion($level->id, [
            'question' => '秘密问题',
            'options' => [
                ['id' => 'a', 'text' => '正确', 'is_correct' => true],
                ['id' => 'b', 'text' => '错误', 'is_correct' => false],
            ],
            'points' => 1,
        ]);

        $devCert = $this->service->startExam($this->user->id, $level->id);
        $examQuestions = $this->service->getExamQuestions($devCert->id);

        $this->assertCount(1, $examQuestions);
        $this->assertArrayNotHasKey('is_correct', $examQuestions[0]['options'][0]);
    }
}
