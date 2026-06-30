import request from '../utils/request';

export function getSdkDashboard() {
  return request.get('/sdk-version/dashboard');
}

export function getSdkVersions() {
  return request.get('/sdk-version');
}

export function getLanguageVersions(language) {
  return request.get(`/sdk-version/language/${language}`);
}

export function getSdkVersion(id) {
  return request.get(`/sdk-version/${id}`);
}

export function createSdkVersion(data) {
  return request.post('/sdk-version', data);
}

export function updateSdkVersion(id, data) {
  return request.put(`/sdk-version/${id}`, data);
}

export function checkUpgrade(language, version) {
  return request.post('/sdk-version/check-upgrade', { language, version });
}

export function getUpgradePath(language, fromVersion) {
  return request.post('/sdk-version/upgrade-path', { language, from_version: fromVersion });
}

export function getMigrationGuide(language, targetVersion = null) {
  return request.post('/sdk-version/migration-guide', { language, target_version: targetVersion });
}

export function markDeprecated(id) {
  return request.post(`/sdk-version/${id}/deprecate`);
}

export function markSunset(id) {
  return request.post(`/sdk-version/${id}/sunset`);
}

export function seedDefaultVersions() {
  return request.post('/sdk-version/seed-defaults');
}

export function processExpired() {
  return request.post('/sdk-version/process-expired');
}
