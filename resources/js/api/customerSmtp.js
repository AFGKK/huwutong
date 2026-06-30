import request from '@/utils/request';

const customerSmtp = {
    dashboard() {
        return request({ url: '/admin/customer-smtp/dashboard', method: 'get' });
    },
    list() {
        return request({ url: '/admin/customer-smtp', method: 'get' });
    },
    create(data) {
        return request({ url: '/admin/customer-smtp', method: 'post', data });
    },
    update(id, data) {
        return request({ url: `/admin/customer-smtp/${id}`, method: 'put', data });
    },
    destroy(id) {
        return request({ url: `/admin/customer-smtp/${id}`, method: 'delete' });
    },
    test(id) {
        return request({ url: `/admin/customer-smtp/${id}/test`, method: 'post' });
    },
    setPrimary(id) {
        return request({ url: `/admin/customer-smtp/${id}/set-primary`, method: 'post' });
    },
    sendTest(id, data) {
        return request({ url: `/admin/customer-smtp/${id}/send-test`, method: 'post', data });
    },
    logs(params) {
        return request({ url: '/admin/customer-smtp/logs/list', method: 'get', params });
    },
    recover() {
        return request({ url: '/admin/customer-smtp/recover', method: 'post' });
    },
    providers() {
        return request({ url: '/admin/customer-smtp/providers/list', method: 'get' });
    },
};

export default customerSmtp;
