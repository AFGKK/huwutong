import request from '@/utils/request';

const scheduledNotification = {
    dashboard(params) {
        return request({ url: '/admin/scheduled-notification/dashboard', method: 'get', params });
    },
    list(params) {
        return request({ url: '/admin/scheduled-notification', method: 'get', params });
    },
    detail(id) {
        return request({ url: `/admin/scheduled-notification/${id}`, method: 'get' });
    },
    create(data) {
        return request({ url: '/admin/scheduled-notification', method: 'post', data });
    },
    update(id, data) {
        return request({ url: `/admin/scheduled-notification/${id}`, method: 'put', data });
    },
    destroy(id) {
        return request({ url: `/admin/scheduled-notification/${id}`, method: 'delete' });
    },
    send(id) {
        return request({ url: `/admin/scheduled-notification/${id}/send`, method: 'post' });
    },
    cancel(id) {
        return request({ url: `/admin/scheduled-notification/${id}/cancel`, method: 'post' });
    },
    preview(id) {
        return request({ url: `/admin/scheduled-notification/${id}/preview`, method: 'get' });
    },
    deliveryLogs(id, params) {
        return request({ url: `/admin/scheduled-notification/${id}/delivery-logs`, method: 'get', params });
    },
    countRecipients(id) {
        return request({ url: `/admin/scheduled-notification/${id}/count-recipients`, method: 'get' });
    },
    options() {
        return request({ url: '/admin/scheduled-notification/options/list', method: 'get' });
    },
};

export default scheduledNotification;
