<template>
    <div class="wizard-page">
        <div class="page-header">
            <h2>{{ t('wizard_page.title') }}</h2>
            <p class="header-subtitle">{{ t('wizard_page.subtitle') }}</p>
        </div>

        <!-- 步骤条 -->
        <el-steps :active="currentStep - 1" align-center class="wizard-steps">
            <el-step
                v-for="(step, idx) in wizardSteps"
                :key="idx"
                :title="step.title"
                :description="step.description"
            />
        </el-steps>

        <!-- 步骤 1: 选择语言 -->
        <div v-if="currentStep === 1" class="step-content">
            <h3 class="step-title">{{ t('wizard_page.step1.title') }}</h3>
            <p class="step-desc">{{ t('wizard_page.step1.desc') }}</p>

            <div class="language-grid">
                <div
                    v-for="lang in languages"
                    :key="lang.id"
                    class="language-card"
                    :class="{ active: selectedLanguage === lang.id }"
                    @click="selectedLanguage = lang.id"
                >
                    <div class="lang-icon">
                        <img
                            v-if="lang.icon === 'php'"
                            src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Ccircle cx='32' cy='32' r='30' fill='%23777BB3'/%3E%3Ctext x='32' y='40' text-anchor='middle' fill='white' font-size='14' font-weight='bold' font-family='monospace'%3EPHP%3C/text%3E%3C/svg%3E"
                            alt="PHP"
                        />
                        <span v-else-if="lang.icon === 'nodejs'" class="icon-placeholder node">JS</span>
                        <span v-else-if="lang.icon === 'python'" class="icon-placeholder py">Py</span>
                        <span v-else-if="lang.icon === 'java'" class="icon-placeholder java">Java</span>
                        <span v-else-if="lang.icon === 'go'" class="icon-placeholder go">Go</span>
                        <span v-else-if="lang.icon === 'dotnet'" class="icon-placeholder dotnet">.NET</span>
                        <span v-else-if="lang.icon === 'rust'" class="icon-placeholder rust">RS</span>
                        <span v-else class="icon-placeholder api">API</span>
                    </div>
                    <div class="lang-name">{{ lang.name }}</div>
                    <div class="lang-desc">{{ lang.description }}</div>
                </div>
            </div>

            <div class="step-actions">
                <el-button type="primary" size="large" :disabled="!selectedLanguage" @click="nextStep">
                    {{ t('actions.next') }}
                    <el-icon><ArrowRight /></el-icon>
                </el-button>
            </div>
        </div>

        <!-- 步骤 2: 选择产品 -->
        <div v-if="currentStep === 2" class="step-content">
            <h3 class="step-title">{{ t('wizard_page.step2.title') }}</h3>
            <p class="step-desc">{{ t('wizard_page.step2.desc') }}</p>

            <div v-loading="productsLoading" class="product-list">
                <div
                    v-for="product in productList"
                    :key="product.id"
                    class="product-card"
                    :class="{ active: selectedProduct === product.id }"
                    @click="selectedProduct = product.id"
                >
                    <div class="product-info">
                        <div class="product-name">{{ product.name }}</div>
                        <div class="product-slug">
                            <code>{{ product.slug }}</code>
                            <el-tag size="small" type="info" effect="plain" class="version-tag">v{{ product.version }}</el-tag>
                        </div>
                        <div class="product-desc">{{ product.description || t('wizard_page.step2.no_description') }}</div>
                    </div>
                    <el-icon v-if="selectedProduct === product.id" class="check-icon" color="#0f172a">
                        <CircleCheck />
                    </el-icon>
                </div>

                <el-empty v-if="!productsLoading && productList.length === 0" :description="t('wizard_page.step2.no_products')" />
            </div>

            <div class="step-actions">
                <el-button @click="prevStep">
                    <el-icon><ArrowLeft /></el-icon> {{ t('actions.prev') }}
                </el-button>
                <el-button type="primary" size="large" :disabled="!selectedProduct" @click="nextStep">
                    {{ t('actions.next') }}
                    <el-icon><ArrowRight /></el-icon>
                </el-button>
            </div>
        </div>

        <!-- 步骤 3: 获取配置 -->
        <div v-if="currentStep === 3" class="step-content">
            <h3 class="step-title">{{ t('wizard_page.step3.title') }}</h3>
            <p class="step-desc">{{ t('wizard_page.step3.desc') }}</p>

            <div v-if="!generatedConfig" class="config-form">
                <el-form :model="configForm" label-width="120px" label-position="right">
                    <el-form-item :label="t('wizard_page.step3.license_key')" required>
                        <el-input
                            v-model="configForm.license_key"
                            :placeholder="t('wizard_page.step3.license_key_placeholder')"
                            style="max-width: 450px;"
                        />
                        <div class="form-tip">{{ t('wizard_page.step3.license_key_tip') }}</div>
                    </el-form-item>
                    <el-form-item :label="t('wizard_page.step3.api_host')">
                        <el-input
                            v-model="configForm.api_host"
                            :placeholder="t('wizard_page.step3.api_host_placeholder', { url: defaultApiUrl })"
                            style="max-width: 450px;"
                        />
                        <div class="form-tip">{{ t('wizard_page.step3.api_host_tip') }}</div>
                    </el-form-item>
                </el-form>

                <div class="step-actions">
                    <el-button @click="prevStep">
                        <el-icon><ArrowLeft /></el-icon> {{ t('actions.prev') }}
                    </el-button>
                    <el-button type="primary" size="large" :loading="generatingConfig" @click="generateConfig">
                        <el-icon><MagicStick /></el-icon> {{ t('wizard_page.step3.generate_config') }}
                    </el-button>
                </div>
            </div>

            <!-- 生成的代码 -->
            <div v-else class="config-result">
                <el-alert
                    :title="t('wizard_page.step3.config_generated')"
                    type="success"
                    show-icon
                    :closable="false"
                    class="mb-4"
                />

                <el-tabs v-model="activeTab" type="border-card">
                    <el-tab-pane :label="t('wizard_page.step3.tab_activate')" name="activate">
                        <div class="code-block">
                            <div class="code-header">
                                <span class="code-lang">{{ t('wizard_page.step3.code_activate', { lang: selectedLanguageLabel }) }}</span>
                                <el-button text size="small" @click="copyCode(generatedConfig.snippets?.activate)">
                                    <el-icon><CopyDocument /></el-icon> {{ t('actions.copy') }}
                                </el-button>
                            </div>
                            <pre><code>{{ generatedConfig.snippets?.activate || t('wizard_page.step3.no_code') }}</code></pre>
                        </div>
                    </el-tab-pane>
                    <el-tab-pane :label="t('wizard_page.step3.tab_validate')" name="validate">
                        <div class="code-block">
                            <div class="code-header">
                                <span class="code-lang">{{ t('wizard_page.step3.code_validate', { lang: selectedLanguageLabel }) }}</span>
                                <el-button text size="small" @click="copyCode(generatedConfig.snippets?.validate)">
                                    <el-icon><CopyDocument /></el-icon> {{ t('actions.copy') }}
                                </el-button>
                            </div>
                            <pre><code>{{ generatedConfig.snippets?.validate || t('wizard_page.step3.no_code') }}</code></pre>
                        </div>
                    </el-tab-pane>
                </el-tabs>

                <el-card shadow="never" class="instructions-card">
                    <template #header>
                        <span><el-icon><InfoFilled /></el-icon> {{ t('wizard_page.step3.integration_steps') }}</span>
                    </template>
                    <el-steps direction="vertical" :active="-1">
                        <el-step
                            v-for="(inst, idx) in generatedConfig.instructions"
                            :key="idx"
                            :title="inst"
                            status="process"
                        />
                    </el-steps>
                </el-card>

                <div class="step-actions">
                    <el-button @click="generatedConfig = null">
                        <el-icon><Edit /></el-icon> {{ t('wizard_page.step3.reconfigure') }}
                    </el-button>
                    <el-button type="primary" size="large" @click="nextStep">
                        <el-icon><Connection /></el-icon> {{ t('wizard_page.step3.verify_connectivity') }}
                    </el-button>
                </div>
            </div>
        </div>

        <!-- 步骤 4: 验证连通性 -->
        <div v-if="currentStep === 4" class="step-content">
            <h3 class="step-title">{{ t('wizard_page.step4.title') }}</h3>
            <p class="step-desc">{{ t('wizard_page.step4.desc') }}</p>

            <div v-if="!connectivityResult" class="connectivity-start">
                <el-button
                    type="primary"
                    size="large"
                    :loading="testingConnectivity"
                    @click="testConnectivity"
                >
                    <el-icon><Aim /></el-icon> {{ t('wizard_page.step4.start_test') }}
                </el-button>
                <p class="test-desc">{{ t('wizard_page.step4.test_with_key', { key: testLicenseLabel }) }}</p>
            </div>

            <div v-else class="connectivity-result">
                <el-result
                    :icon="connectivityResult.overall_success ? 'success' : 'error'"
                    :title="connectivityResult.overall_success ? t('wizard_page.step4.all_passed') : t('wizard_page.step4.partial_failed')"
                    :sub-title="connectivityResult.overall_success ? t('wizard_page.step4.success_sub') : t('wizard_page.step4.fail_sub')"
                >
                    <template #extra>
                        <el-button
                            v-if="!connectivityResult.overall_success"
                            type="primary"
                            @click="testConnectivity"
                        >
                            {{ t('actions.retry') }}
                        </el-button>
                        <el-button @click="currentStep = 3">{{ t('wizard_page.step4.back_to_config') }}</el-button>
                    </template>
                </el-result>

                <el-timeline class="checks-timeline">
                    <el-timeline-item
                        v-for="(check, key) in connectivityResult.checks"
                        :key="key"
                        :type="check.success ? 'success' : 'danger'"
                        :timestamp="check.message"
                    >
                        <div class="check-item">
                            <strong>{{ checkLabels[key] }}</strong>
                            <el-tag v-if="check.status" size="small" :type="check.success ? 'success' : 'danger'">
                                HTTP {{ check.status }}
                            </el-tag>
                        </div>
                    </el-timeline-item>
                </el-timeline>

                <div class="step-actions">
                    <el-button type="primary" @click="$router.push('/licenses')">
                        <el-icon><Key /></el-icon> {{ t('wizard_page.step4.manage_licenses') }}
                    </el-button>
                    <el-button @click="$router.push('/dashboard')">
                        {{ t('wizard_page.step4.back_dashboard') }}
                    </el-button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import {
    ArrowRight, ArrowLeft, CircleCheck, MagicStick,
    CopyDocument, InfoFilled, Edit, Connection, Aim, Key,
} from '@element-plus/icons-vue';
import wizardApi from '@/api/wizard';

