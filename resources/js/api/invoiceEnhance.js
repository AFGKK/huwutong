import apiClient from './client'

// ─── 发票模板 ───
export function getInvoiceTemplates(params) { return apiClient.get('/admin/invoice-enhance/templates', { params }) }
export function createInvoiceTemplate(data) { return apiClient.post('/admin/invoice-enhance/templates', data) }
export function updateInvoiceTemplate(id, data) { return apiClient.put(`/admin/invoice-enhance/templates/${id}`, data) }
export function deleteInvoiceTemplate(id) { return apiClient.delete(`/admin/invoice-enhance/templates/${id}`) }
export function getDefaultInvoiceTemplate() { return apiClient.get('/admin/invoice-enhance/default-template') }

// ─── 账单对账 ───
export function getReconciliations(params) { return apiClient.get('/admin/invoice-enhance/reconciliations', { params }) }
export function createReconciliation(data) { return apiClient.post('/admin/invoice-enhance/reconciliations', data) }
export function resolveReconciliation(id, data) { return apiClient.post(`/admin/invoice-enhance/reconciliations/${id}/resolve`, data) }
export function getReconciliationStats() { return apiClient.get('/admin/invoice-enhance/reconciliation-stats') }
export function autoReconcile() { return apiClient.post('/admin/invoice-enhance/auto-reconcile') }

// ─── 账单拆分 ───
export function getInvoiceSplits(params) { return apiClient.get('/admin/invoice-enhance/splits', { params }) }
export function splitInvoice(data) { return apiClient.post('/admin/invoice-enhance/split', data) }

// ─── 增强统计 ───
export function getInvoiceEnhanceStats() { return apiClient.get('/admin/invoice-enhance/stats') }
