<?php

namespace Tests\Unit\Services;

use App\Services\TextToSqlGuardService;
use Tests\TestCase;

class TextToSqlGuardServiceTest extends TestCase
{
    protected TextToSqlGuardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TextToSqlGuardService::class);
    }

    /* ─── 只读检查 ─── */

    /** @test */
    public function it_allows_select_statements()
    {
        $result = $this->service->validate('SELECT * FROM licenses');

        $this->assertTrue($result['allowed']);
        $this->assertNull($result['reason']);
    }

    /** @test */
    public function it_rejects_insert_statements()
    {
        $result = $this->service->validate("INSERT INTO licenses (license_key) VALUES ('test')");

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('仅允许 SELECT', $result['reason']);
    }

    /** @test */
    public function it_rejects_update_statements()
    {
        $result = $this->service->validate("UPDATE licenses SET status='active' WHERE id=1");

        $this->assertFalse($result['allowed']);
    }

    /** @test */
    public function it_rejects_delete_statements()
    {
        $result = $this->service->validate("DELETE FROM licenses WHERE id=1");

        $this->assertFalse($result['allowed']);
    }

    /* ─── 危险关键词检测 ─── */

    /** @test */
    public function it_rejects_drop_table_statements()
    {
        $result = $this->service->validate('SELECT * FROM licenses; DROP TABLE users;');

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('DROP', $result['reason']);
    }

    /** @test */
    public function it_rejects_truncate_statements()
    {
        $result = $this->service->validate('SELECT * FROM licenses; TRUNCATE TABLE logs;');

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('TRUNCATE', $result['reason']);
    }

    /** @test */
    public function it_rejects_alter_statements()
    {
        $result = $this->service->validate('SELECT * FROM licenses; ALTER TABLE users ADD COLUMN x TEXT;');

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('ALTER', $result['reason']);
    }

    /** @test */
    public function it_rejects_exec_and_grant_statements()
    {
        $this->assertFalse($this->service->validate('SELECT * FROM licenses; EXEC sp_help;')['allowed']);
        $this->assertFalse($this->service->validate('SELECT * FROM licenses; GRANT ALL ON users TO admin;')['allowed']);
    }

    /* ─── 敏感字段检测 ─── */

    /** @test */
    public function it_warns_about_sensitive_columns()
    {
        $result = $this->service->validate('SELECT password, email FROM users');

        $this->assertTrue($result['allowed']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString('password', $result['warnings'][0]);
    }

    /** @test */
    public function it_warns_about_api_key_and_secret()
    {
        $result = $this->service->validate('SELECT api_key, secret FROM users');

        $this->assertTrue($result['allowed']);
        $this->assertNotEmpty($result['warnings']);
    }

    /* ─── 行数限制 ─── */

    /** @test */
    public function it_adds_limit_when_missing()
    {
        $result = $this->service->validate('SELECT * FROM licenses');

        $this->assertTrue($result['allowed']);
        $this->assertStringContainsString('LIMIT', $result['sql']);
        $this->assertStringContainsString('100', $result['sql']);
    }

    /** @test */
    public function it_preserves_existing_limit()
    {
        $result = $this->service->validate('SELECT * FROM licenses LIMIT 5');

        $this->assertTrue($result['allowed']);
        $this->assertStringContainsString('LIMIT 5', $result['sql']);
        $this->assertStringNotContainsString('LIMIT 5,', $result['sql']); // 不重复添加
    }

    /** @test */
    public function it_uses_configured_max_rows()
    {
        $this->service->setMaxRows(50);
        $result = $this->service->validate('SELECT * FROM licenses');

        $this->assertStringContainsString('LIMIT 50', $result['sql']);
    }

    /* ─── 表白名单 ─── */

    /** @test */
    public function it_checks_allowed_tables()
    {
        $this->service->setAllowedTables(['licenses', 'customers']);

        $this->assertTrue($this->service->validate('SELECT * FROM licenses')['allowed']);
        $this->assertTrue($this->service->validate('SELECT * FROM customers')['allowed']);
    }

    /** @test */
    public function it_rejects_tables_not_in_whitelist()
    {
        $this->service->setAllowedTables(['licenses']);

        $result = $this->service->validate('SELECT * FROM users');
        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('users', $result['reason']);
    }

    /** @test */
    public function it_allows_all_tables_when_whitelist_is_empty()
    {
        $this->assertTrue($this->service->validate('SELECT * FROM any_table')['allowed']);
    }

    /* ─── 租户隔离 ─── */

    /** @test */
    public function it_adds_tenant_condition_when_context_provided()
    {
        $result = $this->service->validate('SELECT * FROM licenses', ['tenant_id' => 42]);

        $this->assertTrue($result['allowed']);
        $this->assertStringContainsString('tenant_id = 42', $result['sql']);
    }

    /** @test */
    public function it_does_not_duplicate_tenant_condition()
    {
        $result = $this->service->validate(
            "SELECT * FROM licenses WHERE tenant_id = 42",
            ['tenant_id' => 42]
        );

        // 应该只出现一次 tenant_id = 42
        $matches = preg_match_all('/tenant_id\s*=\s*42/', $result['sql']);
        $this->assertEquals(1, $matches, 'tenant_id 应该只出现一次');
    }

    /** @test */
    public function it_skips_tenant_isolation_without_context()
    {
        $result = $this->service->validate('SELECT * FROM licenses');

        $this->assertStringNotContainsString('tenant_id', $result['sql']);
    }

    /* ─── 特殊字符和注入 ─── */

    /** @test */
    public function it_rejects_into_outfile()
    {
        $result = $this->service->validate("SELECT * FROM licenses INTO OUTFILE '/tmp/evil'");
        $this->assertFalse($result['allowed']);
    }

    /** @test */
    public function it_rejects_sleep_benchmark()
    {
        $result = $this->service->validate("SELECT * FROM licenses WHERE id=1 OR BENCHMARK(10000000,MD5(1))");
        $this->assertFalse($result['allowed']);
    }

    /** @test */
    public function it_allows_simple_valid_select()
    {
        $result = $this->service->validate('SELECT id, license_key, status, created_at FROM licenses WHERE status = \'active\' ORDER BY created_at DESC LIMIT 10');
        $this->assertTrue($result['allowed']);
    }

    /** @test */
    public function it_allows_join_queries()
    {
        $result = $this->service->validate('SELECT l.id, l.license_key, c.name FROM licenses l JOIN customers c ON l.customer_id = c.id WHERE l.status = \'active\'');
        $this->assertTrue($result['allowed']);
    }

    /* ─── 配置管理 ─── */

    /** @test */
    public function it_returns_config_summary()
    {
        $config = $this->service->getConfigSummary();

        $this->assertArrayHasKey('allowed_tables', $config);
        $this->assertArrayHasKey('max_rows', $config);
        $this->assertArrayHasKey('sensitive_columns', $config);
        $this->assertArrayHasKey('forbidden_keywords', $config);
        $this->assertArrayHasKey('query_timeout_seconds', $config);
        $this->assertIsArray($config['forbidden_keywords']);
    }

    /* ─── 最终格式化 ─── */

    /** @test */
    public function it_ensures_trailing_semicolon()
    {
        $result = $this->service->validate('SELECT * FROM licenses');

        $this->assertStringEndsWith(';', trim($result['sql']));
    }
}