const { t } = useI18n();

const currentStep = ref(1);
const languages = ref([]);
const productList = ref([]);
const productsLoading = ref(false);
const selectedLanguage = ref('');
const selectedProduct = ref(null);
const generatingConfig = ref(false);
const generatedConfig = ref(null);
const testingConnectivity = ref(false);
const connectivityResult = ref(null);
const activeTab = ref('activate');

const configForm = ref({
    license_key: '',
    api_host: '',
});

const defaultApiUrl = window.location.origin || 'https://api.huwutong.com';

const wizardStepKeys = ['language', 'product', 'config', 'connectivity'];

const wizardSteps = computed(() =>
    wizardStepKeys.map((key) => ({
        title: t(`wizard_page.steps.${key}.title`),
        description: t(`wizard_page.steps.${key}.desc`),
    }))
);

const checkLabels = computed(() => ({
    api_reachable: t('wizard_page.checks.api_reachable'),
    license_valid: t('wizard_page.checks.license_valid'),
    sdk_handshake: t('wizard_page.checks.sdk_handshake'),
}));

const selectedLanguageLabel = computed(() => {
    const lang = languages.value.find(l => l.id === selectedLanguage.value);
    return lang ? lang.name : selectedLanguage.value.toUpperCase();
});

const testLicenseLabel = computed(() => {
    const key = configForm.value.license_key;
    if (key) return `${key.slice(0, 16)}...`;
    return t('wizard_page.step4.configured_license');
});

