import request from '@/utils/request';

const fingerprintDrift = {
    dashboard() {
        return request({ url: '/fingerprint-drift/dashboard', method: 'get' });
    },
    pending(params) {
        return request({ url: '/fingerprint-drift/pending', method: 'get', params });
    },
    deviceHistory(deviceId) {
        return request({ url: `/fingerprint-drift/device/${deviceId}`, method: 'get' });
    },
    recordSnapshot(data) {
        return request({ url: '/fingerprint-drift/snapshot', method: 'post', data });
    },
    acceptDrift(historyId, data) {
        return request({ url: `/fingerprint-drift/${historyId}/accept`, method: 'post', data });
    },
};

export default fingerprintDrift;
