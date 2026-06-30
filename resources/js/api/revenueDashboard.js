import client from './client';

export default {
  dashboard() {
    return client.get('/admin/revenue/dashboard');
  },
  overview() {
    return client.get('/admin/revenue/overview');
  },
  channelRoi() {
    return client.get('/admin/revenue/channel-roi');
  },
  channelTrend(params = {}) {
    return client.get('/admin/revenue/channel-trend', { params });
  },
  channelQuality() {
    return client.get('/admin/revenue/channel-quality');
  },
  revenueTrend(params = {}) {
    return client.get('/admin/revenue/revenue-trend', { params });
  },
  paymentMethods() {
    return client.get('/admin/revenue/payment-methods');
  },
  agentLevels() {
    return client.get('/admin/revenue/agent-levels');
  },
  agentLeaderboard(params = {}) {
    return client.get('/admin/revenue/agent-leaderboard', { params });
  },
  monthlyReport(params = {}) {
    return client.get('/admin/revenue/monthly-report', { params });
  },
};
