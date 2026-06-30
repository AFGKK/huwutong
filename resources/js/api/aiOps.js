import request from '@/utils/request';

export function getAIOpsDashboard() {
    return request.get('/ai-ops/dashboard');
}

export function getAIOpsTemplates(category) {
    return request.get('/ai-ops/templates', { params: { category } });
}

export function runAIOpsTemplate(key, params = {}) {
    return request.post('/ai-ops/run-template', { key, ...params });
}

export function askAIOpsQuestion(question) {
    return request.post('/ai-ops/ask', { question });
}
