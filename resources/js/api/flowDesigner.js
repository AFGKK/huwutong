import apiClient from './client'

// ─── 设计 CRUD ───
export function listFlowDesigns(params) { return apiClient.get('/admin/flow-designer', { params }) }
export function createFlowDesign(data) { return apiClient.post('/admin/flow-designer', data) }
export function getFlowDesign(id) { return apiClient.get(`/admin/flow-designer/${id}`) }
export function updateFlowDesign(id, data) { return apiClient.put(`/admin/flow-designer/${id}`, data) }
export function deleteFlowDesign(id) { return apiClient.delete(`/admin/flow-designer/${id}`) }

// ─── 节点 ───
export function addFlowNode(designId, data) { return apiClient.post(`/admin/flow-designer/${designId}/nodes`, data) }
export function updateFlowNode(designId, nodeId, data) { return apiClient.put(`/admin/flow-designer/${designId}/nodes/${nodeId}`, data) }
export function deleteFlowNode(designId, nodeId) { return apiClient.delete(`/admin/flow-designer/${designId}/nodes/${nodeId}`) }

// ─── 连线 ───
export function addFlowEdge(designId, data) { return apiClient.post(`/admin/flow-designer/${designId}/edges`, data) }
export function deleteFlowEdge(designId, edgeId) { return apiClient.delete(`/admin/flow-designer/${designId}/edges/${edgeId}`) }

// ─── 批量保存 & 导出 ───
export function saveFlowGraph(designId, data) { return apiClient.post(`/admin/flow-designer/${designId}/save-graph`, data) }
export function exportFlowDesign(designId) { return apiClient.post(`/admin/flow-designer/${designId}/export`) }

// ─── 元数据 ───
export function getFlowDesignerStats() { return apiClient.get('/admin/flow-designer/stats') }
export function getNodePalette() { return apiClient.get('/admin/flow-designer/node-palette') }
export function getFlowCategories() { return apiClient.get('/admin/flow-designer/categories') }
