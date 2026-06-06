import apiClient from './client';

export default {
    send(data) {
        return apiClient.post('/chat/send', data);
    },
    sendStream(data) {
        return apiClient.post('/chat/stream', data, { responseType: 'stream' });
    },
    history(params) {
        return apiClient.get('/chat/history', { params });
    },
    feedback(data) {
        return apiClient.post('/chat/feedback', data);
    },
    intents() {
        return apiClient.get('/chat/intents');
    },
    stats() {
        return apiClient.get('/chat/stats');
    },
};
