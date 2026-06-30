<?php

// M2-86 开发者门户 DevPortal 配置

return [
    /*
    |--------------------------------------------------------------------------
    | 门户信息
    |--------------------------------------------------------------------------
    */
    'site_name' => env('DEV_PORTAL_NAME', 'HWT Developers'),
    'site_url' => env('DEV_PORTAL_URL', 'https://developers.huwutong.com'),

    /*
    |--------------------------------------------------------------------------
    | SDK 下载
    |--------------------------------------------------------------------------
    */
    'sdks' => [
        'php' => [
            'name' => 'PHP SDK',
            'description' => 'Composer 安装，支持 PHP 8.1+',
            'install_command' => 'composer require huwutong/license-sdk',
            'docs_url' => '/api-docs?lang=php',
            'repo_url' => 'https://github.com/huwutong/license-sdk-php',
            'latest_version' => '2.1.0',
        ],
        'python' => [
            'name' => 'Python SDK',
            'description' => 'Pip 安装，支持 Python 3.8+',
            'install_command' => 'pip install huwutong-license',
            'docs_url' => '/api-docs?lang=python',
            'repo_url' => 'https://github.com/huwutong/license-sdk-python',
            'latest_version' => '1.8.0',
        ],
        'javascript' => [
            'name' => 'JavaScript SDK',
            'description' => 'NPM 安装，支持 Node.js 16+ / 浏览器',
            'install_command' => 'npm install @huwutong/license-sdk',
            'docs_url' => '/api-docs?lang=javascript',
            'repo_url' => 'https://github.com/huwutong/license-sdk-js',
            'latest_version' => '2.0.0',
        ],
        'java' => [
            'name' => 'Java SDK',
            'description' => 'Maven/Gradle，支持 Java 11+',
            'install_command' => 'implementation "com.huwutong:license-sdk:1.5.0"',
            'docs_url' => '/api-docs?lang=java',
            'repo_url' => 'https://github.com/huwutong/license-sdk-java',
            'latest_version' => '1.5.0',
        ],
        'go' => [
            'name' => 'Go SDK',
            'description' => 'Go Modules，支持 Go 1.18+',
            'install_command' => 'go get github.com/huwutong/license-sdk-go',
            'docs_url' => '/api-docs?lang=go',
            'repo_url' => 'https://github.com/huwutong/license-sdk-go',
            'latest_version' => '1.3.0',
        ],
        'dotnet' => [
            'name' => '.NET SDK',
            'description' => 'NuGet 安装，支持 .NET 6+',
            'install_command' => 'dotnet add package Huwutong.LicenseSdk',
            'docs_url' => '/api-docs?lang=dotnet',
            'repo_url' => 'https://github.com/huwutong/license-sdk-dotnet',
            'latest_version' => '1.2.0',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 快速链接
    |--------------------------------------------------------------------------
    */
    'quick_links' => [
        ['title' => '快速开始', 'path' => '/quickstart', 'icon' => 'Rocket'],
        ['title' => 'API 文档', 'path' => '/api-docs', 'icon' => 'Document'],
        ['title' => 'API Playground', 'path' => '/playground', 'icon' => 'Monitor'],
        ['title' => '错误码参考', 'path' => '/error-codes', 'icon' => 'WarningFilled'],
        ['title' => 'Webhook 指南', 'path' => '/webhook-endpoints', 'icon' => 'Link'],
        ['title' => 'SDK Telemetry', 'path' => '/telemetry', 'icon' => 'DataBoard'],
        ['title' => '开发者沙箱', 'path' => '/sandbox', 'icon' => 'EditPen'],
        ['title' => 'API 版本', 'path' => '/api-versions', 'icon' => 'Connection'],
    ],

    /*
    |--------------------------------------------------------------------------
    | 快速开始步骤
    |--------------------------------------------------------------------------
    */
    'quickstart_steps' => [
        ['step' => 1, 'title' => '注册账号', 'description' => '创建互物通账号并完成邮箱验证', 'link' => '/register'],
        ['step' => 2, 'title' => '创建 API Key', 'description' => '在管理后台生成你的第一个 API Key', 'link' => '/api-keys'],
        ['step' => 3, 'title' => '安装 SDK', 'description' => '选择你的编程语言并安装对应的 SDK', 'link' => '#sdks'],
        ['step' => 4, 'title' => '集成验证', 'description' => '使用 API Playground 测试你的第一个 API 调用', 'link' => '/playground'],
        ['step' => 5, 'title' => '部署上线', 'description' => '将 License 验证集成到你的应用中', 'link' => '/api-docs'],
    ],
];
