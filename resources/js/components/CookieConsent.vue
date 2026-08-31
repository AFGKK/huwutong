<template>
    <Teleport to="body">
        <Transition name="cookie-fade">
            <div
                v-if="visible"
                :class="[
                    'cookie-consent',
                    `cookie-consent--${config.position || 'bottom'}`,
                    `cookie-consent--${config.layout || 'bar'}`,
                    `cookie-consent--${config.theme === 'dark' ? 'dark' : 'light'}`,
                ]"
            >
                <div class="cookie-consent__inner">
                    <button
                        class="cookie-consent__close"
                        @click="dismissBanner"
                        :title="t('cookie.close')"
                    >
                        &times;
                    </button>
                    <div class="cookie-consent__text">
                        <strong>{{ config.title || t('cookie.title') }}</strong>
                        <p>{{ config.description || t('cookie.description') }}</p>
                        <a
                            v-if="config.privacy_policy_url"
                            :href="config.privacy_policy_url"
                            target="_blank"
                            class="cookie-consent__link"
                        >
                            {{ config.privacy_policy_text || t('footer.privacy_policy') }}
                        </a>
                    </div>

                    <div v-if="showPreferences" class="cookie-consent__prefs">
                        <div
                            v-for="cat in config.categories"
                            :key="cat.id"
                            class="cookie-consent__pref-item"
                        >
                            <el-checkbox
                                v-model="selectedCategories[cat.id]"
                                :disabled="cat.required"
                            >
                                <span class="pref-name">{{ cat.name }}</span>
                                <span class="pref-desc">{{ cat.description }}</span>
                            </el-checkbox>
                        </div>
                    </div>

                    <div class="cookie-consent__actions">
                        <el-button
                            v-if="!showPreferences"
                            type="primary"
                            size="small"
                            @click="acceptAll"
                        >
                            {{ config.accept_all_text || t('cookie.accept_all') }}
                        </el-button>
                        <el-button
                            v-if="showPreferences"
                            type="primary"
                            size="small"
                            @click="savePreferences"
                        >
                            {{ t('cookie.save') }}
                        </el-button>
                        <el-button
                            size="small"
                            @click="rejectAll"
                        >
                            {{ config.reject_all_text || t('cookie.reject_all') }}
                        </el-button>
                        <el-button
                            v-if="!showPreferences"
                            text
                            size="small"
                            @click="showPreferences = true"
                        >
                            {{ config.customize_text || t('cookie.customize') }}
                        </el-button>
                        <el-button
                            v-if="showPreferences"
                            text
                            size="small"
                            @click="showPreferences = false"
                        >
                            {{ t('cookie.back') }}
                        </el-button>
                    </div>
                </div>
            </div>
        </Transition>
        <!-- 撤回/打开 Cookie 设置入口浮动按钮 -->
        <Transition name="cookie-fade">
            <button
                v-if="!visible && config.show_floating_button !== false"
                class="cookie-consent__recall"
                :class="`cookie-consent__recall--${config.theme === 'dark' ? 'dark' : 'light'}`"
                @click="reopenSettings"
                :title="t('cookie.title')"
            >
                🍪
            </button>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { getCookieConfig, submitConsent } from '@/api/cookie-consent';
import { notifyConsent, clearConsent } from '@/utils/cookieConsentManager';

const { t } = useI18n();
const visible = ref(false);
const showPreferences = ref(false);
const hasConsented = ref(false);
const config = ref({
    position: 'bottom',
    layout: 'bar',
    title: '',
    accept_all_text: '',
    reject_all_text: '',
    customize_text: '',
    categories: [],
});
const selectedCategories = reactive({});

const CONSENT_STORAGE_KEY = 'cookie_consent_given';

async function fetchConfig() {
    try {
        const res = await getCookieConfig();
        if (res.data && res.data.is_active !== false) {
            config.value = res.data;
            // Init checkboxes
            if (config.value.categories) {
                config.value.categories.forEach(cat => {
                    selectedCategories[cat.id] = cat.default === true;
                });
            }
            visible.value = true;
        }
    } catch {
        // 静默失败
    }
}

