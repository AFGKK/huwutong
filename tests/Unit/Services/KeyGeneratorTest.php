<?php

namespace Tests\Unit\Services;

use App\Services\KeyGenerator;
use Tests\TestCase;

class KeyGeneratorTest extends TestCase
{
    private KeyGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = app(KeyGenerator::class);
    }

    public function test_generates_standard_key_with_correct_format(): void
    {
        $key = $this->generator->generate('standard');
        $this->assertMatchesRegularExpression('/^HWT-STD-[A-F0-9]{16}-[A-F0-9]{4}$/', $key);
    }

    public function test_generates_enterprise_key(): void
    {
        $key = $this->generator->generate('enterprise');
        $this->assertStringStartsWith('HWT-ENT-', $key);
    }

    public function test_generates_trial_key(): void
    {
        $key = $this->generator->generate('trial');
        $this->assertStringStartsWith('HWT-TRIAL-', $key);
    }

    public function test_generates_development_key(): void
    {
        $key = $this->generator->generate('development');
        $this->assertStringStartsWith('HWT-DEV-', $key);
    }

    public function test_keys_are_unique(): void
    {
        $keys = [];
        for ($i = 0; $i < 100; $i++) {
            $keys[] = $this->generator->generate();
        }
        $this->assertCount(100, array_unique($keys));
    }

    public function test_batch_generates_correct_count(): void
    {
        $keys = $this->generator->generateBatch('standard', 5);
        $this->assertCount(5, $keys);
    }

    public function test_validates_correct_key(): void
    {
        $key = $this->generator->generate('standard');
        $this->assertTrue($this->generator->validateFormat($key));
    }

    public function test_rejects_invalid_key(): void
    {
        $this->assertFalse($this->generator->validateFormat('HWT-STD-INVALID-KEY'));
        $this->assertFalse($this->generator->validateFormat('INVALID-PREFIX-A3F2C8D1E9B07456-1A2B'));
        $this->assertFalse($this->generator->validateFormat(''));
    }

    public function test_rejects_key_with_wrong_checksum(): void
    {
        $this->assertFalse($this->generator->validateFormat('HWT-STD-A3F2C8D1E9B07456-XXXX'));
    }
}