const selectedProductInfo = computed(() => {
    return productList.value.find(p => p.id === selectedProduct.value);
});

async function loadLanguages() {
    try {
        const { data: res } = await wizardApi.languages();
        if (res.success) {
            languages.value = res.data || [];
        }
    } catch {
        // ignore
    }
}

async function loadProducts() {
    if (productsLoading.value) return;
    productsLoading.value = true;
    try {
        const { data: res } = await wizardApi.products();
        if (res.success) {
            productList.value = res.data || [];
            if (res.data.length > 0 && !selectedProduct.value) {
                selectedProduct.value = res.data[0].id;
            }
        }
    } catch {
        ElMessage.warning(t('wizard_page.messages.load_products_failed'));
    } finally {
        productsLoading.value = false;
    }
}

function nextStep() {
    if (currentStep.value < 4) {
        currentStep.value++;
        if (currentStep.value === 2) loadProducts();
    }
}

function prevStep() {
    if (currentStep.value > 1) {
        if (currentStep.value === 4) connectivityResult.value = null;
        if (currentStep.value === 4 || currentStep.value === 3) generatedConfig.value = null;
        currentStep.value--;
    }
}

async function generateConfig() {
    if (!configForm.value.license_key) {
        ElMessage.warning(t('wizard_page.messages.license_key_required'));
        return;
    }

    generatingConfig.value = true;
    try {
        const { data: res } = await wizardApi.generateConfig({
            language: selectedLanguage.value,
            product_id: selectedProduct.value,
            license_key: configForm.value.license_key,
            api_host: configForm.value.api_host || undefined,
        });
        if (res.success) {
            generatedConfig.value = res.data;
            ElMessage.success(t('wizard_page.messages.config_generated'));
        }
    } catch {
        ElMessage.error(t('wizard_page.messages.config_generate_failed'));
    } finally {
        generatingConfig.value = false;
    }
}

