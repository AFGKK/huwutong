<?php

namespace Tests\Feature\Api;

use App\Models\OfficialAccount;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class OfficialAccountCreateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->actingAs($this->user);
    }

    /** @test */
    public function chinese_name_gets_non_empty_slug(): void
    {
        $response = $this->postJson('/api/official-accounts', [
            'name' => '测试互物号',
            'description' => '中文描述',
        ]);

        $response->assertCreated();
        $account = OfficialAccount::where('name', '测试互物号')->first();
        $this->assertNotNull($account);
        $this->assertNotSame('', $account->slug);
        $this->assertNotEmpty($account->slug);
    }

    /** @test */
    public function multiple_chinese_names_get_unique_slugs(): void
    {
        $this->postJson('/api/official-accounts', ['name' => '甲号'])->assertCreated();
        $this->postJson('/api/official-accounts', ['name' => '乙号'])->assertCreated();

        $slugs = OfficialAccount::pluck('slug')->all();
        $this->assertCount(2, $slugs);
        $this->assertSame($slugs, array_unique($slugs));
        foreach ($slugs as $slug) {
            $this->assertNotSame('', $slug);
        }
    }

    /** @test */
    public function form_data_create_with_avatar_works(): void
    {
        Storage::fake('public');

        $response = $this->post('/api/official-accounts', [
            'name' => '带头像的号',
            'description' => 'desc',
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated();
        $account = OfficialAccount::where('name', '带头像的号')->first();
        $this->assertNotNull($account);
        $this->assertNotEmpty($account->avatar);
        $this->assertNotSame('', $account->slug);
    }
}
