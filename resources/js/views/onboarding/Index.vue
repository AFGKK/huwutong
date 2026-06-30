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
                :color="progressPct === 100 ? '#67C23A' : '#409EFF'"
                class="progress-bar"
            />

            <!-- 欢迎步骤 -->
            <div v-if="currentStep === 'welcome'" class="step-content welcome-step">
                <div class="welcome-icon">
                    <el-icon :size="64" color="#409EFF"><MagicStick /></el-icon>
                </div>
                <h1>欢迎使用 HWT License 管理系统</h1>
                <p class="welcome-desc">我们将通过几个简单的步骤帮助您完成系统设置，快速开始管理您的软件许可证。</p>

                <div class="feature-cards">
                    <div class="feature-card">
                        <el-icon :size="32" color="#409EFF"><Key /></el-icon>
                        <h3>License 管理</h3>
                        <p>生成、分发和管理软件许可证</p>
                    </div>
                    <div class="feature-card">
                        <el-icon :size="32" color="#67C23A"><Monitor /></el-icon>
                        <h3>实时监控</h3>
                        <p>跟踪激活和使用情况</p>
                    </div>
                    <div class="feature-card">
                        <el-icon :size="32" color="#E6A23C"><Lock /></el-icon>
                        <h3>安全防护</h3>
                        <p>防篡改、防盗版保护</p>
                    </div>
                </div>

                <div class="step-actions">
                    <el-button @click="skipOnboarding">跳过设置</el-button>
                    <el-button type="primary" size="large" @click="nextStep('welcome')">开始设置</el-button>
                </div>
            </div>

            <!-- 个人资料步骤 -->
            <div v-if="currentStep === 'profile'" class="step-content">
                <h2>完善个人资料</h2>
                <p class="step-desc">请完善您的个人信息</p>
                <el-form ref="profileFormRef" :model="profileForm" :rules="profileRules" label-width="100px" class="step-form">
                    <el-form-item label="姓名" prop="name">
                        <el-input v-model="profileForm.name" placeholder="您的姓名" />
                    </el-form-item>
                    <el-form-item label="手机号" prop="phone">
                        <el-input v-model="profileForm.phone" placeholder="手机号码（可选）" />
                    </el-form-item>
                </el-form>
                <div class="step-actions">
                    <el-button @click="prevStep('profile')">上一步</el-button>
                    <el-button type="primary" @click="handleCompleteStep('profile')">下一步</el-button>
                </div>
            </div>

            <!-- 创建团队步骤 -->
            <div v-if="currentStep === 'tenant'" class="step-content">
                <h2>创建团队</h2>
                <p class="step-desc">创建一个团队或公司账户来管理您的 License</p>
                <el-form ref="tenantFormRef" :model="tenantForm" :rules="tenantRules" label-width="120px" class="step-form">
                    <el-form-item label="团队名称" prop="tenant_name">
                        <el-input v-model="tenantForm.tenant_name" placeholder="例如：我的公司" />
                    </el-form-item>
                    <el-form-item label="团队 Logo">
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
                    <el-button @click="prevStep('tenant')">上一步</el-button>
                    <el-button type="primary" @click="handleCompleteStep('tenant')">下一步</el-button>
                </div>
            </div>

            <!-- 添加产品步骤 -->
            <div v-if="currentStep === 'product'" class="step-content">
                <h2>添加产品</h2>
                <p class="step-desc">添加您要授权管理的第一款产品</p>
                <el-form ref="productFormRef" :model="productForm" :rules="productRules" label-width="120px" class="step-form">
                    <el-form-item label="产品名称" prop="product_name">
                        <el-input v-model="productForm.product_name" placeholder="例如：企业管理系统" />
                    </el-form-item>
                    <el-form-item label="产品描述">
                        <el-input v-model="productForm.product_description" type="textarea" :rows="3" placeholder="描述您的产品功能（可选）" />
                    </el-form-item>
                </el-form>
                <div class="step-actions">
                    <el-button @click="prevStep('product')">上一步</el-button>
                    <el-button type="primary" @click="handleCompleteStep('product')">下一步</el-button>
                </div>
            </div>

            <!-- 生成 API 密钥步骤 -->
            <div v-if="currentStep === 'api_key'" class="step-content">
                <h2>生成 API 密钥</h2>
                <p class="step-desc">创建 API 密钥用于系统集成</p>
                <el-form ref="apiKeyFormRef" :model="apiKeyForm" :rules="apiKeyRules" label-width="120px" class="step-form">
                    <el-form-item label="密钥名称" prop="key_name">
                        <el-input v-model="apiKeyForm.key_name" placeholder="例如：生产环境密钥" />
                    </el-form-item>
                    <el-alert type="info" :closable="false" show-icon class="mt-3">
                        <template #title>API 密钥用于 SDK 和 API 调用中的身份验证，请妥善保管</template>
                    </el-alert>
                </el-form>
                <div class="step-actions">
                    <el-button @click="prevStep('api_key')">上一步</el-button>
                    <el-button type="primary" @click="handleCompleteStep('api_key')">完成</el-button>
                </div>
            </div>

            <!-- 完成步骤 -->
            <div v-if="currentStep === 'complete'" class="step-content complete-step">
                <div class="complete-icon">
                    <el-icon :size="72" color="#67C23A"><CircleCheck /></el-icon>
                </div>
                <h1>设置完成！</h1>
                <p class="complete-desc">您已成功完成系统初始化，现在可以开始使用了。</p>

                <div class="quick-actions">
                    <el-card shadow="hover" class="quick-action-card" @click="$router.push('/licenses')">
                        <el-icon :size="28" color="#409EFF"><Key /></el-icon>
                        <span>创建 License</span>
                    </el-card>
                    <el-card shadow="hover" class="quick-action-card" @click="$router.push('/products')">
                        <el-icon :size="28" color="#67C23A"><Goods /></el-icon>
                        <span>管理产品</span>
                    </el-card>
                    <el-card shadow="hover" class="quick-action-card" @click="$router.push('/api-keys')">
                        <el-icon :size="28" color="#E6A23C"><Key /></el-icon>
                        <span>API 密钥</span>
                    </el-card>
                    <el-card shadow="hover" class="quick-action-card" @click="$router.push('/dashboard')">
                        <el-icon :size="28" color="#909399"><Odometer /></el-icon>
                        <span>前往仪表盘</span>
                    </el-card>
                </div>

                <div class="step-actions">
                    <el-button type="primary" size="large" @click="goToDashboard">进入系统</el-button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import {
    MagicStick, Key, Monitor, Lock, Check, CircleCheck,
    Goods, Plus, Odometer,
} from '@element-plus/icons-vue';
import onboardingApi from '@/api/onboarding';

