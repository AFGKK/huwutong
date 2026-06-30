import request from '@/utils/request';

export function getBillingCycles() {
    return request.get('/admin/billing-cycles');
}

export function getBillingCycleOptions() {
    return request.get('/billing-cycles/options');
}

export function createBillingCycle(data) {
    return request.post('/admin/billing-cycles', data);
}

export function updateBillingCycle(id, data) {
    return request.put(`/admin/billing-cycles/${id}`, data);
}

export function deleteBillingCycle(id) {
    return request.delete(`/admin/billing-cycles/${id}`);
}
