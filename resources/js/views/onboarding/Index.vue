<template>
    <div class="onboarding-page">
        <div class="onboarding-container">
            <!-- 步骤进度条 -->
            <div class="steps-bar">
                <div
                    v-for="(s, idx) in steps"
                    :key="s.key"
                    class="step-dot"
                    :class="{ active: currentStep === s.key, completed: isStepCompleted(s.key) }"
                >
                    <div class="step-indicator">
                        <el-icon v-if="isStepCompleted(s.key)"><Check /></el-icon>
                        <span v-else>{{ idx + 1 }}</span>
                    </div>
                    <div class="step-label">{{ s.label }}</div>
                </div>
            </div>

            <!-- 进度条 -->
            <el-progress
                :percentage="progressPct"
                :stroke-width="6"
                :color="progressPct === 100 ? '#67C23A' : '#0f172a'"
                class="progress-bar"
            />

            <!-- 欢迎步骤 -->
            <div v-if="currentStep === 'welcome'" class="step-content welcome-step">
                <div class="welcome-icon">
                    <el-icon :size="64" color="#0f172a"><MagicStick /></el-icon>
                </div>
                <h1>{{ t('onboarding_page.welcome.title') }}</h1>
                <p class="welcome-desc">{{ t('onboarding_page.welcome.desc') }}</p>

                <div class="feature-cards">
                    <div v-for="feature in welcomeFeatures" :key="feature.key" class="feature-card">
                        <el-icon :size="32" :color="feature.color"><component :is="feature.icon" /></el-icon>
                        <h3>{{ feature.title }}</h3>
                        <p>{{ feature.desc }}</p>
                    </div>
                </div>

                <div class="step-actions">
                    <el-button @click="skipOnboarding">{{ t('onboarding_page.welcome.skip') }}</el-button>
                    <el-button type="primary" size="large" @click="nextStep('welcome')">{{ t('onboarding_page.welcome.start') }}</el-button>
                </div>
            </div>

            <!-- 个人资料步骤 -->
            <div v-if="currentStep === 'profile'" class="step-content">
                <h2>{{ t('onboarding_page.profile.title') }}</h2>
                <p class="step-desc">{{ t('onboarding_page.profile.desc') }}</p>
                <el-form ref="profileFormRef" :model="profileForm" :rules="profileRules" label-width="100px" class="step-form">
                    <el-form-item :label="t('onboarding_page.profile.name')" prop="name">
                        <el-input v-model="profileForm.name" :placeholder="t('onboarding_page.profile.name_ph')" />
                    </el-form-item>
                    <el-form-item :label="t('onboarding_page.profile.phone')" prop="phone">
                        <el-input v-model="profileForm.phone" :placeholder="t('onboarding_page.profile.phone_ph')" />
                    </el-form-item>
                </el-form>
                <div class="step-actions">
                    <el-button @click="prevStep('profile')">{{ t('actions.prev') }}</el-button>
                    <el-button type="primary" @click="handleCompleteStep('profile')">{{ t('actions.next') }}</el-button>
                </div>
            </div>

            <!-- 创建团队步骤 -->
            <div v-if="currentStep === 'tenant'" class="step-content">
                <h2>{{ t('onboarding_page.tenant.title') }}</h2>
                <p class="step-desc">{{ t('onboarding_page.tenant.desc') }}</p>
                <el-form ref="tenantFormRef" :model="tenantForm" :rules="tenantRules" label-width="120px" class="step-form">
                    <el-form-item :label="t('onboarding_page.tenant.name')" prop="tenant_name">
                        <el-input v-model="tenantForm.tenant_name" :placeholder="t('onboarding_page.tenant.name_ph')" />
                    </el-form-item>
                    <el-form-item :label="t('onboarding_page.tenant.logo')">
                        <el-upload
                            class="logo-uploader"
                            action="#"
                            :auto-upload="false"
                            :show-file-list="false"
                            accept="image/png,image/jpeg"
                            :on-change="handleLogoChange"
                        >
                            <img v-if="tenantForm.logoUrl" :src="tenantForm.logoUrl" class="logo-preview" />
                            <el-icon v-else :size="28"><Plus /></el-icon>
                        </el-upload>
                    </el-form-item>
                </el-form>
                <div class="step-actions">
                    <el-button @click="prevStep('tenant')">{{ t('actions.prev') }}</el-button>
                    <el-button type="primary" @click="handleCompleteStep('tenant')">{{ t('actions.next') }}</el-button>
                </div>
            </div>

            <!-- 添加产品步骤 -->
            <div v-if="currentStep === 'product'" class="step-content">
                <h2>{{ t('onboarding_page.product.title') }}</h2>
                <p class="step-desc">{{ t('onboarding_page.product.desc') }}</p>
                <el-form ref="productFormRef" :model="productForm" :rules="productRules" label-width="120px" class="step-form">
                    <el-form-item :label="t('onboarding_page.product.name')" prop="product_name">
                        <el-input v-model="productForm.product_name" :placeholder="t('onboarding_page.product.name_ph')" />
                    </el-form-item>
                    <el-form-item :label="t('onboarding_page.product.description')">
                        <el-input v-model="productForm.product_description" type="textarea" :rows="3" :placeholder="t('onboarding_page.product.description_ph')" />
                    </el-form-item>
                </el-form>
                <div class="step-actions">
                    <el-button @click="prevStep('product')">{{ t('actions.prev') }}</el-button>
                    <el-button type="primary" @click="handleCompleteStep('product')">{{ t('actions.next') }}</el-button>
                </div>
            </div>

            <!-- 生成 API 密钥步骤 -->
            <div v-if="currentStep === 'api_key'" class="step-content">
                <h2>{{ t('onboarding_page.api_key.title') }}</h2>
                <p class="step-desc">{{ t('onboarding_page.api_key.desc') }}</p>
                <el-form ref="apiKeyFormRef" :model="apiKeyForm" :rules="apiKeyRules" label-width="120px" class="step-form">
                    <el-form-item :label="t('onboarding_page.api_key.name')" prop="key_name">
                        <el-input v-model="apiKeyForm.key_name" :placeholder="t('onboarding_page.api_key.name_ph')" />
                    </el-form-item>
                    <el-alert type="info" :closable="false" show-icon class="mt-3">
                        <template #title>{{ t('onboarding_page.api_key.alert') }}</template>
                    </el-alert>
                </el-form>
                <div class="step-actions">
                    <el-button @click="prevStep('api_key')">{{ t('actions.prev') }}</el-button>
                    <el-button type="primary" @click="handleCompleteStep('api_key')">{{ t('onboarding_page.api_key.finish') }}</el-button>
                </div>
            </div>

            <!-- 完成步骤 -->
            <div v-if="currentStep === 'complete'" class="step-content complete-step">
                <div class="complete-icon">
                    <el-icon :size="72" color="#67C23A"><CircleCheck /></el-icon>
                </div>
                <h1>{{ t('onboarding_page.complete.title') }}</h1>
                <p class="complete-desc">{{ t('onboarding_page.complete.desc') }}</p>

                <div class="quick-actions">
                    <el-card
                        v-for="action in quickActions"
                        :key="action.route"
                        shadow="hover"
                        class="quick-action-card"
                        @click="$router.push(action.route)"
                    >
                        <el-icon :size="28" :color="action.color"><component :is="action.icon" /></el-icon>
                        <span>{{ action.label }}</span>
                    </el-card>
                </div>

                <div class="step-actions">
                    <el-button type="primary" size="large" @click="goToDashboard">{{ t('onboarding_page.complete.enter') }}</el-button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import {
    MagicStick, Key, Monitor, Lock, Check, CircleCheck,
    Goods, Plus, Odometer,
} from '@element-plus/icons-vue';
import onboardingApi from '@/api/onboarding';

