<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class AdminUserChineseNameTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->create([
            'email' => 'admin_cn_test@example.com',
            'status' => 'active',
            'tenant_id' => $this->tenant->id,
        ]);

        $role = Role::findOrCreate('super-admin', 'web');
        if (\Schema::hasColumn('roles', 'tenant_id') && empty($role->tenant_id)) {
            $role->tenant_id = $this->tenant->id;
            $role->save();
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::table('model_has_roles')->insert([
            'role_id' => $role->id,
            'model_type' => User::class,
            'model_id' => $this->admin->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->admin->load('roles');
        Sanctum::actingAs($this->admin, ['admin', 'super-admin']);
    }

    public function test_can_create_user_with_chinese_name_via_json(): void
    {
        $payload = [
            'name' => '测试用户中文名',
            'email' => 'cn_user_' . uniqid() . '@test.local',
            'password' => 'Password123!',
            'status' => 'active',
        ];

        $response = $this->postJson('/api/admin/users', $payload);

        $response->assertCreated();
        $this->assertDatabaseHas('users', [
            'email' => $payload['email'],
            'name' => '测试用户中文名',
        ]);
        $this->assertStringNotContainsString('app.admin_user.', (string) $response->getContent());
    }

    public function test_can_create_user_with_ascii_name_via_json(): void
    {
        $payload = [
            'name' => 'TestUserAscii',
            'email' => 'ascii_user_' . uniqid() . '@test.local',
            'password' => 'Password123!',
            'status' => 'active',
        ];

        $response = $this->postJson('/api/admin/users', $payload);

        $response->assertCreated();
        $this->assertDatabaseHas('users', [
            'email' => $payload['email'],
            'name' => 'TestUserAscii',
        ]);
    }

    public function test_chinese_name_not_rejected_as_missing(): void
    {
        $payload = [
            'name' => '张三',
            'email' => 'zhangsan_' . uniqid() . '@test.local',
            'password' => 'Password123!',
        ];

        $response = $this->postJson('/api/admin/users', $payload);

        $response->assertSuccessful();
        $content = strtolower($response->getContent());
        $this->assertStringNotContainsString('name field is required', $content);
        $this->assertStringNotContainsString('姓名不能为空', $response->getContent());
    }

    public function test_empty_name_returns_clear_chinese_message(): void
    {
        app()->setLocale('zh_CN');

        $response = $this->postJson('/api/admin/users', [
            'name' => '',
            'email' => 'empty_name_' . uniqid() . '@test.local',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('姓名', $response->json('error.message') ?? $response->getContent());
    }

    public function test_malformed_utf8_rejected_clearly(): void
    {
        $bad = "\xC3\x28"; // invalid UTF-8 sequence
        $raw = '{"name":"' . $bad . '","email":"badutf8_' . uniqid() . '@test.local","password":"Password123!"}';

        $response = $this->call(
            'POST',
            '/api/admin/users',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer fake',
            ],
            $raw
        );

        // 未认证或编码错误均可；编码中间件应在认证前拦截非法 UTF-8
        $this->assertTrue(in_array($response->getStatusCode(), [401, 422], true));
        if ($response->getStatusCode() === 422) {
            $this->assertStringContainsStringIgnoringCase('utf-8', $response->getContent());
        }
    }
}
