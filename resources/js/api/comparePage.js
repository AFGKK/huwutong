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
