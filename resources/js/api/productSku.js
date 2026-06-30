/**
 * SKU 商品规格管理 API
 * M1.1-24 🛒 商品扩展表
 */

import apiClient from './client';

/** SKU 仪表盘 */
export function getSkuDashboard() {
    return apiClient.get('/admin/product-skus/dashboard');
}

/** SKU 列表 */
export function getSkus(params) {
    return apiClient.get('/admin/product-skus', { params });
}

/** SKU 详情 */
export function getSkuDetail(id) {
    return apiClient.get(`/admin/product-skus/${id}`);
}

/** 创建 SKU */
export function createSku(data) {
    return apiClient.post('/admin/product-skus', data);
}

/** 更新 SKU */
export function updateSku(id, data) {
    return apiClient.put(`/admin/product-skus/${id}`, data);
}

/** 删除 SKU */
export function deleteSku(id) {
    return apiClient.delete(`/admin/product-skus/${id}`);
}

/** 切换上下架 */
export function toggleSku(id) {
    return apiClient.post(`/admin/product-skus/${id}/toggle`);
}

/** 克隆 SKU */
export function cloneSku(id) {
    return apiClient.post(`/admin/product-skus/${id}/clone`);
}

/** 调整库存（含日志） */
export function adjustStock(id, change, reason) {
    return apiClient.post(`/admin/product-skus/${id}/adjust-stock`, { change, reason });
}

/** 库存变更日志 */
export function getStockLogs(id, params) {
    return apiClient.get(`/admin/product-skus/${id}/stock-logs`, { params });
}

/** 获取多币种定价 */
export function getCurrencyPrices(id) {
    return apiClient.get(`/admin/product-skus/${id}/currency-prices`);
}

/** 保存多币种定价 */
export function saveCurrencyPrices(id, prices) {
    return apiClient.post(`/admin/product-skus/${id}/currency-prices`, { prices });
}

/** 批量操作 */
export function batchActionSku(action, ids, extra = {}) {
    return apiClient.post('/admin/product-skus/batch-action', { action, ids, ...extra });
}

/** 批量更新库存 */
export function batchUpdateSkuStock(items) {
    return apiClient.post('/admin/product-skus/batch-stock', { items });
}

/** 上传交付物文件 */
export function uploadDeliverable(file, onProgress) {
    const formData = new FormData();
    formData.append('file', file);
    return apiClient.post('/admin/product-skus/upload-deliverable', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: onProgress,
    });
}

/** 上传 SKU 图片 */
export function uploadSkuImage(file) {
    const formData = new FormData();
    formData.append('file', file);
    return apiClient.post('/admin/product-skus/upload-image', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
}

/** 导出 CSV */
export function exportSkuCsv() {
    return apiClient.get('/admin/product-skus/export/csv', { responseType: 'blob' });
}

/** 导入 CSV */
export function importSkuCsv(csvContent) {
    return apiClient.post('/admin/product-skus/import/csv', { csv: csvContent });
}

/** 低库存列表 */
export function getLowStockSkus() {
    return apiClient.get('/admin/product-skus/low-stock');
}
