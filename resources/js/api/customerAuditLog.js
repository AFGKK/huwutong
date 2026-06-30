import client from './client';

/**
 * M2-130 客户侧审计日志 API
 */
export default {
    /**
     * 获取审计日志列表
     * @param {Object} params - 查询参数
     */
    list(params = {}) {
        return client.get('/customer/audit-logs', { params });
    },

    /**
     * 获取审计日志详情
     * @param {number} id
     */
    detail(id) {
        return client.get(`/customer/audit-logs/${id}`);
    },

    /**
     * 获取审计日志统计
     */
    stats() {
        return client.get('/customer/audit-logs/stats');
    },

    /**
     * 获取操作分类（前端筛选下拉使用）
     */
    actionCategories() {
        return client.get('/customer/audit-logs/action-categories');
    },

    /**
     * 导出审计日志 CSV
     * @param {Object} params - 筛选参数
     * @returns {Promise<Blob>}
     */
    exportCsv(params = {}) {
        return client.get('/customer/audit-logs/export', {
            params,
            responseType: 'blob',
        }).then(response => response.data);
    },
};