const router = useRouter();
const { t } = useI18n();

const stepKeys = ['welcome', 'profile', 'tenant', 'product', 'api_key', 'complete'];

const steps = computed(() =>
    stepKeys.map((key) => ({
        key,
        label: t(`onboarding_page.steps.${key}`),
    })),
);

const welcomeFeatures = computed(() => [
    {
        key: 'license',
        icon: Key,
        color: '#0f172a',
        title: t('onboarding_page.welcome.features.license.title'),
        desc: t('onboarding_page.welcome.features.license.desc'),
    },
    {
        key: 'monitor',
        icon: Monitor,
        color: '#67C23A',
        title: t('onboarding_page.welcome.features.monitor.title'),
        desc: t('onboarding_page.welcome.features.monitor.desc'),
    },
    {
        key: 'security',
        icon: Lock,
        color: '#E6A23C',
        title: t('onboarding_page.welcome.features.security.title'),
        desc: t('onboarding_page.welcome.features.security.desc'),
    },
]);

const quickActions = computed(() => [
    {
        route: '/licenses',
        icon: Key,
        color: '#0f172a',
        label: t('onboarding_page.complete.actions.create_license'),
    },
    {
        route: '/products',
        icon: Goods,
        color: '#67C23A',
        label: t('onboarding_page.complete.actions.manage_products'),
    },
    {
        route: '/api-keys',
        icon: Key,
        color: '#E6A23C',
        label: t('onboarding_page.complete.actions.api_keys'),
    },
    {
        route: '/dashboard',
        icon: Odometer,
        color: '#909399',
        label: t('onboarding_page.complete.actions.dashboard'),
    },
]);

