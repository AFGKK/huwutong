import apiClient from '@/api/client'

export function trustDevice(fingerprint, deviceName = '') {
  return apiClient.post('/devices/trust', { device_fingerprint: fingerprint, device_name: deviceName })
}

export function getTrustedDevices() {
  return apiClient.get('/devices/trusted')
}

export function removeTrustedDevice(id) {
  return apiClient.delete(`/devices/trusted/${id}`)
}

export function clearAllTrustedDevices() {
  return apiClient.delete('/devices/trusted')
}

export function checkDevice(fingerprint, deviceName = '') {
  return apiClient.post('/devices/check', { device_fingerprint: fingerprint, device_name: deviceName })
}
