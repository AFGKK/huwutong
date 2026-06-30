import apiClient from './client'

// ─── 元数据 ───
export function getEntityTypes() { return apiClient.get('/admin/data-import/entity-types') }
export function getEntityFields(entityType) { return apiClient.get(`/admin/data-import/entity-fields/${entityType}`) }
export function getGenerateTemplate(entityType) { return apiClient.get(`/admin/data-import/generate-template/${entityType}`) }

// ─── 上传 & 解析 ───
export function uploadFile(formData) { return apiClient.post('/admin/data-import/upload', formData, { headers: { 'Content-Type': 'multipart/form-data' } }) }
export function parseFile(taskId) { return apiClient.post(`/admin/data-import/tasks/${taskId}/parse`) }

// ─── 映射 ───
export function updateMappings(taskId, mappings) { return apiClient.put(`/admin/data-import/tasks/${taskId}/mappings`, { mappings }) }

// ─── 验证 & 执行 ───
export function validateData(taskId) { return apiClient.post(`/admin/data-import/tasks/${taskId}/validate`) }
export function executeImport(taskId) { return apiClient.post(`/admin/data-import/tasks/${taskId}/execute`) }

// ─── 任务管理 ───
export function getImportTasks(params) { return apiClient.get('/admin/data-import/tasks', { params }) }
export function getImportTask(taskId) { return apiClient.get(`/admin/data-import/tasks/${taskId}`) }
export function getImportLogs(taskId, params) { return apiClient.get(`/admin/data-import/tasks/${taskId}/logs`, { params }) }
export function cancelImport(taskId) { return apiClient.post(`/admin/data-import/tasks/${taskId}/cancel`) }
export function deleteImportTask(taskId) { return apiClient.delete(`/admin/data-import/tasks/${taskId}`) }

// ─── 映射模板 ───
export function getMappingTemplates(params) { return apiClient.get('/admin/data-import/mapping-templates', { params }) }
export function saveMappingTemplate(data) { return apiClient.post('/admin/data-import/mapping-templates', data) }
export function deleteMappingTemplate(id) { return apiClient.delete(`/admin/data-import/mapping-templates/${id}`) }
export function applyTemplate(taskId, templateId) { return apiClient.post(`/admin/data-import/tasks/${taskId}/apply-template`, { template_id: templateId }) }
