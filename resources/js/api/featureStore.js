import client from './client';

export default {
    // 仪表盘
    dashboard() {
        return client.get('/admin/feature-store/dashboard');
    },

    // 特征组管理
    listGroups(params = {}) {
        return client.get('/admin/feature-store/groups', { params });
    },
    getGroup(id) {
        return client.get(`/admin/feature-store/groups/${id}`);
    },
    createGroup(data) {
        return client.post('/admin/feature-store/groups', data);
    },
    updateGroup(id, data) {
        return client.put(`/admin/feature-store/groups/${id}`, data);
    },
    deleteGroup(id) {
        return client.delete(`/admin/feature-store/groups/${id}`);
    },

    // 特征定义
    listFeatures(groupId, params = {}) {
        return client.get(`/admin/feature-store/groups/${groupId}/features`, { params });
    },
    createFeature(groupId, data) {
        return client.post(`/admin/feature-store/groups/${groupId}/features`, data);
    },
    batchCreateFeatures(groupId, features) {
        return client.post(`/admin/feature-store/groups/${groupId}/features/batch`, { features });
    },
    updateFeature(featureId, data) {
        return client.put(`/admin/feature-store/features/${featureId}`, data);
    },
    deleteFeature(featureId) {
        return client.delete(`/admin/feature-store/features/${featureId}`);
    },

    // 在线特征值
    setValue(featureId, data) {
        return client.post(`/admin/feature-store/features/${featureId}/values`, data);
    },
    batchSetValues(featureId, values) {
        return client.post(`/admin/feature-store/features/${featureId}/values/batch`, { values });
    },
    getValue(featureId, entityId) {
        return client.get(`/admin/feature-store/features/${featureId}/values/${entityId}`);
    },
    getFeatureVector(data) {
        return client.post('/admin/feature-store/feature-vector', data);
    },

    // 离线同步
    syncOffline(featureId, entityId = null) {
        return client.post(`/admin/feature-store/features/${featureId}/sync-offline`, { entity_id: entityId });
    },
    syncAllOffline() {
        return client.post('/admin/feature-store/sync-all-offline');
    },
    getOfflineTrainingData(featureId, params) {
        return client.get(`/admin/feature-store/features/${featureId}/offline-training`, { params });
    },

    // 一致性检查
    checkConsistency(featureId, sampleSize = null) {
        return client.post(`/admin/feature-store/features/${featureId}/check-consistency`, { sample_size: sampleSize });
    },
    batchCheckConsistency() {
        return client.post('/admin/feature-store/batch-check-consistency');
    },
    consistencyHistory(params = {}) {
        return client.get('/admin/feature-store/consistency-history', { params });
    },
};
