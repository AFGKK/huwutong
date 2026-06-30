import request from '@/utils/request';

// IP 范围限制
export function getIpRestriction(licenseId) {
    return request.get(`/admin/licenses/${licenseId}/restrictions/ip`);
}

export function saveIpRestriction(licenseId, data) {
    return request.post(`/admin/licenses/${licenseId}/restrictions/ip`, data);
}

export function deleteIpRestriction(licenseId) {
    return request.delete(`/admin/licenses/${licenseId}/restrictions/ip`);
}

// 地理围栏
export function getGeoFence(licenseId) {
    return request.get(`/admin/licenses/${licenseId}/restrictions/geo`);
}

export function saveGeoFence(licenseId, data) {
    return request.post(`/admin/licenses/${licenseId}/restrictions/geo`, data);
}

export function deleteGeoFence(licenseId) {
    return request.delete(`/admin/licenses/${licenseId}/restrictions/geo`);
}

// 通用
export function testIpCheck(licenseId, ip) {
    return request.post('/admin/license-restrictions/test-ip', { license_id: licenseId, ip });
}

export function testGeoCheck(licenseId, ip) {
    return request.post('/admin/license-restrictions/test-geo', { license_id: licenseId, ip });
}

export function getCountries() {
    return request.get('/admin/license-restrictions/countries');
}

export function getRestrictionLogs(params) {
    return request.get('/admin/license-restrictions/logs', { params });
}
