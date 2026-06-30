import request from '@/utils/request';

export function evaluateLicense(licenseId, data) {
    return request.post(`/fraud-risk/evaluate/${licenseId}`, data);
}

export function batchEvaluate() {
    return request.post('/fraud-risk/batch-evaluate');
}

export function getFraudStats() {
    return request.get('/fraud-risk/stats');
}

export function getAnomalies(params) {
    return request.get('/fraud-risk/anomalies', { params });
}

export function analyzeBehavior(data) {
    return request.post('/fraud-risk/analyze', data);
}

export function checkBan() {
    return request.post('/fraud-risk/check-ban');
}

export function unban(data) {
    return request.post('/fraud-risk/unban', data);
}

export function getBehaviorStats() {
    return request.get('/fraud-risk/behavior-stats');
}
