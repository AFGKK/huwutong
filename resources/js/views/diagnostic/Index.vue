<template>
    <div class="diagnostic-page">
        <div class="page-header">
            <div class="header-left">
                <h2>AI 错误诊断助手</h2>
                <span class="header-subtitle">智能分析 License 激活/验证失败原因，提供解决方案</span>
            </div>
        </div>

        <el-alert
            title="AI 驱动诊断"
            type="info"
            :closable="false"
            show-icon
            class="mb-4"
            description="输入 License Key、设备指纹或错误码，AI 将自动分析根因并给出缓解建议。支持常见错误：LICENSE_EXPIRED、DEVICE_LIMIT、FINGERPRINT_MISMATCH 等。"
        />

        <el-row :gutter="16">
            <!-- 诊断输入区 -->
            <el-col :span="10">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>诊断输入</span>
                            <el-tag type="warning" size="small" effect="dark">AI</el-tag>
                        </div>
                    </template>

                    <el-form :model="form" label-position="top">
                        <el-form-item label="License Key">
                            <el-input
                                v-model="form.license_key"
                                placeholder="输入 License Key"
                                clearable
                            />
                        </el-form-item>
                        <el-form-item label="设备指纹">
                            <el-input
                                v-model="form.device_fingerprint"
                                placeholder="输入设备指纹（可选）"
                                clearable
                            />
                        </el-form-item>
                        <el-form-item label="错误码">
                            <el-select v-model="form.error_code" clearable placeholder="选择或输入错误码" filterable allow-create style="width: 100%">
                                <el-option label="LICENSE_EXPIRED" value="LICENSE_EXPIRED" />
                                <el-option label="DEVICE_LIMIT" value="DEVICE_LIMIT" />
                                <el-option label="FINGERPRINT_MISMATCH" value="FINGERPRINT_MISMATCH" />
                                <el-option label="LICENSE_REVOKED" value="LICENSE_REVOKED" />
                                <el-option label="LICENSE_SUSPENDED" value="LICENSE_SUSPENDED" />
                                <el-option label="INVALID_LICENSE_KEY" value="INVALID_LICENSE_KEY" />
                                <el-option label="ACTIVATION_EXPIRED" value="ACTIVATION_EXPIRED" />
                                <el-option label="OFFLINE_ACTIVATION_FAILED" value="OFFLINE_ACTIVATION_FAILED" />
                                <el-option label="RATE_LIMIT_EXCEEDED" value="RATE_LIMIT_EXCEEDED" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="错误消息（可选）">
                            <el-input
                                v-model="form.error_message"
                                type="textarea"
                                :rows="3"
                                placeholder="粘贴完整的错误消息"
                            />
                        </el-form-item>
                        <el-form-item>
                            <el-button
                                type="primary"
                                :loading="diagnosing"
                                @click="handleDiagnose"
                                size="large"
                                style="width: 100%"
                            >
                                <el-icon><MagicStick /></el-icon>
                                AI 诊断分析
                            </el-button>
                        </el-form-item>
                    </el-form>
                </el-card>

                <!-- SDK 建议对照表 -->
                <el-card shadow="never" class="mt-4">
                    <template #header>
                        <div class="card-header">
                            <span>SDK 错误码建议</span>
                            <el-button text size="small" @click="loadSdkSuggestions">刷新</el-button>
                        </div>
                    </template>
                    <div v-loading="loadingSuggestions" class="sdk-suggestions">
                        <div v-for="(suggestion, key) in sdkSuggestions" :key="key" class="suggestion-item">
                            <code class="suggestion-code">{{ key }}</code>
                            <span class="suggestion-action">{{ suggestion }}</span>
                        </div>
                        <el-empty v-if="!Object.keys(sdkSuggestions).length" description="暂无数据" :image-size="50" />
                    </div>
                </el-card>
            </el-col>

            <!-- 诊断结果区 -->
            <el-col :span="14">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>诊断结果</span>
                            <el-tag v-if="diagnosisResult" :type="resultType" size="small">
                                {{ resultStatus }}
                            </el-tag>
                        </div>
                    </template>

                    <div v-if="!diagnosisResult && !diagnosing" class="result-placeholder">
                        <el-empty
                            :image-size="100"
                            description="输入信息后点击「AI 诊断分析」获取结果"
                        >
                            <template #image>
                                <el-icon :size="80" color="#c0c4cc"><MagicStick /></el-icon>
                            </template>
                        </el-empty>
                    </div>

                    <div v-loading="diagnosing" class="diagnosis-result" v-if="diagnosisResult">
                        <!-- 根因分析 -->
                        <div class="result-section">
                            <h4 class="section-title">
                                <el-icon color="#e6a23c"><WarningFilled /></el-icon>
                                根因分析
                            </h4>
                            <div class="section-content" v-if="diagnosisResult.root_cause">
                                <div class="cause-item" v-for="(cause, idx) in diagnosisResult.root_cause" :key="idx">
                                    <el-tag :type="cause.severity === 'critical' ? 'danger' : cause.severity === 'warning' ? 'warning' : 'info'" size="small" effect="dark" style="margin-right: 8px">
                                        {{ cause.severity === 'critical' ? '严重' : cause.severity === 'warning' ? '警告' : '提示' }}
                                    </el-tag>
                                    <span>{{ cause.message }}</span>
                                </div>
                                <div v-if="!diagnosisResult.root_cause.length" class="no-detail">未检测到具体根因</div>
                            </div>
                        </div>

                        <!-- 解决方案 -->
                        <div class="result-section" v-if="diagnosisResult.suggestions?.length">
                            <h4 class="section-title">
                                <el-icon color="#67c23a"><CircleCheck /></el-icon>
                                建议解决方案
                            </h4>
                            <div class="suggestion-list">
                                <div v-for="(s, idx) in diagnosisResult.suggestions" :key="idx" class="suggestion-step" :class="'priority-' + (s.priority || 'medium')">
                                    <div class="step-number">{{ idx + 1 }}</div>
                                    <div class="step-content">
                                        <div class="step-title">{{ s.title || s }}</div>
                                        <div v-if="s.description" class="step-desc">{{ s.description }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 相关资源 -->
                        <div class="result-section" v-if="diagnosisResult.related_resources?.length">
                            <h4 class="section-title">
                                <el-icon color="#409eff"><Link /></el-icon>
                                相关资源
                            </h4>
                            <div class="resource-list">
                                <el-link
                                    v-for="(r, idx) in diagnosisResult.related_resources"
                                    :key="idx"
                                    type="primary"
                                    :href="r.url"
                                    :underline="'never'"
                                    class="resource-link"
                                >
                                    {{ r.title || r }}
                                </el-link>
                            </div>
                        </div>

                        <!-- License 信息 -->
                        <div class="result-section" v-if="diagnosisResult.license_info">
                            <h4 class="section-title">
                                <el-icon color="#409eff"><InfoFilled /></el-icon>
                                License 信息
                            </h4>
                            <div class="info-grid">
                                <div class="info-row">
                                    <span class="info-label">状态</span>
                                    <el-tag :type="licenseStatusType(diagnosisResult.license_info.status)" size="small">
                                        {{ diagnosisResult.license_info.status }}
                                    </el-tag>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">类型</span>
                                    <span>{{ diagnosisResult.license_info.type || '-' }}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">过期时间</span>
                                    <span>{{ diagnosisResult.license_info.expires_at ? formatDate(diagnosisResult.license_info.expires_at) : '-' }}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">设备数</span>
                                    <span>{{ diagnosisResult.license_info.active_devices || 0 }} / {{ diagnosisResult.license_info.max_devices || '-' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { MagicStick, WarningFilled, CircleCheck, Link, InfoFilled } from '@element-plus/icons-vue';
import apiClient from '@/api/client';

const diagnosing = ref(false);
const loadingSuggestions = ref(false);
const diagnosisResult = ref(null);
const sdkSuggestions = ref({});

const form = reactive({
    license_key: '',
    device_fingerprint: '',
    error_code: '',
    error_message: '',
});

const resultType = computed(() => {
    if (!diagnosisResult.value) return 'info';
    const hasCritical = diagnosisResult.value.root_cause?.some(c => c.severity === 'critical');
    const hasWarning = diagnosisResult.value.root_cause?.some(c => c.severity === 'warning');
    if (hasCritical) return 'danger';
    if (hasWarning) return 'warning';
    return 'success';
});

const resultStatus = computed(() => {
    if (!diagnosisResult.value) return '';
    const hasCritical = diagnosisResult.value.root_cause?.some(c => c.severity === 'critical');
    if (hasCritical) return '需立即处理';
    if (diagnosisResult.value.suggestions?.length) return '建议处理';
    return '无需处理';
});

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit',
    });
}

