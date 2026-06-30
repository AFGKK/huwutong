<!-- ─── Live Chat 在线客服 Widget (M2-103) ─── -->
<div id="live-chat-root"></div>

<style>
#live-chat-root {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 9999;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.lc-btn {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: linear-gradient(135deg, #409eff, #337ecc);
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(64, 158, 255, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.2s;
    color: #fff;
    font-size: 24px;
}
.lc-btn:hover { transform: scale(1.08); }
.lc-btn svg { width: 24px; height: 24px; fill: currentColor; }

.lc-window {
    width: 360px;
    height: 520px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.15);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.lc-header {
    background: linear-gradient(135deg, #409eff, #337ecc);
    color: #fff;
    padding: 14px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.lc-header-title { font-weight: 600; font-size: 14px; }
.lc-header-status {
    font-size: 11px; background: rgba(255,255,255,0.2);
    padding: 2px 8px; border-radius: 10px;
}
.lc-header-actions { display: flex; gap: 4px; }
.lc-header-actions button {
    background: none; border: none; color: #fff; cursor: pointer;
    padding: 2px 6px; border-radius: 4px; font-size: 16px;
}
.lc-header-actions button:hover { background: rgba(255,255,255,0.15); }

.lc-messages {
    flex: 1; overflow-y: auto; padding: 16px; background: #f5f7fa;
}
.lc-welcome { text-align: center; padding: 24px 16px; }
.lc-welcome-avatar { font-size: 48px; margin-bottom: 12px; }
.lc-welcome p { color: #606266; font-size: 14px; margin-bottom: 16px; }
.lc-suggestions { display: flex; flex-wrap: wrap; gap: 6px; justify-content: center; }
.lc-suggestion {
    padding: 4px 12px; background: #fff; border: 1px solid #dcdfe6;
    border-radius: 14px; font-size: 12px; color: #606266; cursor: pointer;
    transition: all 0.2s;
}
.lc-suggestion:hover { border-color: #409eff; color: #409eff; }

.lc-msg { display: flex; gap: 6px; margin-bottom: 16px; align-items: flex-start; }
.lc-msg-user { justify-content: flex-end; }
.lc-msg-agent, .lc-msg-ai { justify-content: flex-start; }
.lc-msg-avatar-wrap { display: flex; flex-direction: column; align-items: center; gap: 2px; flex-shrink: 0; }
.lc-msg-avatar-wrap-right { flex-direction: row; gap: 4px; align-items: center; }
.lc-msg-avatar {
    width: 32px; height: 32px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; flex-shrink: 0;
}
.lc-msg-name { font-size: 10px; color: #909399; white-space: nowrap; max-width: 48px; overflow: hidden; text-overflow: ellipsis; text-align: center; }
.lc-msg-body { display: flex; flex-direction: column; gap: 2px; max-width: 75%; }
.lc-msg-content {
    padding: 9px 13px; border-radius: 10px;
    font-size: 13px; line-height: 1.5; word-break: break-word;
}
.lc-msg-user .lc-msg-content {
    background: #409eff; color: #fff;
    border-radius: 10px 10px 0 10px;
}
.lc-msg-ai .lc-msg-content {
    background: #fff; color: #303133;
    border: 1px solid #e4e7ed;
    border-radius: 10px 10px 10px 0;
}
.lc-msg-agent .lc-msg-content {
    background: #e8f5e9; color: #2e7d32;
    border: 1px solid #c8e6c9;
}

.lc-handoff-notice {
    text-align: center; padding: 10px; background: #fff3e0;
    border-radius: 8px; margin-bottom: 12px;
    font-size: 12px; color: #e65100;
}

.lc-typing { display: flex; gap: 4px; padding: 8px 0; justify-content: center; }
.lc-typing span {
    width: 6px; height: 6px; background: #909399; border-radius: 50%;
    animation: lc-blink 1.4s infinite;
}
.lc-typing span:nth-child(2) { animation-delay: 0.2s; }
.lc-typing span:nth-child(3) { animation-delay: 0.4s; }
@keyframes lc-blink { 0%,80%,100% { opacity: 0; } 40% { opacity: 1; } }

.lc-input-wrap {
    padding: 8px 12px; border-top: 1px solid #ebeef5; background: #fff;
    display: flex; gap: 8px;
}
.lc-input-wrap input {
    flex: 1; border: 1px solid #dcdfe6; border-radius: 8px;
    padding: 8px 12px; font-size: 13px; outline: none;
}
.lc-input-wrap input:focus { border-color: #409eff; }
.lc-input-wrap button {
    padding: 8px 16px; background: #409eff; color: #fff;
    border: none; border-radius: 8px; cursor: pointer; font-size: 13px;
}
.lc-input-wrap button:disabled { opacity: 0.5; cursor: not-allowed; }
.lc-input-wrap button:hover:not(:disabled) { background: #337ecc; }
</style>

<script>
(function() {
    var root = document.getElementById('live-chat-root');
    if (!root) return;

    var state = { open: false, conversationId: null, messages: [], loading: false, handoffNotice: null };

    function render() {
        if (!state.open) {
            root.innerHTML =
                '<button class="lc-btn" onclick="window.__lcOpen()" title="在线客服">' +
                '<svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12c0 1.88.54 3.63 1.48 5.12L2 22l5.12-1.48C8.37 21.46 10.12 22 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2z"/></svg>' +
                '</button>';
            return;
        }

        var msgsHtml = '';
        if (state.messages.length === 0) {
            msgsHtml =
                '<div class="lc-welcome">' +
                '<div class="lc-welcome-avatar">🤖</div>' +
                '<p>您好！我是互物通智能客服助手，请问有什么可以帮您？</p>' +
                '<div class="lc-suggestions">' +
                '<span class="lc-suggestion" onclick="window.__lcSend(\'如何激活 License？\')">如何激活 License？</span>' +
                '<span class="lc-suggestion" onclick="window.__lcSend(\'License 已过期怎么办？\')">License 已过期怎么办？</span>' +
                '<span class="lc-suggestion" onclick="window.__lcSend(\'设备数量超出限制\')">设备数量超出限制</span>' +
                '<span class="lc-suggestion" onclick="window.__lcSend(\'转人工客服\')">转人工客服</span>' +
                '</div></div>';
        } else {
            msgsHtml = state.messages.map(function(m) {
                var roleClass = m.role === 'user' ? 'lc-msg-user' : (m.role === 'agent' ? 'lc-msg-agent' : 'lc-msg-ai');
                var isUser = m.role === 'user';
                var avatar = isUser ? '👤' : (m.role === 'agent' ? '👤' : '🤖');
                var name = isUser ? '我' : (m.role === 'agent' ? '人工客服' : '智能客服');
                var avatarStyle = isUser ? 'style="background:#409eff;color:#fff;"' : 'style="background:#e8eef5;color:#606266;"';
                var content = (m.content || '').replace(/\n/g, '<br>');
                return '<div class="lc-msg ' + roleClass + '">' +
                    (isUser ? '' : '<div class="lc-msg-avatar-wrap"><div class="lc-msg-avatar" ' + avatarStyle + '>' + avatar + '</div><div class="lc-msg-name">' + name + '</div></div>') +
                    '<div class="lc-msg-body">' +
                    '<div class="lc-msg-content">' + content + '</div>' +
                    '</div>' +
                    (isUser ? '<div class="lc-msg-avatar-wrap lc-msg-avatar-wrap-right"><div class="lc-msg-name">' + name + '</div><div class="lc-msg-avatar" ' + avatarStyle + '>' + avatar + '</div></div>' : '') +
                    '</div>';
            }).join('');
        }

        var handoffHtml = state.handoffNotice
            ? '<div class="lc-handoff-notice">⏳ 正在为您转接人工客服，请稍候...</div>'
            : '';

        var typingHtml = state.loading
            ? '<div class="lc-msg lc-msg-ai"><div class="lc-msg-avatar-wrap"><div class="lc-msg-avatar" style="background:#e8eef5;color:#606266;">🤖</div><div class="lc-msg-name">智能客服</div></div><div class="lc-msg-body"><div class="lc-msg-content"><div class="lc-typing"><span></span><span></span><span></span></div></div></div></div>'
            : '';

        root.innerHTML =
            '<div class="lc-window">' +
            '<div class="lc-header">' +
            '<div><span class="lc-header-title">🤖 互物通智能客服</span> <span class="lc-header-status">在线</span></div>' +
            '<div class="lc-header-actions">' +
            '<button onclick="window.__lcMinimize()">−</button>' +
            '<button onclick="window.__lcClose()">✕</button>' +
            '</div></div>' +
            '<div class="lc-messages" id="lc-msgs">' + msgsHtml + handoffHtml + typingHtml + '</div>' +
            '<div class="lc-input-wrap">' +
            '<input type="text" id="lc-input" placeholder="输入您的问题..." onkeydown="if(event.key===\'Enter\')window.__lcSend()" />' +
            '<button onclick="window.__lcSend()">发送</button>' +
            '</div></div>';

        // 滚动到底部
        var el = document.getElementById('lc-msgs');
        if (el) el.scrollTop = el.scrollHeight;
        // 聚焦输入框
        var inp = document.getElementById('lc-input');
        if (inp) inp.focus();
    }

    window.__lcOpen = function() { state.open = true; render(); };
    window.__lcMinimize = function() { state.open = false; render(); };
    window.__lcClose = function() {
        state.open = false;
        state.messages = [];
        state.conversationId = null;
        state.handoffNotice = null;
        render();
    };

    window.__lcSend = function(text) {
        var inp = document.getElementById('lc-input');
        var msg = text || (inp ? inp.value.trim() : '');
        if (!msg || state.loading) return;
        if (inp) inp.value = '';

        state.messages.push({ role: 'user', content: msg });
        state.loading = true;
        render();

        var apiBase = '/api';
        var createConv = function() {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', apiBase + '/live-chat/conversations', true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.onload = function() {
                try {
                    var d = JSON.parse(xhr.responseText);
                    state.conversationId = d.data?.id || d.data?.data?.id;
                    if (state.conversationId) sendMsg();
                    else { state.loading = false; render(); }
                } catch(e) { state.loading = false; render(); }
            };
            xhr.onerror = function() { state.loading = false; render(); };
            xhr.send(JSON.stringify({ source: 'widget' }));
        };

        var sendMsg = function() {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', apiBase + '/live-chat/conversations/' + state.conversationId + '/messages', true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.onload = function() {
                state.loading = false;
                try {
                    var d = JSON.parse(xhr.responseText);
                    var data = d.data || d;
                    if (data.reply) {
                        state.messages.push({ role: 'assistant', content: data.reply.content || data.reply });
                    }
                    if (data.handoff) {
                        state.handoffNotice = { handoff_id: data.handoff.id || data.handoff };
                        state.messages.push({ role: 'assistant', content: '⏳ 正在为您转接人工客服，请稍候...' });
                    }
                } catch(e) {}
                render();
            };
            xhr.onerror = function() {
                state.loading = false;
                state.messages.push({ role: 'assistant', content: '抱歉，连接失败。请稍后再试。' });
                render();
            };
            xhr.send(JSON.stringify({ content: msg }));
        };

        if (state.conversationId) { sendMsg(); }
        else { createConv(); }
    };

    render();
})();
</script>
