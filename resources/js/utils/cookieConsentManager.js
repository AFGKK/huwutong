/**
 * Cookie 同意管理器
 *
 * 功能：
 * 1. 检查用户是否同意某个 Cookie 分类
 * 2. 在用户做出选择前自动阻止非必要脚本加载
 * 3. 提供事件监听机制，同意后自动执行回调
 * 4. 提供撤回/修改同意入口
 */
const STORAGE_KEY = 'cookie_consent_given';
const CONSENT_EVENTS = {};

/**
 * 获取当前同意状态
 */
export function getConsent() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return null;
        const data = JSON.parse(raw);
        return {
            action: data.action,        // 'accepted' | 'rejected' | 'customized'
            timestamp: data.timestamp,
            categories: data.categories || [],  // 同意的分类 ID 数组
        };
    } catch {
        return null;
    }
}

/**
 * 检查某个分类是否已被同意
 * @param {string} categoryId - 分类 ID (necessary/functional/analytics/marketing)
 * @returns {boolean}
 */
export function checkConsent(categoryId) {
    const consent = getConsent();
    if (!consent) return false;                     // 尚未选择，默认阻止
    if (categoryId === 'necessary') return true;    // 必要分类始终允许
    if (consent.action === 'accepted') return true; // 接受全部
    if (consent.action === 'rejected') return false; // 拒绝全部
    // customized：检查分类是否在同意列表中
    return consent.categories.includes(categoryId);
}

/**
 * 注册回调：当某个分类被同意时执行
 * 如果已经同意，立即执行
 * @param {string} categoryId
 * @param {Function} callback
 */
export function onConsent(categoryId, callback) {
    if (checkConsent(categoryId)) {
        callback();
        return;
    }
    if (!CONSENT_EVENTS[categoryId]) {
        CONSENT_EVENTS[categoryId] = [];
    }
    CONSENT_EVENTS[categoryId].push(callback);
}

/**
 * 通知所有监听者（由 CookieConsent.vue 在保存时调用）
 * @param {string[]} allowedCategories - 被同意的分类 ID 数组
 */
export function notifyConsent(allowedCategories) {
    Object.keys(CONSENT_EVENTS).forEach(catId => {
        if (allowedCategories.includes(catId)) {
            CONSENT_EVENTS[catId].forEach(fn => fn());
            delete CONSENT_EVENTS[catId];
        }
    });
}

/**
 * 解绑回调
 * @param {string} categoryId
 * @param {Function} callback
 */
export function offConsent(categoryId, callback) {
    if (!CONSENT_EVENTS[categoryId]) return;
    CONSENT_EVENTS[categoryId] = CONSENT_EVENTS[categoryId].filter(fn => fn !== callback);
}

/**
 * 初始化脚本拦截器
 *
 * 在页面加载时扫描所有带 data-cookie-category 属性的元素，
 * 如果用户未同意该分类，则阻止其执行。
 *
 * 用法：
 *   <!-- 分析脚本：用户同意后才加载 -->
 *   <script data-cookie-category="analytics" data-src="https://example.com/analytics.js"></script>
 *
 *   <!-- 内联脚本：用户同意后才执行 -->
 *   <script data-cookie-category="marketing" type="text/plain">
 *       console.log('营销脚本');
 *   </script>
 */
export function initConsentBlocker() {
    // 扫描所有带 data-cookie-category 的 <script> 标签
    document.querySelectorAll('script[data-cookie-category]').forEach(script => {
        const catId = script.getAttribute('data-cookie-category');
        const isAllowed = checkConsent(catId);

        if (isAllowed) {
            // 已同意 -> 执行脚本
            executeScript(script);
        } else {
            // 未同意 -> 隐藏，等同意后执行
            script.setAttribute('data-cookie-pending', 'true');
            script.type = 'text/plain'; // 防止浏览器执行

            // 监听同意事件
            onConsent(catId, () => executeScript(script));
        }
    });

    // 扫描所有带 data-cookie-category 的 <iframe> 标签
    document.querySelectorAll('iframe[data-cookie-category]').forEach(iframe => {
        const catId = iframe.getAttribute('data-cookie-category');
        if (!checkConsent(catId)) {
            const originalSrc = iframe.src;
            iframe.src = '';
            iframe.setAttribute('data-cookie-pending-src', originalSrc);
            onConsent(catId, () => {
                iframe.src = iframe.getAttribute('data-cookie-pending-src') || '';
            });
        }
    });

    // 扫描所有带 data-cookie-category 的 <img> 标签（用于跟踪像素）
    document.querySelectorAll('img[data-cookie-category]').forEach(img => {
        const catId = img.getAttribute('data-cookie-category');
        if (!checkConsent(catId)) {
            const originalSrc = img.src;
            img.src = '';
            img.setAttribute('data-cookie-pending-src', originalSrc);
            onConsent(catId, () => {
                img.src = img.getAttribute('data-cookie-pending-src') || '';
            });
        }
    });
}

/**
 * 执行被拦截的脚本
 */
function executeScript(script) {
    const catId = script.getAttribute('data-cookie-category');
    const src = script.getAttribute('data-src') || script.getAttribute('data-cookie-src');

    if (src) {
        // 外部脚本：动态创建新的 script 标签
        const newScript = document.createElement('script');
        newScript.src = src;
        newScript.async = true;
        Array.from(script.attributes).forEach(attr => {
            if (attr.name !== 'data-cookie-category' && attr.name !== 'data-src' && attr.name !== 'data-cookie-pending' && attr.name !== 'type') {
                newScript.setAttribute(attr.name, attr.value);
            }
        });
        script.parentNode?.replaceChild(newScript, script);
    } else {
        // 内联脚本：改为可执行类型后重新插入
        const newScript = document.createElement('script');
        newScript.textContent = script.textContent;
        Array.from(script.attributes).forEach(attr => {
            if (attr.name !== 'data-cookie-category' && attr.name !== 'data-cookie-pending' && attr.name !== 'type') {
                newScript.setAttribute(attr.name, attr.value);
            }
        });
        script.parentNode?.replaceChild(newScript, script);
    }
}

/**
 * 清除所有同意记录（用于撤回同意）
 */
export function clearConsent() {
    localStorage.removeItem(STORAGE_KEY);
    localStorage.removeItem('cookie_consent');
}
