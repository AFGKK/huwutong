<template>
    <div class="openfeature-page">
        <div class="page-header">
            <div class="header-left">
                <h2>OpenFeature 标志管理</h2>
                <span class="header-subtitle">遵循 OpenFeature 标准的统一 Feature Flag 评估和监控</span>
            </div>
            <div class="header-right">
                <el-button @click="loadFlags">
                    <el-icon><Refresh /></el-icon>
                    刷新评估
                </el-button>
            </div>
        </div>

        <el-alert
            title="OpenFeature 标准"
            type="info"
            :closable="false"
            show-icon
            class="mb-4"
            description="OpenFeature 是一个开放标准，提供统一的功能标志评估 API。兼容 flagd、LaunchDarkly、Split 等主流 Provider。客户端可通过标准 SDK 进行功能开关评估。"
        />

        <!-- 运行状态 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">已注册标志</div>
                        <div class="stat-value">{{ flags.length }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">激活中</div>
                        <div class="stat-value text-success">{{ activeCount }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">已禁用</div>
                        <div class="stat-value text-danger">{{ inactiveCount }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">API 健康状态</div>
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
                    <span>所有 Feature Flag</span>
                    <div class="header-right">
                        <el-tag type="info" size="small">flagd 兼容</el-tag>
                    </div>
                </div>
            </template>

            <el-table :data="flags" v-loading="loading" stripe style="width: 100%">
                <el-table-column type="index" label="#" width="50" />
                <el-table-column prop="key" label="Flag Key" min-width="200">
                    <template #default="{ row }">
                        <div class="key-cell">
                            <code class="flag-key">{{ row.key }}</code>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column prop="name" label="名称" min-width="150" />
                <el-table-column prop="description" label="描述" min-width="200">
                    <template #default="{ row }">
                        <span class="desc-text">{{ row.description || '-' }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="is_active" label="启用状态" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                            {{ row.is_active ? '启用' : '禁用' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="评估结果" width="180">
                    <template #default="{ row }">
                        <div v-if="row.evaluated" class="evaluation-cell">
                            <el-tag :type="row.evaluated.value ? 'success' : 'danger'" size="small" effect="dark">
                                {{ row.evaluated.value !== undefined ? (row.evaluated.value ? 'TRUE' : 'FALSE') : 'N/A' }}
                            </el-tag>
                            <span class="eval-reason">{{ row.evaluated.reason || 'DEFAULT' }}</span>
                            <span v-if="row.evaluated.variant" class="eval-variant">
                                [{{ row.evaluated.variant }}]
                            </span>
                        </div>
                        <span v-else class="no-eval">未评估</span>
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="100" fixed="right">
                    <template #default="{ row }">
                        <el-button text type="primary" size="small" @click="openTestDialog(row)">
                            测试评估
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>

            <el-empty v-if="flags.length === 0 && !loading" :image-size="80" description="暂无 Feature Flag" />
        </el-card>

        <!-- 测试评估 Dialog -->
        <el-dialog v-model="showTestDialog" title="测试 Flag 评估" width="480px">
            <p class="dialog-subtitle">测试 Flag <strong>{{ testFlag?.key }}</strong> 在当前上下文中的评估结果</p>
            <el-form label-position="top">
                <el-form-item label="上下文属性（JSON）">
                    <el-input
                        v-model="testContextJson"
                        type="textarea"
                        :rows="6"
                        placeholder='{"targetingKey": "user-123", "email": "user@example.com"}'
                    />
                </el-form-item>
                <el-form-item label="评估结果">
                    <div v-if="testResult !== null" class="test-result">
                        <el-tag :type="testResult ? 'success' : 'danger'" size="large">
                            {{ testResult ? 'TRUE' : 'FALSE' }}
                        </el-tag>
                        <span v-if="testReason" class="test-reason">Reason: {{ testReason }}</span>
                        <span v-if="testVariant" class="test-variant">Variant: {{ testVariant }}</span>
                    </div>
                    <span v-else class="no-result">点击「测试评估」查看结果</span>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showTestDialog = false">关闭</el-button>
                <el-button type="primary" @click="handleTestEval" :loading="testing">
                    测试评估
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Refresh, CircleCheck, CircleClose } from '@element-plus/icons-vue';
import apiClient from '@/api/client';

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

async function loadFlags() {
    loading.value = true;
    try {
        const { data: res } = await apiClient.get('/openfeature/manage/flags');
        flags.value = res.data || [];
    } catch {
        flags.value = [];
    } finally {
        loading.value = false;
    }
}

async function checkHealth() {
    try {
        const { data: res } = await apiClient.get('/openfeature/health');
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
        const { data: res } = await apiClient.post('/openfeature/evaluate', {
            flagKey,
            type: 'boolean',
            defaultValue: false,
            context,
        });

        testResult.value = res.data?.value ?? false;
        testReason.value = res.data?.reason ?? '';
        testVariant.value = res.data?.variant ?? '';
    } catch (err) {
        ElMessage.error('评估请求失败');
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
