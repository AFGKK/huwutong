import request from '@/utils/request';

export function generateRecommendations(customerId) {
    return request.post('/admin/cross-sell/generate', { customer_id: customerId });
}
export function getRecommendations(params) {
    return request.get('/admin/cross-sell/recommendations', { params });
}
export function recordRecommendationEvent(id, eventType, data) {
    return request.post(`/admin/cross-sell/recommendations/${id}/event`, { event_type: eventType, event_data: data });
}
export function getCrossSellDashboard() {
    return request.get('/admin/cross-sell/dashboard');
}
export function getRecommendationDetail(id) {
    return request.get(`/admin/cross-sell/recommendations/${id}`);
}
