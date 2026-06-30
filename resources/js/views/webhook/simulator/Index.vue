<template>
    <div class="webhook-simulator-page">
        <el-card shadow="never">
            <template #header>
                <div class="card-header">
                    <span>Webhook 事件模拟器</span>
                </div>
            </template>

            <!-- 模拟表单 -->
            <el-form :model="form" label-width="120px" size="default">
                <el-form-item label="事件类型">
                    <el-select
                        v-model="form.event_type"
                        filterable
                        placeholder="选择事件类型"
                        style="width: 320px"
                        @change="handleEventTypeChange"
                    >
                        <el-option-group
                            v-for="group in groupedEventTypes"
                            :key="group.label"
                            :label="group.label"
                        >
                            <el-option
                                v-for="type in group.types"
                                :key="type.value"
                                :label="type.label"
                                :value="type.value"
                            />
                        </el-option-group>
                    </el-select>
                    <el-button text size="small" type="info" class="ml-2" @click="showEventInfo" v-if="form.event_type">
                        查看说明
                    </el-button>
                </el-form-item>

                <el-form-item label="发送方式">
                    <el-radio-group v-model="form.delivery_mode">
                        <el-radio value="broadcast">广播到所有匹配端点</el-radio>
                        <el-radio value="targeted">定向到指定端点</el-radio>
                    </el-radio-group>
                </el-form-item>

                <el-form-item label="目标端点" v-if="form.delivery_mode === 'targeted'">
                    <el-select
                        v-model="form.endpoint_id"
                        filterable
                        placeholder="选择端点"
                        style="width: 320px"
                    >
                        <el-option
                            v-for="ep in endpoints"
                            :key="ep.id"
                            :label="`${ep.name} (${ep.url})`"
                            :value="ep.id"
                        />
                    </el-select>
                </el-form-item>

                <el-form-item label="Payload">
                    <!-- 当选择事件类型后显示示例 payload -->
                    <div class="payload-section">
                        <div class="payload-tabs">
                            <el-radio-group v-model="payloadMode" size="small">
                                <el-radio-button value="sample">示例数据</el-radio-button>
                                <el-radio-button value="custom">自定义</el-radio-button>
                            </el-radio-group>
                        </div>
                        <el-input
                            v-if="payloadMode === 'custom'"
                            v-model="form.payload_json"
                            type="textarea"
                            :rows="10"
                            placeholder="输入 JSON payload"
                            style="width: 100%; font-family: monospace;"
                        />
                        <div v-else class="sample-payload">
                            <pre><code>{{ samplePayload }}</code></pre>
                        </div>
                    </div>
                </el-form-item>

                <el-form-item label="备注">
                    <el-input
                        v-model="form.description"
                        placeholder="可选：描述此次模拟测试的目的"
                        maxlength="500"
                        show-word-limit
                        style="width: 400px"
                    />
                </el-form-item>

                <el-form-item>
                    <el-button type="primary" :loading="sending" @click="handleSimulate" size="large">
                        <el-icon><Connection /></el-icon> 发送模拟事件
                    </el-button>
                    <el-button @click="resetForm" size="large">重置</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 发送结果 -->
        <el-card shadow="never" v-if="result" class="mt-4">
            <template #header>
                <div class="card-header">
                    <span>
                        发送结果
                        <el-tag :type="resultSuccess ? 'success' : 'warning'" size="small" class="ml-2">
                            {{ resultSuccess ? '部分成功' : '全部失败' }}
                        </el-tag>
                    </span>
                </div>
            </template>
            <div class="result-summary">
                <span>发送到 <strong>{{ result.dispatch_count }}</strong> 个端点</span>
            </div>
            <el-table :data="result.results || []" size="small" stripe>
                <el-table-column label="端点" min-width="180">
                    <template #default="{ row }">
                        <div>{{ row.endpoint?.name || '-' }}</div>
                        <div class="endpoint-url">{{ row.endpoint?.url }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="120">
                    <template #default="{ row }">
                        <el-tag :type="row.success ? 'success' : 'danger'" size="small">
                            {{ row.success ? '已发送' : (row.status || '失败') }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="HTTP 状态" width="100">
                    <template #default="{ row }">{{ row.status_code || '-' }}</template>
                </el-table-column>
                <el-table-column label="响应" min-width="200">
                    <template #default="{ row }">
                        <div class="response-text">{{ row.response_body || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="时间" width="170">
                    <template #default="{ row }">{{ row.created_at || '-' }}</template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- 历史记录 -->
        <el-card shadow="never" class="mt-4">
            <template #header>
                <div class="card-header">
                    <span>模拟历史记录</span>
                    <div>
                        <el-form :inline="true" size="small">
                            <el-form-item>
                                <el-select
                                    v-model="historyFilters.event_type"
                                    placeholder="事件类型"
                                    clearable
                                    @change="fetchHistory"
                                    style="width: 160px"
                                >
                                    <el-option v-for="t in flatEventTypes" :key="t.value" :label="t.label" :value="t.value" />
                                </el-select>
                            </el-form-item>
                            <el-form-item>
                                <el-select v-model="historyFilters.status" placeholder="状态" clearable @change="fetchHistory" style="width: 120px">
                                    <el-option label="成功" value="delivered" />
                                    <el-option label="失败" value="failed" />
                                    <el-option label="重试中" value="retrying" />
                                </el-select>
                            </el-form-item>
                            <el-form-item>
                                <el-button type="primary" @click="fetchHistory"><el-icon><Search /></el-icon> 查询</el-button>
                            </el-form-item>
                        </el-form>
                    </div>
                </div>
            </template>
            <el-table :data="history" v-loading="historyLoading" stripe>
                <el-table-column prop="event_type" label="事件类型" width="180" />
                <el-table-column label="端点" min-width="160">
                    <template #default="{ row }">
                        {{ row.endpoint?.name || '-' }}
                        <div class="endpoint-url">{{ row.endpoint?.url }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="90">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'delivered' ? 'success' : row.status === 'retrying' ? 'warning' : 'danger'" size="small">
                            {{ row.status === 'delivered' ? '成功' : row.status === 'retrying' ? '重试中' : '失败' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="HTTP 状态" width="90">
                    <template #default="{ row }">{{ row.status_code || '-' }}</template>
                </el-table-column>
                <el-table-column prop="description" label="备注" min-width="150">
                    <template #default="{ row }">{{ row.description || '-' }}</template>
                </el-table-column>
                <el-table-column prop="created_at" label="时间" width="170" />
            </el-table>
            <div class="pagination-wrap" v-if="historyTotal > 0">
                <el-pagination
                    v-model:current-page="historyPage"
                    v-model:page-size="historyPerPage"
                    :total="historyTotal"
                    :page-sizes="[10, 20, 50]"
                    layout="total, sizes, prev, pager, next"
                    @change="fetchHistory"
                />
            </div>
        </el-card>

        <!-- 事件说明对话框 -->
        <el-dialog v-model="showEventInfoDialog" title="事件说明" width="500px">
            <template v-if="eventInfoData">
                <p><strong>{{ form.event_type }}</strong></p>
                <p class="mt-2">{{ eventInfoData.desc }}</p>
                <el-divider />
                <p class="font-bold">示例 Payload：</p>
                <pre class="payload-pre mt-2"><code>{{ JSON.stringify(eventInfoData.sample, null, 2) }}</code></pre>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Search, Connection } from '@element-plus/icons-vue';
import webhookSimulatorApi from '@/api/webhookSimulator';

// ─── 表单 ───
const form = reactive({
    event_type: '',
    delivery_mode: 'broadcast',
    endpoint_id: null,
    payload_json: '',
    description: '',
});

const payloadMode = ref('sample');
const sending = ref(false);
const result = ref(null);

// ─── 事件类型 ───
const allEventTypes = ref([]);

const groupedEventTypes = computed(() => {
    const groups = {};
    for (const t of allEventTypes.value) {
        const label = t.group ? { license: 'License', subscription: '订阅', customer: '客户', device: '设备', user: '用户', ticket: '工单' }[t.group] || t.group : '其他';
        if (!groups[label]) groups[label] = { label, types: [] };
        groups[label].types.push(t);
    }
    return Object.values(groups);
});

const flatEventTypes = computed(() => allEventTypes.value);

// ─── 示例 Payload ───
const samplePayload = ref('请选择事件类型查看示例 Payload');
const showEventInfoDialog = ref(false);
const eventInfoData = ref(null);

async function handleEventTypeChange() {
    payloadMode.value = 'sample';
    if (!form.event_type) {
        samplePayload.value = '请选择事件类型查看示例 Payload';
        return;
    }
    try {
        const res = await webhookSimulatorApi.eventInfo(form.event_type);
        const data = res.data?.data;
        if (data?.sample) {
            samplePayload.value = JSON.stringify(data.sample, null, 2);
        }
    } catch {
        samplePayload.value = '无法获取示例数据';
    }
}

async function showEventInfo() {
    if (!form.event_type) return;
    try {
        const res = await webhookSimulatorApi.eventInfo(form.event_type);
        eventInfoData.value = res.data?.data;
        showEventInfoDialog.value = true;
    } catch {
        ElMessage.error('获取事件说明失败');
    }
}

// ─── 端点列表 ───
const endpoints = ref([]);

async function fetchEndpoints() {
    try {
        const res = await webhookSimulatorApi.endpoints();
        endpoints.value = res.data?.data || [];
    } catch {
        // silent
    }
}

// ─── 模拟触发 ───
const resultSuccess = computed(() => {
    return result.value?.results?.some(r => r.success);
});

async function handleSimulate() {
    if (!form.event_type) {
        ElMessage.warning('请选择事件类型');
        return;
    }
    if (form.delivery_mode === 'targeted' && !form.endpoint_id) {
        ElMessage.warning('请选择目标端点');
        return;
    }

    sending.value = true;
    try {
        const payload = {
            event_type: form.event_type,
            description: form.description || undefined,
        };

        if (form.delivery_mode === 'targeted') {
            payload.endpoint_id = form.endpoint_id;
        }

        if (payloadMode.value === 'custom' && form.payload_json) {
            try {
                payload.payload = JSON.parse(form.payload_json);
            } catch {
                ElMessage.warning('自定义 Payload 格式无效，请检查 JSON');
                sending.value = false;
                return;
            }
        }

        const res = await webhookSimulatorApi.simulate(payload);
        result.value = res.data?.data;

        if (result.value?.dispatch_count > 0) {
            ElMessage.success(`模拟事件已发送到 ${result.value.dispatch_count} 个端点`);
        } else {
            ElMessage.info('没有匹配的端点');
        }

        await fetchHistory();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || '发送失败');
    } finally {
        sending.value = false;
    }
}

function resetForm() {
    form.event_type = '';
    form.delivery_mode = 'broadcast';
    form.endpoint_id = null;
    form.payload_json = '';
    form.description = '';
    payloadMode.value = 'sample';
    samplePayload.value = '请选择事件类型查看示例 Payload';
    result.value = null;
}

// ─── 历史记录 ───
const history = ref([]);
const historyLoading = ref(false);
const historyPage = ref(1);
const historyPerPage = ref(20);
const historyTotal = ref(0);
const historyFilters = reactive({
    event_type: '',
    status: '',
});

async function fetchHistory() {
    historyLoading.value = true;
    try {
        const params = {
            page: historyPage.value,
            per_page: historyPerPage.value,
        };
        if (historyFilters.event_type) params.event_type = historyFilters.event_type;
        if (historyFilters.status) params.status = historyFilters.status;

        const res = await webhookSimulatorApi.history(params);
        history.value = res.data?.data || [];
        historyTotal.value = res.data?.meta?.total || 0;
    } catch {
        // silent
    } finally {
        historyLoading.value = false;
    }
}

onMounted(async () => {
    try {
        const res = await webhookSimulatorApi.eventTypes();
        allEventTypes.value = res.data?.data || [];
    } catch { /* silent */ }
    await fetchEndpoints();
    await fetchHistory();
});
</script>

<style scoped>
.mt-2 { margin-top: 8px; }
.mt-4 { margin-top: 16px; }
.ml-2 { margin-left: 8px; }
.font-bold { font-weight: 600; }

.card-header {
    display: flex; justify-content: space-between; align-items: center;
}

.payload-section {
    width: 100%;
}

.payload-tabs { margin-bottom: 8px; }

.sample-payload {
    background: var(--el-fill-color-light);
    border: 1px solid var(--el-border-color);
    border-radius: 4px;
    padding: 12px;
    max-height: 300px;
    overflow: auto;
    width: 100%;
}

.sample-payload pre,
.payload-pre {
    margin: 0;
    font-size: 13px;
    line-height: 1.5;
    white-space: pre-wrap;
    word-break: break-all;
}

.endpoint-url {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    word-break: break-all;
}

.result-summary {
    margin-bottom: 12px;
    font-size: 14px;
}

.response-text {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pagination-wrap { display: flex; justify-content: center; margin-top: 16px; }
</style>
