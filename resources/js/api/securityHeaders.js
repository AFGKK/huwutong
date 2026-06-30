import request from '@/utils/request';

export default {
    getConfig() {
        return request.get('/security/headers');
    },
    updateConfig(data) {
        return request.put('/security/headers', data);
    },
    reset() {
        return request.post('/security/headers/reset');
    },
    preview() {
        return request.get('/security/headers/preview');
    },
};
