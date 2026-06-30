import apiClient from './client'

// ─── 概览 ───
export function getSecurityDashboard() { return apiClient.get('/admin/security/dashboard') }
export function getSecurityScore() { return apiClient.get('/admin/security/security-score') }

// ─── IP 白名单 ───
export function getIpWhitelists(params) { return apiClient.get('/admin/security/ip-whitelists', { params }) }
export function createIpWhitelist(data) { return apiClient.post('/admin/security/ip-whitelists', data) }
export function updateIpWhitelist(id, data) { return apiClient.put(`/admin/security/ip-whitelists/${id}`, data) }
export function deleteIpWhitelist(id) { return apiClient.delete(`/admin/security/ip-whitelists/${id}`) }
export function bulkImportIps(data) { return apiClient.post('/admin/security/ip-whitelists/bulk-import', data) }

// ─── 登录策略 ───
export function getPolicies() { return apiClient.get('/admin/security/policies') }
export function updatePolicy(id, data) { return apiClient.put(`/admin/security/policies/${id}`, data) }

// ─── 会话管理 ───
export function getSessions(params) { return apiClient.get('/admin/security/sessions', { params }) }
export function terminateSession(id) { return apiClient.post(`/admin/security/sessions/${id}/terminate`) }
export function terminateMySessions(sessionId) { return apiClient.post('/admin/security/sessions/terminate-mine', { session_id: sessionId }) }
export function terminateAllSessions() { return apiClient.post('/admin/security/sessions/terminate-all') }

// ─── 安全事件 ───
export function getSecurityEvents(params) { return apiClient.get('/admin/security/events', { params }) }
export function getEventTypes() { return apiClient.get('/admin/security/event-types') }
