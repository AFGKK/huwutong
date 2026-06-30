import request from '@/utils/request'

export function getLicenseKeyFormat(licenseId) {
  return request.get(`/licenses/${licenseId}/key-format`)
}

export function batchLicenseKeyFormat(ids) {
  return request.post('/licenses/batch-key-format', { ids })
}

export function migrateLicenseKeyPrefixes() {
  return request.post('/admin/license-key/prefix-migrate')
}

export default {
  getLicenseKeyFormat,
  batchLicenseKeyFormat,
  migrateLicenseKeyPrefixes,
}
