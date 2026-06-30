<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            AdminUserSeeder::class,
            LlmProviderSeeder::class,
            TaxRateSeeder::class,
            SiteSettingsSeeder::class,
            DemoDataSeeder::class,
            AutomationRuleSeeder::class,
            InviteCodeSeeder::class,
            AuditExportSeeder::class,
            DashboardSeeder::class,
            DataImportSeeder::class,
            SecurityCenterSeeder::class,
            SlaSeeder::class,
            AuditVisualizationSeeder::class,
            AlertingSeeder::class,
            LicenseTemplateSeeder::class,
            PortalBrandingSeeder::class,
        ]);
    }
}
