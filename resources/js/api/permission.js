import apiClient from './client';

export default {
    // ── 角色 CRUD ──
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
    roleDuplicate(id, data) {
        return apiClient.post(`/roles/${id}/duplicate`, data);
    },

    // ── 角色层级 ──
    roleHierarchy() {
        return apiClient.get('/roles/hierarchy');
    },

    // ── 角色模板 ──
    roleTemplates(params) {
        return apiClient.get('/role-templates', { params });
    },
    templateStore(data) {
        return apiClient.post('/role-templates', data);
    },
    createRoleFromTemplate(templateId, data) {
        return apiClient.post(`/role-templates/${templateId}/create-role`, data);
    },
    templateDelete(id) {
        return apiClient.delete(`/role-templates/${id}`);
    },
    seedTemplates() {
        return apiClient.post('/role-templates/seed');
    },

    // ── 权限 ──
    allPermissions() {
        return apiClient.get('/permissions');
    },
    myPermissions() {
        return apiClient.get('/permissions/mine');
    },
    permissionCreate(data) {
        return apiClient.post('/permissions', data);
    },
    permissionBatchCreate(data) {
        return apiClient.post('/permissions/batch', data);
    },
    permissionDelete(id) {
        return apiClient.delete(`/permissions/${id}`);
    },

    // ── 用户角色分配 ──
    tenantUsers() {
        return apiClient.get('/users/with-roles');
    },
    userRoles(userId) {
        return apiClient.get(`/users/${userId}/roles`);
    },
    assignRoles(userId, roles) {
        return apiClient.post(`/users/${userId}/roles`, { roles });
    },

    // ── 用户直接权限 ──
    userDirectPermissions(userId) {
        return apiClient.get(`/users/${userId}/direct-permissions`);
    },
    assignUserDirectPermissions(userId, permissions) {
        return apiClient.put(`/users/${userId}/direct-permissions`, { permissions });
    },

    // ── 权限审计日志 ──
    auditLogs(params) {
        return apiClient.get('/permission-audit-logs', { params });
    },
    auditStats() {
        return apiClient.get('/permission-audit-logs/stats');
    },
};
