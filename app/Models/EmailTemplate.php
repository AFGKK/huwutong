<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $fillable = [
        'code', 'name', 'subject', 'body_html', 'body_text',
        'locale', 'variables', 'status',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
        ];
    }

    /**
     * 获取可用的变量占位符列表
     */
    public static function availableVariables(): array
    {
        return [
            'general' => [
                ['key' => '{{site_name}}', 'label' => '网站名称'],
                ['key' => '{{site_url}}', 'label' => '网站地址'],
                ['key' => '{{year}}', 'label' => '当前年份'],
                ['key' => '{{date}}', 'label' => '当前日期'],
                ['key' => '{{time}}', 'label' => '当前时间'],
            ],
            'customer' => [
                ['key' => '{{customer_name}}', 'label' => '客户名称'],
                ['key' => '{{customer_email}}', 'label' => '客户邮箱'],
            ],
            'license' => [
                ['key' => '{{license_key}}', 'label' => 'License Key'],
                ['key' => '{{license_status}}', 'label' => 'License 状态'],
                ['key' => '{{license_type}}', 'label' => 'License 类型'],
                ['key' => '{{expires_at}}', 'label' => '过期时间'],
                ['key' => '{{days_remaining}}', 'label' => '剩余天数'],
                ['key' => '{{product_name}}', 'label' => '产品名称'],
            ],
            'account' => [
                ['key' => '{{user_name}}', 'label' => '用户名'],
                ['key' => '{{user_email}}', 'label' => '用户邮箱'],
                ['key' => '{{login_url}}', 'label' => '登录链接'],
                ['key' => '{{reset_url}}', 'label' => '重置密码链接'],
                ['key' => '{{verify_url}}', 'label' => '验证邮箱链接'],
            ],
            'invoice' => [
                ['key' => '{{invoice_no}}', 'label' => '发票号'],
                ['key' => '{{amount}}', 'label' => '金额'],
                ['key' => '{{currency}}', 'label' => '货币'],
                ['key' => '{{payment_date}}', 'label' => '支付日期'],
            ],
        ];
    }

    /**
     * 渲染模板内容（替换变量）
     */
    public function render(array $data = []): array
    {
        $subject = $this->replaceVariables($this->subject, $data);
        $html = $this->replaceVariables($this->body_html, $data);
        $text = $this->body_text ? $this->replaceVariables($this->body_text, $data) : strip_tags($html);

        return [
            'subject' => $subject,
            'html' => $html,
            'text' => $text,
        ];
    }

    private function replaceVariables(string $content, array $data): string
    {
        $replacements = [
            '{{site_name}}' => config('app.name', 'HWT License'),
            '{{site_url}}' => config('app.url'),
            '{{year}}' => date('Y'),
            '{{date}}' => now()->format('Y-m-d'),
            '{{time}}' => now()->format('H:i:s'),
        ];

        // 添加自定义数据
        foreach ($data as $key => $value) {
            $replacements['{{' . $key . '}}'] = (string) ($value ?? '');
        }

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }
}
