# HWT License Java SDK

官方 Java SDK，用于集成 HWT License 激活 / 验证 / 设备管理。

## 环境要求

- Java 11+
- Maven 或 Gradle

## 安装

### Maven

```xml
<dependency>
  <groupId>com.huwutong</groupId>
  <artifactId>huwutong-sdk</artifactId>
  <version>1.0.0</version>
</dependency>
```

### Gradle

```gradle
implementation "com.huwutong:huwutong-sdk:1.0.0"
```

本地开发也可直接引用仓库内 `sdk/java` 源码。

## 快速开始

```java
import com.huwutong.sdk.HWTClient;
import com.huwutong.sdk.ActivationResult;
import com.huwutong.sdk.ValidationResult;
import com.huwutong.sdk.HWTApiException;

import java.util.Map;

public class Example {
    public static void main(String[] args) {
        HWTClient client = new HWTClient("your_api_key", "https://api.huwutong.com");

        try {
            ActivationResult activation = client.activate("LICENSE-KEY", Map.of(
                "machine_id", "unique-machine-id",
                "hostname", "server-01",
                "platform", "linux"
            ));
            System.out.println("激活成功, 到期: " + activation.getExpiresAt());

            ValidationResult validation = client.validate("LICENSE-KEY", Map.of(
                "machine_id", "unique-machine-id"
            ));
            if (validation.isValid()) {
                System.out.println("License 有效, 到期: " + validation.getExpiresAt());
            } else {
                System.out.println("License 无效: " + validation.getMessage());
            }
        } catch (HWTApiException e) {
            System.err.println("API 错误 [" + e.getCode() + "]: " + e.getMessage());
        }
    }
}
```

## API 参考

| 方法 | 说明 |
|------|------|
| `activate(licenseKey, machineInfo)` | 激活 License 并绑定设备 |
| `validate(licenseKey, machineInfo)` | 在线验证 License |
| `deactivate(licenseKey, machineInfo)` | 解除设备激活 |

## 文档

- 在线教程：[/docs/sdk/java](/docs/sdk/java)
- SDK 总览：[/sdk](/sdk)
- 快速入门：[/docs/quickstart](/docs/quickstart)

## License

MIT
