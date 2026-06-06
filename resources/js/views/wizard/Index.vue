<template>
    <div class="wizard-page">
        <div class="page-header">
            <h2>AI 集成向导</h2>
            <p class="header-subtitle">选择语言 → 选择产品 → 获取配置 → 验证连通性，5 分钟完成接入</p>
        </div>

        <!-- 步骤条 -->
        <el-steps :active="currentStep - 1" align-center class="wizard-steps">
            <el-step title="选择语言" description="选择您的开发语言" />
            <el-step title="选择产品" description="选择要集成的产品" />
            <el-step title="获取配置" description="自动生成 SDK 代码" />
            <el-step title="验证连通" description="一键测试 API 连接" />
        </el-steps>

        <!-- 步骤 1: 选择语言 -->
        <div v-if="currentStep === 1" class="step-content">
            <h3 class="step-title">选择您的开发语言</h3>
            <p class="step-desc">我们为您生成了各语言的 SDK 配置代码</p>

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
                    下一步
                    <el-icon><ArrowRight /></el-icon>
                </el-button>
            </div>
        </div>

        <!-- 步骤 2: 选择产品 -->
        <div v-if="currentStep === 2" class="step-content">
            <h3 class="step-title">选择要集成的产品</h3>
            <p class="step-desc">选择您已购买或需要测试的产品</p>

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
                        <div class="product-desc">{{ product.description || '暂无描述' }}</div>
                    </div>
                    <el-icon v-if="selectedProduct === product.id" class="check-icon" color="#409EFF">
                        <CircleCheck />
                    </el-icon>
                </div>

                <el-empty v-if="!productsLoading && productList.length === 0" description="暂无可用产品" />
            </div>

            <div class="step-actions">
                <el-button @click="prevStep">
                    <el-icon><ArrowLeft /></el-icon> 上一步
                </el-button>
                <el-button type="primary" size="large" :disabled="!selectedProduct" @click="nextStep">
                    下一步
                    <el-icon><ArrowRight /></el-icon>
                </el-button>
            </div>
        </div>

        <!-- 步骤 3: 获取配置 -->
        <div v-if="currentStep === 3" class="step-content">
            <h3 class="step-title">SDK 配置代码</h3>
            <p class="step-desc">根据您选择的语言和产品，自动生成集成代码</p>

            <div v-if="!generatedConfig" class="config-form">
                <el-form :model="configForm" label-width="120px" label-position="right">
                    <el-form-item label="License Key" required>
                        <el-input
                            v-model="configForm.license_key"
                            placeholder="输入您的 License Key"
                            style="max-width: 450px;"
                        />
                        <div class="form-tip">没有 License？先去「License 管理」创建一个</div>
                    </el-form-item>
                    <el-form-item label="API 地址">
                        <el-input
                            v-model="configForm.api_host"
                            placeholder="默认: {{ defaultApiUrl }}"
                            style="max-width: 450px;"
                        />
                        <div class="form-tip">默认使用当前服务的 API 地址</div>
                    </el-form-item>
                </el-form>

                <div class="step-actions">
                    <el-button @click="prevStep">
                        <el-icon><ArrowLeft /></el-icon> 上一步
                    </el-button>
                    <el-button type="primary" size="large" :loading="generatingConfig" @click="generateConfig">
                        <el-icon><MagicStick /></el-icon> 生成配置代码
                    </el-button>
                </div>
            </div>

            <!-- 生成的代码 -->
            <div v-else class="config-result">
                <el-alert
                    title="配置已生成！您可以复制下方代码到您的项目中。"
                    type="success"
                    show-icon
                    :closable="false"
                    class="mb-4"
                />

                <el-tabs v-model="activeTab" type="border-card">
                    <el-tab-pane label="激活代码" name="activate">
                        <div class="code-block">
                            <div class="code-header">
                                <span class="code-lang">{{ selectedLanguageLabel }} - 激活 License</span>
                                <el-button text size="small" @click="copyCode(generatedConfig.snippets?.activate)">
                                    <el-icon><CopyDocument /></el-icon> 复制
                                </el-button>
                            </div>
                            <pre><code>{{ generatedConfig.snippets?.activate || '// 暂无代码' }}</code></pre>
                        </div>
                    </el-tab-pane>
                    <el-tab-pane label="验证代码" name="validate">
                        <div class="code-block">
                            <div class="code-header">
                                <span class="code-lang">{{ selectedLanguageLabel }} - 验证 License</span>
                                <el-button text size="small" @click="copyCode(generatedConfig.snippets?.validate)">
                                    <el-icon><CopyDocument /></el-icon> 复制
                                </el-button>
                            </div>
                            <pre><code>{{ generatedConfig.snippets?.validate || '// 暂无代码' }}</code></pre>
                        </div>
                    </el-tab-pane>
                </el-tabs>

                <el-card shadow="never" class="instructions-card">
                    <template #header>
                        <span><el-icon><InfoFilled /></el-icon> 集成步骤</span>
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
                        <el-icon><Edit /></el-icon> 重新配置
                    </el-button>
                    <el-button type="primary" size="large" @click="nextStep">
                        <el-icon><Connection /></el-icon> 验证连通性
                    </el-button>
                </div>
            </div>
        </div>

        <!-- 步骤 4: 验证连通性 -->
        <div v-if="currentStep === 4" class="step-content">
            <h3 class="step-title">验证连通性</h3>
            <p class="step-desc">测试您的 License Key 与服务端的连接是否正常</p>

            <div v-if="!connectivityResult" class="connectivity-start">
                <el-button
                    type="primary"
                    size="large"
                    :loading="testingConnectivity"
                    @click="testConnectivity"
                >
                    <el-icon><Aim /></el-icon> 开始测试
                </el-button>
                <p class="test-desc">将使用 {{ configForm.license_key ? configForm.license_key.slice(0, 16) + '...' : '已配置的 License' }} 进行连通性测试</p>
            </div>

            <div v-else class="connectivity-result">
                <el-result
                    :icon="connectivityResult.overall_success ? 'success' : 'error'"
                    :title="connectivityResult.overall_success ? '所有检查通过！' : '部分检查未通过'"
                    :sub-title="connectivityResult.overall_success ? '集成配置可用，可以开始开发了！' : '请根据下方详情修复问题'"
                >
                    <template #extra>
                        <el-button
                            v-if="!connectivityResult.overall_success"
                            type="primary"
                            @click="testConnectivity"
                        >
                            重试
                        </el-button>
                        <el-button @click="currentStep = 3">返回配置</el-button>
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
                        <el-icon><Key /></el-icon> 管理 License
                    </el-button>
                    <el-button @click="$router.push('/dashboard')">
                        返回仪表盘
                    </el-button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import {
    ArrowRight, ArrowLeft, CircleCheck, MagicStick,
    CopyDocument, InfoFilled, Edit, Connection, Aim, Key,
} from '@element-plus/icons-vue';
import wizardApi from '@/api/wizard';

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