async function testConnectivity() {
    testingConnectivity.value = true;
    connectivityResult.value = null;
    try {
        const { data: res } = await wizardApi.testConnectivity({
            license_key: configForm.value.license_key,
            api_host: configForm.value.api_host || undefined,
            product_id: selectedProduct.value,
        });
        if (res.success) {
            connectivityResult.value = res.data;
            if (res.data.overall_success) {
                ElMessage.success(t('wizard_page.messages.all_checks_passed'));
            } else {
                ElMessage.warning(t('wizard_page.messages.partial_checks_failed'));
            }
        }
    } catch {
        ElMessage.error(t('wizard_page.messages.connectivity_test_failed'));
        connectivityResult.value = {
            overall_success: false,
            checks: {
                api_reachable: { success: false, status: 0, message: t('wizard_page.messages.request_error') },
            },
        };
    } finally {
        testingConnectivity.value = false;
    }
}

function copyCode(code) {
    if (!code) return;
    navigator.clipboard.writeText(code).then(() => {
        ElMessage.success(t('portal.copied_clipboard'));
    }).catch(() => {
        const ta = document.createElement('textarea');
        ta.value = code;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        ElMessage.success(t('portal.copied_clipboard'));
    });
}

onMounted(() => {
    loadLanguages();
});
</script>

<style scoped>
.wizard-page { padding: 20px; max-width: 960px; margin: 0 auto; }

.page-header { text-align: center; margin-bottom: 40px; }
.page-header h2 { margin: 0; font-size: 24px; }
.header-subtitle { color: var(--el-text-color-secondary); font-size: 14px; margin-top: 8px; }

.wizard-steps { margin-bottom: 40px; }

