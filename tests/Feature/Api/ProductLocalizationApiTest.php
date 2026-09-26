<?php

namespace Tests\Feature\Api;

use App\Models\Language;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class ProductLocalizationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::factory()->create();
        $this->admin = User::factory()->create([
            'status' => 'active',
            'tenant_id' => $tenant->id,
        ]);

        $role = Role::findOrCreate('super-admin', 'web');
        if (\Schema::hasColumn('roles', 'tenant_id') && empty($role->tenant_id)) {
            $role->tenant_id = $tenant->id;
            $role->save();
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::table('model_has_roles')->insert([
            'role_id' => $role->id,
            'model_type' => User::class,
            'model_id' => $this->admin->id,
            'tenant_id' => $tenant->id,
        ]);

        Language::updateOrCreate(
            ['locale' => 'zh_CN'],
            ['name' => '简体中文', 'native_name' => '简体中文', 'is_active' => true, 'sort_order' => 1],
        );
        Language::updateOrCreate(
            ['locale' => 'en'],
            ['name' => 'English', 'native_name' => 'English', 'is_active' => true, 'sort_order' => 2],
        );

        $this->product = Product::factory()->create();
        Sanctum::actingAs($this->admin, ['*', 'admin', 'super-admin']);
    }

    public function test_can_save_zh_cn_product_translation(): void
    {
        $response = $this->postJson("/api/admin/localization/products/{$this->product->id}/translations", [
            'locale' => 'zh_CN',
            'translations' => [
                'name' => '测试商品',
                'description' => '中文描述',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('product_translations', [
            'translatable_id' => $this->product->id,
            'locale' => 'zh_CN',
            'field' => 'name',
            'value' => '测试商品',
        ]);
    }

    public function test_validation_failure_returns_422(): void
    {
        $response = $this->postJson("/api/admin/localization/products/{$this->product->id}/translations", [
            'locale' => 'xx_INVALID',
            'translations' => ['name' => 'x'],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_product_controller_translations_roundtrip(): void
    {
        $this->postJson("/api/products/{$this->product->id}/translations", [
            'translations' => [
                ['locale' => 'en', 'name' => 'Demo', 'description' => 'EN desc'],
            ],
        ])->assertOk();

        $this->getJson("/api/products/{$this->product->id}/translations")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['locale' => 'en', 'name' => 'Demo']);
    }
}
