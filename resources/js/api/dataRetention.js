/**
 * 数据留存策略 API
 * M1.1-14 数据留存策略配置表 + 审计日志归档方案
 */

import request from '@/utils/request';

export function getDataRetentionDashboard() {
  return request.get('/admin/data-retention/dashboard');
}

export function getDataRetentionPolicies() {
  return request.get('/admin/data-retention/policies');
}

export function updateDataRetentionPolicy(id, data) {
  return request.put(`/admin/data-retention/policies/${id}`, data);
}

export function syncDataRetentionPolicies() {
  return request.post('/admin/data-retention/policies/sync');
}

export function runDataRetentionCleanup(data) {
  return request.post('/admin/data-retention/cleanup', data);
}

export function getDataRetentionExecutions(params) {
  return request.get('/admin/data-retention/executions', { params });
}

export function getDataRetentionStorageStats() {
  return request.get('/admin/data-retention/storage-stats');
}
