import client from './client';

export default {
    // 列表
    list(params) {
        return client.get('/admin/order-after-sales/tickets', { params });
    },
    // 详情
    detail(ticketId) {
        return client.get(`/admin/order-after-sales/tickets/${ticketId}`);
    },
    // 创建工单
    createTicket(data) {
        return client.post('/admin/order-after-sales/tickets', data);
    },
    // 获取订单关联工单
    getOrderTickets(orderId) {
        return client.get(`/admin/order-after-sales/orders/${orderId}/tickets`);
    },
    // 回复
    reply(ticketId, data) {
        return client.post(`/admin/order-after-sales/tickets/${ticketId}/reply`, data);
    },
    // 解决
    resolve(ticketId) {
        return client.post(`/admin/order-after-sales/tickets/${ticketId}/resolve`);
    },
    // 关闭
    close(ticketId) {
        return client.post(`/admin/order-after-sales/tickets/${ticketId}/close`);
    },
    // 分配
    assign(ticketId, userId) {
        return client.post(`/admin/order-after-sales/tickets/${ticketId}/assign`, { user_id: userId });
    },
    // 满意度评价
    satisfaction(ticketId, data) {
        return client.post(`/admin/order-after-sales/tickets/${ticketId}/satisfaction`, data);
    },
    // 原因列表
    getReasons() {
        return client.get('/admin/order-after-sales/reasons');
    },
    // 统计
    getStats() {
        return client.get('/admin/order-after-sales/stats');
    },
};
