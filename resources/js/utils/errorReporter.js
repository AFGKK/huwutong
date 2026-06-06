/**
 * 错误报告工具
 *
 * 集中处理 Vue 运行时错误、警告和未捕获的 Promise 异常。
 * 开发环境 console 输出详细信息，生产环境可选择上报到后端。
 */

const isDev = import.meta.env.DEV;

export default {
    /**
     * Vue 运行时错误
     */
    vueError(err, instance, info) {
        if (isDev) {
            console.group('%c[Vue Error]', 'color: #f56c6c; font-weight: bold;');
            console.error('Message:', err?.message || err);
            console.warn('Info:', info);
            if (instance) {
                console.warn('Component:', instance.$options?.name || instance.$options?.__name || 'Anonymous');
            }
            if (err?.stack) {
                console.warn('Stack:', err.stack);
            }
            console.groupEnd();
        }

        // 生产环境可上报到后端
        if (!isDev) {
            this.report({
                type: 'vue_error',
                message: err?.message || String(err),
                stack: err?.stack,
                info,
                component: instance?.$options?.name || instance?.$options?.__name,
                url: window.location.href,
                timestamp: new Date().toISOString(),
            });
        }
    },

    /**
     * Vue 警告
     */
    vueWarning(msg, instance, trace) {
        if (isDev) {
            console.warn(`[Vue Warn] ${msg}`, trace || '');
        }
    },

    /**
     * 未捕获的 Promise 异常
     */
    unhandledRejection(event) {
        const reason = event.reason;

        if (isDev) {
            console.group('%c[Unhandled Rejection]', 'color: #e6a23c; font-weight: bold;');
            console.error('Reason:', reason?.message || reason);
            if (reason?.stack) {
                console.warn('Stack:', reason.stack);
            }
            console.groupEnd();
        }

        // 阻止默认浏览器控制台打印
        event.preventDefault();

        if (!isDev) {
            this.report({
                type: 'unhandled_rejection',
                message: reason?.message || String(reason),
                stack: reason?.stack,
                url: window.location.href,
                timestamp: new Date().toISOString(),
            });
        }
    },

    /**
     * 上报错误到后端（生产环境）
     */
    report(errorData) {
        // 使用 Beacon API 发送，不阻塞页面卸载
        try {
            const payload = JSON.stringify(errorData);
            if (navigator.sendBeacon) {
                navigator.sendBeacon('/api/errors/report', payload);
            } else {
                // fallback: 使用 fetch
                fetch('/api/errors/report', {
                    method: 'POST',
                    body: payload,
                    headers: { 'Content-Type': 'application/json' },
                    keepalive: true,
                }).catch(() => {});
            }
        } catch {
            // 静默失败，不影响用户体验
        }
    },
};
