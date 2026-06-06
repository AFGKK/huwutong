import apiClient from './client';

export default {
    list(params) {
        return apiClient.get('/webhook-replay/events', { params });
    },
    show(id) {
        return apiClient.get(`/webhook-replay/events/${id}`);
    },
    replay(id) {
        return apiClient.post(`/webhook-replay/events/${id}/replay`);
    },
    batchReplay(eventIds) {
        return apiClient.post('/webhook-replay/batch-replay', { event_ids: eventIds });
    },
    replayEndpoint(endpointId) {
        return apiClient.post(`/webhook-replay/endpoints/${endpointId}/replay-all`);
    },
    stats() {
        return apiClient.get('/webhook-replay/stats');
    },
};
