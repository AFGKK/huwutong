<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\License;
use App\Models\Tenant;
use App\Services\KeyGenerator;
use App\Services\KeyPrefixFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KeyPrefixFormatterTest extends TestCase
{
    use RefreshDatabase;

    protected KeyPrefixFormatter $formatter;
    protected KeyGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        if (!Tenant::find(1)) {
            Tenant::factory()->create(['id' => 1]);
        }

        $this->formatter = app(KeyPrefixFormatter::class);
        $this->generator = app(KeyGenerator::class);
    }

    protected function createLicense(string $type, string $key): License
    {
        return License::create([
            'license_key' => $key,
            'customer_id' => Customer::create(['tenant_id' => 1, 'name' => 'Test', 'email' => 't@t.com'])->id,
            'tenant_id' => 1,
            'type' => $type,
            'status' => 'active',
            'seats' => 1,
            'max_devices' => 1,
        ]);
    }

    /** @test */
    public function formats_enterprise_key_correctly()
    {
        $license = $this->createLicense('enterprise', 'SOME-OLD-KEY-1234');
        $formatted = $this->formatter->format($license);
        // Cannot extract suffix from arbitrary key, so generates new
        $this->assertStringStartsWith('HWT-ENT-', $formatted);
    }

    /** @test */
    public function formats_professional_key()
    {
        $license = $this->createLicense('professional', 'OLD-KEY-FORMAT');
        $formatted = $this->formatter->format($license);
        $this->assertStringStartsWith('HWT-PRO-', $formatted);
    }

    /** @test */
    public function formats_trial_key()
    {
        $license = $this->createLicense('trial', 'TRIAL-OLD-KEY');
        $formatted = $this->formatter->format($license);
        $this->assertStringStartsWith('HWT-TRIAL-', $formatted);
    }

    /** @test */
    public function skips_already_formatted_key()
    {
        $newKey = $this->generator->generate('enterprise');
        $this->assertStringStartsWith('HWT-ENT-', $newKey);

        $license = $this->createLicense('enterprise', $newKey);
        $formatted = $this->formatter->format($license);
        $this->assertEquals($newKey, $formatted);
    }

    /** @test */
    public function extracts_suffix_from_old_prefix_format()
    {
        // Old format: HWT-XXX-random-checksum (but wrong prefix)
        $key = 'HWT-OLD-A3F2C8D1E9B07456-1A2B';
        $license = $this->createLicense('professional', $key);
        $formatted = $this->formatter->format($license);
        $this->assertEquals('HWT-PRO-A3F2C8D1E9B07456-1A2B', $formatted);
    }

    /** @test */
    public function extracts_suffix_from_plain_hex_format()
    {
        // Plain format without any prefix: RANDOM-CHECKSUM
        $key = 'A3F2C8D1E9B07456-1A2B';
        $license = $this->createLicense('enterprise', $key);
        $formatted = $this->formatter->format($license);
        $this->assertEquals('HWT-ENT-A3F2C8D1E9B07456-1A2B', $formatted);
    }

    /** @test */
    public function batch_format_works()
    {
        $licenses = [
            $this->createLicense('trial', 'TRIAL-KEY-1'),
            $this->createLicense('enterprise', 'ENT-KEY-2'),
            $this->createLicense('professional', 'PRO-KEY-3'),
        ];

        $results = $this->formatter->formatBatch($licenses);

        $this->assertCount(3, $results);
        foreach ($licenses as $license) {
            $this->assertArrayHasKey($license->id, $results);
            $prefix = KeyGenerator::PREFIX_MAP[$license->type];
            $this->assertStringStartsWith($prefix, $results[$license->id]);
        }
    }

    /** @test */
    public function get_format_label_returns_readable_name()
    {
        $this->assertEquals('企业版', $this->formatter->getFormatLabel('HWT-ENT-XXXX'));
        $this->assertEquals('专业版', $this->formatter->getFormatLabel('HWT-PRO-XXXX'));
        $this->assertEquals('试用版', $this->formatter->getFormatLabel('HWT-TRIAL-XXXX'));
    }
}
