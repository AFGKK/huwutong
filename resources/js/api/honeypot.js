import request from '@/utils/request';

export function getHoneypotDashboard() {
    return request.get('/honeypot/dashboard');
}

export function getHoneypotList(params) {
    return request.get('/honeypot', { params });
}

export function getHoneypotDetail(id) {
    return request.get(`/honeypot/${id}`);
}

export function createHoneypot(data) {
    return request.post('/honeypot', data);
}

export function disableHoneypot(id) {
    return request.post(`/honeypot/${id}/disable`);
}

export function reactivateHoneypot(id) {
    return request.post(`/honeypot/${id}/reactivate`);
}

export function deleteHoneypot(id) {
    return request.delete(`/honeypot/${id}`);
}
