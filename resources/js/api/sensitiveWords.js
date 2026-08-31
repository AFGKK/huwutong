import apiClient from './client';

export function getSensitiveWords(params = {}) {
    return apiClient.get('/im/sensitive-words', { params });
}

export function createSensitiveWord(data) {
    return apiClient.post('/im/sensitive-words', data);
}

export function updateSensitiveWord(id, data) {
    return apiClient.put(`/im/sensitive-words/${id}`, data);
}

export function deleteSensitiveWord(id) {
    return apiClient.delete(`/im/sensitive-words/${id}`);
}

export function testSensitiveWords(data) {
    return apiClient.post('/im/sensitive-words/test', data);
}

export function importSensitiveWords(data) {
    return apiClient.post('/im/sensitive-words/import', data);
}

export function exportSensitiveWords() {
    return apiClient.get('/im/sensitive-words/export');
}