function licenseStatusType(status) {
    const map = {
        active: 'success', expired: 'danger', suspended: 'warning',
        revoked: 'danger', trial: 'primary',
    };
    return map[status] || 'info';
}

async function handleDiagnose() {
    if (!form.license_key && !form.error_code) {
        ElMessage.warning('请输入 License Key 或选择错误码');
        return;
    }

    diagnosing.value = true;
    diagnosisResult.value = null;

    try {
        const payload = {};
        if (form.license_key) payload.license_key = form.license_key;
        if (form.device_fingerprint) payload.device_fingerprint = form.device_fingerprint;
        if (form.error_code) payload.error_code = form.error_code;
        if (form.error_message) payload.error_message = form.error_message;

        const { data: res } = await apiClient.post('/diagnostic/activation', payload);
        diagnosisResult.value = res.data || {};
        ElMessage.success('诊断完成');
    } catch (err) {
        if (err?.response?.status === 422) {
            ElMessage.warning('请输入有效的诊断信息');
        } else {
            ElMessage.error('诊断请求失败，请稍后重试');
        }
    } finally {
        diagnosing.value = false;
    }
}

async function loadSdkSuggestions() {
    loadingSuggestions.value = true;
    try {
        const { data: res } = await apiClient.get('/diagnostic/sdk-suggestions');
        sdkSuggestions.value = res.data || {};
    } catch {
        sdkSuggestions.value = {};
    } finally {
        loadingSuggestions.value = false;
    }
}

