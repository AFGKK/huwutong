/**
 * HWT License 可嵌入式 Widget SDK
 * ========================================
 * M2-141: 客户可在自己产品后台嵌入 License 管理微前端
 *
 * 支持:
 *   - 自定义品牌色/Logo
 *   - JWT 签名免登
 *   - postMessage 双向通信
 *   - React / Vue / 原生 JS
 *
 * 使用方式:
 *   // 原生 JS
 *   HWTWidget.init({ token: 'xxx', container: '#widget', color: '#1a73e8' });
 *
 *   // React
 *   <HWTWidget token="xxx" color="#1a73e8" />
 *
 *   // Vue
 *   <HwtWidget token="xxx" color="#1a73e8" />
 */

(function (global) {
    'use strict';

    const WIDGET_BASE = '/widget/embed';
    const WIDGET_HEIGHT = 600;

    // ─── 主 API ───
    const HWTWidget = {
        /**
         * 初始化 Widget
         * @param {Object} options
         * @param {string} options.token       - JWT 令牌（必需）
         * @param {string|Element} options.container - 容器 CSS 选择器或 DOM 元素
         * @param {string} [options.color]     - 主题色（默认 #1a73e8）
         * @param {string} [options.brand]     - 品牌名（默认 HWT License）
         * @param {number} [options.height]    - iframe 高度（默认 600）
         * @param {Object} [options.on]        - 事件回调
         */
        init: function (options) {
            if (!options.token) {
                console.error('[HWT Widget] Missing token');
                return;
            }

            const container = typeof options.container === 'string'
                ? document.querySelector(options.container)
                : options.container;

            if (!container) {
                console.error('[HWT Widget] Container not found:', options.container);
                return;
            }

            // 构建 Widget URL
            const url = new URL(WIDGET_BASE, window.location.origin);
            url.searchParams.set('token', options.token);
            if (options.color) url.searchParams.set('color', options.color);
            if (options.brand) url.searchParams.set('brand', options.brand);

            // 创建 iframe
            const iframe = document.createElement('iframe');
            iframe.src = url.toString();
            iframe.style.width = '100%';
            iframe.style.height = (options.height || WIDGET_HEIGHT) + 'px';
            iframe.style.border = 'none';
            iframe.style.borderRadius = '8px';
            iframe.style.boxShadow = '0 1px 3px rgba(0,0,0,0.1)';
            iframe.style.overflow = 'hidden';
            iframe.setAttribute('loading', 'lazy');
            iframe.setAttribute('title', options.brand || 'HWT License');
            iframe.setAttribute('allow', 'same-origin');

            // 监听 postMessage
            const callbacks = options.on || {};
            const messageHandler = function (event) {
                if (event.data?.source !== 'hwt-widget') return;

                switch (event.data.type) {
                    case 'loaded':
                        // 自适应高度
                        if (event.data.payload?.height) {
                            iframe.style.height = Math.min(event.data.payload.height + 40, 800) + 'px';
                        }
                        if (callbacks.loaded) callbacks.loaded(event.data.payload);
                        break;
                    case 'ready':
                        if (callbacks.ready) callbacks.ready(event.data.payload);
                        break;
                    case 'error':
                        if (callbacks.error) callbacks.error(event.data.payload);
                        break;
                }
            };

            window.addEventListener('message', messageHandler);

            // 清理
            const observer = new MutationObserver(function () {
                if (!document.contains(iframe)) {
                    window.removeEventListener('message', messageHandler);
                    observer.disconnect();
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });

            container.appendChild(iframe);

            // 返回控制 API
            return {
                element: iframe,
                refresh: function () {
                    iframe.contentWindow.postMessage({ source: 'hwt-host', type: 'refresh' }, '*');
                },
                setColor: function (color) {
                    iframe.contentWindow.postMessage({ source: 'hwt-host', type: 'setColor', color: color }, '*');
                },
                destroy: function () {
                    window.removeEventListener('message', messageHandler);
                    observer.disconnect();
                    iframe.remove();
                },
            };
        },

        /**
         * React 组件
         */
        ReactComponent: null,

        /**
         * Vue 组件
         */
        VueComponent: null,
    };

    // ─── React 组件 ───
    if (global.React) {
        const React = global.React;

        HWTWidget.ReactComponent = function ({ token, color, brand, height, onReady, onError, style }) {
            const containerRef = React.useRef(null);
            const instanceRef = React.useRef(null);

            React.useEffect(function () {
                if (!containerRef.current || !token) return;
                instanceRef.current = HWTWidget.init({
                    token: token,
                    container: containerRef.current,
                    color: color,
                    brand: brand,
                    height: height,
                    on: { ready: onReady, error: onError },
                });
                return function () {
                    if (instanceRef.current?.destroy) instanceRef.current.destroy();
                };
            }, [token, color, brand]);

            return React.createElement('div', { ref: containerRef, style: style || {} });
        };
    }

    // ─── Vue 组件 ───
    if (global.Vue) {
        HWTWidget.VueComponent = {
            props: {
                token: { type: String, required: true },
                color: { type: String, default: '#1a73e8' },
                brand: { type: String, default: 'HWT License' },
                height: { type: Number, default: 600 },
            },
            emits: ['ready', 'error'],
            mounted: function () {
                HWTWidget.init({
                    token: this.token,
                    container: this.$el,
                    color: this.color,
                    brand: this.brand,
                    height: this.height,
                    on: {
                        ready: (data) => this.$emit('ready', data),
                        error: (msg) => this.$emit('error', msg),
                    },
                });
            },
            template: '<div :style="{ width: \'100%\' }"></div>',
        };
    }

    // ─── 导出 ───
    global.HWTWidget = HWTWidget;

})(typeof window !== 'undefined' ? window : global);
