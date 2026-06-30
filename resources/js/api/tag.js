import apiClient from './client';

export default {
    list(params) {
        return apiClient.get('/tags', { params });
    },
    grouped() {
        return apiClient.get('/tags/grouped');
    },
    show(id) {
        return apiClient.get(`/tags/${id}`);
    },
    create(data) {
        return apiClient.post('/tags', data);
    },
    update(id, data) {
        return apiClient.put(`/tags/${id}`, data);
    },
    destroy(id) {
        return apiClient.delete(`/tags/${id}`);
    },
    sync(taggableType, taggableId, tags, group) {
        return apiClient.post('/tags/sync', {
            taggable_type: taggableType,
            taggable_id: taggableId,
            tags,
            group,
        });
    },
    attach(taggableType, taggableId, tag) {
        return apiClient.post('/tags/attach', {
            taggable_type: taggableType,
            taggable_id: taggableId,
            tag,
        });
    },
    detach(taggableType, taggableId, tag) {
        return apiClient.post('/tags/detach', {
            taggable_type: taggableType,
            taggable_id: taggableId,
            tag,
        });
    },
};
