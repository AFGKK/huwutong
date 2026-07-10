<?php

namespace Tests\Unit\Services;

use App\Models\Language;
use App\Models\Translation;
use App\Models\TranslationNamespace;
use App\Services\TranslationEngineService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class TranslationEngineServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TranslationEngineService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TranslationEngineService::class);
    }

    /** @test */
    public function assess_quality_returns_correct_structure()
    {
        // Set a non-default source locale
        Language::create([
            'locale' => 'zh_CN',
            'name' => 'Chinese',
            'native_name' => '中文',
            'is_default' => true,
        ]);

        $translation = Translation::create([
            'namespace_id' => TranslationNamespace::factory()->create()->id,
            'locale' => 'en',
            'key' => 'test.key',
            'default_value' => 'Hello World',
            'value' => '你好世界',
        ]);

        $quality = $this->service->assessQuality($translation);

        $this->assertArrayHasKey('score', $quality);
        $this->assertArrayHasKey('issues', $quality);
        $this->assertArrayHasKey('length_ratio', $quality);
        $this->assertArrayHasKey('source_length', $quality);
        $this->assertArrayHasKey('target_length', $quality);
    }

    /** @test */
    public function assess_quality_returns_zero_for_empty_text()
    {
        $translation = Translation::create([
            'namespace_id' => TranslationNamespace::factory()->create()->id,
            'locale' => 'en',
            'key' => 'test.empty',
            'default_value' => '',
            'value' => '',
        ]);

        $quality = $this->service->assessQuality($translation);

        $this->assertEquals(0, $quality['score']);
    }

    /** @test */
    public function assess_quality_detects_missing_variables()
    {
        Language::create([
            'locale' => 'zh_CN',
            'name' => 'Chinese',
            'native_name' => '中文',
            'is_default' => true,
        ]);

        $translation = Translation::create([
            'namespace_id' => TranslationNamespace::factory()->create()->id,
            'locale' => 'en',
            'key' => 'test.with_var',
            'default_value' => 'Hello {name}, you have {count} messages',
            'value' => '你好 {name}，你有消息',
        ]);

        $quality = $this->service->assessQuality($translation);

        $this->assertLessThan(100, $quality['score']);
        $hasVariableIssue = !empty(array_filter($quality['issues'], fn($i) => str_contains($i, '占位符')));
        $this->assertTrue($hasVariableIssue, 'Should detect missing placeholder variables');
    }

    /** @test */
    public function get_memory_stats_returns_correct_structure()
    {
        $stats = $this->service->getMemoryStats();

        $this->assertArrayHasKey('total_auto_translated', $stats);
        $this->assertArrayHasKey('unique_source_texts', $stats);
        $this->assertArrayHasKey('memory_efficiency', $stats);
    }

    /** @test */
    public function translate_missing_returns_result_structure_when_no_source()
    {
        $language = Language::create([
            'locale' => 'en',
            'name' => 'English',
            'is_active' => true,
            'is_default' => true,
        ]);

        $language2 = Language::create([
            'locale' => 'ja',
            'name' => 'Japanese',
            'is_active' => true,
        ]);

        $ns = TranslationNamespace::factory()->create();

        Translation::create([
            'namespace_id' => $ns->id,
            'locale' => 'ja',
            'key' => 'test.hello',
            'default_value' => 'Hello',
            'value' => null, // missing
        ]);

        // No source language entry exists, so should skip
        $results = $this->service->translateMissing('ja');

        $this->assertArrayHasKey('total', $results);
        $this->assertArrayHasKey('translated', $results);
        $this->assertArrayHasKey('failed', $results);
        $this->assertArrayHasKey('skipped', $results);
        $this->assertEquals(0, $results['translated']);
    }

    /** @test */
    public function simple_translate_returns_same_for_same_locale()
    {
        $result = $this->service->translateWithLlm('Hello', 'zh_CN', 'zh_CN', 'test', 'test.hello');
        $this->assertEquals('Hello', $result);
    }

    /** @test */
    public function assess_quality_reports_good_score_for_direct_translation()
    {
        Language::create([
            'locale' => 'zh_CN',
            'name' => 'Chinese',
            'native_name' => '中文',
            'is_default' => true,
        ]);

        $translation = Translation::create([
            'namespace_id' => TranslationNamespace::factory()->create()->id,
            'locale' => 'en',
            'key' => 'test.good',
            'default_value' => 'Welcome to our platform',
            'value' => '欢迎来到我们的平台',
        ]);

        $quality = $this->service->assessQuality($translation);

        $this->assertGreaterThan(50, $quality['score']);
    }

    /** @test */
    public function cannot_translate_source_locale()
    {
        $language = Language::create([
            'locale' => 'zh_CN',
            'name' => 'Chinese',
            'native_name' => '中文',
            'is_active' => true,
            'is_default' => true,
        ]);

        // Translating to source locale should find 0
        $results = $this->service->translateMissing('zh_CN');

        $this->assertEquals(0, $results['total']);
    }
}
