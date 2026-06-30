import request from '@/utils/request';

export function getPromptDashboard() {
    return request.get('/prompt-templates/dashboard');
}

export function getPromptList(params = {}) {
    return request.get('/prompt-templates', { params });
}

export function getActiveTemplates() {
    return request.get('/prompt-templates/active');
}

export function getPromptDetail(id) {
    return request.get(`/prompt-templates/${id}`);
}

export function createPrompt(data) {
    return request.post('/prompt-templates', data);
}

export function updatePrompt(id, data) {
    return request.put(`/prompt-templates/${id}`, data);
}

export function createPromptVersion(id, data) {
    return request.post(`/prompt-templates/${id}/version`, data);
}

export function setActivePrompt(id) {
    return request.post(`/prompt-templates/${id}/set-active`);
}

export function renderTestPrompt(id, variables = {}) {
    return request.post(`/prompt-templates/${id}/render-test`, { variables });
}

export function deletePrompt(id) {
    return request.delete(`/prompt-templates/${id}`);
}
