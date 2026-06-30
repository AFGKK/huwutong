import request from '@/utils/request';

export function testSlack(webhookUrl) {
    return request.post('/im/slack/test', { webhook_url: webhookUrl });
}

export function testDingTalk(webhookUrl) {
    return request.post('/im/dingtalk/test', { webhook_url: webhookUrl });
}

export function testWeCom(webhookUrl) {
    return request.post('/im/wecom/test', { webhook_url: webhookUrl });
}

export function testFeishu(webhookUrl) {
    return request.post('/im/feishu/test', { webhook_url: webhookUrl });
}

export function sendImMessage(data) {
    return request.post('/im/send', data);
}
