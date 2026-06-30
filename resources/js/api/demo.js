import apiClient from '@/api/client';

export default {
    start(sessionId) {
        return apiClient.post('/demo/start', { session_id: sessionId });
    },
    getData(type = 'all', token) {
        return apiClient.get('/demo/data', {
            params: { type, token },
        });
    },
    advanceStep(step, token) {
        return apiClient.post('/demo/step', { step, token });
    },
    recordAction(action, token) {
        return apiClient.post('/demo/action', { action, token });
    },
    heartbeat(token) {
        return apiClient.post('/demo/heartbeat', { token });
    },
    extend(minutes = 15, token) {
        return apiClient.post('/demo/extend', { minutes, token });
    },
    complete(token) {
        return apiClient.post('/demo/complete', { token });
    },
    register(data, token) {
        return apiClient.post('/demo/register', { ...data, token });
    },
};
