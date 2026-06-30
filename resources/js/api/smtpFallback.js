import request from '@/utils/request';

export default {
    getConfig() {
        return request.get('/smtp-fallback');
    },
    updateConfig(data) {
        return request.put('/smtp-fallback', data);
    },
    test() {
        return request.post('/smtp-fallback/test');
    },
};
