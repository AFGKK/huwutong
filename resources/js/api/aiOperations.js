import apiClient from './client';

export default {
    // ── 知识库自增长 ──
    kbAutoGrowStats() { return apiClient.get('/kb-auto-grow/stats'); },
    kbAutoGrowPending(params) { return apiClient.get('/kb-auto-grow/pending', { params }); },
    kbAutoGrowApprove(id) { return apiClient.post(`/kb-auto-grow/${id}/approve`); },
    kbAutoGrowReject(id) { return apiClient.post(`/kb-auto-grow/${id}/reject`); },
    kbAutoGrowRun(params) { return apiClient.post('/kb-auto-grow/run', params); },

    // ── 深度研究 ──
    deepResearchStart(query) { return apiClient.post('/deep-research/start', { query }); },
    deepResearchHistory(params) { return apiClient.get('/deep-research/history', { params }); },
    deepResearchDetail(id) { return apiClient.get(`/deep-research/${id}`); },
    deepResearchDelete(id) { return apiClient.delete(`/deep-research/${id}`); },

    // ── 搜索增强 ──
    vectorSearch(query, params) { return apiClient.post('/vector-search/search', { query, ...params }); },
    vectorSearchRebuild(force) { return apiClient.post('/vector-search/rebuild', { force }); },
    vectorSearchStats() { return apiClient.get('/vector-search/stats'); },

    // ── 幻觉检测 ──
    hallucinationInspect(text) { return apiClient.post('/hallucination/inspect', { text }); },
    hallucinationAnnotate(text) { return apiClient.post('/hallucination/annotate', { text }); },
    hallucinationHistory(params) { return apiClient.get('/hallucination/history', { params }); },
    hallucinationStats() { return apiClient.get('/hallucination/stats'); },

    // ── 内容溯源 ──
    contentSign(content, type, id) { return apiClient.post('/content-signatures/sign', { content, source_type: type, source_id: id }); },
    contentSignAndMark(content, type, id) { return apiClient.post('/content-signatures/sign-and-mark', { content, source_type: type, source_id: id }); },
    contentVerify(content, hash) { return apiClient.post('/content-signatures/verify', { content, hash }); },
    contentStats() { return apiClient.get('/content-signatures/stats'); },

    // ── 自动化运营 ──
    qualityRate(content) { return apiClient.post('/content-quality/rate', { content }); },
    qualityRun(params) { return apiClient.post('/content-quality/run', params); },
    qualityStats() { return apiClient.get('/content-quality/stats'); },
    qualityHistory(params) { return apiClient.get('/content-quality/history', { params }); },

    // ── 电子签名 ──
    esignCreate(data) { return apiClient.post('/electronic-signatures/create', data); },
    esignSign(id) { return apiClient.post(`/electronic-signatures/${id}/sign`); },
    esignReject(id, remark) { return apiClient.post(`/electronic-signatures/${id}/reject`, { remark }); },
    esignVerify(type, id) { return apiClient.post('/electronic-signatures/verify', { signable_type: type, signable_id: id }); },
    esignMyPending() { return apiClient.get('/electronic-signatures/my-pending'); },
    esignHistory() { return apiClient.get('/electronic-signatures/history'); },
    esignStats() { return apiClient.get('/electronic-signatures/stats'); },

    // ── 自学习引擎 ──
    selfLearn(hours, autoApply) { return apiClient.post('/self-learning/learn', { hours, auto_apply: autoApply }); },
    selfLearnStatus() { return apiClient.get('/self-learning/status'); },
    selfLearnLogs(params) { return apiClient.get('/self-learning/logs', { params }); },
    selfLearnPatterns(params) { return apiClient.get('/self-learning/patterns', { params }); },
};
