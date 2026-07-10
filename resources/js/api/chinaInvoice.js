import apiClient from './client';
export function getChinaInvoiceStats() { return apiClient.get('/admin/china-invoice/stats'); }
export function getDevices() { return apiClient.get('/admin/china-invoice/devices'); }
export function createDevice(d) { return apiClient.post('/admin/china-invoice/devices', d); }
export function deleteDevice(id) { return apiClient.delete(`/admin/china-invoice/devices/${id}`); }
export function getTemplates() { return apiClient.get('/admin/china-invoice/templates'); }
export function createTemplate(d) { return apiClient.post('/admin/china-invoice/templates', d); }
export function deleteTemplate(id) { return apiClient.delete(`/admin/china-invoice/templates/${id}`); }
export function getInvoices(p) { return apiClient.get('/admin/china-invoice/invoices', { params: p }); }
export function issueInvoice(d) { return apiClient.post('/admin/china-invoice/issue', d); }
export function getInvoice(id) { return apiClient.get(`/admin/china-invoice/invoices/${id}`); }
export function redLetter(id, r) { return apiClient.post(`/admin/china-invoice/invoices/${id}/red-letter`, { reason: r }); }
export function voidInvoice(id) { return apiClient.post(`/admin/china-invoice/invoices/${id}/void`); }
export function getTaxReports() { return apiClient.get('/admin/china-invoice/tax-reports'); }
export function generateTaxReport(p) { return apiClient.post('/admin/china-invoice/tax-reports/generate', { period: p }); }
