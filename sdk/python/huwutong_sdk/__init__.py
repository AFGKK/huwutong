"""
HWT License SDK for Python
============================
M2-18: Python SDK 基于统一错误码标准 M2-34

Usage:
    from huwutong_sdk import HWTClient

    client = HWTClient(
        api_key="your_api_key",
        host="https://api.huwutong.com"
    )

    # Activate license
    result = client.activate("LICENSE-KEY-HERE", {"machine_id": "abc123"})
    print(result)

    # Validate license
    result = client.validate("LICENSE-KEY-HERE", {"machine_id": "abc123"})
    print(result.is_valid)

    # Deactivate license
    result = client.deactivate("LICENSE-KEY-HERE", {"machine_id": "abc123"})
"""

__version__ = "1.0.0"

import hashlib
import hmac
import json
import time
from dataclasses import dataclass
from typing import Any, Dict, List, Optional
from urllib.parse import urljoin

import requests


class HWTApiError(Exception):
    """HWT API 异常 (M2-34 标准)"""

    def __init__(self, code: str, message: str, status_code: int = 400, details: Optional[Dict] = None):
        self.code = code
        self.message = message
        self.status_code = status_code
        self.details = details or {}
        super().__init__(f"[{code}] {message}")


class HWTNetworkError(Exception):
    """网络异常"""
    pass


@dataclass
class ActivationResult:
    """激活结果"""
    success: bool
    license_key: str
    expires_at: Optional[str] = None
    features: Optional[List[str]] = None
    message: Optional[str] = None
    raw: Optional[Dict] = None


@dataclass
class ValidationResult:
    """验证结果"""
    is_valid: bool
    license_key: str
    status: Optional[str] = None
    expires_at: Optional[str] = None
    machine_id: Optional[str] = None
    fingerprint_hash: Optional[str] = None
    features: Optional[List[str]] = None
    message: Optional[str] = None
    raw: Optional[Dict] = None


class HWTClient:
    """HWT License 客户端"""

    def __init__(
        self,
        api_key: str,
        host: str = "https://api.huwutong.com",
        timeout: int = 10,
        verify_ssl: bool = True,
        user_agent: Optional[str] = None,
    ):
        self.api_key = api_key
        self.host = host.rstrip("/")
        self.timeout = timeout
        self.verify_ssl = verify_ssl
        self.user_agent = user_agent or f"HWT-SDK-Python/{__version__}"

        self._session = requests.Session()
        self._session.headers.update({
            "Authorization": f"Bearer {self.api_key}",
            "User-Agent": self.user_agent,
            "Content-Type": "application/json",
            "Accept": "application/json",
        })

    def activate(self, license_key: str, machine_info: Optional[Dict] = None, metadata: Optional[Dict] = None) -> ActivationResult:
        """激活 License"""
        payload = {
            "license_key": license_key,
            "machine_info": machine_info or {},
            "metadata": metadata or {},
            "timestamp": int(time.time()),
            "nonce": self._generate_nonce(),
        }
        payload["signature"] = self._sign(payload)

        data = self._post("/api/license/activate", payload)
        return ActivationResult(
            success=True,
            license_key=license_key,
            expires_at=data.get("expires_at"),
            features=data.get("features"),
            message=data.get("message"),
            raw=data,
        )

    def validate(self, license_key: str, machine_info: Optional[Dict] = None) -> ValidationResult:
        """验证 License"""
        payload = {
            "license_key": license_key,
            "machine_info": machine_info or {},
            "timestamp": int(time.time()),
            "nonce": self._generate_nonce(),
        }
        payload["signature"] = self._sign(payload)

        data = self._post("/api/license/validate", payload)
        return ValidationResult(
            is_valid=data.get("valid", False),
            license_key=license_key,
            status=data.get("status"),
            expires_at=data.get("expires_at"),
            machine_id=data.get("machine_id"),
            fingerprint_hash=data.get("fingerprint_hash"),
            features=data.get("features"),
            message=data.get("message"),
            raw=data,
        )

    def deactivate(self, license_key: str, machine_info: Optional[Dict] = None) -> Dict:
        """解除激活"""
        payload = {
            "license_key": license_key,
            "machine_info": machine_info or {},
            "timestamp": int(time.time()),
            "nonce": self._generate_nonce(),
        }
        payload["signature"] = self._sign(payload)
        return self._post("/api/license/deactivate", payload)

    def get_license_info(self, license_key: str) -> Dict:
        """查询 License 信息"""
        return self._get(f"/api/license/info/{license_key}")

    def get_offline_license(self, license_key: str) -> Dict:
        """获取离线 License 文件"""
        return self._get(f"/api/offline/generate?license_key={license_key}")

    def verify_offline(self, license_data: str, public_key: Optional[str] = None) -> Dict:
        """离线验证"""
        return self._post("/api/offline/verify", {
            "license_data": license_data,
            "public_key": public_key or "",
        })

    def check_feature(self, license_key: str, feature_key: str) -> bool:
        """检查 Feature Flag"""
        data = self._post("/api/license/check-feature", {
            "license_key": license_key,
            "feature_key": feature_key,
        })
        return data.get("enabled", False)

    # ─── 内部方法 ───

    def _post(self, path: str, payload: Dict) -> Dict:
        url = urljoin(self.host, path)
        try:
            resp = self._session.post(url, json=payload, timeout=self.timeout, verify=self.verify_ssl)
        except requests.RequestException as e:
            raise HWTNetworkError(f"Network error: {e}")

        return self._handle_response(resp)

    def _get(self, path: str) -> Dict:
        url = urljoin(self.host, path)
        try:
            resp = self._session.get(url, timeout=self.timeout, verify=self.verify_ssl)
        except requests.RequestException as e:
            raise HWTNetworkError(f"Network error: {e}")

        return self._handle_response(resp)

    def _handle_response(self, resp) -> Dict:
        try:
            data = resp.json()
        except ValueError:
            raise HWTApiError("SYS_PARSE_ERROR", "Invalid JSON response", resp.status_code)

        if not resp.ok:
            code = data.get("error", {}).get("code", "SYS_ERROR")
            message = data.get("error", {}).get("message", data.get("message", "Unknown error"))
            raise HWTApiError(code, message, resp.status_code, data.get("error", {}))

        # 标准响应格式
        if "data" in data:
            return data["data"]
        return data

    def _generate_nonce(self) -> str:
        return hashlib.md5(f"{time.time()}{__version__}".encode()).hexdigest()[:16]

    def _sign(self, payload: Dict) -> str:
        sorted_keys = sorted(payload.keys())
        msg = "&".join(f"{k}={payload[k]}" for k in sorted_keys if k != "signature")
        return hmac.new(self.api_key.encode(), msg.encode(), hashlib.sha256).hexdigest()
