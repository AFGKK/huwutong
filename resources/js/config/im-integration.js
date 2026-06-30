// M2-57 IM 通知集成 — 前端配置（反映后端 config/im-integration.php）
export default {
    auto_send: {
        'license · activated': ['slack', 'dingtalk'],
        'license · expired': ['slack', 'dingtalk', 'wecom'],
        'license · expiring soon': ['slack', 'dingtalk', 'wecom'],
        'license · revoked': ['slack', 'dingtalk'],
        'payment · success': ['slack'],
        'payment · failed': ['slack', 'dingtalk', 'wecom'],
        'alert · critical': ['slack', 'dingtalk', 'wecom', 'feishu'],
        'alert · high': ['slack', 'dingtalk'],
    },
};
