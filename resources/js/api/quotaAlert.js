import request from '@/utils/request';

const quotaAlert = {
    dashboard(params) {
        return request({ url: '/admin/quota-alert/dashboard', method: 'get', params });
    },
    list(params) {
        return request({ url: '/admin/quota-alert', method: 'get', params });
    },
    detail(id) {
        return request({ url: `/admin/quota-alert/${id}`, method: 'get' });
    },
    updateLimit(id, data) {
        return request({ url: `/admin/quota-alert/${id}/limit`, method: 'put', data });
    },
    toggleNotifications(id) {
        return request({ url: `/admin/quota-alert/${id}/toggle-notifications`, method: 'post' });
    },
    logs(params) {
        return request({ url: '/admin/quota-alert/logs/list', method: 'get', params });
    },
    checkAll() {
        return request({ url: '/admin/quota-alert/check-all', method: 'post' });
    },
    config() {
        return request({ url: '/admin/quota-alert/config/options', method: 'get' });
    },
};

export default quotaAlert;
