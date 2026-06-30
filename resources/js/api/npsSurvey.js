import request from '@/utils/request';

const npsSurvey = {
    // 仪表盘
    dashboard(params) {
        return request({
            url: '/admin/nps-survey/dashboard',
            method: 'get',
            params,
        });
    },

    // NPS 报告
    report(params) {
        return request({
            url: '/admin/nps-survey/report',
            method: 'get',
            params,
        });
    },

    // 调查列表
    surveys(params) {
        return request({
            url: '/admin/nps-survey/surveys',
            method: 'get',
            params,
        });
    },

    // 反馈列表
    responses(params) {
        return request({
            url: '/admin/nps-survey/responses',
            method: 'get',
            params,
        });
    },

    // 发送调查
    sendSurvey(data) {
        return request({
            url: '/admin/nps-survey/send',
            method: 'post',
            data,
        });
    },

    // 趋势数据
    trend(params) {
        return request({
            url: '/admin/nps-survey/trend',
            method: 'get',
            params,
        });
    },

    // 生成快照
    generateSnapshot() {
        return request({
            url: '/admin/nps-survey/generate-snapshot',
            method: 'post',
        });
    },

    // 可发送调查的用户
    eligibleUsers(params) {
        return request({
            url: '/admin/nps-survey/eligible-users',
            method: 'get',
            params,
        });
    },

    // 配置
    config() {
        return request({
            url: '/admin/nps-survey/config',
            method: 'get',
        });
    },

    // 公开：提交评分
    submitResponse(data) {
        return request({
            url: '/nps-survey/submit-response',
            method: 'post',
            data,
        });
    },
};

export default npsSurvey;
