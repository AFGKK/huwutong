import request from '@/utils/request';

const featureAdoption = {
    // 仪表盘
    dashboard(params) {
        return request({
            url: '/admin/feature-adoption/dashboard',
            method: 'get',
            params,
        });
    },

    // 功能详情
    featureDetail(featureKey, params) {
        return request({
            url: `/admin/feature-adoption/feature/${featureKey}`,
            method: 'get',
            params,
        });
    },

    // 分类详情
    categoryDetail(category, params) {
        return request({
            url: `/admin/feature-adoption/category/${category}`,
            method: 'get',
            params,
        });
    },

    // 漏斗分析
    funnel(funnelKey, params) {
        return request({
            url: `/admin/feature-adoption/funnel/${funnelKey}`,
            method: 'get',
            params,
        });
    },

    // 趋势
    trend(params) {
        return request({
            url: '/admin/feature-adoption/trend',
            method: 'get',
            params,
        });
    },

    // 事件列表
    events(params) {
        return request({
            url: '/admin/feature-adoption/events',
            method: 'get',
            params,
        });
    },

    // 记录事件
    track(data) {
        return request({
            url: '/admin/feature-adoption/track',
            method: 'post',
            data,
        });
    },

    // 批量记录
    batchTrack(data) {
        return request({
            url: '/admin/feature-adoption/batch-track',
            method: 'post',
            data,
        });
    },

    // 生成每日快照
    generateSnapshot() {
        return request({
            url: '/admin/feature-adoption/generate-snapshot',
            method: 'post',
        });
    },

    // 功能定义列表
    featureDefs() {
        return request({
            url: '/admin/feature-adoption/feature-defs',
            method: 'get',
        });
    },

    // 清理过期数据
    prune(params) {
        return request({
            url: '/admin/feature-adoption/prune',
            method: 'post',
            params,
        });
    },
};

export default featureAdoption;
