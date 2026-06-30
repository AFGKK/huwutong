import request from '@/utils/request';

export default {
    getConfig() {
        return request.get('/email-whitelabel');
    },
    updateConfig(data) {
        return request.put('/email-whitelabel', data);
    },
    getDnsGuide() {
        return request.get('/email-whitelabel/dns-guide');
    },
    verify() {
        return request.post('/email-whitelabel/verify');
    },
};
