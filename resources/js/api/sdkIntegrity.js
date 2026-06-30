import request from '../utils/request';

export function getIntegrityDashboard() {
  return request.get('/sdk-integrity/dashboard');
}

export function getIntegrityChecks(params) {
  return request.get('/sdk-integrity/checks', { params });
}

export function submitIntegrityCheck(data) {
  return request.post('/sdk-integrity/submit-check', data);
}

export function issueDestroyCommand(data) {
  return request.post('/sdk-integrity/issue-destroy', data);
}

export function pollDestroyCommand(data) {
  return request.post('/sdk-integrity/poll-destroy', data);
}

export function confirmDestroyCommand(data) {
  return request.post('/sdk-integrity/confirm-destroy', data);
}

export function sendHeartbeat(data) {
  return request.post('/sdk-integrity/heartbeat', data);
}

export function getSdkIntegrityConfig() {
  return request.get('/sdk-integrity/sdk-config');
}

export function getProtectedFiles(params) {
  return request.get('/sdk-integrity/protected-files', { params });
}

export function getDestroyCommands(params) {
  return request.get('/sdk-integrity/commands', { params });
}

export function cancelDestroyCommand(id) {
  return request.post(`/sdk-integrity/commands/${id}/cancel`);
}

export function batchDestroy(data) {
  return request.post('/sdk-integrity/batch-destroy', data);
}

export function processExpiredCommands() {
  return request.post('/sdk-integrity/process-expired');
}
