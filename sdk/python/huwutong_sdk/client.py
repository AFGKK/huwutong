"""
HWT License Python SDK

基于统一错误码标准 M2-34，提供 License 激活/验证/设备绑定功能。

使用:
    from huwutong_sdk import Client
    client = Client('your_api_key', 'https://api.huwutong.com')
    result = client.activate('LICENSE-KEY', machine_info)

安装:
    pip install huwutong-sdk
"""

import time
from dataclasses import dataclass, field
from typing import Any, Optional
from urllib.parse import urljoin

import requests


__version__ = '1.0.0'


class ApiError(Exception):
    """API 错误 (M2-34 标准)"""
    def __init__(self, code: str, message: str, status_code: int = 400, details: dict = None):
        super().__init__(f'[{code}] {message}')
        self.error_code = code
        self.status_code = status_code
        self.details = details or {}


@dataclass
class ActivationResult:
    """激活结果"""
    success: bool = False
    license_key: str = ''
    expires_at: str = ''
    features: list = field(default_factory=list)
    message: str = ''


@dataclass
class ValidationResult:
    """验证结果"""
    is_valid: bool = False
    license_key: str = ''
    status: str = ''
    expires_at: str = ''
    machine_id: str = ''
    features: list = field(default_factory=list)
    message: str = ''


class Client:
    """HWT License 客户端"""

    DEFAULT_TIMEOUT = 10

    def __init__(
        self,
        api_key: str,
        host: str = 'https://api.huwutong.com',
        timeout: int = None,
    ):
        self.api_key = api_key
        self.host = host.rstrip('/')
        self.timeout = timeout or self.DEFAULT_TIMEOUT
        self._session = requests.Session()
        self._session.headers.update({
            'User-Agent': f'HWT-SDK-Python/{__version__}',
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        })

    def activate(self, license_key: str, machine_info: dict) -> ActivationResult:
        """激活 License"""
        data = self._call('POST', '/api/activate', {
            'license_key': license_key,
            'machine_info': machine_info,
        })
        return ActivationResult(
            success=data.get('success', False),
            license_key=license_key,
            expires_at=data.get('expires_at', ''),
            features=data.get('features', []),
            message=data.get('message', ''),
        )

    def validate(self, license_key: str, context: dict = None) -> ValidationResult:
        """验证 License"""
        data = self._call('POST', '/api/validate', {
            'license_key': license_key,
            'context': context or {},
        })
        return ValidationResult(
            is_valid=data.get('is_valid', False),
            license_key=license_key,
            status=data.get('status', ''),
            expires_at=data.get('expires_at', ''),
            machine_id=data.get('machine_id', ''),
            features=data.get('features', []),
            message=data.get('message', ''),
        )

    def deactivate(self, license_key: str, device_id: str = '') -> bool:
        """解除激活"""
        data = self._call('POST', '/api/deactivate', {
            'license_key': license_key,
            'device_id': device_id,
        })
        return data.get('success', False)

    def offline_verify(self, license_key: str, device_id: str) -> dict:
        """离线验证"""
        return self._call('POST', '/api/offline/verify', {
            'license_key': license_key,
            'device_id': device_id,
        })

    def check_feature(self, license_key: str, feature: str) -> bool:
        """检查 Feature 是否可用"""
        data = self._call('GET', '/api/check-feature', {
            'license_key': license_key,
            'feature': feature,
        })
        return data.get('available', False)

    def get_license(self, license_key: str) -> dict:
        """获取 License 信息"""
        return self._call('GET', f'/api/licenses/{self._escape(license_key)}')

    def _call(self, method: str, path: str, params: dict = None) -> dict:
        url = urljoin(self.host, path)
        headers = {'Authorization': f'Bearer {self.api_key}'}

        try:
            if method == 'GET':
                response = self._session.get(
                    url, params=params, headers=headers, timeout=self.timeout
                )
            else:
                response = self._session.post(
                    url, json=params, headers=headers, timeout=self.timeout
                )

            body = response.json()

            if not response.ok or body.get('error'):
                raise ApiError(
                    body.get('code', 'UNKNOWN'),
                    body.get('message', 'Unknown error'),
                    response.status_code,
                    body.get('details', {}),
                )

            return body.get('data', body)
        except requests.exceptions.RequestException as e:
            raise RuntimeError(f'Network error: {e}') from e

    @staticmethod
    def _escape(value: str) -> str:
        import urllib.parse
        return urllib.parse.quote(value, safe='')
