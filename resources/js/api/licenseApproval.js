import request from '@/utils/request';

const licenseApproval = {
    list(params) {
        return request({ url: '/licenses/approvals', method: 'get', params });
    },
    dashboard() {
        return request({ url: '/licenses/approvals/dashboard', method: 'get' });
    },
    create(data) {
        return request({ url: '/licenses/approvals', method: 'post', data });
    },
    show(id) {
        return request({ url: `/licenses/approvals/${id}`, method: 'get' });
    },
    approve(id) {
        return request({ url: `/licenses/approvals/${id}/approve`, method: 'post' });
    },
    reject(id, reason) {
        return request({ url: `/licenses/approvals/${id}/reject`, method: 'post', data: { reason } });
    },
    cancel(id) {
        return request({ url: `/licenses/approvals/${id}/cancel`, method: 'post' });
    },
    check(action) {
        return request({ url: '/licenses/approvals/check/requires', method: 'get', params: { action } });
    },
};

export default licenseApproval;
