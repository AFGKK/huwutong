import apiClient from './client';

export default {
    eventTypes() {
        return apiClient.get('/webhook-simulator/event-types');
    },
    eventInfo(eventType) {
        return apiClient.get(`/webhook-simulator/event-info/${eventType}`);
    },
    endpoints() {
        return apiClient.get('/webhook-simulator/endpoints');
    },
    simulate(data) {
        return apiClient.post('/webhook-simulator/simulate', data);
    },
    history(params) {
        return apiClient.get('/webhook-simulator/history', { params });
    },
};