.step-content { min-height: 400px; }
.step-title { font-size: 20px; margin: 0 0 8px; }
.step-desc { color: var(--el-text-color-secondary); font-size: 14px; margin-bottom: 24px; }

/* 语言选择 */
.language-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 32px;
}
.language-card {
    border: 1px solid var(--el-border-color-light);
    border-radius: 8px;
    padding: 20px 16px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
}
.language-card:hover {
    border-color: var(--el-color-primary-light-5);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}
.language-card.active {
    border-color: var(--el-color-primary);
    background: var(--el-color-primary-light-9);
    box-shadow: 0 0 0 2px var(--el-color-primary-light-7);
}
.lang-icon {
    width: 48px;
    height: 48px;
    margin: 0 auto 12px;
}
.lang-icon img {
    width: 48px;
    height: 48px;
    border-radius: 50%;
}
.icon-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    border-radius: 50%;
    font-weight: 700;
    font-size: 14px;
    color: white;
}
.icon-placeholder.node { background: #339933; }
.icon-placeholder.py { background: #3776AB; }
.icon-placeholder.java { background: #ED8B00; }
.icon-placeholder.go { background: #00ADD8; }
.icon-placeholder.dotnet { background: #512BD4; }
.icon-placeholder.rust { background: #DEA584; color: #333; }
.icon-placeholder.api { background: #0f172a; }
.lang-name { font-weight: 600; font-size: 15px; margin-bottom: 4px; }
.lang-desc { font-size: 12px; color: var(--el-text-color-secondary); }

/* 产品选择 */
.product-list { margin-bottom: 32px; }
.product-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border: 1px solid var(--el-border-color-light);
    border-radius: 8px;
    padding: 16px 20px;
    margin-bottom: 12px;
    cursor: pointer;
    transition: all 0.2s;
}
.product-card:hover {
    border-color: var(--el-color-primary-light-5);
}
.product-card.active {
    border-color: var(--el-color-primary);
    background: var(--el-color-primary-light-9);
}
.product-name { font-weight: 600; font-size: 16px; margin-bottom: 4px; }
.product-slug {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 4px;
}
.product-slug code {
    font-size: 12px;
    background: var(--el-fill-color-light);
    padding: 2px 6px;
    border-radius: 3px;
}
.product-desc { font-size: 13px; color: var(--el-text-color-secondary); }
.check-icon { font-size: 24px; }
.version-tag { font-size: 11px; }

/* 配置表单 */
.config-form { max-width: 600px; }
.form-tip { font-size: 12px; color: var(--el-text-color-secondary); margin-top: 4px; }

/* 配置结果 */
.config-result { margin-bottom: 32px; }
.code-block {
    border: 1px solid var(--el-border-color-light);
    border-radius: 6px;
    overflow: hidden;
    margin-bottom: 16px;
}
.code-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 16px;
    background: var(--el-fill-color-light);
    border-bottom: 1px solid var(--el-border-color-light);
}
.code-lang { font-size: 13px; color: var(--el-text-color-secondary); }
.code-block pre {
    margin: 0;
    padding: 16px;
    background: #1e1e1e;
    color: #d4d4d4;
    overflow-x: auto;
    font-size: 13px;
    line-height: 1.5;
    max-height: 400px;
}
.code-block code { font-family: 'SF Mono', 'Fira Code', 'Cascadia Code', monospace; }

.instructions-card { margin-top: 16px; }

/* 连通性 */
.connectivity-start {
    text-align: center;
    padding: 60px 0;
}
.test-desc { color: var(--el-text-color-secondary); font-size: 14px; margin-top: 12px; }
.connectivity-result { margin-bottom: 32px; }
.checks-timeline { max-width: 500px; margin: 24px auto; }
.check-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.step-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
    margin-top: 32px;
}

.mb-4 { margin-bottom: 16px; }

:deep(.el-steps) { max-width: 700px; margin: 0 auto 40px; }
:deep(.el-tabs--border-card) { margin-bottom: 0; }
</style>
