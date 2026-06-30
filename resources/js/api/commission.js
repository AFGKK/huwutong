import client from './client';

export default {
  // 管理员
  dashboard() {
    return client.get('/commission/dashboard');
  },
  listAgents(params = {}) {
    return client.get('/commission/agents', { params });
  },
  showAgent(id) {
    return client.get(`/commission/agents/${id}`);
  },
  createAgent(data) {
    return client.post('/commission/agents', data);
  },
  updateAgent(id, data) {
    return client.put(`/commission/agents/${id}`, data);
  },
  listPlans(params = {}) {
    return client.get('/commission/plans', { params });
  },
  createPlan(data) {
    return client.post('/commission/plans', data);
  },
  updatePlan(id, data) {
    return client.put(`/commission/plans/${id}`, data);
  },
  listPlanItems(planId) {
    return client.get(`/commission/plans/${planId}/items`);
  },
  createPlanItem(planId, data) {
    return client.post(`/commission/plans/${planId}/items`, data);
  },
  updatePlanItem(id, data) {
    return client.put(`/commission/plan-items/${id}`, data);
  },
  deletePlanItem(id) {
    return client.delete(`/commission/plan-items/${id}`);
  },
  listSettlements(params = {}) {
    return client.get('/commission/settlements', { params });
  },
  listPayouts(params = {}) {
    return client.get('/commission/payouts', { params });
  },
  processPayout(id, data) {
    return client.put(`/commission/payouts/${id}`, data);
  },
  listReferralLinks(params = {}) {
    return client.get('/commission/referral-links', { params });
  },
  createReferralLink(data) {
    return client.post('/commission/referral-links', data);
  },
  deleteReferralLink(id) {
    return client.delete(`/commission/referral-links/${id}`);
  },
  // 代理端
  myCommission() {
    return client.get('/commission/my');
  },
  requestPayout(data) {
    return client.post('/commission/payouts', data);
  },
  // ⭐ 佣金风控 M2-127b
  risk: {
    dashboard() {
      return client.get('/commission/risk/dashboard');
    },
    listNegativeBalance(params = {}) {
      return client.get('/commission/risk/negative-balance', { params });
    },
    showNegativeBalance(id) {
      return client.get(`/commission/risk/negative-balance/${id}`);
    },
    clearNegativeBalance(id, data) {
      return client.post(`/commission/risk/negative-balance/${id}/clear`, data);
    },
    listPendingReviewPayouts(params = {}) {
      return client.get('/commission/risk/payouts/pending-review', { params });
    },
    reviewPayout(id, data) {
      return client.post(`/commission/risk/payouts/${id}/review`, data);
    },
    runTask(task) {
      return client.post('/commission/risk/run-task', { task });
    },
  },
};
