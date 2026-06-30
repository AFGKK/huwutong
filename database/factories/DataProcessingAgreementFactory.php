<?php

namespace Database\Factories;

use App\Models\DataProcessingAgreement;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class DataProcessingAgreementFactory extends Factory
{
    protected $model = DataProcessingAgreement::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'title' => '数据处理协议',
            'version' => '1.' . $this->faker->unique()->numberBetween(1, 999),
            'content' => $this->faker->paragraphs(5, true),
            'status' => DataProcessingAgreement::STATUS_DRAFT,
            'data_categories' => [
                '用户账户信息（姓名、邮箱、电话）',
                '授权记录（License Key、设备指纹）',
                '订阅与支付信息',
            ],
            'processing_purposes' => [
                '提供 License 授权验证服务',
                '客户支持与故障排查',
                '合规审计与日志记录',
            ],
            'sub_processors' => [
                ['name' => '阿里云', 'purpose' => '云服务器托管', 'location' => '中国杭州'],
                ['name' => 'AWS', 'purpose' => 'CDN 加速', 'location' => '美国弗吉尼亚'],
            ],
            'security_measures' => [
                '数据传输加密（TLS 1.3）',
                '静态数据加密（AES-256）',
                '访问控制（RBAC + MFA）',
                '审计日志（Merkle 链）',
            ],
            'jurisdiction' => '中华人民共和国法律',
        ];
    }
}
