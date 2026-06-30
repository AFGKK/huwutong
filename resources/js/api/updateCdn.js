/**
 * 更新包 CDN 分发 API (M2-69)
 */

import request from '@/utils/request';

export function getCdnDashboard() {
    return request.get('/admin/update-cdn/dashboard');
}

export function getCdnConfig() {
    return request.get('/admin/update-cdn/config');
}

export function getBandwidthStats() {
    return request.get('/admin/update-cdn/bandwidth');
}

export function getDownloadLogs(params) {
    return request.get('/admin/update-cdn/downloads', { params });
}

export function purgeCdnCache(data) {
    return request.post('/admin/update-cdn/purge', data);
}

export function publishAndPurge(packageId) {
    return request.post(`/admin/update-cdn/packages/${packageId}/publish-purge`);
}

export function getChunkInfo(packageId) {
    return request.get(`/admin/update-cdn/packages/${packageId}/chunks`);
}

export function getPackageUrls(packageId) {
    return request.get(`/admin/update-cdn/packages/${packageId}/urls`);
}
