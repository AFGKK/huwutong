# HWT License Python SDK

官方 Python SDK，用于集成 HWT License 激活/验证/设备管理功能。

## 安装

```bash
pip install huwutong-sdk
```

## 快速开始

```python
from huwutong_sdk import HWTClient

client = HWTClient(
    api_key="your_api_key_here",
    host="https://api.huwutong.com"
)

# 激活 License
result = client.activate("LICENSE-KEY-HERE", {
    "machine_id": "unique-machine-id",
    "hostname": "server-01",
    "platform": "linux"
})
print(f"激活成功: {result.expires_at}")

# 验证 License
result = client.validate("LICENSE-KEY-HERE", {
    "machine_id": "unique-machine-id"
})
if result.is_valid:
    print(f"License 有效，到期: {result.expires_at}")
else:
    print(f"License 无效: {result.message}")

# 检查 Feature Flag
enabled = client.check_feature("LICENSE-KEY-HERE", "ai_features")
print(f"AI 功能: {'启用' if enabled else '未启用'}")
```

## API 参考

| 方法 | 说明 |
|------|------|
| `activate()` | 激活 License |
| `validate()` | 验证 License |
| `deactivate()` | 解除激活 |
| `get_license_info()` | 查询 License |
| `check_feature()` | 检查 Feature |
| `verify_offline()` | 离线验证 |
| `get_offline_license()` | 获取离线文件 |

## 错误处理

```python
from huwutong_sdk import HWTClient, HWTApiError, HWTNetworkError

client = HWTClient(api_key="xxx")

try:
    result = client.activate("INVALID-KEY", {})
except HWTApiError as e:
    print(f"API 错误 [{e.code}]: {e.message}")
except HWTNetworkError as e:
    print(f"网络错误: {e}")
```
