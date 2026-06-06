import client from './client';

export default {
    // 获取默认版本信息（公开）
    defaultInfo() {
        return client.get('/api-version');
    },

    // 获取所有版本列表
    index(params = {}) {
        return client.get('/api-versions', { params });
    },

    // 获取版本详情
    show(version) {
        return client.get(`/api-versions/${version}`);
    },

    // 创建版本
    store(data) {
        return client.post('/api-versions', data);
    },

    // 更新版本
    update(version, data) {
        return client.put(`/api-versions/${version}`, data);
    },

    // 删除版本
    destroy(version) {
        return client.delete(`/api-versions/${version}`);
    },

    // 标记废弃
    deprecate(version, data = {}) {
        return client.post(`/api-versions/${version}/deprecate`, data);
    },

    // 停用
    sunset(version) {
        return client.post(`/api-versions/${version}/sunset`);
    },

    // 退役
    retire(version) {
        return client.post(`/api-versions/${version}/retire`);
    },

    // 获取版本路由
    routes(version) {
        return client.get(`/api-versions/${version}/routes`);
    },

    // 注册单条路由
    registerRoute(version, data) {
        return client.post(`/api-versions/${version}/routes`, data);
    },

    // 批量导入路由
    importRoutes(version, data) {
        return client.post(`/api-versions/${version}/routes/import`, data);
    },

    // 删除路由
    deleteRoute(version, routeId) {
        return client.delete(`/api-versions/${version}/routes/${routeId}`);
    },

    // 版本调用统计
    callStats(version, params = {}) {
        return client.get(`/api-versions/${version}/call-stats`, { params });
    },

    // 版本使用趋势
    usageTrend(params = {}) {
        return client.get('/api-versions/usage-trend', { params });
    },

    // 版本影响分析
    impactAnalysis(version) {
        return client.get(`/api-versions/${version}/impact-analysis`);
    },
};
