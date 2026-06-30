import request from '@/utils/request';

export function getSdkVersions() {
    return request.get('/sdk/versions');
}

export function getSdkExample(language, action = 'activate') {
    return request.get('/sdk/example', { params: { language, action } });
}

export function getSdkMatrix() {
    return request.get('/sdk/matrix');
}
