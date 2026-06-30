import request from '../utils/request';

const utmTracker = {
    // 仪表盘
    dashboard(params) {
        return request({
            url: '/admin/utm-tracker/dashboard',
            method: 'get',
            params,
        });
    },

    // 归因报告
    attributionReport(params) {
        return request({
            url: '/admin/utm-tracker/attribution-report',
            method: 'get',
            params,
        });
    },

    // 来源详细统计
    sourceDetail(params) {
        return request({
            url: '/admin/utm-tracker/source-detail',
            method: 'get',
            params,
        });
    },

    // 记录列表
    records(params) {
        return request({
            url: '/admin/utm-tracker/records',
            method: 'get',
            params,
        });
    },

    // 用户 UTM 历史
    userHistory(userId) {
        return request({
            url: `/admin/utm-tracker/users/${userId}/history`,
            method: 'get',
        });
    },

    // 配置选项
    options() {
        return request({
            url: '/admin/utm-tracker/options',
            method: 'get',
        });
    },

    // 公开：记录 UTM 访问
    recordVisit(data) {
        return request({
            url: '/utm-tracker/record-visit',
            method: 'post',
            data,
        });
    },

    // 公开：关联 UTM 到用户
    associateUser(data) {
        return request({
            url: '/utm-tracker/associate-user',
            method: 'post',
            data,
        });
    },
};

export default utmTracker;
