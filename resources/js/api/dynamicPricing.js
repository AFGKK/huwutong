import apiClient from './client';

export default {
  // ─── 阶梯定价管理 ───
  getTiers(pricingPlanId) {
    return apiClient.get('/admin/pricing/dynamic/tiers', { params: { pricing_plan_id: pricingPlanId } });
  },
  createTier(data) {
    return apiClient.post('/admin/pricing/dynamic/tiers', data);
  },
  updateTier(id, data) {
    return apiClient.put(`/admin/pricing/dynamic/tiers/${id}`, data);
  },
  deleteTier(id) {
    return apiClient.delete(`/admin/pricing/dynamic/tiers/${id}`);
  },

  // ─── 动态定价规则 ───
  getRules(params = {}) {
    return apiClient.get('/admin/pricing/dynamic/rules', { params });
  },
  getRule(id) {
    return apiClient.get(`/admin/pricing/dynamic/rules/${id}`);
  },
  createRule(data) {
    return apiClient.post('/admin/pricing/dynamic/rules', data);
  },
  updateRule(id, data) {
    return apiClient.put(`/admin/pricing/dynamic/rules/${id}`, data);
  },
  deleteRule(id) {
    return apiClient.delete(`/admin/pricing/dynamic/rules/${id}`);
  },
  toggleRule(id) {
    return apiClient.post(`/admin/pricing/dynamic/rules/${id}/toggle`);
  },

  // ─── 定价计算与模拟 ───
  calculatePrice(data) {
    return apiClient.post('/admin/pricing/dynamic/calculate', data);
  },
  simulatePricing(data) {
    return apiClient.post('/admin/pricing/dynamic/simulate', data);
  },

  // ─── LLM 优化 ───
  optimizePricing(pricingPlanId, marketData = {}) {
    return apiClient.post('/admin/pricing/dynamic/optimize', {
      pricing_plan_id: pricingPlanId,
      market_data: marketData,
    });
  },

  // ─── 应用日志 ───
  getApplicationLogs(params = {}) {
    return apiClient.get('/admin/pricing/dynamic/logs', { params });
  },

  // ─── 元数据 ───
  getMetadata() {
    return apiClient.get('/admin/pricing/dynamic/metadata');
  },

  // ─── 定价实验 (M3-26) ───
  getExperiments(params = {}) {
    return apiClient.get('/admin/pricing/dynamic/experiments', { params });
  },
  createExperiment(data) {
    return apiClient.post('/admin/pricing/dynamic/experiments', data);
  },
  getExperiment(id) {
    return apiClient.get(`/admin/pricing/dynamic/experiments/${id}`);
  },
  updateExperiment(id, data) {
    return apiClient.put(`/admin/pricing/dynamic/experiments/${id}`, data);
  },
  startExperiment(id) {
    return apiClient.post(`/admin/pricing/dynamic/experiments/${id}/start`);
  },
  pauseExperiment(id) {
    return apiClient.post(`/admin/pricing/dynamic/experiments/${id}/pause`);
  },
  completeExperiment(id) {
    return apiClient.post(`/admin/pricing/dynamic/experiments/${id}/complete`);
  },
  calculateResults(id) {
    return apiClient.post(`/admin/pricing/dynamic/experiments/${id}/calculate`);
  },
  assignToExperiment(id, data) {
    return apiClient.post(`/admin/pricing/dynamic/experiments/${id}/assign`, data);
  },
  recordEvent(id, data) {
    return apiClient.post(`/admin/pricing/dynamic/experiments/${id}/events`, data);
    },
    deleteExperiment(id) {
        return apiClient.delete(`/admin/pricing/dynamic/experiments/${id}`);
    },
  getExperimentStats() {
    return apiClient.get('/admin/pricing/dynamic/experiment-stats');
  },

  // ─── M3-26 增强 ───
  applyWinningTreatment(id) {
    return apiClient.post(`/admin/pricing/dynamic/experiments/${id}/apply-winning`);
  },
  getRecommendations() {
    return apiClient.get('/admin/pricing/dynamic/recommendations');
  },
  batchAssignToExperiments(data) {
    return apiClient.post('/admin/pricing/dynamic/batch-assign', data);
  },
};
