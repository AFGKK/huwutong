import request from '@/utils/request';

export function runClustering() {
    return request.post('/admin/customer-clustering/run');
}
export function getClusteringDashboard() {
    return request.get('/admin/customer-clustering/dashboard');
}
export function getSegmentCustomers(segmentKey, params) {
    return request.get(`/admin/customer-clustering/segments/${segmentKey}/customers`, { params });
}
export function getCustomerCluster(id) {
    return request.get(`/admin/customer-clustering/customers/${id}/cluster`);
}
export function getClusteringHistory(params) {
    return request.get('/admin/customer-clustering/history', { params });
}