function hasGivenConsent() {
    // 兼容 Blade 模板设置的简单 key
    const simpleConsent = localStorage.getItem('cookie_consent');
    if (simpleConsent === 'accepted' || simpleConsent === 'rejected') {
        hasConsented.value = true;
        return true;
    }

    const given = localStorage.getItem(CONSENT_STORAGE_KEY);
    if (!given) return false;
    try {
        const data = JSON.parse(given);
        const lifetime = parseInt(config.value.consent_lifetime_days || '365', 10);
        const expiry = data.timestamp + lifetime * 24 * 60 * 60 * 1000;
        const valid = Date.now() < expiry;
        if (valid) hasConsented.value = true;
        return valid;
    } catch {
        return false;
    }
}

function saveConsent(action, cats) {
    const data = {
        action,
        timestamp: Date.now(),
        categories: cats,
    };
    localStorage.setItem(CONSENT_STORAGE_KEY, JSON.stringify(data));
    // 兼容 Blade cookie-banner 的简单 key
    localStorage.setItem('cookie_consent', action);
    visible.value = false;
    showPreferences.value = false;
    hasConsented.value = true;

    // 通知 Cookie 同意管理器（触发被拦截的脚本加载）
    notifyConsent(cats);

    // 发送到后端 (fire-and-forget)
    submitConsent(action, cats).catch(() => {});
}

function acceptAll() {
    const allCats = {};
    if (config.value.categories) {
        config.value.categories.forEach(cat => {
            allCats[cat.id] = true;
        });
    }
    Object.assign(selectedCategories, allCats);
    saveConsent('accepted', Object.keys(allCats));
}

function rejectAll() {
    const necessaryCats = {};
    if (config.value.categories) {
        config.value.categories.forEach(cat => {
            necessaryCats[cat.id] = !!cat.required;
        });
    }
    Object.assign(selectedCategories, necessaryCats);
    saveConsent('rejected', Object.keys(necessaryCats));
}

function savePreferences() {
    const cats = Object.entries(selectedCategories)
        .filter(([, v]) => v)
        .map(([k]) => k);
    saveConsent('customized', cats);
}

function reopenSettings() {
    // 清除同意记录，重新打开设置面板
    clearConsent();
    hasConsented.value = false;
    // 重新加载配置并显示
    fetchConfig();
}

function dismissBanner() {
    // 关闭横幅（相当于拒绝全部，但不触发脚本加载）
    visible.value = false;
    showPreferences.value = false;
}

onMounted(() => {
    fetchConfig().then(() => {
        if (hasGivenConsent()) {
            visible.value = false;
        }
    });
});
</script>