const router = useRouter();

const steps = [
    { key: 'welcome', label: '欢迎' },
    { key: 'profile', label: '资料' },
    { key: 'tenant', label: '团队' },
    { key: 'product', label: '产品' },
    { key: 'api_key', label: 'API 密钥' },
    { key: 'complete', label: '完成' },
];

const currentStep = ref('welcome');
const completedSteps = ref([]);
const progressPct = ref(0);
const loading = ref(false);
const profileFormRef = ref(null);
const tenantFormRef = ref(null);
const productFormRef = ref(null);
const apiKeyFormRef = ref(null);

// 表单数据
const profileForm = reactive({ name: '', phone: '' });
const tenantForm = reactive({ tenant_name: '', logoUrl: '' });
const productForm = reactive({ product_name: '', product_description: '' });
const apiKeyForm = reactive({ key_name: '' });

// 表单校验
const profileRules = { name: [{ required: true, message: '请输入姓名', trigger: 'blur' }] };
const tenantRules = { tenant_name: [{ required: true, message: '请输入团队名称', trigger: 'blur' }] };
const productRules = { product_name: [{ required: true, message: '请输入产品名称', trigger: 'blur' }] };
const apiKeyRules = { key_name: [{ required: true, message: '请输入密钥名称', trigger: 'blur' }] };

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

                // 如果已完成，直接跳转
                if (onboarding.is_completed) {
                    currentStep.value = 'complete';
                    progressPct.value = 100;
                }

                // 预填用户信息
                if (res.data?.user) {
                    if (res.data.user.has_tenant) tenantForm.tenant_name = '已完成';
                    if (res.data.user.has_products) productForm.product_name = '已完成';
                    if (res.data.user.has_api_keys) apiKeyForm.key_name = '已完成';
                }
            }
        }
    } catch {
        ElMessage.error('加载 Onboarding 状态失败');
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
                ElMessage.success('🎉 所有步骤已完成！');
                currentStep.value = 'complete';
                progressPct.value = 100;
            } else {
                ElMessage.success('步骤已完成');
            }
        }
    } catch (err) {
        ElMessage.error(err.response?.data?.message || '操作失败');
    } finally {
        loading.value = false;
    }
}

function nextStep(step) {
    handleCompleteStep(step);
}

function prevStep(step) {
    const idx = steps.findIndex(s => s.key === step);
    if (idx > 0) {
        currentStep.value = steps[idx - 1].key;
    }
}

async function skipOnboarding() {
    loading.value = true;
    try {
        await onboardingApi.skip('用户选择跳过');
        ElMessage.info('已跳过后勤设置');
        currentStep.value = 'complete';
        progressPct.value = 100;
    } catch {
        ElMessage.error('操作失败');
    } finally {
        loading.value = false;
    }
}

function handleLogoChange(file) {
    tenantForm.logoUrl = URL.createObjectURL(file.raw);
    ElMessage.success('Logo 已选择（可在设置中上传）');
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
    box-shadow: 0 0 0 4px rgba(64, 158, 255, 0.2);
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
    box-shadow: 0 4px 12px rgba(64, 158, 255, 0.1);
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
