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
                    <div class="cookie-consent__text">
                        <strong>{{ config.title || 'Cookie 设置' }}</strong>
                        <p>{{ config.description || '我们使用 Cookie 来提升您的使用体验。您可以选择接受或拒绝非必要的 Cookie。' }}</p>
                        <a
                            v-if="config.privacy_policy_url"
                            :href="config.privacy_policy_url"
                            target="_blank"
                            class="cookie-consent__link"
                        >
                            {{ config.privacy_policy_text || '隐私政策' }}
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
                            {{ config.accept_all_text || '接受全部' }}
                        </el-button>
                        <el-button
                            v-if="showPreferences"
                            type="primary"
                            size="small"
                            @click="savePreferences"
                        >
                            保存设置
                        </el-button>
                        <el-button
                            size="small"
                            @click="rejectAll"
                        >
                            {{ config.reject_all_text || '拒绝全部' }}
                        </el-button>
                        <el-button
                            v-if="!showPreferences"
                            text
                            size="small"
                            @click="showPreferences = true"
                        >
                            {{ config.customize_text || '自定义设置' }}
                        </el-button>
                        <el-button
                            v-if="showPreferences"
                            text
                            size="small"
                            @click="showPreferences = false"
                        >
                            返回
                        </el-button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from 'vue';
import { getCookieConfig, submitConsent } from '@/api/cookie-consent';

const visible = ref(false);
const showPreferences = ref(false);
const config = ref({
    position: 'bottom',
    layout: 'bar',
    title: 'Cookie 设置',
    accept_all_text: '接受全部',
    reject_all_text: '拒绝全部',
    customize_text: '自定义设置',
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
    const given = localStorage.getItem(CONSENT_STORAGE_KEY);
    if (!given) return false;
    try {
        const data = JSON.parse(given);
        const lifetime = parseInt(config.value.consent_lifetime_days || '365', 10);
        const expiry = data.timestamp + lifetime * 24 * 60 * 60 * 1000;
        return Date.now() < expiry;
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
    visible.value = false;
    showPreferences.value = false;

    // Send to server (fire-and-forget)
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
    border: 1px solid #e4e7ed;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.12);
    padding: 16px 20px;
    max-width: 100%;
}

.cookie-consent--dark {
    background: #1d1e1f;
    color: #e0e0e0;
    border-color: #333;
}

.cookie-consent--bar {
    left: 0;
    right: 0;
}

.cookie-consent--bar.cookie-consent--bottom {
    bottom: 0;
    border-bottom: none;
}

.cookie-consent--bar.cookie-consent--top {
    top: 0;
    border-top: none;
}

.cookie-consent--modal {
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 520px;
    max-width: calc(100vw - 32px);
    border-radius: 8px;
}

.cookie-consent--floating {
    bottom: 24px;
    right: 24px;
    width: 360px;
    border-radius: 12px;
}

.cookie-consent__inner {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.cookie-consent__text strong {
    display: block;
    margin-bottom: 4px;
}

.cookie-consent__text p {
    margin: 4px 0;
    font-size: 13px;
    color: #606266;
    line-height: 1.5;
}

.cookie-consent--dark .cookie-consent__text p {
    color: #a0a4a8;
}

.cookie-consent__link {
    font-size: 13px;
    color: #409eff;
    text-decoration: none;
}

.cookie-consent__prefs {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 8px 0;
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

.cookie-consent__actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

/* Transition */
.cookie-fade-enter-active,
.cookie-fade-leave-active {
    transition: all 0.3s ease;
}

.cookie-fade-enter-from {
    opacity: 0;
    transform: translateY(20px);
}

.cookie-fade-leave-to {
    opacity: 0;
    transform: translateY(20px);
}
</style>
