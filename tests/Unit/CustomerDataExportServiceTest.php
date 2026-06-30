<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\License;
use App\Models\CustomerDataExport;
use App\Services\CustomerDataExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerDataExportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CustomerDataExportService $service;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CustomerDataExportService();
        $this->customer = Customer::factory()->create();
        License::factory()->count(3)->create([
            'customer_id' => $this->customer->id,
        ]);
    }

    /** @test */
    public function it_returns_available_types()
    {
        $types = $this->service->getAvailableTypes($this->customer);

        $this->assertCount(4, $types);

        $licenses = collect($types)->firstWhere('type', 'licenses');
        $this->assertNotNull($licenses);
        $this->assertEquals(3, $licenses['record_count']);
        $this->assertTrue($licenses['can_export']);

        $customers = collect($types)->firstWhere('type', 'customers');
        $this->assertEquals(1, $customers['record_count']);
    }

    /** @test */
    public function it_creates_and_processes_an_export()
    {
        $export = $this->service->createExport($this->customer, 'licenses');

        $this->assertInstanceOf(CustomerDataExport::class, $export);
        $this->assertEquals('completed', $export->status);
        $this->assertNotNull($export->file_path);
        $this->assertNotNull($export->file_name);
        $this->assertGreaterThan(0, $export->record_count);
        $this->assertEquals(3, $export->record_count);
        $this->assertNotNull($export->completed_at);
        $this->assertNotNull($export->expires_at);
    }

    /** @test */
    public function it_creates_customer_info_export()
    {
        try {
            $export = $this->service->createExport($this->customer, 'customers');
            $this->assertEquals('completed', $export->status, 'Failed: ' . ($export->error_message ?? 'no error'));
        } catch (\Exception $e) {
            $this->fail('Exception: ' . $e->getMessage());
        }
    }

    /** @test */
    public function it_returns_export_history()
    {
        $this->service->createExport($this->customer, 'licenses');
        $this->service->createExport($this->customer, 'customers');

        $history = $this->service->getExportHistory($this->customer);

        $this->assertCount(2, $history);
    }

    /** @test */
    public function it_handles_download()
    {
        $export = $this->service->createExport($this->customer, 'licenses');

        $file = $this->service->download($export);

        $this->assertNotNull($file);
        $this->assertArrayHasKey('content', $file);
        $this->assertArrayHasKey('name', $file);
        $this->assertEquals('text/csv', $file['mime']);
        $this->assertStringContainsString('License Key', $file['content']);
    }

    /** @test */
    public function it_fails_download_for_expired_export()
    {
        $export = CustomerDataExport::create([
            'customer_id' => $this->customer->id,
            'type' => 'licenses',
            'format' => 'csv',
            'status' => 'completed',
            'expires_at' => now()->subDay(),
        ]);

        $file = $this->service->download($export);
        $this->assertNull($file);
    }

    /** @test */
    public function it_creates_invoice_export()
    {
        Invoice::factory()->count(2)->create([
            'customer_id' => $this->customer->id,
            'status' => 'paid',
        ]);

        $export = $this->service->createExport($this->customer, 'invoices');

        $this->assertEquals('completed', $export->status, 'Failed: ' . ($export->error_message ?? 'no error'));
        $this->assertEquals(2, $export->record_count);
    }

    /** @test */
    public function it_returns_stats()
    {
        $this->service->createExport($this->customer, 'licenses');

        $stats = $this->service->getStats();

        $this->assertEquals(1, $stats['total_exports']);
        $this->assertEquals(1, $stats['by_type']['licenses']);
        $this->assertEquals(1, $stats['by_status']['completed']);
    }

    /** @test */
    public function it_cleanups_expired_exports()
    {
        $export = CustomerDataExport::create([
            'customer_id' => $this->customer->id,
            'type' => 'licenses',
            'format' => 'csv',
            'status' => 'completed',
            'file_path' => 'exports/licenses/2026/06/test_expired.csv',
            'file_name' => 'test_expired.csv',
            'expires_at' => now()->subDay(),
        ]);

        $count = $this->service->cleanupExpired();
        $this->assertEquals(1, $count);
        $this->assertDatabaseMissing('customer_data_exports', ['id' => $export->id]);
    }
}
