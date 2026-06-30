import apiClient from './client'

export default {
  knowledgeQuery(data) { return apiClient.post('/enterprise-ai/knowledge-query', data) },
  meetingMinutes(convId) { return apiClient.get(`/enterprise-ai/meeting-minutes/${convId}`) },
  crossSessionInsights() { return apiClient.get('/enterprise-ai/cross-session-insights') },
  onboardingGuide() { return apiClient.get('/enterprise-ai/onboarding-guide') },
  formSuggestions(data) { return apiClient.post('/enterprise-ai/form-suggestions', data) },
  agentToolCall(data) { return apiClient.post('/enterprise-ai/agent-tool-call', data) },
  botBuilder(data) { return apiClient.post('/enterprise-ai/bot-builder', data) },
  multiAgentPipeline(data) { return apiClient.post('/enterprise-ai/multi-agent-pipeline', data) },
  openApi(data) { return apiClient.post('/enterprise-ai/open-api', data) },
  finetuneData(data) { return apiClient.post('/enterprise-ai/finetune-data', data) },
  webSearchStatus() { return apiClient.get('/enterprise-ai/web-search/status') },
  moderatorAgenda(convId, data) { return apiClient.post(`/enterprise-ai/moderator/agenda/${convId}`, data) },
  moderatorMediate(convId, data) { return apiClient.post(`/enterprise-ai/moderator/mediate/${convId}`, data) },
  moderatorSummary(convId, data) { return apiClient.post(`/enterprise-ai/moderator/summary/${convId}`, data) },
  moderatorFocus(convId, data) { return apiClient.post(`/enterprise-ai/moderator/focus/${convId}`, data) },
}
