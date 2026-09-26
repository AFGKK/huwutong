// Compatible shim: supports both request.get(url) and request({ url, method })
import apiClient from '../api/client';

function request(configOrUrl, maybeConfig) {
    if (typeof configOrUrl === 'string') {
        return apiClient(configOrUrl, maybeConfig);
    }
    const { url, method = 'get', data, params, ...rest } = configOrUrl || {};
    return apiClient({
        url,
        method,
        data,
        params,
        ...rest,
    });
}

request.get = (url, config) => apiClient.get(url, config);
request.post = (url, data, config) => apiClient.post(url, data, config);
request.put = (url, data, config) => apiClient.put(url, data, config);
request.patch = (url, data, config) => apiClient.patch(url, data, config);
request.delete = (url, config) => apiClient.delete(url, config);

export default request;
