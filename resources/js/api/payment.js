import request from '@/utils/request';

export function getPaymentStats() {
    return request.get('/payment/stats');
}

export function getTransactions(params) {
    return request.get('/payment/transactions', { params });
}

export function getTransactionDetail(id) {
    return request.get(`/payment/transactions/${id}`);
}

export function getGatewayConfig() {
    return request.get('/payment/gateway-config');
}

export function switchDriver(driver) {
    return request.post('/payment/switch-driver', { driver });
}

export function getWebhookLogs(params) {
    return request.get('/payment/webhook-logs', { params });
}

/**
 * 支付记录管理 API (M1.1-27)
 * payments 表的管理接口
 */

/** 支付仪表盘 */
export function getPaymentsDashboard() {
    return request.get('/admin/payments/dashboard');
}

/** 支付记录列表 */
export function getPaymentRecords(params) {
    return request.get('/admin/payments', { params });
}

/** 支付记录详情 */
export function getPaymentRecordDetail(id) {
    return request.get(`/admin/payments/${id}`);
}

/** 退款 */
export function refundPayment(id, data) {
    return request.post(`/admin/payments/${id}/refund`, data);
}

/** 支付趋势 */
export function getPaymentTrend(params) {
    return request.get('/admin/payments/trend/data', { params });
}
