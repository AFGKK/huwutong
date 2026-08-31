import apiClient from './client'

export default {
  call(data) {
    return apiClient.post('/calls/call', data)
  },
  respond(id, data) {
    return apiClient.post(`/calls/${id}/respond`, data)
  },
  end(id) {
    return apiClient.post(`/calls/${id}/end`)
  },
  status(id) {
    return apiClient.get(`/calls/${id}/status`)
  },
  signal(id, data) {
    return apiClient.post(`/calls/${id}/signal`, data)
  },
  iceServers() {
    return apiClient.get('/calls/ice-servers')
  },
  incoming() {
    return apiClient.get('/calls/incoming')
  },
  signalPoll(id, type) {
    return apiClient.get(`/calls/${id}/signal-poll`, { params: { type } })
  },
  history() {
    return apiClient.get('/calls/history')
  },
}
