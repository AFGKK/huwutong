import apiClient from './client'

// ─── 用户行为 ───
export function recordBehavior(data) { return apiClient.post('/personalization/behavior', data) }
export function getBehaviorStats(params) { return apiClient.get('/personalization/behavior/stats', { params }) }

// ─── 用户偏好 ───
export function getAllPreferences() { return apiClient.get('/personalization/preferences') }
export function getPreference(key) { return apiClient.get(`/personalization/preference/${key}`) }
export function setPreference(key, value) { return apiClient.post('/personalization/preference', { key, value }) }

// ─── 推荐引擎 ───
export function generateRecommendations(customerId) { return apiClient.post('/personalization/generate', { customer_id: customerId }) }
export function getRecommendations(customerId) { return apiClient.get('/personalization/recommendations', { params: { customer_id: customerId } }) }
export function refreshAllRecommendations() { return apiClient.post('/personalization/refresh', data) }
export function dismissRecommendation(id) { return apiClient.post(`/personalization/recommendations/${id}/dismiss`) }
export function clickRecommendation(id) { return apiClient.post(`/personalization/recommendations/${id}/click`) }

// ─── 个性化主页 ───
export function getPersonalizedHomepage() { return apiClient.get('/personalization/homepage') }

// ─── 管理端 ───
export function getPersonalizationAdminDashboard() { return apiClient.get('/personalization/admin/dashboard') }

// ─── 元数据 ───
export function getEventTypes() { return apiClient.get('/personalization/event-types') }
