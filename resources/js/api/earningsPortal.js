import client from './client';

export default {
  dashboard() {
    return client.get('/portal/earnings/dashboard');
  },
  commissions(params = {}) {
    return client.get('/portal/earnings/commissions', { params });
  },
  channels() {
    return client.get('/portal/earnings/channels');
  },
  saveAccount(channel, accountInfo) {
    return client.post('/portal/earnings/channels/account', { channel, account_info: accountInfo });
  },
  deleteAccount(channel) {
    return client.delete(`/portal/earnings/channels/account/${channel}`);
  },
  getPreferences() {
    return client.get('/portal/earnings/preferences');
  },
  savePreferences(data) {
    return client.put('/portal/earnings/preferences', data);
  },

  // ── M3-74 补充 ──
  getTaxInfo() {
    return client.get('/portal/earnings/tax-info');
  },
  saveTaxInfo(data) {
    return client.post('/portal/earnings/tax-info', data);
  },
  settlementCalendar() {
    return client.get('/portal/earnings/settlement-calendar');
  },
  exportCommissions(params = {}) {
    return client.get('/portal/earnings/commissions/export', { params, responseType: 'blob' });
  },
};
