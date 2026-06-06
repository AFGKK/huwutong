import apiClient from './client';

export default {
    // 角色
    roles(params) {
        return apiClient.get('/roles', { params });
    },
    roleShow(id) {
        return apiClient.get(`/roles/${id}`);
    },
    roleCreate(data) {
        return apiClient.post('/roles', data);
    },
    roleUpdate(id, data) {
        return apiClient.put(`/roles/${id}`, data);
    },
    roleDelete(id) {
        return apiClient.delete(`/roles/${id}`);
    },

    // 权限
    allPermissions() {
        return apiClient.get('/permissions');
    },
    myPermissions() {
        return apiClient.get('/permissions/mine');
    },

    // 用户角色管理
    tenantUsers() {
        return apiClient.get('/users/with-roles');
    },
    userRoles(userId) {
        return apiClient.get(`/users/${userId}/roles`);
    },
    assignRoles(userId, roles) {
        return apiClient.post(`/users/${userId}/roles`, { roles });
    },
};
