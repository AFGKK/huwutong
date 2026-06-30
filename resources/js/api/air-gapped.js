import request from '@/utils/request';

const BASE = '/admin/air-gapped';

export default {
    // 状态与指标
    getStatus() {
        return request.get(`${BASE}/status`);
    },
    getMetrics() {
        return request.get(`${BASE}/metrics`);
    },
    healthCheck() {
        return request.get(`${BASE}/health`);
    },
    getDockerInfo() {
        return request.get(`${BASE}/docker`);
    },

    // License 管理
    listLicenses() {
        return request.get(`${BASE}/licenses`);
    },
    scanUsb() {
        return request.post(`${BASE}/licenses/scan-usb`);
    },
    importLicense(filePath, validate = true) {
        return request.post(`${BASE}/licenses/import`, { file_path: filePath, validate });
    },
    uploadLicense(file) {
        const formData = new FormData();
        formData.append('license_file', file);
        return request.post(`${BASE}/licenses/upload`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
    },

    // 更新管理
    listUpdates() {
        return request.get(`${BASE}/updates`);
    },
    applyUpdate(packageName) {
        return request.post(`${BASE}/updates/apply`, { package: packageName });
    },
    uploadUpdate(file) {
        const formData = new FormData();
        formData.append('update_package', file);
        return request.post(`${BASE}/updates/upload`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
            timeout: 300000, // 5min for large uploads
        });
    },
};
