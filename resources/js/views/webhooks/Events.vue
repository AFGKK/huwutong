<template>
    <div class="webhook-events-page">
        <div class="page-header">
            <h2>{{ t('webhook_events_page.title') }}</h2>
            <div class="header-actions">
                <el-button @click="fetchStats">{{ t('webhook_events_page.stats_btn') }}</el-button>
                <el-button type="primary" @click="fetchData" :loading="loading">{{ t('actions.refresh') }}</el-button>
            </div>
        </div>

        <el-row :gutter="16" class="mb-4">
            <el-col :span="6" v-for="s in eventStats" :key="s.label">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-content">
                        <div class="stat-value" :style="{ color: s.color }">{{ s.count }}</div>
                        <div class="stat-label">{{ s.label }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-card class="mb-4">
            <el-form :model="filters" inline @keyup.enter="fetchData">
                <el-form-item :label="t('webhook_events_page.event_type')">
                    <el-input v-model="filters.event_type" :placeholder="t('webhook_events_page.type_ph')" clearable style="width: 160px" />
                </el-form-item>
                <el-form-item :label="t('webhook_events_page.cols.status')">
                    <el-select v-model="filters.status" :placeholder="t('webhook_events_page.all')" clearable style="width: 120px">
                        <el-option :label="t('webhook_events_page.statuses.pending')" value="pending" />
                        <el-option :label="t('webhook_events_page.statuses.retrying')" value="retrying" />
                        <el-option :label="t('webhook_events_page.statuses.delivered')" value="delivered" />
                        <el-option :label="t('webhook_events_page.statuses.failed')" value="failed" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="fetchData">{{ t('actions.search') }}</el-button>
                    <el-button @click="resetFilters">{{ t('actions.reset') }}</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <el-card>
            <el-table :data="events" v-loading="loading" stripe>
                <el-table-column prop="event_type" :label="t('webhook_events_page.event_type')" width="150" />
                <el-table-column :label="t('webhook_events_page.cols.status')" width="100">
                    <template #default="{ row }">
                        <el-tag :type="eventStatusType(row.status)" size="small" effect="dark">
                            {{ eventStatusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="endpoint?.url" :label="t('webhook_events_page.cols.url')" min-width="250" :formatter="(r) => r.endpoint?.url || '-'" />
                <el-table-column prop="attempts" :label="t('webhook_events_page.cols.attempts')" width="90" align="center" />
                <el-table-column prop="last_attempt_at" :label="t('webhook_events_page.cols.last_attempt')" width="170" />
                <el-table-column prop="next_retry_at" :label="t('webhook_events_page.cols.next_retry')" width="170">
                    <template #default="{ row }">
                        <span v-if="row.next_retry_at" class="retry-text">{{ row.next_retry_at }}</span>
                        <span v-else class="no-retry">-</span>
                    </template>
                </el-table-column>
                <el-table-column :label="t('webhook_events_page.cols.actions')" width="130" fixed="right">
                    <template #default="{ row }">
                        <el-button text type="primary" size="small" @click="showEventDetail(row)">{{ t('webhook_events_page.detail') }}</el-button>
                        <el-button text type="primary" size="small" @click="replayEvent(row.id)">{{ t('webhook_events_page.replay') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="pagination-wrap" v-if="meta">
                <el-pagination
                    v-model:current-page="meta.current_page"
                    :page-size="meta.per_page"
                    :total="meta.total"
                    layout="total, prev, pager, next"
                    @current-change="fetchData"
                />
            </div>
        </el-card>

        <el-dialog v-model="showDetail" :title="t('webhook_events_page.detail_title', { id: detailEvent?.id })" width="700px">
            <el-descriptions v-if="detailEvent" :column="2" border>
                <el-descriptions-item :label="t('webhook_events_page.event_id')" :span="2">
                    <code>{{ detailEvent.id }}</code>
                </el-descriptions-item>
                <el-descriptions-item :label="t('webhook_events_page.event_type')">{{ detailEvent.event_type }}</el-descriptions-item>
                <el-descriptions-item :label="t('webhook_events_page.cols.status')">
                    <el-tag :type="eventStatusType(detailEvent.status)" size="small">
                        {{ eventStatusLabel(detailEvent.status) }}
                    </el-tag>
                </el-descriptions-item>
                <el-descriptions-item :label="t('webhook_events_page.cols.attempts')">{{ detailEvent.attempts }}</el-descriptions-item>
                <el-descriptions-item :label="t('webhook_events_page.max_attempts')">{{ detailEvent.max_attempts || '-' }}</el-descriptions-item>
                <el-descriptions-item :label="t('webhook_events_page.created')" :span="2">{{ detailEvent.created_at }}</el-descriptions-item>
                <el-descriptions-item :label="t('webhook_events_page.cols.last_attempt')" :span="2">{{ detailEvent.last_attempt_at || '-' }}</el-descriptions-item>
                <el-descriptions-item :label="t('webhook_events_page.cols.next_retry')" :span="2">{{ detailEvent.next_retry_at || '-' }}</el-descriptions-item>
                <el-descriptions-item :label="t('webhook_events_page.response_status')">
                    <el-tag v-if="detailEvent.response_status" :type="detailEvent.response_status >= 400 ? 'danger' : 'success'" size="small">
                        {{ detailEvent.response_status }}
                    </el-tag>
                    <span v-else>-</span>
                </el-descriptions-item>
                <el-descriptions-item :label="t('webhook_events_page.response_time')">{{ detailEvent.response_time ? `${detailEvent.response_time}ms` : '-' }}</el-descriptions-item>
                <el-descriptions-item :label="t('webhook_events_page.request_body')" :span="2">
                    <pre v-if="detailEvent.request_body" class="payload-box">{{ formatJson(detailEvent.request_body) }}</pre>
                    <span v-else>-</span>
                </el-descriptions-item>
                <el-descriptions-item :label="t('webhook_events_page.response_body')" :span="2">
                    <pre v-if="detailEvent.response_body" class="payload-box">{{ formatJson(detailEvent.response_body) }}</pre>
                    <span v-else>-</span>
                </el-descriptions-item>
            </el-descriptions>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import apiClient from '@/api/client'
import { ElMessage } from 'element-plus'

const { t } = useI18n()

const loading = ref(false)
const events = ref([])
const meta = ref(null)
const showDetail = ref(false)
const detailEvent = ref(null)
const statsLoading = ref(false)

const filters = reactive({
    event_type: '',
    status: '',
})

const eventStats = computed(() => [
    { label: t('webhook_events_page.stats.all'), count: statsCounts.total, color: '#0f172a' },
    { label: t('webhook_events_page.statuses.pending'), count: statsCounts.pending, color: '#909399' },
    { label: t('webhook_events_page.statuses.failed'), count: statsCounts.failed, color: '#f56c6c' },
    { label: t('webhook_events_page.statuses.delivered'), count: statsCounts.delivered, color: '#67c23a' },
])

const statsCounts = reactive({ total: 0, pending: 0, failed: 0, delivered: 0 })

const STATUS_TYPES = {
    pending: 'info',
    retrying: 'warning',
    delivered: 'success',
    failed: 'danger',
}

function eventStatusType(status) { return STATUS_TYPES[status] || 'info' }
function eventStatusLabel(status) {
    const key = { pending: 'pending', retrying: 'retrying', delivered: 'delivered', failed: 'failed' }[status]
    return key ? t(`webhook_events_page.statuses.${key}`) : status
}

async function fetchStats() {
    statsLoading.value = true
    try {
        const { data: res } = await apiClient.get('/webhook-replay/stats')
        const s = res.data || {}
        statsCounts.total = s.total || 0
        statsCounts.pending = s.pending || 0
        statsCounts.failed = s.failed || 0
        statsCounts.delivered = s.delivered || 0
    } catch {
        // ignore
    } finally {
        statsLoading.value = false
    }
}

async function fetchData(page) {
    loading.value = true
    try {
        const params = {
            page: page || meta.value?.current_page || 1,
            per_page: 20,
        }
        if (filters.event_type) params.event_type = filters.event_type
        if (filters.status) params.status = filters.status

        const { data: res } = await apiClient.get('/webhook-replay/events', { params })
        events.value = res.data?.data || []
        meta.value = res.meta
    } catch {
        // ignore
    } finally {
        loading.value = false
    }
}

function resetFilters() {
    filters.event_type = ''
    filters.status = ''
    fetchData(1)
}

async function replayEvent(id) {
    try {
        await apiClient.post(`/webhook-replay/events/${id}/replay`)
        ElMessage.success(t('webhook_events_page.messages.replay_ok'))
        fetchData()
        fetchStats()
    } catch {
        ElMessage.error(t('webhook_events_page.messages.replay_failed'))
    }
}

function showEventDetail(row) {
    detailEvent.value = row
    showDetail.value = true
}

function formatJson(obj) {
    if (!obj) return ''
    try {
        return typeof obj === 'string' ? JSON.stringify(JSON.parse(obj), null, 2) : JSON.stringify(obj, null, 2)
    } catch {
        return String(obj)
    }
}

onMounted(() => {
    fetchStats()
    fetchData(1)
})
</script>

<style scoped>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.page-header h2 { margin: 0; }
.header-actions { display: flex; gap: 8px; }
.mb-4 { margin-bottom: 16px; }

.stat-card { cursor: default; }
.stat-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.stat-value { font-size: 24px; font-weight: 700; }
.stat-label { font-size: 13px; color: #909399; margin-top: 2px; }

.pagination-wrap {
    display: flex;
    justify-content: center;
    margin-top: 20px;
}

.retry-text { color: #e6a23c; }
.no-retry { color: #c0c4cc; }

.payload-box {
    max-height: 200px;
    overflow: auto;
    background: #f5f7fa;
    padding: 8px;
    border-radius: 4px;
    font-size: 11px;
    line-height: 1.5;
    white-space: pre-wrap;
    word-break: break-all;
}
</style>
