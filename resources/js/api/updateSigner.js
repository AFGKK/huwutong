import request from '../utils/request';

export function getSignerDashboard() {
  return request.get('/update-signer/dashboard');
}

export function getVerificationLogs(params) {
  return request.get('/update-signer/verification-logs', { params });
}

export function getPublicKey(params) {
  return request.get('/update-signer/public-key', { params });
}

export function signPackage(id, data = {}) {
  return request.post(`/update-signer/packages/${id}/sign`, data);
}

export function verifySignature(data) {
  return request.post('/update-signer/verify', data);
}

export function createRollback(id, data = {}) {
  return request.post(`/update-signer/packages/${id}/rollback`, data);
}

export function approveRollback(id) {
  return request.post(`/update-signer/rollbacks/${id}/approve`);
}

export function executeRollback(id) {
  return request.post(`/update-signer/rollbacks/${id}/execute`);
}

export function getRollbacks(params) {
  return request.get('/update-signer/rollbacks', { params });
}

export function createGrayRelease(id, data) {
  return request.post(`/update-signer/packages/${id}/gray-release`, data);
}

export function startGrayRelease(id) {
  return request.post(`/update-signer/gray-releases/${id}/start`);
}

export function advanceGrayRelease(id) {
  return request.post(`/update-signer/gray-releases/${id}/advance`);
}

export function pauseGrayRelease(id) {
  return request.post(`/update-signer/gray-releases/${id}/pause`);
}

export function getGrayReleases(params) {
  return request.get('/update-signer/gray-releases', { params });
}

export function checkUpdateEligibility(data) {
  return request.post('/update-signer/check-eligibility', data);
}
