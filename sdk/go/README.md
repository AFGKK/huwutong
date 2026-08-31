# HWT License Go SDK

官方 Go SDK，用于集成 HWT License 激活 / 验证 / 设备管理。

## 环境要求

- Go 1.18+

## 安装

```bash
go get github.com/huwutong/huwutong-sdk-go
```

本地开发可直接引用仓库内 `sdk/go` 模块：

```bash
# go.mod
require github.com/huwutong/huwutong-sdk-go v0.0.0
replace github.com/huwutong/huwutong-sdk-go => ./sdk/go
```

## 快速开始

```go
package main

import (
	"fmt"
	"log"

	"github.com/huwutong/huwutong-sdk-go/huwutong"
)

func main() {
	client := huwutong.NewClient("your_api_key", "https://api.huwutong.com")

	activation, err := client.Activate("LICENSE-KEY", map[string]interface{}{
		"machine_id": "unique-machine-id",
		"hostname":   "server-01",
		"platform":   "linux",
	})
	if err != nil {
		log.Fatalf("激活失败: %v", err)
	}
	fmt.Printf("激活成功, 到期: %s\n", activation.ExpiresAt)

	result, err := client.Validate("LICENSE-KEY", map[string]interface{}{
		"machine_id": "unique-machine-id",
	})
	if err != nil {
		log.Fatalf("验证失败: %v", err)
	}
	if result.IsValid {
		fmt.Printf("License 有效, 到期: %s\n", result.ExpiresAt)
	} else {
		fmt.Printf("License 无效: %s\n", result.Message)
	}
}
```

## API 参考

| 方法 | 说明 |
|------|------|
| `NewClient(apiKey, host)` | 创建客户端 |
| `Activate(licenseKey, machineInfo)` | 激活 License 并绑定设备 |
| `Validate(licenseKey, machineInfo)` | 在线验证 License |
| `Deactivate(licenseKey, machineInfo)` | 解除设备激活 |

## 错误处理

```go
var apiErr *huwutong.ApiError
if errors.As(err, &apiErr) {
    log.Printf("API Error [%s]: %s", apiErr.Code, apiErr.Message)
}
```

## 文档

- 在线教程：[/docs/sdk/go](/docs/sdk/go)
- SDK 总览：[/sdk](/sdk)
- 快速入门：[/docs/quickstart](/docs/quickstart)

## License

MIT