const checkLabels = {
    api_reachable: 'API 服务可达性',
    license_valid: 'License 有效性',
    sdk_handshake: 'SDK 握手测试',
};

const selectedLanguageLabel = computed(() => {
    const lang = languages.value.find(l => l.id === selectedLanguage.value);
    return lang ? lang.name : selectedLanguage.value.toUpperCase();
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
        ElMessage.warning('加载产品列表失败');
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
        ElMessage.warning('请输入 License Key');
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
            ElMessage.success('配置代码已生成');
        }
    } catch {
        ElMessage.error('配置生成失败');
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
                ElMessage.success('所有检查通过！');
            } else {
                ElMessage.warning('部分检查未通过，请查看详情');
            }
        }
    } catch {
        ElMessage.error('连通性测试失败');
        connectivityResult.value = {
            overall_success: false,
            checks: {
                api_reachable: { success: false, status: 0, message: '请求异常' },
            },
        };
    } finally {
        testingConnectivity.value = false;
    }
}

function copyCode(code) {
    if (!code) return;
    navigator.clipboard.writeText(code).then(() => {
        ElMessage.success('代码已复制到剪贴板');
    }).catch(() => {
        // fallback
        const ta = document.createElement('textarea');
        ta.value = code;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        ElMessage.success('代码已复制到剪贴板');
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
.icon-placeholder.api { background: #409EFF; }
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
