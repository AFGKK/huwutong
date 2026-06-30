import apiClient from './client';

export default {
  // 隔离仪表盘
  getDashboard() {
    return apiClient.get('/admin/tenant-isolation/dashboard');
  },

  // 配额方案 CRUD
  getQuotaPlans() {
    return apiClient.get('/admin/quota-plans');
  },
  createQuotaPlan(data) {
    return apiClient.post('/admin/quota-plans', data);
  },
  updateQuotaPlan(id, data) {
    return apiClient.put(`/admin/quota-plans/${id}`, data);
  },
  deleteQuotaPlan(id) {
    return apiClient.delete(`/admin/quota-plans/${id}`);
  },

  // 租户配额管理
  getTenantQuota(tenantId) {
    return apiClient.get(`/admin/tenants/${tenantId}/quota`);
  },
  updateTenantQuota(tenantId, data) {
    return apiClient.put(`/admin/tenants/${tenantId}/quota`, data);
  },
  refreshTenantUsage(tenantId) {
    return apiClient.post(`/admin/tenants/${tenantId}/refresh-usage`);
  },
  updateIsolationLevel(tenantId, data) {
    return apiClient.put(`/admin/tenants/${tenantId}/isolation-level`, data);
  },

  // 隔离审计日志
  getAuditLogs(tenantId, params = {}) {
    return apiClient.get(`/admin/tenants/${tenantId}/audit-logs`, { params });
  },
  resolveAuditLog(id) {
    return apiClient.post(`/admin/audit-logs/${id}/resolve`);
  },

  // 跨租户共享
  getShares(tenantId, direction = 'outgoing') {
    return apiClient.get(`/admin/tenants/${tenantId}/shares`, { params: { direction } });
  },
  createShare(data) {
    return apiClient.post('/admin/shares', data);
  },
  revokeShare(id) {
    return apiClient.post(`/admin/shares/${id}/revoke`);
  },

  // 批量操作
  batchRefresh() {
    return apiClient.post('/admin/tenants/batch-refresh-usage');
  },
};
