import request from '../utils/request';

export function getCacheDashboard() {
  return request.get('/sdk-cache/dashboard');
}

export function reportCacheStatus(data) {
  return request.post('/sdk-cache/report-status', data);
}

export function checkCacheStatus(data) {
  return request.post('/sdk-cache/check-status', data);
}

export function getCacheConfig() {
  return request.get('/sdk-cache/config');
}

export function getCacheRecords(params) {
  return request.get('/sdk-cache/records', { params });
}

export function invalidateByLicense(data) {
  return request.post('/sdk-cache/invalidate-by-license', data);
}

export function invalidateByInstance(data) {
  return request.post('/sdk-cache/invalidate-by-instance', data);
}

export function getInvalidationLogs(params) {
  return request.get('/sdk-cache/invalidation-logs', { params });
}

export function batchInvalidate(data) {
  return request.post('/sdk-cache/batch-invalidate', data);
}

export function processExpiredCache() {
  return request.post('/sdk-cache/process-expired');
}

export function markCacheTampered(id) {
  return request.post(`/sdk-cache/records/${id}/mark-tampered`);
}
