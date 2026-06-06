import apiClient from '@/api/client'

export function getLegalConsents(params = {}) {
  return apiClient.get('/legal-consents', { params })
}

export function getLegalConsent(id) {
  return apiClient.get(`/legal-consents/${id}`)
}

export function createLegalConsent(data) {
  return apiClient.post('/legal-consents', data)
}

export function updateLegalConsent(id, data) {
  return apiClient.put(`/legal-consents/${id}`, data)
}

export function publishLegalConsent(id) {
  return apiClient.post(`/legal-consents/${id}/publish`)
}

export function getConsentLogs(params = {}) {
  return apiClient.get('/legal-consents/logs', { params })
}

export function getCurrentConsent(type = 'privacy_policy') {
  return apiClient.get('/legal-consents/current', { params: { type } })
}

export function submitConsent(legalConsentId) {
  return apiClient.post('/legal-consent', { legal_consent_id: legalConsentId })
}
