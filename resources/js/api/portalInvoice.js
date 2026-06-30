import apiClient from './client';

/**
 * 客户门户自助发票 API
 */
export default {
    // 发票统计
    stats() {
        return apiClient.get('/auto-invoice/stats');
    },
    // 发票列表
    list(params) {
        return apiClient.get('/auto-invoice', { params });
    },
    // 发票详情
    show(id) {
        return apiClient.get(`/auto-invoice/${id}`);
    },
    // 发票预览 (HTML)
    preview(id) {
        return apiClient.get(`/auto-invoice/${id}/preview`, { responseType: 'text' });
    },
    // 从订单生成发票
    generate(orderId, invoiceTitleId) {
        return apiClient.post(`/auto-invoice/${orderId}/generate`, { invoice_title_id: invoiceTitleId });
    },
    // 重发发票邮件
    resend(invoiceId) {
        return apiClient.post(`/auto-invoice/${invoiceId}/resend`);
    },
    // 发票抬头列表
    titles() {
        return apiClient.get('/auto-invoice/titles/list');
    },
    // 创建发票抬头
    createTitle(data) {
        return apiClient.post('/auto-invoice/titles', data);
    },
    // 更新发票抬头
    updateTitle(id, data) {
        return apiClient.put(`/auto-invoice/titles/${id}`, data);
    },
    // 删除发票抬头
    deleteTitle(id) {
        return apiClient.delete(`/auto-invoice/titles/${id}`);
    },
};
