import apiClient from './client'

// 模板 CRUD (复用现有)
export function getLicenseTemplates(params) { return apiClient.get('/license-templates', { params }) }
export function getLicenseTemplate(id) { return apiClient.get(`/license-templates/${id}`) }
export function createLicenseTemplate(data) { return apiClient.post('/license-templates', data) }
export function updateLicenseTemplate(id, data) { return apiClient.put(`/license-templates/${id}`, data) }
export function deleteLicenseTemplate(id) { return apiClient.delete(`/license-templates/${id}`) }
export function toggleLicenseTemplate(id) { return apiClient.post(`/license-templates/${id}/toggle-active`) }

// 模板扩展
export function getLicenseTemplateVariables(templateId) { return apiClient.get(`/license-templates/${templateId}/variables`) }
export function saveLicenseTemplateVariables(templateId, variables) { return apiClient.post(`/license-templates/${templateId}/variables`, { variables }) }
export function saveLicenseTemplateFieldMappings(templateId, mappings) { return apiClient.post(`/license-templates/${templateId}/field-mappings`, { mappings }) }
export function getLicenseTemplateWithExtras(templateId) { return apiClient.get(`/license-templates/${templateId}/with-extras`) }
export function previewLicenseGeneration(templateId, rows) { return apiClient.post(`/license-templates/${templateId}/preview`, { rows }) }

// 批量生成
export function batchGenerateLicenses(templateId, data) { return apiClient.post(`/license-templates/${templateId}/batch-generate`, data) }
export function getBatchTasks(params) { return apiClient.get('/batch-tasks', { params }) }
export function getBatchTask(id) { return apiClient.get(`/batch-tasks/${id}`) }
export function deleteBatchTask(id) { return apiClient.delete(`/batch-tasks/${id}`) }
