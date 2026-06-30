import client from './client';

export default {
    list(params = {}) {
        return client.get('/ssl-certificates', { params });
    },
    show(id) {
        return client.get(`/ssl-certificates/${id}`);
    },
    create(data) {
        return client.post('/ssl-certificates', data);
    },
    update(id, data) {
        return client.put(`/ssl-certificates/${id}`, data);
    },
    renew(id) {
        return client.post(`/ssl-certificates/${id}/renew`);
    },
    revoke(id, data = {}) {
        return client.post(`/ssl-certificates/${id}/revoke`, data);
    },
    stats() {
        return client.get('/ssl-certificates/stats');
    },
    certificateContent(id) {
        return client.get(`/ssl-certificates/${id}/content`);
    },
};
