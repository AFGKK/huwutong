<template>
    <div class="webhook-events-page">
        <div class="page-header">
            <h2>Webhook 事件</h2>
            <div class="header-actions">
                <el-button @click="fetchStats">统计</el-button>
                <el-button type="primary" @click="fetchData" :loading="loading">刷新</el-button>
            </div>
        </div>

        <!-- 统计卡片 -->
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

        <!-- 筛选 -->
        <el-card class="mb-4">
            <el-form :model="filters" inline @keyup.enter="fetchData">
                <el-form-item label="事件类型">
                    <el-input v-model="filters.event_type" placeholder="筛选类型" clearable style="width: 160px" />
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="filters.status" placeholder="全部" clearable style="width: 120px">
                        <el-option label="待处理" value="pending" />
                        <el-option label="重试中" value="retrying" />
                        <el-option label="已送达" value="delivered" />
                        <el-option label="失败" value="failed" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="fetchData">搜索</el-button>
                    <el-button @click="resetFilters">重置</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <el-card>
            <el-table :data="events" v-loading="loading" stripe>
                <el-table-column prop="event_type" label="事件类型" width="150" />
                <el-table-column label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag :type="eventStatusType(row.status)" size="small" effect="dark">
                            {{ eventStatusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="endpoint?.url" label="目标 URL" min-width="250" :formatter="(r) => r.endpoint?.url || '-'" />
                <el-table-column prop="attempts" label="尝试次数" width="90" align="center" />
                <el-table-column prop="last_attempt_at" label="最后尝试" width="170" />
                <el-table-column prop="next_retry_at" label="下次重试" width="170">
                    <template #default="{ row }">
                        <span v-if="row.next_retry_at" class="retry-text">{{ row.next_retry_at }}</span>
                        <span v-else class="no-retry">-</span>
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="130" fixed="right">
                    <template #default="{ row }">
                        <el-button text type="primary" size="small" @click="showEventDetail(row)">详情</el-button>
                        <el-button text type="primary" size="small" @click="replayEvent(row.id)">重放</el-button>
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

        <!-- 事件详情弹窗 -->
        <el-dialog v-model="showDetail" :title="`事件详情 #${detailEvent?.id}`" width="700px">
            <el-descriptions v-if="detailEvent" :column="2" border>
                <el-descriptions-item label="事件 ID" :span="2">
                    <code>{{ detailEvent.id }}</code>
                </el-descriptions-item>
                <el-descriptions-item label="事件类型">{{ detailEvent.event_type }}</el-descriptions-item>
                <el-descriptions-item label="状态">
                    <el-tag :type="eventStatusType(detailEvent.status)" size="small">
                        {{ eventStatusLabel(detailEvent.status) }}
                    </el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="尝试次数">{{ detailEvent.attempts }}</el-descriptions-item>
                <el-descriptions-item label="最大重试">{{ detailEvent.max_attempts || '-' }}</el-descriptions-item>
                <el-descriptions-item label="创建时间" :span="2">{{ detailEvent.created_at }}</el-descriptions-item>
                <el-descriptions-item label="最后尝试" :span="2">{{ detailEvent.last_attempt_at || '-' }}</el-descriptions-item>
                <el-descriptions-item label="下次重试" :span="2">{{ detailEvent.next_retry_at || '-' }}</el-descriptions-item>
                <el-descriptions-item label="响应状态码">
                    <el-tag v-if="detailEvent.response_status" :type="detailEvent.response_status >= 400 ? 'danger' : 'success'" size="small">
                        {{ detailEvent.response_status }}
                    </el-tag>
                    <span v-else>-</span>
                </el-descriptions-item>
                <el-descriptions-item label="响应耗时">{{ detailEvent.response_time ? `${detailEvent.response_time}ms` : '-' }}</el-descriptions-item>
                <el-descriptions-item label="请求体" :span="2">
                    <pre v-if="detailEvent.request_body" class="payload-box">{{ formatJson(detailEvent.request_body) }}</pre>
                    <span v-else>-</span>
                </el-descriptions-item>
                <el-descriptions-item label="响应体" :span="2">
                    <pre v-if="detailEvent.response_body" class="payload-box">{{ formatJson(detailEvent.response_body) }}</pre>
                    <span v-else>-</span>
                </el-descriptions-item>
            </el-descriptions>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import apiClient from '@/api/client';
import { ElMessage } from 'element-plus';

const loading = ref(false);
const events = ref([]);
const meta = ref(null);
const showDetail = ref(false);
const detailEvent = ref(null);
const statsLoading = ref(false);

const filters = reactive({
    event_type: '',
    status: '',
});

const eventStats = reactive([
    { label: '全部事件', count: 0, color: '#409eff' },
    { label: '待处理', count: 0, color: '#909399' },
    { label: '失败', count: 0, color: '#f56c6c' },
    { label: '已送达', count: 0, color: '#67c23a' },
]);

const STATUS_CONFIG = {
    pending: { type: 'info', label: '待处理' },
    retrying: { type: 'warning', label: '重试中' },
    delivered: { type: 'success', label: '已送达' },
    failed: { type: 'danger', label: '失败' },
};

function eventStatusType(status) { return STATUS_CONFIG[status]?.type || 'info'; }
function eventStatusLabel(status) { return STATUS_CONFIG[status]?.label || status; }

async function fetchStats() {
    statsLoading.value = true;
    try {
        const { data: res } = await apiClient.get('/webhook-replay/stats');
        const s = res.data || {};
        eventStats[0].count = s.total || 0;
        eventStats[1].count = s.pending || 0;
        eventStats[2].count = s.failed || 0;
        eventStats[3].count = s.delivered || 0;
    } catch {
        // ignore
    } finally {
        statsLoading.value = false;
    }
}

async function fetchData(page) {
    loading.value = true;
    try {
        const params = {
            page: page || meta.value?.current_page || 1,
            per_page: 20,
        };
        if (filters.event_type) params.event_type = filters.event_type;
        if (filters.status) params.status = filters.status;

        const { data: res } = await apiClient.get('/webhook-replay/events', { params });
        events.value = res.data?.data || [];
        meta.value = res.meta;
    } catch {
        // ignore
    } finally {
        loading.value = false;
    }
}

function resetFilters() {
    filters.event_type = '';
    filters.status = '';
    fetchData(1);
}

async function replayEvent(id) {
    try {
        await apiClient.post(`/webhook-replay/events/${id}/replay`);
        ElMessage.success('已触发重放');
        fetchData();
        fetchStats();
    } catch {
        ElMessage.error('重放失败');
    }
}

function showEventDetail(row) {
    detailEvent.value = row;
    showDetail.value = true;
}

function formatJson(obj) {
    if (!obj) return '';
    try {
        return typeof obj === 'string' ? JSON.stringify(JSON.parse(obj), null, 2) : JSON.stringify(obj, null, 2);
    } catch {
        return String(obj);
    }
}

onMounted(() => {
    fetchStats();
    fetchData(1);
});
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