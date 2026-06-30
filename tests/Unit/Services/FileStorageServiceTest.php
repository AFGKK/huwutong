<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\CustomerFile;
use App\Models\FileShareLink;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FileStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class FileStorageServiceTest extends TestCase
{
    use RefreshDatabase;

    private FileStorageService $service;
    private Tenant $tenant;
    private User $user;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FileStorageService::class);
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->user->id]);
        $this->actingAs($this->user);
    }

    public function test_validates_file_size()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('文件大小超过限制');

        // 创建一个超过限制的虚拟文件
        $file = UploadedFile::fake()->create('large.pdf', 60 * 1024); // 60MB (超过50MB限制)

        $this->service->upload($file, $this->customer->id, $this->tenant->id);
    }

    public function test_validates_mime_type()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('不支持的文件类型');

        $file = UploadedFile::fake()->create('test.exe', 100)->mimeType('application/x-msdownload');

        $this->service->upload($file, $this->customer->id, $this->tenant->id);
    }

    public function test_can_create_share_link()
    {
        $file = CustomerFile::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'original_name' => 'test.pdf',
            'storage_path' => 'customer-files/test/test.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'file_extension' => 'pdf',
            'uploaded_by' => $this->user->id,
        ]);

        $link = $this->service->createShareLink($file, [
            'max_downloads' => 5,
            'expires_at' => now()->addDays(7),
        ]);

        $this->assertInstanceOf(FileShareLink::class, $link);
        $this->assertEquals(5, $link->max_downloads);
        $this->assertTrue($link->is_active);
        $this->assertTrue($link->isValid());
    }

    public function test_share_link_validity_checks()
    {
        $file = CustomerFile::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'original_name' => 'test.pdf',
            'storage_path' => 'customer-files/test/test.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'file_extension' => 'pdf',
            'uploaded_by' => $this->user->id,
        ]);

        // 测试过期链接
        $expiredLink = FileShareLink::create([
            'customer_file_id' => $file->id,
            'token' => 'expired-token',
            'expires_at' => now()->subDay(),
            'is_active' => true,
        ]);
        $this->assertFalse($expiredLink->isValid());

        // 测试下载次数耗尽
        $exhaustedLink = FileShareLink::create([
            'customer_file_id' => $file->id,
            'token' => 'exhausted-token',
            'max_downloads' => 3,
            'download_count' => 3,
            'is_active' => true,
        ]);
        $this->assertFalse($exhaustedLink->isValid());

        // 测试已撤销链接
        $revokedLink = FileShareLink::create([
            'customer_file_id' => $file->id,
            'token' => 'revoked-token',
            'is_active' => false,
        ]);
        $this->assertFalse($revokedLink->isValid());
    }

    public function test_get_file_by_share_token()
    {
        $file = CustomerFile::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'original_name' => 'test.pdf',
            'storage_path' => 'customer-files/test/test.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'file_extension' => 'pdf',
            'uploaded_by' => $this->user->id,
        ]);

        FileShareLink::create([
            'customer_file_id' => $file->id,
            'token' => 'valid-token',
            'is_active' => true,
        ]);

        $found = $this->service->getFileByShareToken('valid-token');
        $this->assertNotNull($found);
        $this->assertEquals($file->id, $found->id);

        $notFound = $this->service->getFileByShareToken('invalid-token');
        $this->assertNull($notFound);
    }

    public function test_list_files()
    {
        CustomerFile::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'original_name' => 'doc1.pdf',
            'storage_path' => 'customer-files/doc1.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 100,
            'file_extension' => 'pdf',
            'category' => 'contract',
            'uploaded_by' => $this->user->id,
        ]);

        CustomerFile::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'original_name' => 'doc2.jpg',
            'storage_path' => 'customer-files/doc2.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 200,
            'file_extension' => 'jpg',
            'category' => 'receipt',
            'uploaded_by' => $this->user->id,
        ]);

        $result = $this->service->listFiles($this->tenant->id);
        $this->assertEquals(2, $result->total());

        // 按分类过滤
        $filtered = $this->service->listFiles($this->tenant->id, ['category' => 'contract']);
        $this->assertEquals(1, $filtered->total());

        // 按搜索过滤
        $searched = $this->service->listFiles($this->tenant->id, ['search' => 'doc2']);
        $this->assertEquals(1, $searched->total());
    }

    public function test_get_stats()
    {
        CustomerFile::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'original_name' => 'file1.pdf',
            'storage_path' => 'path/file1.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 500,
            'file_extension' => 'pdf',
            'category' => 'contract',
            'uploaded_by' => $this->user->id,
        ]);

        CustomerFile::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'original_name' => 'file2.pdf',
            'storage_path' => 'path/file2.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1500,
            'file_extension' => 'pdf',
            'category' => 'contract',
            'uploaded_by' => $this->user->id,
        ]);

        CustomerFile::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'original_name' => 'file3.jpg',
            'storage_path' => 'path/file3.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 300,
            'file_extension' => 'jpg',
            'category' => 'receipt',
            'uploaded_by' => $this->user->id,
        ]);

        $stats = $this->service->getStats($this->tenant->id);

        $this->assertEquals(3, $stats['total_files']);
        $this->assertEquals(2300, $stats['total_size']);
        $this->assertArrayHasKey('contract', $stats['by_category']);
        $this->assertArrayHasKey('receipt', $stats['by_category']);
    }

    public function test_file_formatted_size()
    {
        $file = CustomerFile::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'original_name' => 'test.pdf',
            'storage_path' => 'path/test.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1048576,
            'file_extension' => 'pdf',
            'uploaded_by' => $this->user->id,
        ]);

        $this->assertEquals('1 MB', $file->formattedSize());
    }

    public function test_can_revoke_share_link()
    {
        $file = CustomerFile::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'original_name' => 'test.pdf',
            'storage_path' => 'path/test.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'file_extension' => 'pdf',
            'uploaded_by' => $this->user->id,
        ]);

        $link = FileShareLink::create([
            'customer_file_id' => $file->id,
            'token' => 'test-token',
            'is_active' => true,
        ]);

        $this->service->revokeShareLink($link);
        $link->refresh();

        $this->assertFalse($link->is_active);
    }
}
