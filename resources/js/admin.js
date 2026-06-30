import { createApp } from 'vue';
import { createPinia } from 'pinia';
import ElementPlus from 'element-plus';
import * as ElementPlusIconsVue from '@element-plus/icons-vue';
import 'element-plus/dist/index.css';
import router from './router';
import App from './App.vue';
import './bootstrap';
import errorReporter from './utils/errorReporter';
import './pwa/register-sw'; // PWA Service Worker 注册

const app = createApp(App);

// 注册所有 Element Plus 图标
for (const [key, component] of Object.entries(ElementPlusIconsVue)) {
    app.component(key, component);
}

app.use(createPinia());
app.use(router);
app.use(ElementPlus, { size: 'default' });

// 轻量 i18n — 未配置翻译时直接返回 key 本身
app.config.globalProperties.$t = (key) => key;

// 全局错误处理器
app.config.errorHandler = (err, instance, info) => {
    errorReporter.vueError(err, instance, info);
};

app.config.warnHandler = (msg, instance, trace) => {
    errorReporter.vueWarning(msg, instance, trace);
};

// 全局未捕获 Promise 异常
window.addEventListener('unhandledrejection', (event) => {
    errorReporter.unhandledRejection(event);
});

app.mount('#admin-app');
