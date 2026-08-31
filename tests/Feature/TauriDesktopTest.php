<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * D-33: Tauri 轻量版配置冒烟
 */
class TauriDesktopTest extends TestCase
{
    public function test_tauri_project_structure_exists(): void
    {
        $base = base_path('desktop/tauri');

        foreach ([
            'package.json',
            'src/index.html',
            'src/main.js',
            'src-tauri/Cargo.toml',
            'src-tauri/tauri.conf.json',
            'src-tauri/src/lib.rs',
        ] as $file) {
            $this->assertFileExists("{$base}/{$file}", "Missing: {$file}");
        }
    }

    public function test_tauri_commands_use_sdk_and_public_lookup(): void
    {
        $lib = file_get_contents(base_path('desktop/tauri/src-tauri/src/lib.rs'));

        $this->assertStringContainsString('lookup_license', $lib);
        $this->assertStringContainsString('validate_license', $lib);
        $this->assertStringContainsString('huwutong_sdk::HwtClient', $lib);
        $this->assertStringContainsString('public-lookup', $lib);
    }

    public function test_cargo_toml_links_sdk_tauri(): void
    {
        $toml = file_get_contents(base_path('desktop/tauri/src-tauri/Cargo.toml'));

        $this->assertStringContainsString('huwutong-sdk = { path = "../../../sdk/tauri" }', $toml);
        $this->assertStringContainsString('opt-level = "z"', $toml);
    }

    public function test_tauri_dev_scripts_exist(): void
    {
        $this->assertFileExists(base_path('scripts/tauri-dev.ps1'));
        $this->assertFileExists(base_path('scripts/tauri-dev.sh'));
    }

    public function test_tauri_env_check_command(): void
    {
        $this->artisan('tauri:env-check --json')
            ->assertExitCode(0);
    }
}
