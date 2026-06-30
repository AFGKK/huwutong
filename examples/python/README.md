# HWT License Python SDK Demo

> 互物通授权系统 Python 集成示例 — 支持 Flask / 独立使用

SDK 包位于 [`sdk/python/`](../../sdk/python/)，本目录包含 Flask 集成示例。

## 安装

```bash
# 安装 SDK
cd ../../sdk/python
pip install -e .

# 安装 Flask
cd ../../examples/python
pip install flask
```

## 用法

```bash
# 启动 Flask 示例服务
HWT_API_KEY=sk_test_xxx python app.py

# 测试 License 验证
curl -H "X-License-Key: HWT-XXXX" http://localhost:5000/api/protected
```

## Flask 蓝图集成

```python
from huwutong_sdk import HWTClient

client = HWTClient(api_key="sk_test_xxx")

# License 验证装饰器
def require_license(f):
    @wraps(f)
    def decorated(*args, **kwargs):
        license_key = request.headers.get("X-License-Key")
        result = client.validate(license_key)
        if result.is_valid:
            request.license_info = result
            return f(*args, **kwargs)
        return jsonify({"error": "LICENSE_INVALID"}), 403
    return decorated
```

## API 参考

| 方法 | 说明 |
|------|------|
| `client.activate(key, device)` | 激活 License |
| `client.validate(key)` | 验证 License |
| `client.check_feature(key, feature)` | 检查 Feature |
| `client.get_license_info(key)` | 查询信息 |
| `client.heartbeat(key)` | 心跳上报 |
