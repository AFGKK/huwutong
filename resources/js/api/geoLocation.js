import request from '@/utils/request';

const geoLocation = {
    dashboard(params) {
        return request({ url: '/admin/geo-location/dashboard', method: 'get', params });
    },
    stats(params) {
        return request({ url: '/admin/geo-location/stats', method: 'get', params });
    },
    mapData(params) {
        return request({ url: '/admin/geo-location/map-data', method: 'get', params });
    },
    records(params) {
        return request({ url: '/admin/geo-location/records', method: 'get', params });
    },
    record(data) {
        return request({ url: '/admin/geo-location/record', method: 'post', data });
    },
    blacklist() {
        return request({ url: '/admin/geo-location/blacklist', method: 'get' });
    },
    updateBlacklist(data) {
        return request({ url: '/admin/geo-location/blacklist', method: 'put', data });
    },
};

export default geoLocation;
