<?php

namespace Database\Seeders;

use App\Models\LicenseTemplate;
use App\Models\LicenseTemplateVariable;
use Illuminate\Database\Seeder;

class LicenseTemplateSeeder extends Seeder
{
    public function run(): void
    {
        if (LicenseTemplate::where('name', '标准单用户许可证')->exists()) {
            $this->command->info('License templates already seeded, skipping.');
            return;
        }

        // ─── 标准单用户许可证 ───
        $standard = LicenseTemplate::create([
            'name' => '标准单用户许可证',
            'description' => '适用于个人用户的标准许可证模板',
            'type' => 'standard',
            'seats' => 1,
            'max_devices' => 2,
            'expiry_days' => 365,
            'metadata' => [
                'support_level' => 'basic',
                'auto_renew' => false,
                'notes' => '客户: {{customer_name}}, 邮箱: {{customer_email}}',
            ],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        LicenseTemplateVariable::insert([
            [
                'license_template_id' => $standard->id,
                'key' => 'customer_name',
                'label' => '客户名称',
                'variable_type' => 'string',
                'is_required' => true,
                'sort_order' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'license_template_id' => $standard->id,
                'key' => 'customer_email',
                'label' => '客户邮箱',
                'variable_type' => 'string',
                'is_required' => true,
                'sort_order' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        // ─── 企业批量许可证 ───
        $enterprise = LicenseTemplate::create([
            'name' => '企业批量许可证',
            'description' => '适用于企业的批量许可证模板，支持多座位',
            'type' => 'enterprise',
            'seats' => 10,
            'max_devices' => 50,
            'expiry_days' => 730,
            'metadata' => [
                'support_level' => 'premium',
                'auto_renew' => true,
                'department' => '{{department}}',
                'notes' => '企业: {{company_name}}, 管理员: {{admin_email}}',
            ],
            'is_active' => true,
            'sort_order' => 2,
        ]);

        LicenseTemplateVariable::insert([
            [
                'license_template_id' => $enterprise->id, 'key' => 'company_name',
                'label' => '企业名称', 'variable_type' => 'string',
                'is_required' => true, 'sort_order' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'license_template_id' => $enterprise->id, 'key' => 'admin_email',
                'label' => '管理员邮箱', 'variable_type' => 'string',
                'is_required' => true, 'sort_order' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
        LicenseTemplateVariable::insert([
            [
                'license_template_id' => $enterprise->id, 'key' => 'department',
                'label' => '部门', 'variable_type' => 'select',
                'options' => json_encode(['engineering' => '研发部', 'sales' => '销售部', 'finance' => '财务部', 'hr' => '人事部']),
                'default_value' => 'engineering',
                'is_required' => false,
                'sort_order' => 2,
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        // ─── 试用许可证 ───
        LicenseTemplate::create([
            'name' => '试用许可证',
            'description' => '短期试用许可证模板',
            'type' => 'trial',
            'seats' => 1,
            'max_devices' => 1,
            'expiry_days' => 14,
            'metadata' => ['support_level' => 'none', 'is_trial' => true],
            'is_active' => true,
            'sort_order' => 3,
        ]);

        $this->command->info('Seeded 3 license templates with variables.');
    }
}
