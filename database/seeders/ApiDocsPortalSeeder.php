<?php

namespace Database\Seeders;

use App\Models\ApiDocTag;
use App\Models\ApiSdkConfig;
use Illuminate\Database\Seeder;

class ApiDocsPortalSeeder extends Seeder
{
    public function run(): void
    {
        // SDK 配置
        $sdks = [
            ['name' => 'PHP SDK', 'language' => 'php', 'version' => '1.0.0', 'description' => 'Composer PHP 客户端', 'install_command' => 'composer require hwt/license-api', 'setup_code' => '$client = new GuzzleHttp\Client([\'base_uri\' => config(\'app.url\') . \'/api\', \'headers\' => [\'Authorization\' => \'Bearer \' . $apiKey]]);', 'is_active' => true],
            ['name' => 'Python SDK', 'language' => 'python', 'version' => '1.0.0', 'description' => 'Pip Python 客户端', 'install_command' => 'pip install hwt-api-client', 'setup_code' => "import requests\nsession = requests.Session()\nsession.headers.update({'Authorization': f'Bearer {api_key}'})", 'is_active' => true],
            ['name' => 'JavaScript SDK', 'language' => 'javascript', 'version' => '1.0.0', 'description' => 'NPM JavaScript 客户端', 'install_command' => 'npm install hwt-api-client', 'setup_code' => "import axios from 'axios';\nconst client = (apiKey) => axios.create({baseURL: '/api', headers: {Authorization: 'Bearer ' + apiKey}});", 'is_active' => true],
            ['name' => 'Go SDK', 'language' => 'go', 'version' => '1.0.0', 'description' => 'Go 客户端', 'install_command' => 'go get github.com/hwt/api-client', 'setup_code' => 'package main', 'is_active' => true],
            ['name' => 'Java SDK', 'language' => 'java', 'version' => '1.0.0', 'description' => 'Maven Java 客户端', 'install_command' => "implementation 'com.hwt:api-client:1.0.0'", 'setup_code' => '// See Maven central for details', 'is_active' => true],
            ['name' => 'Ruby SDK', 'language' => 'ruby', 'version' => '1.0.0', 'description' => 'Gem Ruby 客户端', 'install_command' => 'gem install hwt-api', 'setup_code' => "require 'hwt/api'", 'is_active' => true],
        ];

        foreach ($sdks as $sdk) {
            ApiSdkConfig::updateOrCreate(['language' => $sdk['language']], $sdk);
        }
        $this->command->info('SDK configs seeded: ' . ApiSdkConfig::count());

        // 标签
        $tags = [
            ['name' => 'authentication', 'label' => '认证鉴权', 'description' => '登录、注册、令牌管理', 'sort_order' => 1],
            ['name' => 'licenses', 'label' => '许可证管理', 'description' => '许可证创建、验证、管理', 'sort_order' => 2],
            ['name' => 'subscriptions', 'label' => '订阅管理', 'description' => '订阅生命周期', 'sort_order' => 3],
            ['name' => 'billing', 'label' => '计费财务', 'description' => '发票、支付、退款', 'sort_order' => 4],
            ['name' => 'customers', 'label' => '客户管理', 'description' => '客户信息管理', 'sort_order' => 5],
            ['name' => 'webhooks', 'label' => 'Webhook', 'description' => '事件通知', 'sort_order' => 6],
            ['name' => 'admin', 'label' => '系统管理', 'description' => '管理后台功能', 'sort_order' => 7],
            ['name' => 'audit', 'label' => '审计合规', 'description' => '审计日志与合规', 'sort_order' => 8],
        ];

        foreach ($tags as $tag) {
            ApiDocTag::updateOrCreate(['name' => $tag['name']], $tag);
        }
        $this->command->info('Tags seeded: ' . ApiDocTag::count());
    }
}
