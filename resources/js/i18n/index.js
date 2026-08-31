// D-22: 前端国际化初始化
// 负责创建 vue-i18n 实例，自动从 API 获取当前语言

import { createI18n } from 'vue-i18n';
import zhCN from '../locales/zh_CN';
import en from '../locales/en';

// 从 Cookie/本地存储获取上次选择的语言
function getInitialLocale() {
    // 1. Cookie
    const match = document.cookie.match(/(?:^|;\s*)locale=([^;]+)/);
    if (match) {
        const locale = match[1].trim();
        if (['zh_CN', 'en'].includes(locale)) return locale;
    }
    // 2. localStorage
    const stored = localStorage.getItem('locale');
    if (stored && ['zh_CN', 'en'].includes(stored)) return stored;
    // 3. HTML lang 属性
    const htmlLang = document.documentElement.lang?.replace('-', '_');
    if (htmlLang && ['zh_CN', 'en'].includes(htmlLang)) return htmlLang;
    // 4. 浏览器语言
    const navLang = (navigator.language || '').replace('-', '_');
    if (navLang.startsWith('zh')) return 'zh_CN';
    // 5. 默认
    return 'zh_CN';
}

const i18n = createI18n({
    legacy: false, // 使用 Composition API 模式
    locale: getInitialLocale(),
    fallbackLocale: 'zh_CN',
    messages: {
        zh_CN: zhCN,
        en,
    },
    // 未找到翻译时返回 key 本身
    missing: (locale, key) => key,
});

// 切换语言并同步到 Cookie/localStorage
export function switchLocale(locale) {
    i18n.global.locale.value = locale;
    document.cookie = `locale=${locale}; path=/; max-age=${60 * 60 * 24 * 365}`;
    localStorage.setItem('locale', locale);
    document.documentElement.lang = locale.replace('_', '-');
}

export default i18n;