onMounted(() => {
    loadSdkSuggestions();
});
</script>

<style scoped>
.diagnostic-page { padding: 20px; }

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}
.header-left h2 { margin: 0; font-size: 20px; }
.header-subtitle {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-left: 12px;
}

.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-weight: 600;
}

.result-placeholder {
    padding: 60px 0;
}

.diagnosis-result {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.result-section {
    padding: 16px;
    background: var(--el-color-info-light-9);
    border-radius: 8px;
}

.section-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 15px;
    font-weight: 600;
    color: var(--el-text-color-primary);
    margin: 0 0 12px 0;
}

.section-content {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.cause-item {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    font-size: 14px;
    line-height: 1.5;
}
.no-detail {
    font-size: 13px;
    color: var(--el-text-color-secondary);
}

.suggestion-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.suggestion-step {
    display: flex;
    gap: 12px;
    padding: 10px 12px;
    background: #fff;
    border-radius: 6px;
    border: 1px solid var(--el-border-color-light);
}
.step-number {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: var(--el-color-primary);
    color: #fff;
    font-size: 12px;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.step-content {
    flex: 1;
}
.step-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--el-text-color-primary);
}
.step-desc {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-top: 2px;
}

.resource-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.resource-link {
    justify-content: flex-start;
}

.info-grid {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.info-row {
    display: flex;
    align-items: center;
    gap: 12px;
}
.info-label {
    width: 80px;
    font-size: 13px;
    color: var(--el-text-color-secondary);
    flex-shrink: 0;
}

.sdk-suggestions {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.suggestion-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 0;
    border-bottom: 1px solid var(--el-border-color-light);
}
.suggestion-item:last-child { border-bottom: none; }
.suggestion-code {
    font-size: 12px;
    background: #f5f7fa;
    padding: 2px 6px;
    border-radius: 3px;
    white-space: nowrap;
    flex-shrink: 0;
}
.suggestion-action {
    font-size: 13px;
    color: var(--el-text-color-regular);
}

:deep(.el-card__body) { padding: 16px; }
</style>
