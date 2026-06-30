"""
HWT License Flask 集成示例

展示如何在 Flask 应用中集成 HWT License 验证

用法:
    pip install flask huwutong-sdk
    python app.py

访问: http://localhost:5000
"""

import os
from functools import wraps

from flask import Flask, jsonify, request

from huwutong_sdk import HWTClient, HWTApiError

app = Flask(__name__)

# 初始化 SDK 客户端
client = HWTClient(
    api_key=os.getenv("HWT_API_KEY", "your_api_key_here"),
    host=os.getenv("HWT_HOST", "https://api.huwutong.com"),
)

# M2-34 错误码 → HTTP 状态码映射
ERROR_STATUS_MAP = {
    "LICENSE_EXPIRED": 403,
    "LICENSE_SUSPENDED": 403,
    "LICENSE_REVOKED": 403,
    "DEVICE_LIMIT": 429,
    "FINGERPRINT_MISMATCH": 401,
    "RATE_LIMITED": 429,
}


def require_license(f):
    """License 验证装饰器"""

    @wraps(f)
    def decorated(*args, **kwargs):
        license_key = request.headers.get("X-License-Key")

        if not license_key:
            return jsonify({
                "error": "LICENSE_KEY_REQUIRED",
                "message": "请提供 X-License-Key 请求头",
            }), 401

        try:
            result = client.validate(license_key)
            if result.is_valid:
                request.license_info = result
                return f(*args, **kwargs)
            else:
                return jsonify({
                    "error": result.error_code or "LICENSE_INVALID",
                    "message": result.message or "License 无效",
                }), 403
        except HWTApiError as e:
            status = ERROR_STATUS_MAP.get(e.code, 401)
            return jsonify({
                "error": e.code,
                "message": e.message,
            }), status

    return decorated


@app.route("/")
def index():
    """公开路由"""
    return jsonify({
        "service": "HWT License Flask Demo",
        "version": "1.0.0",
        "endpoints": {
            "/api/protected": "需要 License 验证 (X-License-Key header)",
        },
    })


@app.route("/api/protected")
@require_license
def protected_route():
    """受保护路由（需 License 验证）"""
    license_info = request.license_info
    return jsonify({
        "message": "✅ 访问成功! License 验证通过",
        "license": {
            "key": license_info.license_key,
            "status": license_info.status,
            "expires_at": license_info.expires_at,
            "features": license_info.features or [],
        },
    })


if __name__ == "__main__":
    print("🚀 HWT License Flask Demo 运行在 http://localhost:5000")
    print("测试: curl -H 'X-License-Key: HWT-XXXX' http://localhost:5000/api/protected")
    app.run(host="0.0.0.0", port=5000, debug=True)
