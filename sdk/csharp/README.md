# Huwutong C# (.NET) SDK

M2-21: 基于统一错误码标准 M2-34

## 安装

```bash
# NuGet
dotnet add package Huwutong.Sdk
```

或手动引用 `HuwutongSdk.csproj`。

## 快速开始

```csharp
using HuwutongSdk;

var client = new HwtClient(
    apiKey: "your_api_key",
    host: "https://api.huwutong.com"
);

// 激活
var activation = await client.Activate("LICENSE-KEY", new Dictionary<string, string>
{
    ["machine_id"] = "unique-machine-id",
    ["hostname"] = Environment.MachineName
});
Console.WriteLine(activation.Success);

// 验证
var validation = await client.Validate("LICENSE-KEY", new Dictionary<string, string>
{
    ["machine_id"] = "unique-machine-id"
});
Console.WriteLine(validation.IsValid);

// 检查 Feature
var feature = await client.CheckFeature("LICENSE-KEY", "ai_assistant");
Console.WriteLine(feature.Data?.Enabled);
```

## 错误码

| 错误码 | 说明 |
|--------|------|
| `LICENSE_EXPIRED` | License 已过期 |
| `DEVICE_LIMIT` | 设备数超限 |
| `FINGERPRINT_MISMATCH` | 设备指纹不匹配 |
| `LICENSE_REVOKED` | License 已吊销 |
| `LICENSE_SUSPENDED` | License 已暂停 |
| `INVALID_SIGNATURE` | 签名无效 |
| `RATE_LIMITED` | 请求频率超限 |
