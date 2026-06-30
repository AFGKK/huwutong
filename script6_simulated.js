







    let _token = localStorage.getItem('auth_token');







    let _currentRating = 5;







    function getAuthHeaders() {







        const h = { 'Accept': 'application/json', 'Content-Type': 'application/json' };







        if (_token) h['Authorization'] = 'Bearer ' + _token;







        return h;







    }







    // ─── 收藏功能 ───







    async function toggleWishlist(productId, event) {
        if (!_token) { window.location.href = '/build/login?redirect=' + encodeURIComponent(window.location.href); return; }

        try {
            const r = await fetch('/api/wishlist/toggle', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': _token },
                body: JSON.stringify({ product_id: productId }),
            });
            const data = await r.json();
            if (data.success) {
                const btn = event ? event.currentTarget : document.getElementById('detail-wishlist-btn');
                if (btn) {
                    const isWishlisted = data.data?.wishlisted;
                    if (isWishlisted) {
                        btn.classList.add('text-red-500', 'border-red-300');
                        btn.classList.remove('text-gray-500');
                    } else {
                        btn.classList.remove('text-red-500', 'border-red-300');
                        btn.classList.add('text-gray-500');
                    }
                }
                if (data.message) showToast(data.message);
            }
        } catch(e) { console.error(e); }
    }

    function renderStars(rating) {







        return Array.from({length:5}, (_,i) => `<span class="${i < rating ? 'text-yellow-400' : 'text-gray-300'}">★</span>`).join('');







    }







    async function loadReviews(productId) {







        try {







            // Stats







            const statsRes = await fetch('/api/products/' + productId + '/reviews/stats', { headers: getAuthHeaders() });







            const statsData = await statsRes.json();







            const stats = statsData.data;







            if (stats && stats.total_reviews > 0) {







                document.getElementById('review-stats').classList.remove('hidden');







                document.getElementById('avg-rating').textContent = (stats.avg_rating || 0).toFixed(1);







                document.getElementById('rating-stars').innerHTML = renderStars(Math.round(stats.avg_rating || 0));







                document.getElementById('rating-count').textContent = stats.total_reviews + ' 条评价';







                const dist = stats.distribution || {};







                const barsHtml = [5,4,3,2,1].map(star =>







                    `<div class="flex items-center gap-2 text-sm"><span class="w-6 text-gray-500">${star}</span><div class="flex-1 bg-gray-100 rounded-full h-2"><div class="bg-yellow-400 h-2 rounded-full" style="width:${(stats.percentages||[])[star-1]||0}%"></div></div><span class="w-8 text-right text-gray-400">${dist[star]||0}</span></div>`







                ).join('');







                document.getElementById('rating-bars').innerHTML = barsHtml;







            }







            // Reviews







            const revRes = await fetch('/api/products/' + productId + '/reviews?per_page=10', { headers: getAuthHeaders() });







            const revData = await revRes.json();







            const reviews = revData.data?.data || [];







            const container = document.getElementById('reviews-container');







            if (reviews.length === 0) {







                container.innerHTML = '<div class="text-center py-12 text-gray-400"><svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg><p>暂无评价，来写第一条吧</p></div>';







            } else {







                container.innerHTML = reviews.map(r => `







                    <div class="bg-white rounded-xl p-5 border border-gray-100">







                        <div class="flex items-center gap-3 mb-2">







                            <div class="w-8 h-8 rounded-full overflow-hidden flex-shrink-0 bg-primary-100 flex items-center justify-center">







                                ${r.user?.avatar_url ? `<img src="${r.user.avatar_url}" alt="" class="w-full h-full object-cover" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'" />` : ''}







                                <span class="text-primary-600 font-bold text-sm" ${r.user?.avatar_url ? 'style="display:none"' : ''}>${(r.user?.name || '?')[0]}</span>







                            </div>







                            <div><div class="text-sm font-medium">${r.is_anonymous ? '匿名用户' : (r.user?.name || '用户')}</div><div class="text-yellow-400 text-sm">${renderStars(r.rating)}</div></div>







                            <span class="ml-auto text-xs text-gray-400">${(r.created_at || '').substring(0,10)}</span>







                        </div>







                        <div class="text-sm text-gray-600 ml-11">${r.content}</div>







                        ${r.admin_reply ? `<div class="ml-11 mt-2 p-3 bg-gray-50 rounded-lg text-sm text-gray-500"><span class="font-medium">商家回复：</span>${r.admin_reply}</div>` : ''}







                    </div>







                `).join('');







            }







        } catch (e) { console.error(e); }







    }







    async function submitReview(productId) {







        if (!_token) { window.location.href = '/build/login?redirect=' + encodeURIComponent(window.location.href); return; }







    // ─── 暗色模式切换 ───







    function toggleDarkMode() {







        const html = document.documentElement;







        const isDark = html.getAttribute('data-theme') === 'dark';







        if (isDark) {







            html.removeAttribute('data-theme');







            localStorage.setItem('huwutong_theme', 'light');







        } else {







            html.setAttribute('data-theme', 'dark');







            localStorage.setItem('huwutong_theme', 'dark');







        }







        updateDarkToggleIcon();







    }







    function updateDarkToggleIcon() {







        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';







        const sun = document.querySelector('.dark-toggle-sun');







        const moon = document.querySelector('.dark-toggle-moon');







        if (sun && moon) {







            sun.classList.toggle('hidden', !isDark);







            moon.classList.toggle('hidden', isDark);







        }







    }







    document.addEventListener('DOMContentLoaded', updateDarkToggleIcon);







    function switchImage(btn, url) {







        document.querySelectorAll('.gallery-thumb').forEach(t => t.classList.remove('border-primary-500'));







        btn.classList.add('border-primary-500');







        document.getElementById('main-image').innerHTML = `<img src="${url}" class="w-full h-full object-cover">`;







    }







    // ─── Token 登录───★€







    (function() {







        const token = localStorage.getItem('auth_token');







        if (!token) return;







        _token = token;







        if (document.querySelector('#session-user-section')) return;







        fetch('/api/user', {







            headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' },







            credentials: 'same-origin',







        }).then(r => r.json()).then(res => {







            if (!res.data) return;







            const u = res.data;







            const initial = (u.name || '?').charAt(0).toUpperCase();







            const avatarHtml = u.avatar_url







                ? `<img src="${u.avatar_url}" alt="" class="w-8 h-8 rounded-full object-cover bg-gray-200" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">`







                : '';







            const fallbackHtml = `<div class="w-8 h-8 rounded-full bg-primary-100 text-primary-600 text-sm font-bold flex items-center justify-center"${u.avatar_url ? ' style="display:none"' : ''}>${initial}</div>`;







            // 桌面★★€







            const guestLinks = document.querySelector('.guest-links-desktop');







            if (guestLinks) {







                guestLinks.innerHTML =







                    '<a href="/build/cart" class="relative">购物车<span id="cart-badge-desktop" class="absolute -top-2 -right-4 bg-red-500 text-white text-xs rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1 hidden">0</span></a>' +







                    '<div class="flex items-center gap-2 pl-4 border-l border-gray-200">' +







                        avatarHtml +







                        fallbackHtml +







                        '<span class="text-sm font-medium text-gray-700">' + u.name + '</span>' +







                        '<a href="/build/logout" id="logout-link-desktop">退出</a>' +







                    '</div>';







            }







            // 移动★★€







            const mobileGuestLinks = document.querySelector('.guest-links-mobile');







            if (mobileGuestLinks) {







                mobileGuestLinks.innerHTML =







                    '<div class="flex items-center gap-3 py-2 border-b border-gray-100 mb-2">' +







                        (u.avatar_url







                            ? `<img src="${u.avatar_url}" alt="" class="w-10 h-10 rounded-full object-cover bg-gray-200">`







                            : `<div class="w-10 h-10 rounded-full bg-primary-100 text-primary-600 font-bold flex items-center justify-center">${initial}</div>`) +







                        '<div><div class="text-sm font-medium text-gray-900">' + u.name + '</div><div class="text-xs text-gray-500">' + (u.email || '') + '</div></div>' +







                    '</div>' +







                    '<a href="/build/cart">购物车</a>' +







                    '<a href="/build/logout" id="logout-link-mobile">退出</a>';







            }







            // 更新★★★车★★€







            fetch('/api/cart/summary', {







                headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' },







            }).then(r => r.json()).then(d => {







                if (d.success) {







                    const count = d.data?.total_quantity || 0;







                    const badge = document.getElementById('cart-badge-desktop');







                    if (badge) { badge.textContent = count; badge.classList.toggle('hidden', count === 0); }







                }







            }).catch(() => {});







        }).catch(() => {});







    })();







    // ─── 初始化：收藏状★€? 评★加载 ───







    checkWishlist(4);







    loadReviews(4);







    setRating(5);







    // ─── ★★★车功能─★€★€







    const _isAuth = test;







    async function addToCart(skuId) {







        if (!_isAuth) {







            window.location.href = '/build/login?redirect=' + encodeURIComponent(window.location.href);







            return;







        }







        try {







            const res = await fetch('/api/cart/add', {







                method: 'POST',







                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'Authorization': 'Bearer ' + _token },







                body: JSON.stringify({ sku_id: skuId, quantity: 1 })







            });







            const data = await res.json();







            if (data.success) {







                const badge = document.getElementById('cart-badge-desktop');







                if (badge) {







                    const count = (parseInt(badge.textContent) || 0) + 1;







                    badge.textContent = count;







                    badge.classList.remove('hidden');







                }







                showToast('已成功添加到购物车');







            } else {







                showToast(data.message || '添加失败');







            }







        } catch (e) {







            showToast('请先登录后再操作');







        }







    }







    function prevImage() {







        if (_lightboxImages.length > 1) {







            _lightboxIndex = (_lightboxIndex - 1 + _lightboxImages.length) % _lightboxImages.length;







            showLightboxImage();







        }







    }







    function nextImage() {







        if (_lightboxImages.length > 1) {







            _lightboxIndex = (_lightboxIndex + 1) % _lightboxImages.length;







            showLightboxImage();







        }







    }







    document.addEventListener('keydown', function(e) {







        const lb = document.getElementById('image-lightbox');







        if (!lb || lb.classList.contains('hidden')) return;







        if (e.key === 'Escape') closeLightbox();







        if (e.key === 'ArrowLeft') prevImage();







        if (e.key === 'ArrowRight') nextImage();







    });







    // ─── IM 客服聊天 ───







    let _chatSessionId = localStorage.getItem('im_chat_session') || 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);







    localStorage.setItem('im_chat_session', _chatSessionId);







    let _chatLoading = false;







    let _unreadCount = 0;







    let _lastMsgCount = 0;







    let _pollTimer = null;







    let _lastMsgDate = ''; // 用于时间分组







    function clearUnreadBadge() {







        _unreadCount = 0;







        const badge = document.getElementById('chat-unread-badge');







        if (badge) { badge.classList.add('hidden'); badge.textContent = '0'; }







    }







    function updateUnreadBadge() {







        const badge = document.getElementById('chat-unread-badge');







        if (!badge) return;







        const dlg = document.getElementById('im-chat-dialog');







        if (!dlg.classList.contains('hidden')) { clearUnreadBadge(); return; }







        if (_unreadCount > 0) {







            badge.textContent = _unreadCount > 99 ? '99+' : String(_unreadCount);







            badge.classList.remove('hidden');







        } else {







            badge.classList.add('hidden');







        }







    }







    // ★新消★€







    function startPolling() {







        if (_pollTimer) return;







        _pollTimer = setInterval(async () => {







            const dlg = document.getElementById('im-chat-dialog');







            if (!dlg.classList.contains('hidden')) return;







            try {







                const res = await fetch('/api/chat/history?session_id=' + encodeURIComponent(_chatSessionId), {







                    headers: { 'Accept': 'application/json' }







                });







                const data = await res.json();







                if (data.success && data.data?.messages?.length > _lastMsgCount) {







                    const newCount = data.data.messages.length - _lastMsgCount;







                    _unreadCount += newCount;







                    _lastMsgCount = data.data.messages.length;







                    updateUnreadBadge();







                }







            } catch {}







        }, 10000); // ★€0秒轮★€







    }







    function stopPolling() {







        if (_pollTimer) { clearInterval(_pollTimer); _pollTimer = null; }







    }







    function toggleChat() {







        const dialog = document.getElementById('im-chat-dialog');







        const btn = document.getElementById('im-chat-btn');







        const isOpen = !dialog.classList.contains('hidden');







        if (isOpen) {







            dialog.classList.add('hidden');







            dialog.classList.remove('flex');







            btn.classList.remove('hidden');







        } else {







            dialog.classList.remove('hidden');







            dialog.classList.add('flex');







            btn.classList.add('hidden');







            clearUnreadBadge();







            loadChatHistory();







            // 首★打开发★€★★迎★







            setTimeout(() => {







                const welcomed = sessionStorage.getItem('chat_welcomed_' + _chatSessionId);







                if (!welcomed) {







                    sessionStorage.setItem('chat_welcomed_' + _chatSessionId, '1');







                    sendWelcomeMessage();







                }







            }, 500);







            setTimeout(() => document.getElementById('chat-<input>name }}';







        const sellerName = 'test';







        const welcome = '您好！我★€ + sellerName + '的智能★服助手，很高兴为您服务★€★😊\n\n' +







            '关于 **' + name + '**，您★★★：\n' +







            '💬 直接输入★咨★\n' +







            '📋 点击下方「常见问题★€★快速提问\n' +







            '📦 点击「发送商品★€★查看商品★情\n' +







            '👤 如需人工服务，点击★€★转人工★€;







        appendChatMessage({ role: 'assistant', content: welcome, timestamp: new Date().toISOString() });







        scrollChatToBottom();







    }







    async function loadChatHistory() {







        try {







            const res = await fetch('/api/chat/history?session_id=' + encodeURIComponent(_chatSessionId), {







                headers: { 'Accept': 'application/json' }







            });







            const data = await res.json();







            if (data.success && data.data?.messages?.length > 0) {







                _lastMsgCount = data.data.messages.length;







                const container = document.getElementById('chat-messages');







                container.innerHTML = '';







                data.data.messages.forEach(msg => appendChatMessage(msg));







                scrollChatToBottom();







            }







        } catch {}







    }







    async function sendChatMessage() {







        const <input><div class="w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center shrink-0 text-primary-600 font-bold text-xs">★</div><div class="chat-msg-bubble bg-white rounded-2xl rounded-tl-sm px-4 py-3 shadow-sm border border-gray-100"><div class="flex gap-1"><span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0s"></span><span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0.15s"></span><span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0.3s"></span></div></div></div>`;







        scrollChatToBottom();







        try {







            const res = await fetch('/api/chat/send', {







                method: 'POST',







                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': 'csrf-token' },







                body: JSON.stringify({ message: msg, session_id: _chatSessionId })







            });







            const data = await res.json();







            document.getElementById(loadingId)?.remove();







            if (data.success && data.data) {







                const reply = data.data.reply || data.data.message || data.data.content || '';







                if (reply) {







                    appendChatMessage({ role: 'assistant', content: reply, timestamp: new Date().toISOString() });







                } else {







                    appendChatMessage({ role: 'assistant', content: '感谢您的咨★，★服将尽快回★您★€€, timestamp: new Date().toISOString() });







                }







            } else {







                appendChatMessage({ role: 'assistant', content: '抱歉，暂时无法响应，请稍后再试★€€, timestamp: new Date().toISOString() });







            }







        } catch {







            document.getElementById(loadingId)?.remove();







            appendChatMessage({ role: 'assistant', content: '网络异常，★★€查连接后重试★€, timestamp: new Date().toISOString() });







        }







        scrollChatToBottom();







        _chatLoading = false;







    }







    function appendChatMessage(msg) {







        const container = document.getElementById('chat-messages');







        const isUser = msg.role === 'user';







        const msgDate = msg.timestamp ? new Date(msg.timestamp) : new Date();







        const time = msgDate.toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit' });







        // 时间分组：★测日期变★€







        const dateKey = msgDate.getFullYear() + '-' + (msgDate.getMonth()+1) + '-' + msgDate.getDate();







        if (dateKey !== _lastMsgDate) {







            _lastMsgDate = dateKey;







            const today = new Date();







            const todayKey = today.getFullYear() + '-' + (today.getMonth()+1) + '-' + today.getDate();







            const yesterday = new Date(today);







            yesterday.setDate(yesterday.getDate() - 1);







            const yesterdayKey = yesterday.getFullYear() + '-' + (yesterday.getMonth()+1) + '-' + yesterday.getDate();







            let label;







            if (dateKey === todayKey) label = '今天';







            else if (dateKey === yesterdayKey) label = '昨天';







            else label = (msgDate.getMonth()+1) + '★?+ msgDate.getDate() + '★?







            const dividerHtml = '<div class="chat-date-divider"><span>' + label + '</span></div>';







            const lastChild = container.lastElementChild;







            if (!lastChild || !lastChild.classList.contains('chat-date-divider')) {







                container.insertAdjacentHTML('beforeend', dividerHtml);







            }







        }







        // 已★状★€★：客服回★时将上一条用户消★★★记为已★







        if (!isUser) {







            const userMsgs = container.querySelectorAll('.chat-msg-in.flex-row-reverse');







            if (userMsgs.length > 0) {







                const lastUser = userMsgs[userMsgs.length - 1];







                const statusEl = lastUser.querySelector('.msg-status');







                if (statusEl) {







                    statusEl.textContent = '已★';







                }







            }







        }







        const sellerAvatar = 'test';







        const sellerName = 'test';







        const userAvatar = document.querySelector('#session-user-section img')?.src || '';







            : (sellerAvatar ? `<img src="${sellerAvatar}" class="w-full h-full object-cover rounded-full" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'"><span class="text-primary-600 font-bold text-xs" style="display:none">${sellerName}</span>` : `<span class="text-primary-600 font-bold text-xs">${sellerName}</span>`);







        const statusHtml = isUser ? '<span class="msg-status" style="font-size:10px;opacity:0.7">已发送</span>' : '';







        const msgId = 'msg-' + Date.now() + '-' + Math.random().toString(36).substr(2, 6);







        const html = `







            <div id="${msgId}" class="flex items-start gap-2.5 ${isUser ? 'flex-row-reverse' : ''} chat-msg-in${isUser ? ' user-msg' : ''}" data-time="${msg.timestamp ? new Date(msg.timestamp).getTime() : Date.now()}"







                 oncontextmenu="event.preventDefault();showMsgMenu('${msgId}', event)"







                 ontouchstart="touchStartMsg(event, '${msgId}')"







                 ontouchend="touchEndMsg(event, '${msgId}')">







                <div class="w-8 h-8 rounded-full ${isUser ? 'bg-primary-100' : 'bg-primary-100'} overflow-hidden flex items-center justify-center shrink-0 font-bold text-xs">







                    ${avatarHtml}







                </div>







                <div class="chat-msg-bubble ${isUser ? 'bg-primary-600 text-white rounded-2xl rounded-tr-sm' : 'bg-white dark-bg-card rounded-2xl rounded-tl-sm shadow-sm border border-gray-100 dark-border'} px-4 py-3">







                    <p class="text-sm leading-relaxed">${escapeHtml(msg.content)}</p>







                    <div class="flex items-center justify-end gap-1 mt-1.5">







                        <span class="text-[10px] ${isUser ? 'text-primary-200' : 'text-gray-400 dark-text-muted'}">${time}</span>







                        ${statusHtml}







                    </div>







                </div>







            </div>







        `;







        // 移除空白提示







        const empty = container.querySelector('.text-center.py-8');







        if (empty) empty.remove();







        container.insertAdjacentHTML('beforeend', html);







    }







    function scrollChatToBottom() {







        const container = document.getElementById('chat-messages');







        setTimeout(() => { container.scrollTop = container.scrollHeight; }, 50);







    }







    // ─── 聊天文件上传 ───







    async function uploadChatFile(<input> maxSize) {







            alert('文件大小不能超过 20MB');







            <input>







                <div class="w-8 h-8 rounded-full bg-primary-100 overflow-hidden flex items-center justify-center shrink-0 font-bold text-xs">







                    <span class="text-primary-600 font-bold text-xs">★</span>







                </div>







                <div class="chat-msg-bubble bg-primary-600 text-white rounded-2xl rounded-tr-sm px-3 py-2">







                    <div class="flex items-center gap-2">







                        <svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>







                        <span class="text-sm">上传中..</span>







                    </div>







                </div>







            </div>`







            : `<div class="flex items-start gap-2.5 flex-row-reverse chat-msg-in">







                <div class="w-8 h-8 rounded-full bg-primary-100 overflow-hidden flex items-center justify-center shrink-0 font-bold text-xs">







                    <span class="text-primary-600 font-bold text-xs">★</span>







                </div>







                <div class="chat-msg-bubble bg-primary-600 text-white rounded-2xl rounded-tr-sm px-3 py-2">







                    <div class="flex items-center gap-2">







                        <svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>







                        <span class="text-sm">上传 ${file.name}...</span>







                    </div>







                </div>







            </div>`;







        container.insertAdjacentHTML('beforeend', loadingHtml);







        scrollChatToBottom();







        try {







            const formData = new FormData();







            formData.append('file', file);







            const res = await fetch('/api/im/upload', {







                method: 'POST',







                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': 'csrf-token' },







                body: formData,







                credentials: 'same-origin',







            });







            const data = await res.json();







            // 移除加载★€







            document.getElementById(loadingId)?.remove();







            if (data.success && data.data) {







                const fileUrl = data.data.url;







                const fileName = data.data.name;







                const fileSize = (data.data.size / 1024).toFixed(1);







                let fileHtml;







                if (isImage) {







                    fileHtml = `<div class="flex items-start gap-2.5 flex-row-reverse chat-msg-in">







                        <div class="w-8 h-8 rounded-full bg-primary-100 overflow-hidden flex items-center justify-center shrink-0 font-bold text-xs">







                            <span class="text-primary-600 font-bold text-xs">★</span>







                        </div>







                        <div class="chat-msg-bubble bg-primary-600 rounded-2xl rounded-tr-sm px-2 py-2 max-w-[260px]">







                            <a href="${fileUrl}" target="_blank" rel="noopener">







                                <img src="${fileUrl}" alt="${fileName}" class="w-full rounded-lg object-cover max-h-[240px]" onerror="this.parentElement.innerHTML=this.alt">







                            </a>







                            <div class="text-[10px] text-primary-200 mt-1 text-right">刚刚</div>







                        </div>







                    </div>`;







                } else {







                    const icon = file.type.includes('pdf') ? '📄' : file.type.includes('zip') || file.type.includes('rar') ? '📦' : '📎';







                    fileHtml = `<div class="flex items-start gap-2.5 flex-row-reverse chat-msg-in">







                        <div class="w-8 h-8 rounded-full bg-primary-100 overflow-hidden flex items-center justify-center shrink-0 font-bold text-xs">







                            <span class="text-primary-600 font-bold text-xs">★</span>







                        </div>







                        <div class="chat-msg-bubble bg-primary-600 text-white rounded-2xl rounded-tr-sm px-4 py-3 max-w-[260px]">







                            <a href="${fileUrl}" target="_blank" rel="noopener" class="flex items-start gap-3">







                                <span class="text-2xl">${icon}</span>







                                <div class="min-w-0">







                                    <div class="text-sm font-medium truncate">${fileName}</div>







                                    <div class="text-[10px] text-primary-200">${fileSize} KB</div>







                                </div>







                            </a>







                            <div class="text-[10px] text-primary-200 mt-1 text-right">刚刚</div>







                        </div>







                    </div>`;







                }







                container.insertAdjacentHTML('beforeend', fileHtml);







                scrollChatToBottom();







                // 同时发★€★消★★★客服 API







                const msg = isImage ? '[图片] ' + fileName : '[文件] ' + fileName;







                fetch('/api/chat/send', {







                    method: 'POST',







                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': 'csrf-token' },







                    body: JSON.stringify({ message: '用户发★€★了' + msg + '★€ + fileUrl, session_id: _chatSessionId })







                }).then(r => r.json()).then(d => {







                    if (d.success && d.data) {







                        const reply = d.data.reply || d.data.message || '';







                        if (reply) appendChatMessage({ role: 'assistant', content: reply, timestamp: new Date().toISOString() });







                    }







                }).catch(() => {});







            } else {







                appendChatMessage({ role: 'user', content: '文件上传失败，★重试', timestamp: new Date().toISOString() });







            }







        } catch (e) {







            document.getElementById(loadingId)?.remove();







            appendChatMessage({ role: 'user', content: '文件上传失败: ' + e.message, timestamp: new Date().toISOString() });







        }







        <input> {







                document.addEventListener('click', closeEmojiOutside, { once: true });







            }, 10);







        }







    







    function closeEmojiOutside(e) {







        const picker = document.getElementById('emoji-picker');







        const btn = document.querySelector('[onclick*="toggleEmojiPicker"]');







        if (picker && !picker.contains(e.target) && btn && !btn.contains(e.target)) {







            picker.classList.add('hidden');







        } else {







            setTimeout(() => {







                document.addEventListener('click', closeEmojiOutside, { once: true });







            }, 10);







        }







    }







    function insertEmoji(emoji) {







        const <input> 0 && (now - msgTime) < 120000;







        const recallBtn = menu.querySelector('button:first-child');







        if (recallBtn) {







            recallBtn.style.display = canRecall ? 'flex' : 'none';







        }







        menu.classList.remove('hidden');







        setTimeout(() => {







            document.addEventListener('click', closeMsgMenu, { once: true });







        }, 10);







    }







    function closeMsgMenu() {







        document.getElementById('chat-context-menu')?.classList.add('hidden');







        _msgMenuTarget = null;







    }







    function touchStartMsg(event, msgId) {







        const el = document.getElementById(msgId);







        if (!el || !el.classList.contains('user-msg')) return;







        _touchTimer = setTimeout(() => {







            _touchTimer = null;







            const touch = event.touches[0];







            showMsgMenu(msgId, { clientX: touch.clientX, clientY: touch.clientY });







        }, 600);







    }







    function touchEndMsg(event, msgId) {







        if (_touchTimer) { clearTimeout(_touchTimer); _touchTimer = null; }







    }







    function deleteMsg() {







        const el = document.getElementById(_msgMenuTarget);







        if (el) {







            el.style.transition = 'all 0.3s ease';







            el.style.opacity = '0';







            el.style.transform = 'translateX(20px)';







            setTimeout(() => el.remove(), 300);







        }







        closeMsgMenu();







    }







    function recallMsg() {







        const el = document.getElementById(_msgMenuTarget);







        if (el) {







            const recallHtml = '<div class="flex justify-center py-2 chat-msg-in"><div class="text-xs text-gray-400 dark:text-gray-500 bg-gray-100 dark:bg-gray-800 px-3 py-1.5 rounded-full">你撤回了一条消息</div></div>';







            el.insertAdjacentHTML('afterend', recallHtml);







            el.remove();







        }







        closeMsgMenu();







    }







    // ─── ★★登录制 ───







    let _mediaRecorder = null;







    let _audioChunks = [];







    let _recordingTimer = null;







    let _recordingSeconds = 0;







    let _audioStream = null;







    async function toggleRecording() {







        const indicator = document.getElementById('voice-recording-indicator');







        const btn = document.getElementById('voice-record-btn');







        const <input> t.stop());







                _audioStream = null;







            }







            return;







        







        // 开始录制







        try {







            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });







            _audioStream = stream;







            _audioChunks = [];







            _recordingSeconds = 0;







            _mediaRecorder = new MediaRecorder(stream, { mimeType: MediaRecorder.isTypeSupported('audio/webm') ? 'audio/webm' : 'audio/mp4' });







            _mediaRecorder.ondataavailable = function(e) {







                if (e.data.size > 0) _audioChunks.push(e.data);







            };







            _mediaRecorder.onstop = async function() {







                const audioBlob = new Blob(_audioChunks, { type: _mediaRecorder.mimeType });







                await uploadVoiceMessage(audioBlob);







            };







            _mediaRecorder.start();







            // 显示录音状★€?







            indicator.classList.remove('hidden');







            indicator.classList.add('flex');







            btn.classList.add('hidden');







            <input> {







                _recordingSeconds++;







                const m = String(Math.floor(_recordingSeconds / 60)).padStart(2, '0');







                const s = String(_recordingSeconds % 60).padStart(2, '0');







                document.getElementById('voice-timer').textContent = m + ':' + s;







                if (_recordingSeconds >= 60) toggleRecording(); // 月镀0★€







            }, 1000);







        } catch (err) {







            alert('无法访问麦克风，请确保已授予麦克风权限');







        }







    






    function cancelRecording() {







        if (_mediaRecorder && _mediaRecorder.state === 'recording') {







            _mediaRecorder.onstop = null;







            _mediaRecorder.stop();







            _mediaRecorder = null;







        }







        if (_recordingTimer) { clearInterval(_recordingTimer); _recordingTimer = null; }







        if (_audioStream) { _audioStream.getTracks().forEach(t => t.stop()); _audioStream = null; }







        _audioChunks = [];







        const indicator = document.getElementById('voice-recording-indicator');







        indicator.classList.add('hidden');







        indicator.classList.remove('flex');







        document.getElementById('voice-record-btn').classList.remove('hidden');







        document.getElementById('chat-<input><div class="w-8 h-8 rounded-full bg-primary-100 overflow-hidden flex items-center justify-center shrink-0 font-bold text-xs"><span class="text-primary-600 font-bold text-xs">★</span></div><div class="chat-msg-bubble bg-primary-600 text-white rounded-2xl rounded-tr-sm px-3 py-2"><div class="flex items-center gap-2"><svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg><span class="text-sm">发送中..</span></div></div></div>');







        scrollChatToBottom();







        try {







            const formData = new FormData();







            const ext = blob.type.includes('webm') ? '.webm' : '.m4a';







            formData.append('file', blob, 'voice-message' + ext);







            const res = await fetch('/api/im/upload', {







                method: 'POST',







                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': 'csrf-token' },







                body: formData,







                credentials: 'same-origin',







            });







            const data = await res.json();







            document.getElementById(loadingId)?.remove();







            if (data.success && data.data) {







                const fileUrl = data.data.url;







                const duration = _recordingSeconds;







                const voiceHtml = '<div class="flex items-start gap-2.5 flex-row-reverse chat-msg-in">' +







                    '<div class="w-8 h-8 rounded-full bg-primary-100 overflow-hidden flex items-center justify-center shrink-0 font-bold text-xs">' +







                    '<span class="text-primary-600 font-bold text-xs">★</span></div>' +







                    '<div class="chat-msg-bubble bg-primary-600 text-white rounded-2xl rounded-tr-sm px-4 py-3 max-w-[280px]">' +







                    '<div class="flex items-center gap-3">' +







                    '<button onclick="playVoice(this)" data-url="' + fileUrl + '" class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center hover:bg-white/30 transition shrink-0">' +







                    '<svg class="w-5 h-5 ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' +







                    '</button>' +







                    '<div class="flex-1 min-w-0">' +







                    '<div class="bg-white/20 rounded-full h-2 relative overflow-hidden">' +







                    '<div class="voice-wave bg-white/40 h-full rounded-full" style="width:40%"></div></div>' +







                    '<div class="text-[10px] text-primary-200 mt-1">' + duration + '"</div></div>' +







                    '</div><div class="text-[10px] text-primary-200 mt-1.5 text-right">刚刚</div></div></div>';







                container.insertAdjacentHTML('beforeend', voiceHtml);







                scrollChatToBottom();







                fetch('/api/chat/send', {







                    method: 'POST',







                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': 'csrf-token' },







                    body: JSON.stringify({ message: '[★★★消息] ' + duration + '★€, session_id: _chatSessionId })







                }).then(r => r.json()).then(d => {







                    if (d.success && d.data) {







                        const reply = d.data.reply || d.data.message || '';







                        if (reply) appendChatMessage({ role: 'assistant', content: reply, timestamp: new Date().toISOString() });







                    }







                }).catch(() => {});







            } else {







                appendChatMessage({ role: 'user', content: '★★★发★€★失★? timestamp: new Date().toISOString() });







            }







        } catch (e) {







            document.getElementById(loadingId)?.remove();







            appendChatMessage({ role: 'user', content: '★★★发★€★失★? timestamp: new Date().toISOString() });







        }







    }







    function playVoice(btn) {







        const url = btn.dataset.url;







        const audio = new Audio(url);







        const icon = btn.querySelector('svg');







        audio.onended = function() {







            icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>';







        };







        audio.play();







        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/>';







    }







    // ─── FAQ 快捷提问 ───







    function toggleFaqQuick() {







        const panel = document.getElementById('faq-quick-panel');







        panel.classList.toggle('hidden');







        if (!panel.classList.contains('hidden')) {







            loadFaqs();







            setTimeout(() => {







                document.addEventListener('click', function closeFaq(e) {







                    const p = document.getElementById('faq-quick-panel');







                    const btn = document.querySelector('[onclick*="toggleFaqQuick"]');







                    if (p && !p.contains(e.target) && btn && !btn.contains(e.target)) {







                        p.classList.add('hidden');







                        document.removeEventListener('click', closeFaq);







                    }







                }, { once: true });







            }, 10);







        }







    }







    async function loadFaqs() {







        const list = document.getElementById('faq-list');







        if (!list || list.dataset.loaded) return;







        try {







            const res = await fetch('/api/chat-faqs', { headers: { 'Accept': 'application/json' } });







            const data = await res.json();







            if (data.success && data.data?.length > 0) {







                list.dataset.loaded = '1';







                list.innerHTML = data.data.map(f =>







                    '<button onclick="sendFaqMessage(\'' + f.question.replace(/'/g, "\\'") + '\')" class="w-full text-left px-4 py-3 text-sm text-gray-700 dark-text hover:bg-gray-50 dark:hover:bg-gray-700 border-b border-gray-50 dark-border transition">' +







                    (f.icon || '💬') + ' ' + f.question +







                    '</button>'







                ).join('');







            } else if (!list.dataset.loaded) {







                list.innerHTML = '<div class="px-4 py-6 text-center text-xs text-gray-400">暂无常见问题</div>';







            }







        } catch {







            if (!list.dataset.loaded) {







                list.innerHTML = '<div class="px-4 py-6 text-center text-xs text-gray-400">加载失败</div>';







            }







        }







    }







    function sendFaqMessage(msg) {







        // 关闭面板







        document.getElementById('faq-quick-panel')?.classList.add('hidden');







        // 清空输入框并聚焦







        const name = 'test-product';







        const image = 'test';







        const url = 'test';







        const price = '¥testtest';







        const container = document.getElementById('chat-messages');







        // 构建商品卡片 HTML







        const cardHtml = `







            <div class="flex items-start gap-2.5 flex-row-reverse chat-msg-in">







                <div class="w-8 h-8 rounded-full bg-primary-100 overflow-hidden flex items-center justify-center shrink-0 font-bold text-xs">







                    <span class="text-primary-600 font-bold text-xs">★</span>







                </div>







                <div class="chat-msg-bubble bg-primary-600 text-white rounded-2xl rounded-tr-sm px-4 py-3 max-w-[320px]">







                    <a href="${url}" class="block">







                        <div class="flex gap-3 p-2">







                            <div class="w-16 h-16 rounded-lg overflow-hidden bg-white/10 shrink-0 flex items-center justify-center">







                                ${image ? `<img src="${image}" alt="${name}" class="w-full h-full object-cover">` : '<svg class="w-6 h-6 text-white/40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>'}







                            </div>







                            <div class="flex-1 min-w-0">







                                <div class="text-sm font-semibold text-white truncate">${name}</div>







                                <div class="text-base font-bold text-yellow-300 mt-1">${price}</div>







                                <div class="text-[10px] text-white/60 mt-1 flex items-center gap-1">







                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>







                                    查看详情







                                </div>







                            </div>







                        </div>







                    </a>







                    <div class="text-[10px] text-primary-200 mt-1.5 text-right">刚刚</div>







                </div>







            </div>







        `;







        // 移除空白提示







        const empty = container.querySelector('.text-center.py-8');







        if (empty) empty.remove();







        container.insertAdjacentHTML('beforeend', cardHtml);







        scrollChatToBottom();







        // 同时发★€★文字消★★★客服 API







        _chatLoading = true;







        const loadingId = 'chat-loading-' + Date.now();







        container.innerHTML += '<div id="' + loadingId + '" class="flex items-start gap-2.5 chat-msg-in"><div class="w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center shrink-0 text-primary-600 font-bold text-xs">test</div><div class="chat-msg-bubble bg-white dark-bg-card rounded-2xl rounded-tl-sm px-4 py-3 shadow-sm border border-gray-100 dark-border"><div class="flex gap-1"><span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0s"></span><span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0.15s"></span><span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0.3s"></span></div></div></div>';







        scrollChatToBottom();







        fetch('/api/chat/send', {







            method: 'POST',







            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': 'csrf-token' },







            body: JSON.stringify({ message: '我想了解这个产品★€ + name + ' - ' + url, session_id: _chatSessionId })







        })







        .then(r => r.json())







        .then(data => {







            document.getElementById(loadingId)?.remove();







            if (data.success && data.data) {







                const reply = data.data.reply || data.data.message || data.data.content || '';







                if (reply) appendChatMessage({ role: 'assistant', content: reply, timestamp: new Date().toISOString() });







                else appendChatMessage({ role: 'assistant', content: '感谢您的咨★！这★★★品是 ' + name + '，★★€了解更★请点击卡片查看★情★€€, timestamp: new Date().toISOString() });







            } else {







                appendChatMessage({ role: 'assistant', content: '感谢您的咨★！我已收到您发★€★的产品信息，★服将尽快为您服务★€, timestamp: new Date().toISOString() });







            }







            scrollChatToBottom();







        })







        .catch(() => {







            document.getElementById(loadingId)?.remove();







            appendChatMessage({ role: 'assistant', content: '已收到您的产品卡片，客服将尽★★★复★€€, timestamp: new Date().toISOString() });







            scrollChatToBottom();







        })







        .finally(() => { _chatLoading = false; });






    }







    // ─── 客服工单 ───







    function requestHandoff() {







        if (!_token && !test) {







            const ans = confirm('您需要登录后才能★★★人工客服。\n★★★前往登录★€);







            if (ans) window.location.href = ans;<div class="w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center shrink-0 text-primary-600 font-bold text-xs">test</div><div class="chat-msg-bubble bg-white dark-bg-card rounded-2xl rounded-tl-sm px-4 py-3 shadow-sm border border-gray-100 dark-border"><div class="flex gap-1"><span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0s"></span><span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0.15s"></span><span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0.3s"></span></div></div></div>';







        scrollChatToBottom();







        // 发★€★转接消★★★ AI 客服







        fetch('/api/chat/send', {







            method: 'POST',







            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': 'csrf-token' },







            body: JSON.stringify({ message: '【转人工】用户★求转接人工★服，请帮助转接★€€, session_id: _chatSessionId })







        })







        .then(r => r.json())







        .then(data => {







            document.getElementById(loadingId)?.remove();







            // 显示加载状态







            const handoffMsg = document.createElement('div');







            handoffMsg.className = 'flex justify-center chat-msg-in';







            handoffMsg.innerHTML = `







                <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-center max-w-[250px]">







                    <div class="flex items-center justify-center gap-2 text-amber-700 text-sm font-medium mb-1">







                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>







                        <span>您的请求已提交</span>







                    </div>







                    <p class="text-xs text-amber-600">正在为您匹配在线客服人员，请稍后</p>







                </div>







            `;







            container.appendChild(handoffMsg);







            scrollChatToBottom();







            // AI 回★







            if (data.success && data.data) {







                const reply = data.data.reply || data.data.message || '';







                if (reply) appendChatMessage({ role: 'assistant', content: reply, timestamp: new Date().toISOString() });







            }







        })







        .catch(() => {







            document.getElementById(loadingId)?.remove();







            appendChatMessage({ role: 'assistant', content: '已收到您的转人工请求，★服人员将尽快接入。★耐心等待★€, timestamp: new Date().toISOString() });







            scrollChatToBottom();







        });







    }







    function escapeHtml(text) {







        const div = document.createElement('div');







        div.textContent = text;







        return div.innerHTML;







    }







    // ─── ★触屏滑动★★★ ───







    (function() {







        let touchStartX = 0;







        let touchEndX = 0;







        const lb = document.getElementById('image-lightbox');







        if (!lb) return;







        lb.addEventListener('touchstart', function(e) {







            touchStartX = e.changedTouches[0].screenX;







        }, { passive: true });







        lb.addEventListener('touchend', function(e) {







            touchEndX = e.changedTouches[0].screenX;







            const diff = touchStartX - touchEndX;







            if (Math.abs(diff) > 50) {







                if (diff > 0) nextImage();







                else prevImage();







            }







        }, { passive: true });







    })();







    // ─── 图片缩放拖拽 ───







    let _zoomLevel = 1;







    let _isPanning = false;







    let _panStartX = 0, _panStartY = 0;







    let _panX = 0, _panY = 0;







    let _pinchDist = 0;







    let _isZoomed = false;







    function resetZoom() {







        _zoomLevel = 1;







        _panX = 0;







        _panY = 0;







        _isZoomed = false;







        applyTransform();







    }







    function toggleZoom(e) {







        if (_zoomLevel > 1) {







            resetZoom();







        } else {







            _zoomLevel = 2.5;







            _panX = 0;







            _panY = 0;







            _isZoomed = true;







            applyTransform();







        }







    }







    function startPinch(e) {







        if (e.touches.length === 2) {







            _pinchDist = Math.hypot(e.touches[0].clientX - e.touches[1].clientX, e.touches[0].clientY - e.touches[1].clientY);







            _isPanning = true;







            _panStartX = e.touches[0].clientX - _panX;







            _panStartY = e.touches[0].clientY - _panY;







        }







    }







    function applyTransform() {







        const img = document.querySelector('#lightbox-image-container img');







        if (!img) return;







        img.style.transform = 'translate(' + _panX + 'px, ' + _panY + 'px) scale(' + _zoomLevel + ')';







    }







    function startPan(e) {







        _isPanning = true;







        _panStartX = e.clientX - _panX;







        _panStartY = e.clientY - _panY;







    }







    function doPan(e) {







        if (!_isPanning) return;







        _panX = e.clientX - _panStartX;







        _panY = e.clientY - _panStartY;







        applyTransform();







    }







    function endPan() {







        _isPanning = false;







    }







    function doPinch(e) {







        e.preventDefault();







        if (e.touches.length === 2) {







            const dist = Math.hypot(e.touches[0].clientX - e.touches[1].clientX, e.touches[0].clientY - e.touches[1].clientY);







            const scale = dist / _pinchDist;







            _zoomLevel = Math.min(Math.max(_zoomLevel * scale, 1), 5);







            _pinchDist = dist;







            _isZoomed = _zoomLevel > 1;







            applyTransform();







        } else if (e.touches.length === 1 && _isPanning) {







            _panX = e.touches[0].clientX - _panStartX;







            _panY = e.touches[0].clientY - _panStartY;







            applyTransform();







        }







    }







    function endPinch() {







        if (_zoomLevel < 1.2) resetZoom();







        _isPanning = false;







    }







    // 鼠标滚轮缩放







    document.addEventListener('DOMContentLoaded', function() {







        const container = document.getElementById('lightbox-image-container');







        if (!container) return;







        container.addEventListener('wheel', function(e) {







            if (!document.getElementById('image-lightbox').classList.contains('hidden')) {







                e.preventDefault();







                const delta = e.deltaY > 0 ? -0.1 : 0.1;







                _zoomLevel = Math.min(Math.max(_zoomLevel + delta, 1), 5);







                _isZoomed = _zoomLevel > 1;







                applyTransform();







            }







        }, { passive: false });







    });







    // 切换图片时重★★★★?







    const _origShowLightboxImage = showLightboxImage;







    showLightboxImage = function() {







        resetZoom();







        _origShowLightboxImage();







    };







    // ─── 底部预览栏更───







    document.addEventListener('DOMContentLoaded', function() {







        const bar = document.getElementById('mobile-sticky-bar');







        if (!bar) return;







        window.addEventListener('scroll', function() {







            const currentScroll = window.pageYOffset;







            if (currentScroll > 300) {







                bar.classList.remove('translate-y-full');







            } else {







                bar.classList.add('translate-y-full');







            }







        }, { passive: true });







    });







    // ─── 回到顶部 ───







    document.addEventListener('DOMContentLoaded', function() {







        const btn = document.getElementById('back-to-top');







        if (!btn) return;







        window.addEventListener('scroll', function() {







            if (window.pageYOffset > 400) {







                btn.classList.remove('opacity-0', 'pointer-events-none');







                btn.classList.add('opacity-100');







            } else {







                btn.classList.add('opacity-0', 'pointer-events-none');







                btn.classList.remove('opacity-100');







            }







        }, { passive: true });







        btn.addEventListener('click', function() {







            window.scrollTo({ top: 0, behavior: 'smooth' });







        });







    });







    // ─── 分享功能 ───







    function openShareDialog() {







        document.getElementById('share-dialog').classList.remove('hidden');







        document.getElementById('share-dialog').classList.add('flex');







        document.getElementById('wechat-qr-area').classList.add('hidden');







        document.body.style.overflow = 'hidden';







    }







    function closeShareDialog() {







        document.getElementById('share-dialog').classList.add('hidden');







        document.getElementById('share-dialog').classList.remove('flex');







        document.body.style.overflow = '';







    }







    function shareWeibo() {







        const url = encodeURIComponent(window.location.href);







        const title = 'test-product - 互物通';







        window.open('https://service.weibo.com/share/share.php?url=' + url + '&title=' + title + '&pic=test', '_blank', 'width=600,height=500');







        closeShareDialog();







    }







    function shareWechat() {







        const area = document.getElementById('wechat-qr-area');







        area.classList.toggle('hidden');







    }







    async function shareCopyLink() {







        try {







            await navigator.clipboard.writeText(window.location.href);







            const btn = document.querySelector('#share-dialog .copy-btn');







            if (!btn) return;







            const orig = btn.innerHTML;







            btn.innerHTML = '<div class="flex items-center gap-2"><svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></div><span class="text-sm text-green-600 font-medium">已复制</span>';







            setTimeout(() => { btn.innerHTML = orig; }, 2000);







        } catch {







            // fallback







            const ta = document.createElement('textarea');







            ta.value = window.location.href;







            document.body.appendChild(ta);







            ta.select();







            document.execCommand('copy');







            document.body.removeChild(ta);







            showToast('★★★★已复制');







        }







    }







    // 插入到月前面







        items.unshift(product);







        // 限制数量







        if (items.length > MAX_RECENT) items = items.slice(0, MAX_RECENT);







        try {







            localStorage.setItem(RECENT_KEY, JSON.stringify(items));







        } catch {}







    }







    function renderRecentlyViewed() {







        const section = document.getElementById('recently-viewed-section');







        const container = document.getElementById('recently-viewed-container');







        if (!section || !container) return;







        let items = [];







        try {







            const raw = localStorage.getItem(RECENT_KEY);







            if (raw) items = JSON.parse(raw);







        } catch {}







        // 过滤掉当前产★?







        items = items.filter(p => String(p.id) !== '4');







        if (items.length === 0) {







            section.classList.add('hidden');







            return;







        }







        section.classList.remove('hidden');







        container.innerHTML = items.map(p => `







            <a href="/products/${p.slug}" class="block group">







                <div class="aspect-[4/3] bg-gray-50 flex items-center justify-center overflow-hidden">







                    ${p.image ? `<img src="${p.image}" alt="${p.name}" class="w-full h-full object-cover" onerror="this.parentElement.innerHTML='<svg class=\\'w-8 h-8 text-gray-300\\' fill=\\'none\\' stroke=\\'currentColor\\' viewBox=\\'0 0 24 24\\'><path stroke-linecap=\\'round\\' stroke-linejoin=\\'round\\' stroke-width=\\'1.5\\' d=\\'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z\\'/></svg>'">`







                    : `<svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>`







                }







                </div>







                <div class="p-3">







                    <div class="text-sm font-medium text-gray-900 truncate">${p.name}</div>







                    <div class="text-sm font-bold text-primary-600 mt-1">${p.price || '面议'}</div>







                </div>







            </a>







        `).join('');







    }







    function clearRecentlyViewed() {







        try {







            localStorage.removeItem(RECENT_KEY);







        } catch {}







        const section = document.getElementById('recently-viewed-section');







        if (section) section.classList.add('hidden');







    }







    // 页面加载时保存当前产★?







    document.addEventListener('DOMContentLoaded', function() {







        saveRecentlyViewed({







            id: '4',







            name: 'test-product',







            image: 'test',







            url: 'test',







            price: '¥testtest'







        });







        renderRecentlyViewed();







        // 加载聊天窗口配置







        fetch('/api/settings/public')







            .then(r => r.json())







            .then(d => {







                if (d.success) {







                    const data = d.data || {};







                    const w = parseInt(data.chat_widget_width) || 440;







                    const h = parseInt(data.chat_widget_height) || 640;







                    const pos = data.chat_widget_position || 'right';







                    const dlg = document.getElementById('im-chat-dialog');







                    const btn = document.getElementById('im-chat-btn');







                    if (dlg) {







                        dlg.style.width = w + 'px';







                        dlg.style.height = h + 'px';







                        dlg.classList.remove('right-4', 'md:right-8', 'left-4', 'md:left-8');







                        dlg.style.left = '';







                        dlg.style.transform = '';







                        if (pos === 'left') {







                            dlg.classList.add('left-4', 'md:left-8');







                        } else {







                            dlg.classList.add('right-4', 'md:right-8');







                        }







                    }







                    if (btn) {







                        btn.classList.remove('right-4', 'md:right-8', 'left-4', 'md:left-8');







                        btn.style.left = '';







                        btn.style.transform = '';







                        if (pos === 'left') {







                            btn.classList.add('left-4', 'md:left-8');







                        } else {







                            btn.classList.add('right-4', 'md:right-8');







                        }







                    }







                }







            })







            .catch(() => {});







        startPolling();







    });







    // ─── 产品对比 ───







    const COMPARE_KEY = 'huwutong_compare_products';







    const MAX_COMPARE = 4;







    function getCompareList() {







        try {







            const raw = localStorage.getItem(COMPARE_KEY);







            return raw ? JSON.parse(raw) : [];







        } catch { return []; }







    }







    function saveCompareList(items) {







        try { localStorage.setItem(COMPARE_KEY, JSON.stringify(items)); } catch {}







        updateCompareBar();







        updateCompareButtons();







    }







    function toggleCompare(id, name, image, url, price) {







        let items = getCompareList();







        const idx = items.findIndex(p => String(p.id) === String(id));







        if (idx >= 0) {







            items.splice(idx, 1);







        } else {







            if (items.length >= MAX_COMPARE) {







                alert('月★★★★对比' + MAX_COMPARE + ' 了吗？?;







                return;







            }







            items.push({ id: String(id), name, image: image || '', url, price });







        }







        saveCompareList(items);







    }







    function isInCompare(id) {







        return getCompareList().some(p => String(p.id) === String(id));







    }







    function updateCompareButtons() {







        const list = getCompareList();







        // 更新详情页按★€







        document.querySelectorAll('[id^="compare-btn-"]').forEach(btn => {







            const pid = btn.id.replace('compare-btn-', '');







            const added = list.some(p => String(p.id) === pid);







            btn.classList.toggle('border-amber-400', added);







            btn.classList.toggle('text-amber-600', added);







            btn.classList.toggle('bg-amber-50', added);







            btn.innerHTML = added







                ? '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> 已★★?







                : '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg> 对比';







        });







        // 更新相关产品按钮







        document.querySelectorAll('.compare-rp-btn').forEach(btn => {







            const pid = btn.dataset.pid;







            const added = list.some(p => String(p.id) === pid);







            const icon = btn.querySelector('.compare-rp-icon');







            if (icon) {







                if (added) {







                    icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>';







                    btn.classList.add('bg-amber-100', 'border-amber-300');







                } else {







                    icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>';







                    btn.classList.remove('bg-amber-100', 'border-amber-300');







                }







            }







        });







    }







    function updateCompareBar() {







        const bar = document.getElementById('compare-floating-bar');







        const container = document.getElementById('compare-bar-items');







        if (!bar || !container) return;







        const items = getCompareList();







        if (items.length === 0) {







            bar.classList.add('hidden');







            return;







        }







        bar.classList.remove('hidden');







        container.innerHTML = items.map((p, i) => `







            <div class="flex items-center gap-2 bg-gray-50 rounded-lg px-3 py-2 border border-gray-200">







                <div class="w-8 h-8 rounded-lg bg-gray-100 overflow-hidden flex-shrink-0">







                    ${p.image ? `<img src="${p.image}" alt="" class="w-full h-full object-cover">` : '<div class="w-full h-full bg-gray-100"></div>'}







                </div>







                <span class="text-xs font-medium text-gray-700 max-w-[80px] truncate">${p.name}</span>







                <button onclick="toggleCompare('${p.id}','${p.name}','${p.image}','${p.url}','${p.price}')" class="w-5 h-5 flex items-center justify-center text-gray-400 hover:text-red-500 transition shrink-0">&times;</button>







            </div>







        `).join('');







        document.getElementById('compare-bar-count').textContent = items.length;







        const url = '/compare-products?ids=' + items.map(p => p.id).join(',');







        document.getElementById('compare-bar-link').href = 'mailto:' + (ser?.email || '');







        document.getElementById('stock-notify-phone').value = '';







        document.getElementById('stock-notify-msg').classList.add('hidden');







        document.getElementById('stock-notify-submit-btn').disabled = false;







        document.getElementById('stock-notify-submit-btn').textContent = '提交订阅';







        document.getElementById('stock-notify-dialog').classList.remove('hidden');







        document.getElementById('stock-notify-dialog').classList.add('flex');







        document.body.style.overflow = 'hiddn';







    }







    function closeStockNotify() {







        document.getElementById('stock-notify-dialog').classList.add('hidden');







        document.getElementById('stock-notify-dialog').classList.remove('flex');







        document.body.style.overflow = '';







    }







    async function submitStockNotify() {







        const email = document.getElementById('stock-notify-email').value.trim();







        const phone = document.getElementById('stock-notify-phone').value.trim();







        const btn = document.getElementById('stock-notify-submit-btn');







        const msg = document.getElementById('stock-notify-msg');







        if (!email && !phone) {







            msg.textContent = '请至少填写邮箱或手机号';








            msg.className = 'text-sm text-center text-red-500';







            msg.classList.remove('hidden');







            return;







        }







        btn.disabled = true;







        btn.textContent = '提交★€..';







        try {







            const res = await fetch('/api/stock-notify/subscribe', {







                method: 'POST',







                headers: {







                    'Content-Type': 'application/json',







                    'Accept': 'application/json',







                    'X-CSRF-TOKEN': 'csrf-token',







                },







                body: JSON.stringify({ sku_id: _notifySkuId, email, phone }),







                credentials: 'same-origin',







            });







            const data = await res.json();







            if (data.success) {







                msg.textContent = data.message || '订阅成功★€;







                msg.className = 'text-sm text-center text-green-600';







                msg.classList.remove('hidden');







                setTimeout(closeStockNotify, 2000);







            } else {







                msg.textContent = data.message || data.errors?.email?.[0] || '订阅失败，★重试';







                msg.className = 'text-sm text-center text-red-500';







                msg.classList.remove('hidden');







            }







        } catch (e) {







            msg.textContent = '网络异常，★稍后重试';







            msg.className = 'text-sm text-center text-red-500';







            msg.classList.remove('hidden');







        }







        btn.disabled = false;







        btn.textContent = '提交订阅';







    }


