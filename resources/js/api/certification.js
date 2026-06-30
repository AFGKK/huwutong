import apiClient from './client';

export default {
    // 认证等级
    getLevels(params = {}) {
        return apiClient.get('/admin/certification/levels', { params });
    },
    createLevel(data) {
        return apiClient.post('/admin/certification/levels', data);
    },
    updateLevel(id, data) {
        return apiClient.put(`/admin/certification/levels/${id}`, data);
    },

    // 题库
    getQuestions(levelId, params = {}) {
        return apiClient.get(`/admin/certification/levels/${levelId}/questions`, { params });
    },
    addQuestion(levelId, data) {
        return apiClient.post(`/admin/certification/levels/${levelId}/questions`, data);
    },
    bulkAddQuestions(levelId, data) {
        return apiClient.post(`/admin/certification/levels/${levelId}/questions/bulk`, data);
    },

    // 考试
    startExam(data) {
        return apiClient.post('/admin/certification/exam/start', data);
    },
    getExamQuestions(devCertId) {
        return apiClient.get(`/admin/certification/exam/${devCertId}/questions`);
    },
    submitAnswer(devCertId, data) {
        return apiClient.post(`/admin/certification/exam/${devCertId}/answer`, data);
    },
    submitExam(devCertId) {
        return apiClient.post(`/admin/certification/exam/${devCertId}/submit`);
    },

    // 我的认证
    myCertifications() {
        return apiClient.get('/admin/certification/my');
    },
    myStats() {
        return apiClient.get('/admin/certification/my/stats');
    },

    // 管理
    globalStats() {
        return apiClient.get('/admin/certification/stats');
    },
    revoke(id, data = {}) {
        return apiClient.post(`/admin/certification/${id}/revoke`, data);
    },
};
