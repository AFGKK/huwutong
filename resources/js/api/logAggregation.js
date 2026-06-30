import client from './client';

export default {
    getDashboard() {
        return client.get('/admin/log-aggregation/dashboard');
    },
    search(params = {}) {
        return client.get('/admin/log-aggregation/search', { params });
    },
    show(id) {
        return client.get(`/admin/log-aggregation/entries/${id}`);
    },
    getLevelStats(params = {}) {
        return client.get('/admin/log-aggregation/level-stats', { params });
    },
    getSlowQueries() {
        return client.get('/admin/log-aggregation/slow-queries');
    },
    getPathStats(params = {}) {
        return client.get('/admin/log-aggregation/path-stats', { params });
    },
    prune() {
        return client.post('/admin/log-aggregation/prune');
    },
    saveSearch(data) {
        return client.post('/admin/log-aggregation/saved-searches', data);
    },
    listSavedSearches() {
        return client.get('/admin/log-aggregation/saved-searches');
    },
    deleteSavedSearch(id) {
        return client.delete(`/admin/log-aggregation/saved-searches/${id}`);
    },
};
