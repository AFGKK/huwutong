<template>
    <div class="webhook-replay-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('webhook_page.title') }}</h2>
                <span class="header-subtitle">{{ t('webhook_page.subtitle') }}</span>
            </div>
            <div class="header-right">
                <el-button type="primary" :disabled="!selectedIds.length" @click="handleBatchReplay">
                    <el-icon><Refresh /></el-icon>
                    {{ t('webhook_page.batch_replay', { n: selectedIds.length }) }}
                </el-button>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="stats-row">
            <el-col :span="4" v-for="stat in statsList" :key="stat.label">
                <el-card shadow="never" class="stat-card" :body-style="{ padding: '16px' }">
                    <div class="stat-value" :style="{ color: stat.color }">{{ stat.value }}</div>
                    <div class="stat-label">{{ stat.label }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 筛选 -->
        <el-card shadow="never" class="filter-card">
            <el-form :inline="true" :model="filters" size="small">
                <el-form-item :label="t('webhook_page.filters.status')">
                    <el-select v-model="filters.status" :placeholder="t('webhook_page.filters.status_replayable')" clearable @change="loadEvents" style="width:140px;">
                        <el-option
                            v-for="opt in statusFilterOptions"
                            :key="opt.value"
                            :label="opt.label"
                            :value="opt.value"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('webhook_page.filters.event_type')">
                    <el-input v-model="filters.event_type" :placeholder="t('webhook_page.filters.event_type_ph')" clearable @input="loadEvents" style="width:180px;" />
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 表格 -->
        <el-card shadow="never">
            <el-table
                :data="events"
                v-loading="loading"
                stripe
                @selection-change="onSelectionChange"
                @row-click="openDetail"
                row-key="id"
                style="cursor: pointer;"
            >
                <el-table-column type="selection" width="40" />
                <el-table-column :label="t('webhook_page.cols.event_type')" width="180" prop="event_type">
                    <template #default="{ row }">
                        <el-tag size="small" :type="eventTypeTag(row.event_type)">
                            {{ row.event_type }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('webhook_page.cols.endpoint')" min-width="200" prop="webhook_endpoint">
                    <template #default="{ row }">
                        <div class="endpoint-cell">
                            <div class="endpoint-name">{{ row.webhook_endpoint?.name || '-' }}</div>
                            <div class="endpoint-url" :title="row.webhook_endpoint?.url">
                                {{ row.webhook_endpoint?.url || '-' }}
                            </div>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column :label="t('webhook_page.cols.status')" width="100" prop="status">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)" size="small" effect="dark">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('webhook_page.cols.attempts')" width="80" prop="attempts" align="center">
                    <template #default="{ row }">
                        <span>{{ row.attempts || 0 }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="t('webhook_page.cols.next_retry')" width="170" prop="next_retry_at">
                    <template #default="{ row }">
                        <span v-if="row.next_retry_at">{{ formatDate(row.next_retry_at) }}</span>
                        <span v-else class="text-muted">-</span>
                    </template>
                </el-table-column>
                <el-table-column :label="t('webhook_page.cols.created_at')" width="170" prop="created_at">
                    <template #default="{ row }">
                        {{ formatDate(row.created_at) }}
                    </template>
                </el-table-column>
                <el-table-column :label="t('webhook_page.cols.actions')" width="140" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            text
                            size="small"
                            type="primary"
                            :disabled="!isReplayable(row)"
                            @click.stop="handleReplay(row)"
                        >
                            {{ row.status === 'delivered' ? t('actions.view') : t('webhook_page.replay') }}
                        </el-button>
                        <el-button text size="small" type="primary" @click.stop="openDetail(row)">
                            {{ t('actions.view_details') }}
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>

            <!-- 分页 -->
            <div v-if="total > 0" class="pagination-bar">
                <el-pagination
                    v-model:current-page="currentPage"
                    :page-size="perPage"
                    :total="total"
                    layout="total, prev, pager, next"
                    @current-change="loadEvents"
                />
            </div>
        </el-card>

        <!-- 事件详情 Dialog -->
        <el-dialog v-model="detailVisible" :title="t('webhook_page.detail.title')" width="800px" top="5vh" :close-on-click-modal="false">
            <div v-loading="detailLoading">
                <!-- 基本信息 -->
                <el-descriptions :column="2" border size="small" class="detail-section">
                    <el-descriptions-item :label="t('webhook_page.detail.event_id')">{{ detailData?.event?.id }}</el-descriptions-item>
                    <el-descriptions-item :label="t('webhook_page.detail.event_type')">
                        <el-tag size="small" :type="eventTypeTag(detailData?.event?.event_type)">
                            {{ detailData?.event?.event_type }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('webhook_page.detail.status')">
                        <el-tag :type="statusType(detailData?.event?.status)" size="small" effect="dark">
                            {{ statusLabel(detailData?.event?.status) }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('webhook_page.detail.attempts')">{{ detailData?.event?.attempts || 0 }}</el-descriptions-item>
                    <el-descriptions-item :label="t('webhook_page.detail.endpoint_name')">{{ detailData?.event?.webhook_endpoint?.name || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('webhook_page.detail.endpoint_url')" :span="1">
                        <code class="url-code">{{ detailData?.event?.webhook_endpoint?.url || '-' }}</code>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('webhook_page.detail.payload')" :span="2">
                        <pre class="payload-preview"><code>{{ formatJson(detailData?.event?.payload) }}</code></pre>
                    </el-descriptions-item>
                </el-descriptions>

                <!-- 重放按钮 -->
                <div v-if="isReplayable(detailData?.event)" class="detail-actions">
                    <el-button type="primary" :loading="replaying" @click="handleReplay(detailData.event)">
                        <el-icon><Refresh /></el-icon> {{ t('webhook_page.detail.replay_event') }}
                    </el-button>
                </div>

                <!-- 交付历史 -->
                <h4 class="section-title">{{ t('webhook_page.detail.delivery_history') }}</h4>
                <el-timeline v-if="detailData?.deliveries?.length">
                    <el-timeline-item
                        v-for="delivery in detailData.deliveries"
                        :key="delivery.id"
                        :type="delivery.status === 'delivered' ? 'success' : 'danger'"
                        :timestamp="formatDate(delivery.created_at)"
                    >
                        <div class="delivery-item">
                            <div class="delivery-header">
                                <strong>{{ t('webhook_page.detail.attempt_n', { n: delivery.attempt }) }}</strong>
                                <el-tag
                                    :type="delivery.status === 'delivered' ? 'success' : 'danger'"
                                    size="small"
                                >
                                    {{ delivery.status }}
                                </el-tag>
                                <el-tag v-if="delivery.response_code" size="small" type="info">
                                    HTTP {{ delivery.response_code }}
                                </el-tag>
                            </div>
                            <div v-if="delivery.error_message" class="delivery-error">
                                {{ delivery.error_message }}
                            </div>
                            <div v-if="delivery.response_body" class="delivery-response">
                                <div class="response-toggle" @click="toggleResponse(delivery.id)">
                                    <el-icon><ArrowDown /></el-icon> {{ t('webhook_page.detail.response_body') }}
                                </div>
                                <pre v-if="expandedResponses[delivery.id]" class="response-body"><code>{{ delivery.response_body }}</code></pre>
                            </div>
                        </div>
                    </el-timeline-item>
                </el-timeline>
                <el-empty v-else :description="t('webhook_page.detail.no_deliveries')" :image-size="60" />
            </div>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh, ArrowDown } from '@element-plus/icons-vue';
import webhookApi from '@/api/webhook';

const { t, locale } = useI18n();

const loading = ref(false);
const events = ref([]);
const total = ref(0);
const currentPage = ref(1);
const perPage = ref(20);
const selectedIds = ref([]);
const detailVisible = ref(false);
const detailLoading = ref(false);
const detailData = ref(null);
const replaying = ref(false);
const expandedResponses = reactive({});
const statsData = ref(null);

const filters = reactive({
    status: '',
    event_type: '',
});

const statusFilterOptions = computed(() => [
    { label: t('webhook_page.filters.status_replayable'), value: '' },
    { label: t('webhook_page.filters.status_retrying'), value: 'retrying' },
    { label: t('webhook_page.filters.status_dead_letter'), value: 'dead_letter' },
    { label: t('webhook_page.filters.status_delivered'), value: 'delivered' },
    { label: t('webhook_page.filters.status_paused'), value: 'paused' },
    { label: t('webhook_page.filters.status_pending'), value: 'pending' },
]);

const statsList = computed(() => {
    const s = statsData.value;
    if (!s) return [];
    return [
        { label: t('webhook_page.stats.pending_replay'), value: s.pending_replay || 0, color: '#E6A23C' },
        { label: t('webhook_page.stats.dead_letter'), value: s.dead_letter || 0, color: '#F56C6C' },
        { label: t('webhook_page.stats.delivered_today'), value: s.delivered_today || 0, color: '#67C23A' },
        { label: t('webhook_page.stats.failed_today'), value: s.failed_today || 0, color: '#F56C6C' },
        { label: t('webhook_page.stats.active_endpoints'), value: s.total_endpoints || 0, color: '#0f172a' },
        { label: t('webhook_page.stats.paused_endpoints'), value: s.paused_endpoints || 0, color: '#909399' },
    ];
});

function statusLabel(status) {
    const map = {
        pending: t('webhook_page.status.pending'),
        retrying: t('webhook_page.status.retrying'),
        dead_letter: t('webhook_page.status.dead_letter'),
        delivered: t('webhook_page.status.delivered'),
        paused: t('webhook_page.status.paused'),
    };
    return map[status] || status;
}

function statusType(status) {
    const map = {
        pending: 'warning',
        retrying: 'warning',
        dead_letter: 'danger',
        delivered: 'success',
        paused: 'info',
    };
    return map[status] || 'info';
}

function eventTypeTag(type) {
    if (!type) return 'info';
    if (type.includes('activated') || type.includes('created')) return 'success';
    if (type.includes('expiring') || type.includes('suspended')) return 'warning';
    if (type.includes('revoked') || type.includes('deleted') || type.includes('failed')) return 'danger';
    return 'primary';
}

function isReplayable(event) {
    if (!event) return false;
    return ['retrying', 'dead_letter', 'pending'].includes(event.status);
}

function formatDate(dateStr) {
    if (!dateStr) return null;
    const loc = locale.value === 'zh_CN' ? 'zh-CN' : 'en-US';
    return new Date(dateStr).toLocaleString(loc, {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

function formatJson(data) {
    if (!data) return '{}';
    try {
        return JSON.stringify(data, null, 2);
    } catch {
        return String(data);
    }
}

function toggleResponse(id) {
    expandedResponses[id] = !expandedResponses[id];
}

function onSelectionChange(selection) {
    selectedIds.value = selection.filter(s => isReplayable(s)).map(s => s.id);
}

async function loadStats() {
    try {
        const { data: res } = await webhookApi.stats();
        if (res.success) {
            statsData.value = res.data;
        }
    } catch {
        statsData.value = null;
    }
}

async function loadEvents() {
    loading.value = true;
    try {
        const params = { per_page: perPage.value, page: currentPage.value };
        if (filters.status) params.status = filters.status;
        if (filters.event_type) params.event_type = filters.event_type;

        const { data: res } = await webhookApi.list(params);
        if (res.success) {
            events.value = res.data?.data || [];
            total.value = res.meta?.total || 0;
        } else {
            events.value = [];
            total.value = 0;
        }
    } catch {
        events.value = [];
        total.value = 0;
    } finally {
        loading.value = false;
    }
}

async function openDetail(row) {
    detailVisible.value = true;
    detailLoading.value = true;
    detailData.value = null;
    try {
        const { data: res } = await webhookApi.show(row.id);
        if (res.success) {
            detailData.value = res.data;
        }
    } catch {
        ElMessage.error(t('webhook_page.messages.load_detail_fail'));
    } finally {
        detailLoading.value = false;
    }
}

async function handleReplay(event) {
    replaying.value = true;
    try {
        const { data: res } = await webhookApi.replay(event.id);
        if (res.success) {
            if (res.data?.delivered) {
                ElMessage.success(t('webhook_page.messages.replay_ok'));
            } else {
                ElMessage.warning(t('webhook_page.messages.replay_fail'));
            }
            loadEvents();
            loadStats();
            if (detailVisible.value && detailData.value) {
                openDetail(event);
            }
        }
    } catch {
        ElMessage.error(t('webhook_page.messages.replay_request_fail'));
    } finally {
        replaying.value = false;
    }
}

async function handleBatchReplay() {
    if (!selectedIds.value.length) {
        ElMessage.warning(t('webhook_page.messages.select_events'));
        return;
    }

    try {
        await ElMessageBox.confirm(
            t('webhook_page.messages.batch_confirm', { n: selectedIds.value.length }),
            t('webhook_page.messages.batch_title'),
            {
                confirmButtonText: t('webhook_page.messages.batch_confirm_btn'),
                cancelButtonText: t('actions.cancel'),
                type: 'warning',
            }
        );

        const { data: res } = await webhookApi.batchReplay(selectedIds.value);
        if (res.success) {
            ElMessage.success(res.message || t('webhook_page.messages.batch_ok'));
            selectedIds.value = [];
            loadEvents();
            loadStats();
        }
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error(t('webhook_page.messages.batch_fail'));
        }
    }
}

onMounted(() => {
    loadStats();
    loadEvents();
});
</script>

<style scoped>
.webhook-replay-page { padding: 20px; }

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

/* 统计 */
.stats-row { margin-bottom: 16px; }
.stat-card { text-align: center; }
.stat-value { font-size: 28px; font-weight: 700; line-height: 1.2; }
.stat-label { font-size: 13px; color: var(--el-text-color-secondary); margin-top: 4px; }

.filter-card { margin-bottom: 16px; }
.filter-card :deep(.el-card__body) { padding: 12px 16px; }

.endpoint-cell { }
.endpoint-name { font-weight: 500; font-size: 13px; }
.endpoint-url {
    font-size: 11px;
    color: var(--el-text-color-secondary);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 200px;
}

.pagination-bar {
    display: flex;
    justify-content: flex-end;
    padding: 16px 0 0;
}

.text-muted { color: var(--el-text-color-secondary); }

/* 详情 */
.detail-section { margin-bottom: 20px; }
.detail-section :deep(.el-descriptions__cell) { padding: 8px 12px; }

.url-code {
    font-size: 12px;
    word-break: break-all;
}

.payload-preview {
    margin: 0;
    background: var(--el-fill-color-light);
    padding: 12px;
    border-radius: 4px;
    font-size: 12px;
    max-height: 200px;
    overflow: auto;
}
.payload-preview code { font-family: 'SF Mono', 'Fira Code', monospace; }

.detail-actions { margin-bottom: 20px; }

.section-title {
    font-size: 15px;
    font-weight: 600;
    margin: 0 0 16px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--el-border-color-light);
}

.delivery-item { }
.delivery-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 4px;
}
.delivery-error {
    color: #F56C6C;
    font-size: 13px;
    margin-top: 4px;
}
.delivery-response { margin-top: 4px; }
.response-toggle {
    font-size: 12px;
    color: var(--el-color-primary);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.response-body {
    margin: 8px 0 0;
    background: #1e1e1e;
    color: #d4d4d4;
    padding: 12px;
    border-radius: 4px;
    font-size: 12px;
    max-height: 300px;
    overflow: auto;
}
.response-body code { font-family: 'SF Mono', 'Fira Code', monospace; }

:deep(.el-card__body) { padding: 16px; }
</style>
