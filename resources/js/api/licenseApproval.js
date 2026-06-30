import request from '@/utils/request';

const licenseApproval = {
    list(params) {
        return request({ url: '/admin/licenses/approvals', method: 'get', params });
    },
    dashboard() {
        return request({ url: '/admin/licenses/approvals/dashboard', method: 'get' });
    },
    create(data) {
        return request({ url: '/admin/licenses/approvals', method: 'post', data });
    },
    show(id) {
        return request({ url: `/admin/licenses/approvals/${id}`, method: 'get' });
    },
    approve(id) {
        return request({ url: `/admin/licenses/approvals/${id}/approve`, method: 'post' });
    },
    reject(id, reason) {
        return request({ url: `/admin/licenses/approvals/${id}/reject`, method: 'post', data: { reason } });
    },
    cancel(id) {
        return request({ url: `/admin/licenses/approvals/${id}/cancel`, method: 'post' });
    },
    check(action) {
        return request({ url: '/admin/licenses/approvals/check/requires', method: 'get', params: { action } });
    },
};

export default licenseApproval;
