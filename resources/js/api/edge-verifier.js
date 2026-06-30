import request from '@/utils/request';

const BASE = '/admin/edge';

export default {
    // 仪表盘
    getDashboard() {
        return request.get(`${BASE}/dashboard`);
    },
    getDeploymentGuide() {
        return request.get(`${BASE}/deployment-guide`);
    },

    // Token 管理
    generateToken(licenseKey, ttl) {
        return request.post(`${BASE}/generate-token`, { license_key: licenseKey, ttl });
    },
    batchGenerateTokens(licenseKeys) {
        return request.post(`${BASE}/batch-generate`, { license_keys: licenseKeys });
    },
    getTokenInfo(token) {
        return request.post(`${BASE}/token-info`, { token });
    },

    // 验证
    verifyToken(token) {
        return request.post(`${BASE}/verify`, { token });
    },

    // 吊销
    revokeLicense(licenseKey) {
        return request.post(`${BASE}/revoke`, { license_key: licenseKey });
    },
    syncRevocationList() {
        return request.post(`${BASE}/sync-revocation`);
    },
};
