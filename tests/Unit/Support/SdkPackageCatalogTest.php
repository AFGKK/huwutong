<?php

namespace Tests\Unit\Support;

use App\Support\SdkPackageCatalog;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SdkPackageCatalogTest extends TestCase
{
    #[Test]
    public function it_resolves_canonical_packages_from_sdk_docs_config(): void
    {
        $this->assertSame('huwutong/huwutong-sdk-php', SdkPackageCatalog::packageName('php'));
        $this->assertSame('huwutong-sdk', SdkPackageCatalog::packageName('node'));
        $this->assertSame('huwutong-sdk', SdkPackageCatalog::packageName('python'));
        $this->assertSame('github.com/huwutong/huwutong-sdk-go', SdkPackageCatalog::packageName('go'));
        $this->assertSame('@huwutong/sdk', SdkPackageCatalog::packageName('electron'));
    }

    #[Test]
    public function install_commands_match_public_docs(): void
    {
        $this->assertSame(
            'composer require huwutong/huwutong-sdk-php',
            SdkPackageCatalog::installCommand('php')
        );
        $this->assertSame(
            'npm install huwutong-sdk',
            SdkPackageCatalog::installCommand('node')
        );
        $this->assertSame(
            'pip install huwutong-sdk',
            SdkPackageCatalog::installCommand('python')
        );
        $this->assertSame(
            'cargo add huwutong-sdk',
            SdkPackageCatalog::installCommand('rust')
        );
        $this->assertSame(
            'flutter pub add huwutong_sdk',
            SdkPackageCatalog::installCommand('flutter')
        );
        $this->assertStringNotContainsString('license-sdk', SdkPackageCatalog::installCommand('php') ?? '');
    }

    #[Test]
    public function docs_urls_point_to_local_public_routes(): void
    {
        $this->assertStringContainsString('/docs/sdk/php', SdkPackageCatalog::publicDocsUrl('php'));
        $this->assertStringContainsString('/docs/sdk/node', SdkPackageCatalog::publicDocsUrl('node'));
    }
}
