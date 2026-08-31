<?php

// M2-86 开发者门户 DevPortal 配置（包名与 config/sdk-docs.php、sdk/ 目录对齐）

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
    | SDK 下载（公开页以 config/sdk-docs.php 为准；此处保留后台兼容字段）
    |--------------------------------------------------------------------------
    */
    'sdks' => [
        'php' => [
            'name' => 'PHP SDK',
            'description' => 'Composer 安装，支持 PHP 8.1+',
            'install_command' => 'composer require huwutong/huwutong-sdk-php',
            'docs_url' => '/docs/sdk/php',
            'repo_url' => 'https://github.com/huwutong/huwutong-sdk-php',
            'latest_version' => '1.0.0',
        ],
        'python' => [
            'name' => 'Python SDK',
            'description' => 'Pip 安装，支持 Python 3.8+',
            'install_command' => 'pip install huwutong-sdk',
            'docs_url' => '/docs/sdk/python',
            'repo_url' => 'https://github.com/huwutong/huwutong-sdk-python',
            'latest_version' => '1.0.0',
        ],
        'javascript' => [
            'name' => 'JavaScript / Node.js SDK',
            'description' => 'NPM 安装，支持 Node.js 16+',
            'install_command' => 'npm install huwutong-sdk',
            'docs_url' => '/docs/sdk/node',
            'repo_url' => 'https://github.com/huwutong/huwutong-sdk-node',
            'latest_version' => '1.0.0',
        ],
        'java' => [
            'name' => 'Java SDK',
            'description' => 'Maven/Gradle，支持 Java 11+',
            'install_command' => 'implementation "com.huwutong:huwutong-sdk:1.0.0"',
            'docs_url' => '/docs/sdk/java',
            'repo_url' => 'https://github.com/huwutong/huwutong-sdk-java',
            'latest_version' => '1.0.0',
        ],
        'go' => [
            'name' => 'Go SDK',
            'description' => 'Go Modules，支持 Go 1.18+',
            'install_command' => 'go get github.com/huwutong/huwutong-sdk-go',
            'docs_url' => '/docs/sdk/go',
            'repo_url' => 'https://github.com/huwutong/huwutong-sdk-go',
            'latest_version' => '1.0.0',
        ],
        'dotnet' => [
            'name' => '.NET SDK',
            'description' => 'NuGet 安装，支持 .NET 8+',
            'install_command' => 'dotnet add package Huwutong.Sdk',
            'docs_url' => '/docs/sdk/csharp',
            'repo_url' => 'https://github.com/huwutong/huwutong-sdk-dotnet',
            'latest_version' => '1.0.0',
        ],
        'flutter' => [
            'name' => 'Flutter SDK',
            'description' => 'Dart 包，适用于 Flutter',
            'install_command' => 'huwutong_sdk: path ./sdk/flutter',
            'docs_url' => '/docs/sdk/flutter',
            'repo_url' => 'https://github.com/huwutong/huwutong-sdk-flutter',
            'latest_version' => '1.0.0',
        ],
        'electron' => [
            'name' => 'Electron SDK',
            'description' => 'Electron 主进程集成',
            'install_command' => 'npm install @huwutong/sdk',
            'docs_url' => '/docs/sdk/electron',
            'repo_url' => 'https://github.com/huwutong/huwutong-sdk-electron',
            'latest_version' => '1.0.0',
        ],
        'tauri' => [
            'name' => 'Tauri / Rust SDK',
            'description' => 'Rust crate，适用于 Tauri',
            'install_command' => 'huwutong-sdk = { path = "./sdk/tauri" }',
            'docs_url' => '/docs/sdk/tauri',
            'repo_url' => 'https://github.com/huwutong/huwutong-sdk-tauri',
            'latest_version' => '1.0.0',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 快速链接
    |--------------------------------------------------------------------------
    */
    'quick_links' => [
        ['title' => '快速开始', 'path' => '/docs/quickstart', 'icon' => 'Rocket'],
        ['title' => 'SDK 文档', 'path' => '/sdk', 'icon' => 'Document'],
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
        ['step' => 3, 'title' => '安装 SDK', 'description' => '选择你的编程语言并安装对应的 SDK', 'link' => '/sdk'],
        ['step' => 4, 'title' => '集成验证', 'description' => '按语言文档完成 activate / validate', 'link' => '/docs/sdk/php'],
        ['step' => 5, 'title' => '部署上线', 'description' => '将 License 验证集成到你的应用中', 'link' => '/docs/quickstart'],
    ],
];
