import client from './client';

export default {
  list(params = {}) {
    return client.get('/rate-limits/rules', { params });
  },
  create(data) {
    return client.post('/rate-limits/rules', data);
  },
  update(id, data) {
    return client.put(`/rate-limits/rules/${id}`, data);
  },
  destroy(id) {
    return client.delete(`/rate-limits/rules/${id}`);
  },
  stats(params = {}) {
    return client.get('/rate-limits/stats', { params });
  },
  keyTypes() {
    return client.get('/rate-limits/key-types');
  },
};
