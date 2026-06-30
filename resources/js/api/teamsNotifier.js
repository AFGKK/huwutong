import request from '@/utils/request';

const teamsNotifier = {
    dashboard() {
        return request({ url: '/admin/teams-notifier/dashboard', method: 'get' });
    },
    list() {
        return request({ url: '/admin/teams-notifier', method: 'get' });
    },
    create(data) {
        return request({ url: '/admin/teams-notifier', method: 'post', data });
    },
    update(id, data) {
        return request({ url: `/admin/teams-notifier/${id}`, method: 'put', data });
    },
    destroy(id) {
        return request({ url: `/admin/teams-notifier/${id}`, method: 'delete' });
    },
    test(id) {
        return request({ url: `/admin/teams-notifier/${id}/test`, method: 'post' });
    },
    sendTest(id) {
        return request({ url: `/admin/teams-notifier/${id}/send-test`, method: 'post' });
    },
    sendActivation(data) {
        return request({ url: '/admin/teams-notifier/send-activation', method: 'post', data });
    },
    sendAlert(data) {
        return request({ url: '/admin/teams-notifier/send-alert', method: 'post', data });
    },
    logs(params) {
        return request({ url: '/admin/teams-notifier/logs/list', method: 'get', params });
    },
    config() {
        return request({ url: '/admin/teams-notifier/config/options', method: 'get' });
    },
};

export default teamsNotifier;