const currentStep = ref('welcome');
const completedSteps = ref([]);
const progressPct = ref(0);
const loading = ref(false);
const profileFormRef = ref(null);
const tenantFormRef = ref(null);
const productFormRef = ref(null);
const apiKeyFormRef = ref(null);

const profileForm = reactive({ name: '', phone: '' });
const tenantForm = reactive({ tenant_name: '', logoUrl: '' });
const productForm = reactive({ product_name: '', product_description: '' });
const apiKeyForm = reactive({ key_name: '' });

const profileRules = computed(() => ({
    name: [{ required: true, message: t('onboarding_page.validation.name_required'), trigger: 'blur' }],
}));
const tenantRules = computed(() => ({
    tenant_name: [{ required: true, message: t('onboarding_page.validation.tenant_name_required'), trigger: 'blur' }],
}));
const productRules = computed(() => ({
    product_name: [{ required: true, message: t('onboarding_page.validation.product_name_required'), trigger: 'blur' }],
}));
const apiKeyRules = computed(() => ({
    key_name: [{ required: true, message: t('onboarding_page.validation.key_name_required'), trigger: 'blur' }],
}));

async function fetchOnboardingState() {
    loading.value = true;
    try {
        const res = await onboardingApi.dashboard();
        if (res.success) {
            const onboarding = res.data?.onboarding;
            if (onboarding) {
                currentStep.value = onboarding.current_step || 'welcome';
                completedSteps.value = onboarding.completed_steps || [];
                progressPct.value = onboarding.progress_pct || 0;

                if (onboarding.is_completed) {
                    currentStep.value = 'complete';
                    progressPct.value = 100;
                }

                if (res.data?.user) {
                    const doneLabel = t('onboarding_page.messages.already_done');
                    if (res.data.user.has_tenant) tenantForm.tenant_name = doneLabel;
                    if (res.data.user.has_products) productForm.product_name = doneLabel;
                    if (res.data.user.has_api_keys) apiKeyForm.key_name = doneLabel;
                }
            }
        }
    } catch {
        ElMessage.error(t('onboarding_page.messages.load_failed'));
    } finally {
        loading.value = false;
    }
}

function isStepCompleted(key) {
    return completedSteps.value.includes(key);
}

async function handleCompleteStep(step) {
    let data = {};
    let formRef = null;

    switch (step) {
        case 'profile':
            formRef = profileFormRef;
            data = { name: profileForm.name, phone: profileForm.phone };
            break;
        case 'tenant':
            formRef = tenantFormRef;
            data = { tenant_name: tenantForm.tenant_name };
            break;
        case 'product':
            formRef = productFormRef;
            data = { product_name: productForm.product_name, product_description: productForm.product_description };
            break;
        case 'api_key':
            formRef = apiKeyFormRef;
            data = { key_name: apiKeyForm.key_name };
            break;
    }

    if (formRef) {
        const valid = await formRef.value?.validate().catch(() => false);
        if (!valid) return;
    }

    loading.value = true;
    try {
        const res = await onboardingApi.completeStep(step, data);
        if (res.success) {
            const onboarding = res.data;
            currentStep.value = onboarding.current_step || 'complete';
            completedSteps.value = onboarding.completed_steps || [];
            progressPct.value = onboarding.progress_pct || 0;

            if (onboarding.is_completed) {
                ElMessage.success(t('onboarding_page.messages.all_completed'));
                currentStep.value = 'complete';
                progressPct.value = 100;
            } else {
                ElMessage.success(t('onboarding_page.messages.step_completed'));
            }
        }
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('messages.failed'));
    } finally {
        loading.value = false;
    }
}

function nextStep(step) {
    handleCompleteStep(step);
}

function prevStep(step) {
    const idx = steps.value.findIndex(s => s.key === step);
    if (idx > 0) {
        currentStep.value = steps.value[idx - 1].key;
    }
}

