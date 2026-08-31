<template>
    <div class="openfeature-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('openfeature_page.title') }}</h2>
                <span class="header-subtitle">{{ t('openfeature_page.subtitle') }}</span>
            </div>
            <div class="header-right">
                <el-button @click="loadFlags">
                    <el-icon><Refresh /></el-icon>
                    {{ t('openfeature_page.refresh_btn') }}
                </el-button>
            </div>
        </div>

        <el-alert
            :title="t('openfeature_page.alert_title')"
            type="info"
            :closable="false"
            show-icon
            class="mb-4"
            :description="t('openfeature_page.alert_desc')"
        />

        <!-- 运行状态 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">{{ t('openfeature_page.stats.registered') }}</div>
                        <div class="stat-value">{{ flags.length }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">{{ t('openfeature_page.stats.active') }}</div>
                        <div class="stat-value text-success">{{ activeCount }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">{{ t('openfeature_page.stats.inactive') }}</div>
                        <div class="stat-value text-danger">{{ inactiveCount }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">{{ t('openfeature_page.stats.api_health') }}</div>
                        <div class="stat-value" :class="healthStatusClass">
                            <el-icon><component :is="healthIcon" /></el-icon>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 标志列表 -->
        <el-card shadow="never">
            <template #header>
                <div class="card-header">
                    <span>{{ t('openfeature_page.flags_card_title') }}</span>
                    <div class="header-right">
                        <el-tag type="info" size="small">{{ t('openfeature_page.flagd_tag') }}</el-tag>
                    </div>
                </div>
            </template>

            <el-table :data="flags" v-loading="loading" stripe style="width: 100%">
                <el-table-column type="index" label="#" width="50" />
                <el-table-column prop="key" :label="t('openfeature_page.cols.key')" min-width="200">
                    <template #default="{ row }">
                        <div class="key-cell">
                            <code class="flag-key">{{ row.key }}</code>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column prop="name" :label="t('openfeature_page.cols.name')" min-width="150" />
                <el-table-column prop="description" :label="t('openfeature_page.cols.description')" min-width="200">
                    <template #default="{ row }">
                        <span class="desc-text">{{ row.description || '-' }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="is_active" :label="t('openfeature_page.cols.status')" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                            {{ statusLabel(row.is_active) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('openfeature_page.cols.evaluation')" width="180">
                    <template #default="{ row }">
                        <div v-if="row.evaluated" class="evaluation-cell">
                            <el-tag :type="row.evaluated.value ? 'success' : 'danger'" size="small" effect="dark">
                                {{ evalValueLabel(row.evaluated.value) }}
                            </el-tag>
                            <span class="eval-reason">{{ row.evaluated.reason || t('openfeature_page.eval.default_reason') }}</span>
                            <span v-if="row.evaluated.variant" class="eval-variant">
                                [{{ row.evaluated.variant }}]
                            </span>
                        </div>
                        <span v-else class="no-eval">{{ t('openfeature_page.eval.not_evaluated') }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="t('openfeature_page.cols.actions')" width="100" fixed="right">
                    <template #default="{ row }">
                        <el-button text type="primary" size="small" @click="openTestDialog(row)">
                            {{ t('openfeature_page.test_eval_btn') }}
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>

            <el-empty v-if="flags.length === 0 && !loading" :image-size="80" :description="t('openfeature_page.empty')" />
        </el-card>

        <!-- 测试评估 Dialog -->
        <el-dialog v-model="showTestDialog" :title="t('openfeature_page.test_dialog_title')" width="480px">
            <p class="dialog-subtitle">{{ t('openfeature_page.test_dialog_subtitle', { key: testFlag?.key }) }}</p>
            <el-form label-position="top">
                <el-form-item :label="t('openfeature_page.context_label')">
                    <el-input
                        v-model="testContextJson"
                        type="textarea"
                        :rows="6"
                        :placeholder="t('openfeature_page.context_ph')"
                    />
                </el-form-item>
                <el-form-item :label="t('openfeature_page.result_label')">
                    <div v-if="testResult !== null" class="test-result">
                        <el-tag :type="testResult ? 'success' : 'danger'" size="large">
                            {{ evalValueLabel(testResult) }}
                        </el-tag>
                        <span v-if="testReason" class="test-reason">{{ t('openfeature_page.reason_label') }}: {{ testReason }}</span>
                        <span v-if="testVariant" class="test-variant">{{ t('openfeature_page.variant_label') }}: {{ testVariant }}</span>
                    </div>
                    <span v-else class="no-result">{{ t('openfeature_page.no_result_hint') }}</span>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showTestDialog = false">{{ t('actions.close') }}</el-button>
                <el-button type="primary" @click="handleTestEval" :loading="testing">
                    {{ t('openfeature_page.test_eval_btn') }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Refresh, CircleCheck, CircleClose } from '@element-plus/icons-vue';
import openFeatureApi from '@/api/openFeature';

const { t } = useI18n();

const loading = ref(false);
const testing = ref(false);
const flags = ref([]);
const showTestDialog = ref(false);
const testFlag = ref(null);
const testContextJson = ref('');
const testResult = ref(null);
const testReason = ref('');
const testVariant = ref('');

const activeCount = computed(() => flags.value.filter(f => f.is_active).length);
const inactiveCount = computed(() => flags.value.filter(f => !f.is_active).length);

const healthStatusClass = computed(() => flags.value.length > 0 ? 'text-success' : 'text-warning');
const healthIcon = computed(() => flags.value.length > 0 ? CircleCheck : CircleClose);

const statusLabels = computed(() => ({
    true: t('openfeature_page.status.enabled'),
    false: t('openfeature_page.status.disabled'),
}));

const evalValueLabels = computed(() => ({
    true: t('openfeature_page.eval.true'),
    false: t('openfeature_page.eval.false'),
    na: t('openfeature_page.eval.na'),
}));

function statusLabel(isActive) {
    return statusLabels.value[isActive ? 'true' : 'false'];
}

function evalValueLabel(value) {
    if (value === undefined) return evalValueLabels.value.na;
    return evalValueLabels.value[value ? 'true' : 'false'];
}

async function loadFlags() {
    loading.value = true;
    try {
        const { data: res } = await openFeatureApi.manageAllFlags();
        flags.value = res.data || [];
    } catch {
        flags.value = [];
    } finally {
        loading.value = false;
    }
}

async function checkHealth() {
    try {
        const { data: res } = await openFeatureApi.health();
        return res.data?.healthy ?? false;
    } catch {
        return false;
    }
}

function openTestDialog(flag) {
    testFlag.value = flag;
    testContextJson.value = JSON.stringify({ targetingKey: 'test-user' }, null, 2);
    testResult.value = null;
    testReason.value = '';
    testVariant.value = '';
    showTestDialog.value = true;
}

async function handleTestEval() {
    if (!testFlag.value) return;

    testing.value = true;
    testResult.value = null;
    try {
        let context = {};
        try {
            context = JSON.parse(testContextJson.value);
        } catch {
            context = {};
        }

        const flagKey = testFlag.value.key;
        const { data: res } = await openFeatureApi.evaluate({
            flagKey,
            type: 'boolean',
            defaultValue: false,
            context,
        });

        testResult.value = res.data?.value ?? false;
        testReason.value = res.data?.reason ?? '';
        testVariant.value = res.data?.variant ?? '';
    } catch (err) {
        ElMessage.error(t('openfeature_page.messages.eval_request_failed'));
    } finally {
        testing.value = false;
    }
}

onMounted(() => {
    loadFlags();
});
</script>

<style scoped>
.openfeature-page { padding: 20px; }

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

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-weight: 600;
}

.stat-item {
    text-align: center;
    padding: 8px 0;
}
.stat-label {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-bottom: 6px;
}
.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--el-text-color-primary);
}
.text-success { color: var(--el-color-success); }
.text-danger { color: var(--el-color-danger); }
.text-warning { color: var(--el-color-warning); }

.key-cell {
    display: flex;
    align-items: center;
}
.flag-key {
    background: #f5f7fa;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 13px;
    color: var(--el-color-primary);
    font-weight: 600;
}

.desc-text {
    font-size: 13px;
    color: var(--el-text-color-secondary);
}

.evaluation-cell {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.eval-reason {
    font-size: 11px;
    color: var(--el-text-color-placeholder);
}
.eval-variant {
    font-size: 11px;
    color: var(--el-text-color-secondary);
    background: #f5f7fa;
    padding: 1px 4px;
    border-radius: 3px;
}
.no-eval {
    font-size: 12px;
    color: var(--el-text-color-placeholder);
}

.dialog-subtitle {
    font-size: 14px;
    color: var(--el-text-color-secondary);
    margin-bottom: 16px;
}

.test-result {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: var(--el-color-info-light-9);
    border-radius: 6px;
}
.test-reason, .test-variant {
    font-size: 12px;
    color: var(--el-text-color-secondary);
}
.no-result {
    font-size: 13px;
    color: var(--el-text-color-placeholder);
}

:deep(.el-card__body) { padding: 16px; }
</style>