<style scoped>
.cookie-consent {
    position: fixed;
    z-index: 9999;
    background: #fff;
    border: 1px solid #e8ecf1;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    padding: 20px 24px;
    max-width: 100%;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.cookie-consent--dark {
    background: #1a1b1e;
    color: #e8e8e8;
    border-color: #2a2b2e;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

/* ─── Bar 布局 ─── */
.cookie-consent--bar {
    left: 0;
    right: 0;
}
.cookie-consent--bar.cookie-consent--bottom {
    bottom: 0;
    border-bottom: none;
    border-radius: 12px 12px 0 0;
}
.cookie-consent--bar.cookie-consent--top {
    top: 0;
    border-top: none;
    border-radius: 0 0 12px 12px;
}

/* ─── Modal 布局 ─── */
.cookie-consent--modal {
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 500px;
    max-width: calc(100vw - 32px);
    border-radius: 16px;
    box-shadow: 0 16px 48px rgba(0, 0, 0, 0.15);
}
.cookie-consent--modal::before {
    content: '';
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(4px);
    z-index: -1;
}

/* ─── Floating 布局 ─── */
.cookie-consent--floating {
    bottom: 24px;
    right: 24px;
    width: 380px;
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
}

/* ─── 内部结构 ─── */
.cookie-consent__inner {
    display: flex;
    flex-direction: column;
    gap: 14px;
    position: relative;
}

/* ─── 关闭按钮 ─── */
.cookie-consent__close {
    position: absolute; top: -8px; right: -8px;
    width: 28px; height: 28px; border-radius: 50%;
    background: #f3f4f6; border: 1px solid #e5e7eb;
    color: #9ca3af; font-size: 16px; line-height: 1;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    transition: all .15s; z-index: 1;
}
.cookie-consent__close:hover {
    background: #e5e7eb; color: #6b7280;
}
.cookie-consent--dark .cookie-consent__close {
    background: #2a2b2e; border-color: #3a3b3e; color: #9ca3af;
}
.cookie-consent--dark .cookie-consent__close:hover {
    background: #3a3b3e; color: #d1d5db;
}

.cookie-consent__text {
    position: relative;
    padding-left: 40px;
}
.cookie-consent__text::before {
    content: '🍪';
    position: absolute;
    left: 0;
    top: 2px;
    font-size: 24px;
    line-height: 1;
}

.cookie-consent__text strong {
    display: block;
    margin-bottom: 4px;
    font-size: 15px;
}

.cookie-consent__text p {
    margin: 4px 0;
    font-size: 13px;
    color: #606266;
    line-height: 1.6;
}

.cookie-consent--dark .cookie-consent__text p {
    color: #a0a4a8;
}

.cookie-consent__link {
    font-size: 13px;
    color: #0f172a;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.2s;
}
.cookie-consent__link:hover {
    color: #1e293b;
    text-decoration: underline;
}

/* ─── 偏好设置 ─── */
.cookie-consent__prefs {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 12px 0;
    border-top: 1px solid #ebeef5;
    border-bottom: 1px solid #ebeef5;
}

.cookie-consent--dark .cookie-consent__prefs {
    border-color: #333;
}

.cookie-consent__pref-item {
    display: flex;
    align-items: flex-start;
}

.pref-name {
    font-weight: 600;
    font-size: 13px;
}

.pref-desc {
    display: block;
    font-size: 12px;
    color: #909399;
    margin-top: 2px;
}

/* ─── 按钮区域 ─── */
.cookie-consent__actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.cookie-consent__actions .el-button {
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.cookie-consent__actions .el-button:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.2);
}

.cookie-consent__actions .el-button--primary {
    background: linear-gradient(135deg, #0f172a, #1e293b);
    border: none;
}

.cookie-consent__actions .el-button--primary:hover {
    background: linear-gradient(135deg, #3399ff, #2d72c4);
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.3);
}

/* ─── 过渡动画 ─── */
.cookie-fade-enter-active,
.cookie-fade-leave-active {
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

.cookie-fade-enter-from {
    opacity: 0;
    transform: translateY(24px);
}

.cookie-fade-leave-to {
    opacity: 0;
    transform: translateY(24px);
}

/* ─── 移动端适配 ─── */
@media (max-width: 640px) {
    .cookie-consent--floating {
        left: 12px;
        right: 12px;
        bottom: 12px;
        width: auto;
    }
    .cookie-consent--modal {
        width: calc(100vw - 24px);
    }
    .cookie-consent__actions {
        flex-direction: column;
    }
    .cookie-consent__actions .el-button {
        width: 100%;
    }
    .cookie-consent__recall {
        bottom: 100px;
        right: 12px;
    }
}

/* ─── 撤回同意浮动按钮 ─── */
.cookie-consent__recall {
    position: fixed;
    bottom: 100px;
    right: 24px;
    z-index: 9998;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    border: 1px solid #e8ecf1;
    background: #fff;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
    cursor: pointer;
    font-size: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}
.cookie-consent__recall:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 24px rgba(0, 0, 0, 0.18);
}
.cookie-consent__recall--dark {
    background: #1a1b1e;
    border-color: #2a2b2e;
}
.cookie-consent__recall--dark:hover {
    background: #2a2b2e;
}
</style>
