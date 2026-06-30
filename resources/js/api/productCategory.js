import request from '@/utils/request';

export default {
    list(params) {
        return request.get('/product-categories', { params });
    },
    tree(params) {
        return request.get('/product-categories/tree', { params });
    },
    options(params) {
        return request.get('/product-categories/options', { params });
    },
    get(id) {
        return request.get(`/product-categories/${id}`);
    },
    create(data) {
        return request.post('/product-categories', data);
    },
    update(id, data) {
        return request.put(`/product-categories/${id}`, data);
    },
    delete(id) {
        return request.delete(`/product-categories/${id}`);
    },
    reorder(orders) {
        return request.post('/product-categories/reorder', { orders });
    },
    products(id, params) {
        return request.get(`/product-categories/${id}/products`, { params });
    },
    // 🆕 批量操作
    batchToggle(ids, isActive) {
        return request.post('/product-categories/batch/toggle', { ids, is_active: isActive });
    },
    batchDelete(ids) {
        return request.post('/product-categories/batch/delete', { ids });
    },
    // 🆕 移动/路径/统计/合并
    move(id, data) {
        return request.put(`/product-categories/${id}/move`, data);
    },
    getPath(id) {
        return request.get(`/product-categories/${id}/path`);
    },
    stats() {
        return request.get('/product-categories/stats/data');
    },
    merge(sourceId, targetId) {
        return request.post('/product-categories/merge', { source_id: sourceId, target_id: targetId });
    },
    // 🆕 导入导出
    exportCsv() {
        return request.get('/product-categories/export/csv', { responseType: 'blob' });
    },
    importCsv(csvContent) {
        return request.post('/product-categories/import/csv', { csv: csvContent });
    },
};
