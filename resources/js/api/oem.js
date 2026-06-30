import request from '@/utils/request';

export function getOemDashboard() {
    return request.get('/admin/oem/dashboard');
}

export function getOemTiers() {
    return request.get('/admin/oem/tiers');
}

export function subscribeOem(data) {
    return request.post('/admin/oem/subscribe', data);
}

export function cancelOem(data) {
    return request.post('/admin/oem/cancel', data || {});
}

export function getOemHistory() {
    return request.get('/admin/oem/history');
}

export function checkOemFeature(feature) {
    return request.get('/admin/oem/check-feature', { params: { feature } });
}

export function saveBrandedLogin(data) {
    return request.put('/admin/oem/branded-login', data);
}