async function skipOnboarding() {
    loading.value = true;
    try {
        await onboardingApi.skip(t('onboarding_page.skip_reason'));
        ElMessage.info(t('onboarding_page.messages.skipped'));
        currentStep.value = 'complete';
        progressPct.value = 100;
    } catch {
        ElMessage.error(t('messages.failed'));
    } finally {
        loading.value = false;
    }
}

function handleLogoChange(file) {
    tenantForm.logoUrl = URL.createObjectURL(file.raw);
    ElMessage.success(t('onboarding_page.messages.logo_selected'));
}

function goToDashboard() {
    router.push('/dashboard');
}

onMounted(() => {
    fetchOnboardingState();
});
</script>

<style scoped>
.onboarding-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 40px 20px;
}

.onboarding-container {
    background: #fff;
    border-radius: 16px;
    padding: 48px;
    max-width: 720px;
    width: 100%;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
}

/* 步骤条 */
.steps-bar {
    display: flex;
    justify-content: space-between;
    margin-bottom: 24px;
    position: relative;
}

.step-dot {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    flex: 1;
    position: relative;
}

.step-dot::after {
    content: '';
    position: absolute;
    top: 14px;
    left: 60%;
    right: -40%;
    height: 2px;
    background: var(--el-border-color-light);
    z-index: 0;
}

.step-dot:last-child::after {
    display: none;
}

.step-dot.completed::after {
    background: var(--el-color-success);
}

.step-indicator {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 600;
    background: var(--el-border-color-light);
    color: var(--el-text-color-secondary);
    z-index: 1;
    transition: all 0.3s;
}

.step-dot.active .step-indicator {
    background: var(--el-color-primary);
    color: #fff;
    box-shadow: 0 0 0 4px rgba(15, 23, 42, 0.2);
}

.step-dot.completed .step-indicator {
    background: var(--el-color-success);
    color: #fff;
}

.step-label {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    white-space: nowrap;
}

.step-dot.active .step-label {
    color: var(--el-color-primary);
    font-weight: 600;
}

.step-dot.completed .step-label {
    color: var(--el-color-success);
}

.progress-bar {
    margin-bottom: 32px;
}

/* 步骤内容 */
.step-content {
    text-align: center;
}

.step-content h1 {
    font-size: 28px;
    font-weight: 700;
    color: var(--el-text-color-primary);
    margin: 0 0 12px;
}

.step-content h2 {
    font-size: 24px;
    font-weight: 600;
    color: var(--el-text-color-primary);
    margin: 0 0 8px;
}

.step-desc {
    color: var(--el-text-color-secondary);
    font-size: 15px;
    margin-bottom: 28px;
}

.welcome-icon,
.complete-icon {
    margin-bottom: 20px;
}

/* 功能卡片 */
.feature-cards {
    display: flex;
    gap: 16px;
    margin: 28px 0;
    justify-content: center;
}

.feature-card {
    flex: 1;
    padding: 20px 16px;
    border-radius: 12px;
    background: var(--el-fill-color-lighter);
    text-align: center;
    transition: transform 0.2s;
}

.feature-card:hover {
    transform: translateY(-4px);
}

.feature-card h3 {
    font-size: 15px;
    font-weight: 600;
    margin: 8px 0 4px;
}

.feature-card p {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin: 0;
}

/* 表单 */
.step-form {
    max-width: 450px;
    margin: 0 auto;
    text-align: left;
}

.step-actions {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin-top: 32px;
}

.mt-3 {
    margin-top: 16px;
}

/* Logo 上传 */
.logo-uploader {
    border: 1px dashed var(--el-border-color);
    border-radius: 8px;
    cursor: pointer;
    width: 100px;
    height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: border-color 0.2s;
}

.logo-uploader:hover {
    border-color: var(--el-color-primary);
}

.logo-preview {
    width: 100px;
    height: 100px;
    object-fit: contain;
    border-radius: 8px;
}

/* 完成 */
.complete-desc {
    font-size: 16px;
    color: var(--el-text-color-secondary);
    margin-bottom: 32px;
}

.quick-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 24px;
}

.quick-action-card {
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 20px;
    transition: all 0.2s;
}

.quick-action-card:hover {
    border-color: var(--el-color-primary);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.1);
}

.quick-action-card span {
    font-size: 15px;
    font-weight: 500;
}

:deep(.el-card__body) {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 20px !important;
}
</style>
