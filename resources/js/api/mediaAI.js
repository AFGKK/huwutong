import apiClient from './client'

export default {
  imageAnalysis(data) { return apiClient.post('/media-ai/image-analysis', data) },
  videoSummary(data) { return apiClient.post('/media-ai/video-summary', data) },
  phishingDetection(data) { return apiClient.post('/media-ai/phishing-detect', data) },
  piiDetection(data) { return apiClient.post('/media-ai/pii-detect', data) },
  textToSpeech(data) { return apiClient.post('/media-ai/tts', data) },
  realtimeTranslation(data) { return apiClient.post('/media-ai/translate', data) },
  markAIContent(data) { return apiClient.post('/media-ai/mark-ai-content', data) },
  algorithmFiling(data) { return apiClient.post('/media-ai/algorithm-filing', data) },
}
