import apiClient from './client'

export default {
  autoReply(data) { return apiClient.post('/cs-ai/auto-reply', data) },
  autoReplyStream(data) { return apiClient.post('/cs-ai/auto-reply-stream', data) },
  evaluateConfidence(data) { return apiClient.post('/cs-ai/confidence', data) },
  intentClassification(data) { return apiClient.post('/cs-ai/intent', data) },
  sentimentAnalysis(data) { return apiClient.post('/cs-ai/sentiment', data) },
  agentAssist(convId, data) { return apiClient.post(`/cs-ai/agent-assist/${convId}`, data) },
  qualityCheck(convId) { return apiClient.get(`/cs-ai/quality-check/${convId}`) },
  dialogStateMachine(data) { return apiClient.post('/cs-ai/dialog-state', data) },
}
