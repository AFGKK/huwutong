import client from './client';

export default {
    // 仪表盘
    dashboard() { return client.get('/seo/dashboard'); },

    // 站点地图
    sitemap() { return client.get('/seo/sitemap'); },

    // SEO 元数据
    showMetadata(modelType, modelId) { return client.get('/seo/metadata', { params: { model_type: modelType, model_id: modelId } }); },
    upsertMetadata(data) { return client.post('/seo/metadata', data); },
    destroyMetadata(modelType, modelId) { return client.delete('/seo/metadata', { params: { model_type: modelType, model_id: modelId } }); },

    // URL 重定向
    listRedirects(params = {}) { return client.get('/seo/redirects', { params }); },
    showRedirect(id) { return client.get(`/seo/redirects/${id}`); },
    storeRedirect(data) { return client.post('/seo/redirects', data); },
    updateRedirect(id, data) { return client.put(`/seo/redirects/${id}`, data); },
    destroyRedirect(id) { return client.delete(`/seo/redirects/${id}`); },
    bulkImport(entries) { return client.post('/seo/redirects/bulk-import', { entries }); },
};
