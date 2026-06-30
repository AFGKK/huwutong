import request from '@/utils/request';

export function getAttackDashboard() {
    return request.get('/admin/attack-detection/dashboard');
}
export function getAttackEvents(params) {
    return request.get('/admin/attack-detection/events', { params });
}
export function getAttackEventDetail(id) {
    return request.get(`/admin/attack-detection/events/${id}`);
}
export function updateAttackEventStatus(id, status) {
    return request.put(`/admin/attack-detection/events/${id}/status`, { status });
}
export function getIpBlocks(params) {
    return request.get('/admin/attack-detection/ip-blocks', { params });
}
export function blockIp(data) {
    return request.post('/admin/attack-detection/ip-blocks', data);
}
export function unblockIp(ip) {
    return request.delete(`/admin/attack-detection/ip-blocks/${ip}`);
}
export function analyzeAttack(data) {
    return request.post('/admin/attack-detection/analyze', data);
}
