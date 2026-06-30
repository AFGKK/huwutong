import apiClient from './client';

export default {
    /**
     * 获取公开品牌数据
     */
    publicBranding(params = {}) {
        return apiClient.get('/branding', { params });
    },

    /**
     * 获取当前租户品牌配置
     */
    show() {
        return apiClient.get('/admin/portal-branding');
    },

    /**
     * 更新品牌配置
     */
    update(data) {
        return apiClient.put('/admin/portal-branding', data);
    },

    /**
     * 重置为默认配置
     */
    reset(params = {}) {
        return apiClient.post('/admin/portal-branding/reset', params);
    },

    /**
     * 获取主题模板
     */
    themeTemplates() {
        return apiClient.get('/admin/portal-branding/theme-templates');
    },

    /**
     * 应用主题模板
     */
    applyTheme(data) {
        return apiClient.post('/admin/portal-branding/apply-theme', data);
    },
};
