import request from '@/utils/request';

const licenseHealth = {
    dashboard() {
        return request({ url: '/portal/license-health/dashboard', method: 'get' });
    },
    list() {
        return request({ url: '/portal/license-health', method: 'get' });
    },
    show(licenseId) {
        return request({ url: `/portal/license-health/${licenseId}`, method: 'get' });
    },
};

export default licenseHealth;
