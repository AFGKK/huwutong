import request from '@/utils/request';

export function getComparison() {
    return request.get('/compare');
}

export function getAdvantages() {
    return request.get('/compare/advantages');
}

export function getCompetitors() {
    return request.get('/compare/competitors');
}

export function getCompareConfig() {
    return request.get('/compare/config');
}

export function updateCompareConfig(data) {
    return request.put('/compare', data);
}

export function resetCompareConfig() {
    return request.post('/compare/reset');
}
