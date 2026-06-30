import apiClient from './client'

export default {
  show(params) { return apiClient.get('/admin/portal-branding', { params }) },
  update(data) { return apiClient.put('/admin/portal-branding', data) },
  reset(params) { return apiClient.post('/admin/portal-branding/reset', {}, { params }) },
  themeTemplates() { return apiClient.get('/admin/portal-branding/theme-templates') },
  applyTheme(data) { return apiClient.post('/admin/portal-branding/apply-theme', data) },
}
