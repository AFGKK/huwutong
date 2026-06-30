/**
 * 客户 API Key 管理 API (M2-96)
 */

import request from '@/utils/request';

// ── 客户侧 ──

/** 仪表盘 */
export function getDashboard() {
    return request.get('/customer-api-keys/dashboard');
}

/** 我的 API Key 列表 */
export function getMyKeys(params) {
    return request.get('/customer-api-keys', { params });
}

/** 创建 API Key */
export function createKey(data) {
    return request.post('/customer-api-keys', data);
}

/** 更新 API Key */
export function updateKey(id, data) {
    return request.put(`/customer-api-keys/${id}`, data);
}

/** 删除 API Key */
export function deleteKey(id) {
    return request.delete(`/customer-api-keys/${id}`);
}

/** 切换启用/禁用 */
export function toggleKey(id) {
    return request.post(`/customer-api-keys/${id}/toggle`);
}

/** 获取可用权限列表 */
export function getAbilities() {
    return request.get('/customer-api-keys/abilities');
}

// ── 管理端 ──

/** 管理端仪表盘 */
export function getAdminDashboard() {
    return request.get('/admin/customer-api-keys/dashboard');
}

/** 管理端列表 */
export function getAdminKeys(params) {
    return request.get('/admin/customer-api-keys', { params });
}

/** 管理端删除 */
export function adminDeleteKey(id) {
    return request.delete(`/admin/customer-api-keys/${id}`);
}

/** 管理端切换状态 */
export function adminToggleKey(id) {
    return request.post(`/admin/customer-api-keys/${id}/toggle`);
}
