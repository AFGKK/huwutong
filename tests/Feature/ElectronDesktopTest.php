<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * D-32: Electron 管理壳配置冒烟
 */
class ElectronDesktopTest extends TestCase
{
    public function test_electron_project_structure_exists(): void
    {
        $base = base_path('desktop/electron');

        foreach ([
            'package.json',
            'config.js',
            'src/main.js',
            'src/preload.js',
            'src/updater.js',
        ] as $file) {
            $this->assertFileExists("{$base}/{$file}", "Missing: {$file}");
        }
    }

    public function test_main_process_has_tray_and_updater(): void
    {
        $main = file_get_contents(base_path('desktop/electron/src/main.js'));

        $this->assertStringContainsString('Tray', $main);
        $this->assertStringContainsString('setupAutoUpdater', $main);
        $this->assertStringContainsString('requestSingleInstanceLock', $main);
    }

    public function test_preload_exposes_desktop_api(): void
    {
        $preload = file_get_contents(base_path('desktop/electron/src/preload.js'));

        $this->assertStringContainsString('hwtDesktop', $preload);
        $this->assertStringContainsString('contextBridge', $preload);
    }

    public function test_package_json_has_electron_builder_targets(): void
    {
        $pkg = json_decode(file_get_contents(base_path('desktop/electron/package.json')), true);

        $this->assertSame('hwt-admin-desktop', $pkg['name']);
        $this->assertArrayHasKey('electron', $pkg['devDependencies']);
        $this->assertArrayHasKey('win', $pkg['build']);
        $this->assertArrayHasKey('mac', $pkg['build']);
    }

    public function test_electron_dev_scripts_exist(): void
    {
        $this->assertFileExists(base_path('scripts/electron-dev.ps1'));
        $this->assertFileExists(base_path('scripts/electron-dev.sh'));
    }

    public function test_electron_env_check_command(): void
    {
        $this->artisan('electron:env-check --json')
            ->assertExitCode(0);
    }
}
