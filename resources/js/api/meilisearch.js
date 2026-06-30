import client from './client';

export default {
    health() { return client.get('/meilisearch/health'); },
    indexes() { return client.get('/meilisearch/indexes'); },
    setupIndex(index) { return client.post('/meilisearch/indexes/setup', { index }); },
    deleteIndex(uid) { return client.delete('/meilisearch/indexes', { params: { uid } }); },
    sync(type) { return client.post('/meilisearch/sync', { type: type || 'all' }); },
    search(params) { return client.get('/meilisearch/search', { params }); },
    clear(uid) { return client.post('/meilisearch/clear', { uid }); },
    stats() { return client.get('/meilisearch/stats'); },
};
