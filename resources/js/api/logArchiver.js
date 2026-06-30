import request from '../utils/request';

export function getArchiverDashboard() {
  return request.get('/log-archiver/dashboard');
}

export function getArchiveTiers() {
  return request.get('/log-archiver/tiers');
}

export function getArchiveStats() {
  return request.get('/log-archiver/stats');
}

export function getArchivePolicies() {
  return request.get('/log-archiver/policies');
}

export function upsertArchivePolicy(data) {
  return request.post('/log-archiver/policies', data);
}

export function executeArchive(id) {
  return request.post(`/log-archiver/policies/${id}/archive`);
}

export function getArchiveRecords(params) {
  return request.get('/log-archiver/records', { params });
}

export function requestRestore(id, data) {
  return request.post(`/log-archiver/records/${id}/restore`, data);
}

export function executeRestore(id) {
  return request.post(`/log-archiver/restore-requests/${id}/execute`);
}

export function getRestoreRequests(params) {
  return request.get('/log-archiver/restore-requests', { params });
}

export function cancelRestore(id) {
  return request.post(`/log-archiver/restore-requests/${id}/cancel`);
}

export function processExpiredRestores() {
  return request.post('/log-archiver/process-expired');
}
