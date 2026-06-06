import { createApp } from 'vue';
import { createPinia } from 'pinia';
import ElementPlus from 'element-plus';
import * as ElementPlusIconsVue from '@element-plus/icons-vue';
import 'element-plus/dist/index.css';
import router from './router';
import App from './App.vue';
import './bootstrap';

const app = createApp(App);

// 注册所有 Element Plus 图标
for (const [key, component] of Object.entries(ElementPlusIconsVue)) {
    app.component(key, component);
}

app.use(createPinia());
app.use(router);
app.use(ElementPlus, { size: 'default' });
app.mount('#admin-app');
