import { createApp } from 'vue';
import { createPinia } from 'pinia';
import * as ElementPlusIconsVue from '@element-plus/icons-vue';
import router from './router';
import App from './App.vue';
import './bootstrap';
import errorReporter from './utils/errorReporter';
import './pwa/register-sw'; // PWA Service Worker 注册
import i18n from './i18n'; // D-22: vue-i18n

const app = createApp(App);

// 注册所有 Element Plus 图标
for (const [key, component] of Object.entries(ElementPlusIconsVue)) {
    app.component(key, component);
}

app.use(createPinia());
app.use(router);
app.use(i18n); // D-22: 使用 vue-i18n

// D-22: 通过全局属性提供 $t 兼容旧代码（vue-i18n 也会注册 $t）
// 已有的 app.config.globalProperties.$t 将被 vue-i18n 覆盖

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
