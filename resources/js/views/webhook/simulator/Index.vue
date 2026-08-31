<template>
    <div class="webhook-simulator-page">
        <el-card shadow="never">
            <template #header>
                <div class="card-header">
                    <span>{{ t('webhook_simulator_page.title') }}</span>
                </div>
            </template>

            <!-- 模拟表单 -->
            <el-form :model="form" label-width="120px" size="default">
                <el-form-item :label="t('webhook_page.filters.event_type')">
                    <el-select
                        v-model="form.event_type"
                        filterable
                        :placeholder="t('webhook_simulator_page.form.event_type_ph')"
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
                        {{ t('webhook_simulator_page.form.view_info') }}
                    </el-button>
                </el-form-item>

                <el-form-item :label="t('webhook_simulator_page.form.delivery_mode')">
                    <el-radio-group v-model="form.delivery_mode">
                        <el-radio value="broadcast">{{ t('webhook_simulator_page.form.delivery_broadcast') }}</el-radio>
                        <el-radio value="targeted">{{ t('webhook_simulator_page.form.delivery_targeted') }}</el-radio>
                    </el-radio-group>
                </el-form-item>

                <el-form-item :label="t('webhook_simulator_page.form.target_endpoint')" v-if="form.delivery_mode === 'targeted'">
                    <el-select
                        v-model="form.endpoint_id"
                        filterable
                        :placeholder="t('webhook_filter_page.endpoint_ph')"
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

                <el-form-item :label="t('webhook_simulator_page.form.payload')">
                    <div class="payload-section">
                        <div class="payload-tabs">
                            <el-radio-group v-model="payloadMode" size="small">
                                <el-radio-button value="sample">{{ t('webhook_simulator_page.form.sample_data') }}</el-radio-button>
                                <el-radio-button value="custom">{{ t('webhook_simulator_page.form.custom') }}</el-radio-button>
                            </el-radio-group>
                        </div>
                        <el-input
                            v-if="payloadMode === 'custom'"
                            v-model="form.payload_json"
                            type="textarea"
                            :rows="10"
                            :placeholder="t('webhook_simulator_page.form.payload_json_ph')"
                            style="width: 100%; font-family: monospace;"
                        />
                        <div v-else class="sample-payload">
                            <pre><code>{{ samplePayload }}</code></pre>
                        </div>
                    </div>
                </el-form-item>

                <el-form-item :label="t('webhook_simulator_page.form.description')">
                    <el-input
                        v-model="form.description"
                        :placeholder="t('webhook_simulator_page.form.description_ph')"
                        maxlength="500"
                        show-word-limit
                        style="width: 400px"
                    />
                </el-form-item>

                <el-form-item>
                    <el-button type="primary" :loading="sending" @click="handleSimulate" size="large">
                        <el-icon><Connection /></el-icon> {{ t('webhook_simulator_page.form.send') }}
                    </el-button>
                    <el-button @click="resetForm" size="large">{{ t('actions.reset') }}</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 发送结果 -->
        <el-card shadow="never" v-if="result" class="mt-4">
            <template #header>
                <div class="card-header">
                    <span>
                        {{ t('webhook_simulator_page.result.title') }}
                        <el-tag :type="resultSuccess ? 'success' : 'warning'" size="small" class="ml-2">
                            {{ resultSuccess ? t('webhook_simulator_page.result.has_success') : t('webhook_simulator_page.result.none_delivered') }}
                        </el-tag>
                    </span>
                </div>
            </template>
            <div class="result-summary">
                <span>{{ t('webhook_simulator_page.result.dispatch_summary', { n: result.dispatch_count }) }}</span>
            </div>
            <el-table :data="result.results || []" size="small" stripe>
                <el-table-column :label="t('webhook_page.cols.endpoint')" min-width="180">
                    <template #default="{ row }">
                        <div>{{ row.endpoint?.name || '-' }}</div>
                        <div class="endpoint-url">{{ row.endpoint?.url }}</div>
                    </template>
                </el-table-column>
                <el-table-column :label="t('webhook_page.cols.status')" width="120">
                    <template #default="{ row }">
                        <el-tag :type="row.success ? 'success' : 'danger'" size="small">
                            {{ row.success ? t('webhook_simulator_page.status.sent') : (row.status || t('webhook_simulator_page.status.failed')) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('webhook_simulator_page.cols.http_status')" width="100">
                    <template #default="{ row }">{{ row.status_code || '-' }}</template>
                </el-table-column>
                <el-table-column :label="t('webhook_simulator_page.cols.response')" min-width="200">
                    <template #default="{ row }">
                        <div class="response-text">{{ row.response_body || '-' }}</div>
                    </template>
                </el-table-column>
                <el-table-column :label="t('webhook_simulator_page.cols.time')" width="170">
                    <template #default="{ row }">{{ row.created_at || '-' }}</template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- 历史记录 -->
        <el-card shadow="never" class="mt-4">
            <template #header>
                <div class="card-header">
                    <span>{{ t('webhook_simulator_page.history.title') }}</span>
                    <div>
                        <el-form :inline="true" size="small">
                            <el-form-item>
                                <el-select
                                    v-model="historyFilters.event_type"
                                    :placeholder="t('webhook_page.filters.event_type')"
                                    clearable
                                    @change="fetchHistory"
                                    style="width: 160px"
                                >
                                    <el-option v-for="evt in flatEventTypes" :key="evt.value" :label="evt.label" :value="evt.value" />
                                </el-select>
                            </el-form-item>
                            <el-form-item>
                                <el-select v-model="historyFilters.status" :placeholder="t('webhook_page.cols.status')" clearable @change="fetchHistory" style="width: 120px">
                                    <el-option
                                        v-for="opt in historyStatusOptions"
                                        :key="opt.value"
                                        :label="opt.label"
                                        :value="opt.value"
                                    />
                                </el-select>
                            </el-form-item>
                            <el-form-item>
                                <el-button type="primary" @click="fetchHistory"><el-icon><Search /></el-icon> {{ t('actions.search') }}</el-button>
                            </el-form-item>
                        </el-form>
                    </div>
                </div>
            </template>
            <el-table :data="history" v-loading="historyLoading" stripe>
                <el-table-column prop="event_type" :label="t('webhook_page.filters.event_type')" width="180" />
                <el-table-column :label="t('webhook_page.cols.endpoint')" min-width="160">
                    <template #default="{ row }">
                        {{ row.endpoint?.name || '-' }}
                        <div class="endpoint-url">{{ row.endpoint?.url }}</div>
                    </template>
                </el-table-column>
                <el-table-column :label="t('webhook_page.cols.status')" width="90">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'delivered' ? 'success' : row.status === 'retrying' ? 'warning' : 'danger'" size="small">
                            {{ historyStatusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('webhook_simulator_page.cols.http_status')" width="90">
                    <template #default="{ row }">{{ row.status_code || '-' }}</template>
                </el-table-column>
                <el-table-column prop="description" :label="t('webhook_simulator_page.form.description')" min-width="150">
                    <template #default="{ row }">{{ row.description || '-' }}</template>
                </el-table-column>
                <el-table-column prop="created_at" :label="t('webhook_simulator_page.cols.time')" width="170" />
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
        <el-dialog v-model="showEventInfoDialog" :title="t('webhook_simulator_page.event_info_dialog.title')" width="500px">
            <template v-if="eventInfoData">
                <p><strong>{{ form.event_type }}</strong></p>
                <p class="mt-2">{{ eventInfoData.desc }}</p>
                <el-divider />
                <p class="font-bold">{{ t('webhook_simulator_page.event_info_dialog.sample_payload') }}</p>
                <pre class="payload-pre mt-2"><code>{{ JSON.stringify(eventInfoData.sample, null, 2) }}</code></pre>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Search, Connection } from '@element-plus/icons-vue';
import webhookSimulatorApi from '@/api/webhookSimulator';

const { t } = useI18n();

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

const eventGroupLabels = computed(() => ({
    license: t('webhook_simulator_page.event_groups.license'),
    subscription: t('webhook_simulator_page.event_groups.subscription'),
    customer: t('webhook_simulator_page.event_groups.customer'),
    device: t('webhook_simulator_page.event_groups.device'),
    user: t('webhook_simulator_page.event_groups.user'),
    ticket: t('webhook_simulator_page.event_groups.ticket'),
    other: t('webhook_simulator_page.event_groups.other'),
}));

const groupedEventTypes = computed(() => {
    const groups = {};
    const labels = eventGroupLabels.value;
    for (const evt of allEventTypes.value) {
        const label = evt.group ? labels[evt.group] || evt.group : labels.other;
        if (!groups[label]) groups[label] = { label, types: [] };
        groups[label].types.push(evt);
    }
    return Object.values(groups);
});

const flatEventTypes = computed(() => allEventTypes.value);

const historyStatusOptions = computed(() => [
    { label: t('webhook_simulator_page.status.delivered'), value: 'delivered' },
    { label: t('webhook_simulator_page.status.failed'), value: 'failed' },
    { label: t('webhook_page.status.retrying'), value: 'retrying' },
]);

function historyStatusLabel(status) {
    const map = {
        delivered: t('webhook_simulator_page.status.delivered'),
        retrying: t('webhook_page.status.retrying'),
        failed: t('webhook_simulator_page.status.failed'),
    };
    return map[status] || status;
}

// ─── 示例 Payload ───
const samplePayload = ref('');
const showEventInfoDialog = ref(false);
const eventInfoData = ref(null);

function resetSamplePayload() {
    samplePayload.value = t('webhook_simulator_page.messages.sample_placeholder');
}

async function handleEventTypeChange() {
    payloadMode.value = 'sample';
    if (!form.event_type) {
        resetSamplePayload();
        return;
    }
    try {
        const res = await webhookSimulatorApi.eventInfo(form.event_type);
        const data = res.data?.data;
        if (data?.sample) {
            samplePayload.value = JSON.stringify(data.sample, null, 2);
        }
    } catch {
        samplePayload.value = t('webhook_simulator_page.messages.sample_fetch_failed');
    }
}

async function showEventInfo() {
    if (!form.event_type) return;
    try {
        const res = await webhookSimulatorApi.eventInfo(form.event_type);
        eventInfoData.value = res.data?.data;
        showEventInfoDialog.value = true;
    } catch {
        ElMessage.error(t('webhook_simulator_page.messages.event_info_failed'));
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
        ElMessage.warning(t('webhook_simulator_page.messages.select_event_type'));
        return;
    }
    if (form.delivery_mode === 'targeted' && !form.endpoint_id) {
        ElMessage.warning(t('webhook_simulator_page.messages.select_endpoint'));
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
                ElMessage.warning(t('webhook_simulator_page.messages.invalid_payload_json'));
                sending.value = false;
                return;
            }
        }

        const res = await webhookSimulatorApi.simulate(payload);
        result.value = res.data?.data;

        if (result.value?.dispatch_count > 0) {
            ElMessage.success(t('webhook_simulator_page.messages.simulate_sent', { n: result.value.dispatch_count }));
        } else {
            ElMessage.info(t('webhook_simulator_page.messages.no_matching_endpoints'));
        }

        await fetchHistory();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('webhook_simulator_page.messages.send_failed'));
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
    resetSamplePayload();
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
    resetSamplePayload();
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
