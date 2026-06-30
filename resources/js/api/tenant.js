import apiClient from './client';

export default {
    // 管理后台租户列表
    adminList(params) {
        return apiClient.get('/admin/tenants', { params });
    },
    adminShow(id) {
        return apiClient.get(`/admin/tenants/${id}`);
    },
    adminCreate(data) {
        return apiClient.post('/admin/tenants', data);
    },
    adminUpdate(id, data) {
        return apiClient.put(`/admin/tenants/${id}`, data);
    },
    adminDestroy(id) {
        return apiClient.delete(`/admin/tenants/${id}`);
    },
    adminToggleStatus(id) {
        return apiClient.post(`/admin/tenants/${id}/toggle-status`);
    },
    adminStats() {
        return apiClient.get('/admin/tenants/stats');
    },

    // 成员管理（暂通过用户管理模块处理）
    adminMembers(tenantId) {
        return apiClient.get('/admin/users', { params: { tenant_id: tenantId } });
    },
    adminAddMember(tenantId, data) {
        return apiClient.post(`/admin/tenants/${tenantId}/members`, data);
    },
    adminUpdateMemberRole(tenantId, memberId, data) {
        return apiClient.put(`/admin/tenants/${tenantId}/members/${memberId}`, data);
    },
    adminRemoveMember(tenantId, memberId) {
        return apiClient.delete(`/admin/tenants/${tenantId}/members/${memberId}`);
    },
};
