import apiClient from './client';

const staticAssetCdnApi = {
    /**
     * 获取 CDN 部署统计
     */
    stats() {
        return apiClient.get('/static-assets/cdn/stats');
    },

    /**
     * 部署构建产物到 CDN
     */
    deploy(version = null) {
        return apiClient.post('/static-assets/cdn/deploy', { version });
    },

    /**
     * 激活指定版本
     */
    activate(version) {
        return apiClient.post('/static-assets/cdn/activate', { version });
    },

    /**
     * 回滚到指定版本
     */
    rollback(version) {
        return apiClient.post('/static-assets/cdn/rollback', { version });
    },

    /**
     * 获取版本列表
     */
    versions() {
        return apiClient.get('/static-assets/cdn/versions');
    },

    /**
     * 获取当前版本详情
     */
    currentVersion() {
        return apiClient.get('/static-assets/cdn/version/current');
    },

    /**
     * 删除指定版本
     */
    deleteVersion(version) {
        return apiClient.delete(`/static-assets/cdn/versions/${version}`);
    },

    /**
     * 获取本地构建产物文件列表
     */
    buildFiles() {
        return apiClient.get('/static-assets/build-files');
    },
};

export default staticAssetCdnApi;
