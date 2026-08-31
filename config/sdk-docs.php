<?php

/**
 * 公开 SDK 集成文档配置（与 sdk/ 目录包名对齐）
 */

return [
    'aliases' => [
        'php' => 'php',
        'node' => 'node',
        'nodejs' => 'node',
        'javascript' => 'node',
        'js' => 'node',
        'python' => 'python',
        'py' => 'python',
        'go' => 'go',
        'golang' => 'go',
        'java' => 'java',
        'csharp' => 'csharp',
        'cs' => 'csharp',
        'dotnet' => 'csharp',
        'c-sharp' => 'csharp',
        'flutter' => 'flutter',
        'dart' => 'flutter',
        'electron' => 'electron',
        'tauri' => 'tauri',
        'rust' => 'tauri',
    ],

    'sdks' => [
        'php' => [
            'id' => 'php',
            'lang_label' => 'PHP',
            'name' => 'PHP SDK',
            'description' => 'Composer 安装，支持 PHP 8.1+（Laravel / Symfony / ThinkPHP）',
            'frameworks' => 'Laravel / Symfony / ThinkPHP',
            'install_command' => 'composer require huwutong/huwutong-sdk-php',
            'package' => 'huwutong/huwutong-sdk-php',
            'version' => '1.0.0',
            'requires' => 'PHP >= 8.1',
            'docs_url' => '/docs/sdk/php',
            'repo_path' => 'sdk/php',
            'example_tab' => true,
            'example' => <<<'CODE'
require 'vendor/autoload.php';

use Huwutong\Client;

$client = new Client('your_api_key', 'https://api.huwutong.com');

$result = $client->activate('LICENSE-KEY', [
    'machine_id' => 'unique-machine-id',
    'hostname'   => gethostname(),
]);

$validation = $client->validate('LICENSE-KEY', [
    'machine_id' => 'unique-machine-id',
]);

echo $validation->isValid ? 'License valid' : 'License invalid';
CODE,
            'methods' => [
                ['name' => 'activate()', 'desc' => '激活 License 并绑定设备'],
                ['name' => 'validate()', 'desc' => '在线验证 License'],
                ['name' => 'deactivate()', 'desc' => '解除设备激活'],
                ['name' => 'getLicenseInfo()', 'desc' => '查询 License 详情'],
                ['name' => 'checkFeature()', 'desc' => '检查 Feature Flag'],
                ['name' => 'verifyOffline()', 'desc' => '离线验证'],
            ],
            'steps' => [
                '在管理后台创建 API Key，并复制密钥',
                '执行安装命令引入 SDK',
                '用 API Key 初始化 Client，调用 activate / validate',
                '在应用启动或关键路径中周期性校验授权',
            ],
        ],

        'node' => [
            'id' => 'node',
            'lang_label' => 'Node.js',
            'name' => 'Node.js SDK',
            'description' => 'npm 安装，支持 Node.js 16+（Express / Koa / Next.js）',
            'frameworks' => 'Express / Koa / Next.js',
            'install_command' => 'npm install huwutong-sdk',
            'package' => 'huwutong-sdk',
            'version' => '1.0.0',
            'requires' => 'Node.js >= 16',
            'docs_url' => '/docs/sdk/node',
            'repo_path' => 'sdk/node',
            'example_tab' => true,
            'example' => <<<'CODE'
const { Client } = require('huwutong-sdk');

const client = new Client('your_api_key', 'https://api.huwutong.com');

async function main() {
  await client.activate('LICENSE-KEY', {
    machine_id: 'unique-machine-id',
    hostname: require('os').hostname(),
  });

  const result = await client.validate('LICENSE-KEY', {
    machine_id: 'unique-machine-id',
  });

  console.log(result.isValid ? 'License valid' : 'License invalid');
}

main();
CODE,
            'methods' => [
                ['name' => 'activate()', 'desc' => '激活 License 并绑定设备'],
                ['name' => 'validate()', 'desc' => '在线验证 License'],
                ['name' => 'deactivate()', 'desc' => '解除设备激活'],
                ['name' => 'offlineVerify()', 'desc' => '离线验证'],
            ],
            'steps' => [
                '在管理后台创建 API Key',
                'npm / yarn 安装 huwutong-sdk',
                '初始化 Client 后调用 activate / validate',
                '建议在中间件或服务启动时校验授权',
            ],
        ],

        'python' => [
            'id' => 'python',
            'lang_label' => 'Python',
            'name' => 'Python SDK',
            'description' => 'pip 安装，支持 Python 3.8+（Django / Flask / FastAPI）',
            'frameworks' => 'Django / Flask / FastAPI',
            'install_command' => 'pip install huwutong-sdk',
            'package' => 'huwutong-sdk',
            'version' => '1.0.0',
            'requires' => 'Python >= 3.8',
            'docs_url' => '/docs/sdk/python',
            'repo_path' => 'sdk/python',
            'example_tab' => true,
            'example' => <<<'CODE'
from huwutong_sdk import HWTClient

client = HWTClient(
    api_key='your_api_key',
    host='https://api.huwutong.com',
)

client.activate('LICENSE-KEY', {
    'machine_id': 'unique-machine-id',
    'hostname': 'server-01',
})

result = client.validate('LICENSE-KEY', {
    'machine_id': 'unique-machine-id',
})
print('License valid' if result.is_valid else 'License invalid')
CODE,
            'methods' => [
                ['name' => 'activate()', 'desc' => '激活 License 并绑定设备'],
                ['name' => 'validate()', 'desc' => '在线验证 License'],
                ['name' => 'deactivate()', 'desc' => '解除设备激活'],
                ['name' => 'get_license_info()', 'desc' => '查询 License 详情'],
                ['name' => 'check_feature()', 'desc' => '检查 Feature Flag'],
                ['name' => 'verify_offline()', 'desc' => '离线验证'],
            ],
            'steps' => [
                '在管理后台创建 API Key',
                'pip 安装 huwutong-sdk',
                '用 HWTClient 完成激活与验证',
                '在服务入口或定时任务中保持校验',
            ],
        ],

        'go' => [
            'id' => 'go',
            'lang_label' => 'Go',
            'name' => 'Go SDK',
            'description' => 'Go Modules，支持 Go 1.18+（Gin / Echo / Fiber）',
            'frameworks' => 'Gin / Echo / Fiber',
            'install_command' => 'go get github.com/huwutong/huwutong-sdk-go',
            'package' => 'github.com/huwutong/huwutong-sdk-go',
            'version' => '1.0.0',
            'requires' => 'Go >= 1.18',
            'docs_url' => '/docs/sdk/go',
            'repo_path' => 'sdk/go',
            'example_tab' => true,
            'example' => <<<'CODE'
package main

import (
    "fmt"
    "log"

    "github.com/huwutong/huwutong-sdk-go/huwutong"
)

func main() {
    client := huwutong.NewClient("your_api_key", "https://api.huwutong.com")

    _, err := client.Activate("LICENSE-KEY", map[string]interface{}{
        "machine_id": "unique-machine-id",
        "hostname":   "server-01",
    })
    if err != nil {
        log.Fatal(err)
    }

    result, err := client.Validate("LICENSE-KEY", map[string]interface{}{
        "machine_id": "unique-machine-id",
    })
    if err != nil {
        log.Fatal(err)
    }
    fmt.Println(result.IsValid)
}
CODE,
            'methods' => [
                ['name' => 'Activate()', 'desc' => '激活 License 并绑定设备'],
                ['name' => 'Validate()', 'desc' => '在线验证 License'],
                ['name' => 'Deactivate()', 'desc' => '解除设备激活'],
                ['name' => 'GetLicenseInfo()', 'desc' => '查询 License 详情'],
                ['name' => 'CheckFeature()', 'desc' => '检查 Feature Flag'],
            ],
            'steps' => [
                '在管理后台创建 API Key',
                'go get 引入官方模块',
                'NewClient 后调用 Activate / Validate',
                '在 HTTP 中间件或进程启动时校验',
            ],
        ],

        'java' => [
            'id' => 'java',
            'lang_label' => 'Java',
            'name' => 'Java SDK',
            'description' => 'Maven / Gradle，支持 Java 11+（Spring Boot / Micronaut）',
            'frameworks' => 'Spring Boot / Micronaut',
            'install_command' => '<!-- Maven --><dependency>
  <groupId>com.huwutong</groupId>
  <artifactId>huwutong-sdk</artifactId>
  <version>1.0.0</version>
</dependency>',
            'install_alt' => 'implementation "com.huwutong:huwutong-sdk:1.0.0"',
            'package' => 'com.huwutong:huwutong-sdk',
            'version' => '1.0.0',
            'requires' => 'Java >= 11',
            'docs_url' => '/docs/sdk/java',
            'repo_path' => 'sdk/java',
            'example_tab' => true,
            'example' => <<<'CODE'
import com.huwutong.sdk.HWTClient;
import com.huwutong.sdk.ValidationResult;

import java.util.Map;

HWTClient client = new HWTClient("your_api_key", "https://api.huwutong.com");

client.activate("LICENSE-KEY", Map.of(
    "machine_id", "unique-machine-id",
    "hostname", "server-01"
));

ValidationResult result = client.validate("LICENSE-KEY", Map.of(
    "machine_id", "unique-machine-id"
));
System.out.println(result.isValid() ? "License valid" : "License invalid");
CODE,
            'methods' => [
                ['name' => 'activate()', 'desc' => '激活 License 并绑定设备'],
                ['name' => 'validate()', 'desc' => '在线验证 License'],
                ['name' => 'deactivate()', 'desc' => '解除设备激活'],
                ['name' => 'getLicenseInfo()', 'desc' => '查询 License 详情'],
                ['name' => 'checkFeature()', 'desc' => '检查 Feature Flag'],
            ],
            'steps' => [
                '在管理后台创建 API Key',
                '通过 Maven 或 Gradle 引入依赖',
                '初始化 HWTClient 并调用 activate / validate',
                '在应用启动过滤器或定时任务中校验',
            ],
        ],

        'csharp' => [
            'id' => 'csharp',
            'lang_label' => 'C#',
            'name' => 'C# / .NET SDK',
            'description' => 'NuGet 安装，支持 .NET 8+（ASP.NET Core）',
            'frameworks' => '.NET Core / ASP.NET',
            'install_command' => 'dotnet add package Huwutong.Sdk',
            'package' => 'Huwutong.Sdk',
            'version' => '1.0.0',
            'requires' => '.NET 8+',
            'docs_url' => '/docs/sdk/csharp',
            'repo_path' => 'sdk/csharp',
            'example_tab' => true,
            'example' => <<<'CODE'
using HuwutongSdk;

var client = new HwtClient(
    apiKey: "your_api_key",
    host: "https://api.huwutong.com"
);

await client.Activate("LICENSE-KEY", new Dictionary<string, string>
{
    ["machine_id"] = "unique-machine-id",
    ["hostname"] = Environment.MachineName,
});

var result = await client.Validate("LICENSE-KEY", new Dictionary<string, string>
{
    ["machine_id"] = "unique-machine-id",
});
Console.WriteLine(result.IsValid ? "License valid" : "License invalid");
CODE,
            'methods' => [
                ['name' => 'Activate()', 'desc' => '激活 License 并绑定设备'],
                ['name' => 'Validate()', 'desc' => '在线验证 License'],
                ['name' => 'CheckFeature()', 'desc' => '检查 Feature Flag'],
            ],
            'steps' => [
                '在管理后台创建 API Key',
                'NuGet 添加 Huwutong.Sdk',
                '创建 HwtClient 完成激活与验证',
                '在宿主启动或中间件中校验授权',
            ],
        ],

        'flutter' => [
            'id' => 'flutter',
            'lang_label' => 'Flutter',
            'name' => 'Flutter / Dart SDK',
            'description' => 'Dart 包，适用于 Flutter 移动端与桌面端',
            'frameworks' => 'Flutter / Dart',
            'install_command' => "dependencies:\n  huwutong_sdk:\n    path: ./sdk/flutter",
            'package' => 'huwutong_sdk',
            'version' => '1.0.0',
            'requires' => 'Dart SDK >= 3.0',
            'docs_url' => '/docs/sdk/flutter',
            'repo_path' => 'sdk/flutter',
            'example_tab' => false,
            'example' => <<<'CODE'
import 'package:huwutong_sdk/huwutong_sdk.dart';

final client = HwtClient(
  apiKey: 'your_api_key',
  host: 'https://api.huwutong.com',
);

Future<void> main() async {
  await client.activate('LICENSE-KEY', {
    'machine_id': 'unique-machine-id',
  });

  final result = await client.validate('LICENSE-KEY', {
    'machine_id': 'unique-machine-id',
  });
  print(result.isValid);
}
CODE,
            'methods' => [
                ['name' => 'activate()', 'desc' => '激活 License 并绑定设备'],
                ['name' => 'validate()', 'desc' => '在线验证 License'],
            ],
            'steps' => [
                '在管理后台创建 API Key',
                '在 pubspec.yaml 以 path 依赖引入 sdk/flutter',
                '使用 HwtClient 完成激活与验证',
                '在 App 启动流程中校验授权状态',
            ],
        ],

        'electron' => [
            'id' => 'electron',
            'lang_label' => 'Electron',
            'name' => 'Electron SDK',
            'description' => '桌面端主进程集成，基于 Node.js API',
            'frameworks' => 'Electron 主进程',
            'install_command' => 'npm install @huwutong/sdk',
            'package' => '@huwutong/sdk',
            'version' => '1.0.0',
            'requires' => 'Electron + Node.js',
            'docs_url' => '/docs/sdk/electron',
            'repo_path' => 'sdk/electron',
            'example_tab' => false,
            'example' => <<<'CODE'
const os = require('os');
const { HwtClient } = require('@huwutong/sdk');

const client = new HwtClient({
  apiKey: 'your_api_key',
  host: 'https://api.huwutong.com',
});

async function main() {
  await client.activate('LICENSE-KEY', {
    machine_id: 'unique-machine-id',
    hostname: os.hostname(),
  });

  const result = await client.validate('LICENSE-KEY', {
    machine_id: 'unique-machine-id',
  });
  console.log(result.isValid);
}

main();
CODE,
            'methods' => [
                ['name' => 'activate()', 'desc' => '激活 License 并绑定设备'],
                ['name' => 'validate()', 'desc' => '在线验证 License'],
            ],
            'steps' => [
                '在管理后台创建 API Key',
                '在 Electron 主进程依赖中安装 @huwutong/sdk',
                '应用启动时激活并定期 validate',
                '校验失败时限制功能或引导重新授权',
            ],
        ],

        'tauri' => [
            'id' => 'tauri',
            'lang_label' => 'Tauri',
            'name' => 'Tauri / Rust SDK',
            'description' => 'Rust crate，适用于 Tauri 桌面应用',
            'frameworks' => 'Tauri / Rust',
            'install_command' => "[dependencies]\nhuwutong-sdk = { path = \"./sdk/tauri\" }",
            'package' => 'huwutong-sdk',
            'version' => '1.0.0',
            'requires' => 'Rust 2021 edition',
            'docs_url' => '/docs/sdk/tauri',
            'repo_path' => 'sdk/tauri',
            'example_tab' => false,
            'example' => <<<'CODE'
use huwutong_sdk::HwtClient;
use std::collections::HashMap;

fn main() -> Result<(), Box<dyn std::error::Error>> {
    let client = HwtClient::new(
        "your_api_key",
        Some("https://api.huwutong.com".to_string()),
        None,
    );

    let mut device = HashMap::new();
    device.insert("machine_id".to_string(), "unique-machine-id".to_string());

    let activation = client.activate("LICENSE-KEY", device.clone())?;
    println!("activated: {}", activation.success);

    let validation = client.validate("LICENSE-KEY", device)?;
    println!("valid: {}", validation.is_valid());
    Ok(())
}
CODE,
            'methods' => [
                ['name' => 'activate()', 'desc' => '激活 License 并绑定设备'],
                ['name' => 'validate()', 'desc' => '在线验证 License'],
            ],
            'steps' => [
                '在管理后台创建 API Key',
                '在 Cargo.toml 以 path 依赖引入 sdk/tauri',
                '使用 HwtClient 完成激活与验证',
                '在应用启动命令中校验授权',
            ],
        ],
    ],
];
