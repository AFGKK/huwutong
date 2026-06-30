<template>
    <div class="sessions-admin-page">
        <div class="page-header">
            <h2>Session 管理 <small class="text-muted">M1.4-30</small></h2>
            <div class="header-actions">
                <el-button @click="loadDashboard(); loadList()">
                    <el-icon><Refresh /></el-icon> 刷新
                </el-button>
            </div>
        </div>

        <el-tabs v-model="activeTab">
            <!-- ═══════════════ 概览 ═══════════════ -->
            <el-tab-pane label="概览" name="dashboard">
                <div v-loading="dashboardLoading">
                    <el-row :gutter="16" class="mb-4">
                        <el-col :span="6">
                            <el-card shadow="never">
                                <div class="stat-item">
                                    <div class="stat-value text-primary">{{ dashboard.total_sessions || 0 }}</div>
                                    <div class="stat-label">总会话</div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="6">
                            <el-card shadow="never">
                                <div class="stat-item">
                                    <div class="stat-value text-success">{{ dashboard.active_users || 0 }}</div>
                                    <div class="stat-label">活跃用户</div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="6">
                            <el-card shadow="never">
                                <div class="stat-item">
                                    <div class="stat-value text-warning">{{ dashboard.mfa_sessions || 0 }}</div>
                                    <div class="stat-label">MFA 已验证</div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="6">
                            <el-card shadow="never">
                                <div class="stat-item">
                                    <div class="stat-value text-danger">{{ dashboard.expiring_soon || 0 }}</div>
                                    <div class="stat-label">即将过期</div>
                                </div>
                            </el-card>
                        </el-col>
                    </el-row>

                    <el-row :gutter="16">
                        <el-col :span="12">
                            <el-card shadow="never" class="mb-4">
                                <template #header><span>设备类型分布</span></template>
                                <div v-if="dashboard.device_type_distribution" class="distribution-list">
                                    <div v-for="(count, type) in dashboard.device_type_distribution" :key="type" class="dist-item">
                                        <span class="dist-label">{{ type || '未知' }}</span>
                                        <el-progress :percentage="calcPct(count, dashboard.total_sessions)" :stroke-width="16" />
                                        <span class="dist-count">{{ count }}</span>
                                    </div>
                                    <div v-if="!Object.keys(dashboard.device_type_distribution || {}).length" class="text-center text-muted">暂无数据</div>
                                </div>
                                <div v-else class="text-center text-muted py-4">暂无数据</div>
                            </el-card>
                        </el-col>
                        <el-col :span="12">
                            <el-card shadow="never" class="mb-4">
                                <template #header><span>今日会话趋势（按小时）</span></template>
                                <div v-if="dashboard.hourly_trend" class="trend-chart">
                                    <div v-for="(count, hour) in dashboard.hourly_trend" :key="hour" class="trend-bar-wrapper">
                                        <div class="trend-bar" :style="{ height: calcBar(count, dashboard.hourly_trend) + '%' }" :title="`${hour}:00 - ${count} 会话`"></div>
                                        <span class="trend-label">{{ hour }}:00</span>
                                    </div>
                                </div>
                                <div v-else class="text-center text-muted py-4">暂无数据</div>
                            </el-card>
                        </el-col>
                    </el-row>
                </div>
            </el-tab-pane>

            <!-- ═══════════════ 会话列表 ═══════════════ -->
            <el-tab-pane label="会话列表" name="list">
                <el-card shadow="never">
                    <div class="toolbar">
                        <el-input v-model="filters.search" placeholder="搜索用户/设备/IP" clearable style="width:240px" @clear="loadList" @keyup.enter="loadList" />
                        <el-select v-model="filters.device_type" placeholder="设备类型" clearable style="width:140px" @change="loadList">
                            <el-option label="桌面" value="desktop" />
                            <el-option label="移动端" value="mobile" />
                            <el-option label="平板" value="tablet" />
                            <el-option label="API" value="api" />
                            <el-option label="未知" value="unknown" />
                        </el-select>
                        <el-select v-model="filters.is_current" placeholder="当前设备" clearable style="width:120px" @change="loadList">
                            <el-option label="是" :value="1" />
                            <el-option label="否" :value="0" />
                        </el-select>
                        <el-date-picker v-model="dateRange" type="daterange" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期" value-format="YYYY-MM-DD" @change="onDateChange" />
                        <el-button type="danger" plain :disabled="!selectedIds.length" @click="handleBatchTerminate">
                            <el-icon><Remove /></el-icon> 批量踢出 ({{ selectedIds.length }})
                        </el-button>
                    </div>

                    <el-table v-loading="loading" :data="sessions" @selection-change="onSelectionChange" stripe border style="width:100%">
                        <el-table-column type="selection" width="40" />
                        <el-table-column label="用户" width="140" show-overflow-tooltip>
                            <template #default="{ row }">
                                <span>{{ row.user?.name || row.user_id }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="session_id" label="Session ID" width="200" show-overflow-tooltip />
                        <el-table-column label="IP / 位置" width="150" show-overflow-tooltip>
                            <template #default="{ row }">
                                <div>{{ row.ip_address || '-' }}</div>
                                <small class="text-muted">{{ row.location || '' }}</small>
                            </template>
                        </el-table-column>
                        <el-table-column label="设备" width="120">
                            <template #default="{ row }">
                                <el-tag :type="deviceTypeTag(row.device_type)" size="small">{{ row.device_type || '未知' }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="浏览器 / 系统" width="160" show-overflow-tooltip>
                            <template #default="{ row }">
                                <div>{{ row.browser || '-' }}</div>
                                <small class="text-muted">{{ row.os || '' }}</small>
                            </template>
                        </el-table-column>
                        <el-table-column label="MFA" width="70" align="center">
                            <template #default="{ row }">
                                <el-tag :type="row.is_mfa_verified ? 'success' : 'danger'" size="small">{{ row.is_mfa_verified ? '是' : '否' }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="当前" width="60" align="center">
                            <template #default="{ row }">
                                <el-tag v-if="row.is_current" type="primary" size="small">是</el-tag>
                                <span v-else class="text-muted">否</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="最后活跃" width="170">
                            <template #default="{ row }">{{ formatDate(row.last_activity_at) }}</template>
                        </el-table-column>
                        <el-table-column label="过期时间" width="170">
                            <template #default="{ row }">{{ formatDate(row.expires_at) }}</template>
                        </el-table-column>
                        <el-table-column label="操作" width="120" fixed="right">
                            <template #default="{ row }">
                                <el-button type="danger" size="small" :disabled="row.is_current" @click="handleTerminate(row)">
                                    踢出
                                </el-button>
                            </template>
                        </el-table-column>
                    </el-table>

                    <div class="pagination-wrap">
                        <el-pagination
                            v-model:current-page="pagination.page"
                            v-model:page-size="pagination.per_page"
                            :total="pagination.total"
                            :page-sizes="[15, 30, 50, 100]"
                            layout="total, sizes, prev, pager, next"
                            @change="loadList"
                        />
                    </div>
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <!-- 踢出用户所有会话对话框 -->
        <el-dialog v-model="terminateUserDialog" title="踢出用户所有会话" width="420px">
            <el-form label-position="top">
                <el-form-item label="用户 ID">
                    <el-input-number v-model="terminateUserId" :min="1" style="width:100%" placeholder="请输入用户 ID" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="terminateUserDialog = false">取消</el-button>
                <el-button type="danger" @click="handleTerminateUser">确认踢出</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh, Remove } from '@element-plus/icons-vue';
import { getSessionDashboard, getSessions, terminateSession, batchTerminateSessions, terminateUserSessions } from '@/api/session';

const activeTab = ref('dashboard');
const dashboardLoading = ref(false);
const loading = ref(false);
const dashboard = ref({});
const sessions = ref([]);
const selectedIds = ref([]);
const dateRange = ref(null);

const filters = reactive({
    search: '',
    device_type: '',
    is_current: '',
    date_from: '',
    date_to: '',
});

const pagination = reactive({
    page: 1,
    per_page: 15,
    total: 0,
});

const terminateUserDialog = ref(false);
const terminateUserId = ref(null);

// ── 仪表盘 ──
async function loadDashboard() {
    dashboardLoading.value = true;
    try {
        const { data: res } = await getSessionDashboard();
        dashboard.value = res.data || {};
    } catch {
        dashboard.value = {};
    } finally {
        dashboardLoading.value = false;
    }
}

// ── 会话列表 ──
async function loadList() {
    loading.value = true;
    try {
        const params = {
            ...filters,
            page: pagination.page,
            per_page: pagination.per_page,
        };
        Object.keys(params).forEach(k => { if (!params[k] && params[k] !== 0) delete params[k]; });
        const { data: res } = await getSessions(params);
        sessions.value = res.data?.data || [];
        pagination.total = res.data?.total || 0;
    } catch {
        sessions.value = [];
        pagination.total = 0;
    } finally {
        loading.value = false;
    }
}

function onDateChange(range) {
    if (range) {
        filters.date_from = range[0];
        filters.date_to = range[1];
    } else {
        filters.date_from = '';
        filters.date_to = '';
    }
    loadList();
}

function onSelectionChange(selection) {
    selectedIds.value = selection.map(s => s.id);
}

// ── 踢出单个会话 ──
async function handleTerminate(row) {
    try {
        await ElMessageBox.confirm(
            `确定踢出用户「${row.user?.name || row.user_id}」的会话？`,
            '踢出会话',
            { confirmButtonText: '确定踢出', cancelButtonText: '取消', type: 'warning' }
        );
        const { data: res } = await terminateSession(row.id);
        ElMessage.success(res.message || '已踢出');
        loadList();
    } catch { /* cancelled */ }
}

// ── 批量踢出 ──
async function handleBatchTerminate() {
    if (!selectedIds.value.length) return;
    try {
        await ElMessageBox.confirm(
            `确定批量踢出 ${selectedIds.value.length} 个会话？`,
            '批量踢出',
            { confirmButtonText: '确定踢出', cancelButtonText: '取消', type: 'warning' }
        );
        const { data: res } = await batchTerminateSessions(selectedIds.value);
        ElMessage.success(res.message || `成功踢出 ${res.data?.success || 0} 个`);
        selectedIds.value = [];
        loadList();
    } catch { /* cancelled */ }
}

// ── 踢出用户所有会话 ──
async function handleTerminateUser() {
    if (!terminateUserId.value) {
        ElMessage.warning('请输入用户 ID');
        return;
    }
    try {
        await ElMessageBox.confirm(
            `确定踢出用户 ID ${terminateUserId.value} 的所有会话？`,
            '踢出用户所有会话',
            { confirmButtonText: '确定踢出', cancelButtonText: '取消', type: 'warning' }
        );
        const { data: res } = await terminateUserSessions(terminateUserId.value);
        ElMessage.success(res.message || '已踢出');
        terminateUserDialog.value = false;
        loadList();
    } catch { /* cancelled */ }
}

// ── 工具函数 ──
function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

function deviceTypeTag(type) {
    const map = { desktop: '', mobile: 'warning', tablet: 'info', api: 'success' };
    return map[type] || 'info';
}

function calcPct(count, total) {
    if (!total) return 0;
    return Math.round((count / total) * 100);
}

function calcBar(count, trend) {
    if (!trend || !count) return 0;
    const vals = Object.values(trend);
    const max = Math.max(...vals);
    return max ? (count / max) * 100 : 0;
}

onMounted(() => {
    loadDashboard();
    loadList();
});
</script>

<style scoped>
.sessions-admin-page { padding: 20px; }

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}
.page-header h2 { margin: 0; font-size: 20px; }

.text-muted { color: var(--el-text-color-secondary); }
.mb-4 { margin-bottom: 16px; }
.py-4 { padding: 24px 0; }
.text-center { text-align: center; }

.stat-item { text-align: center; padding: 12px 0; }
.stat-value { font-size: 28px; font-weight: 700; }
.stat-label { font-size: 13px; color: var(--el-text-color-secondary); margin-top: 4px; }
.text-primary { color: var(--el-color-primary); }
.text-success { color: var(--el-color-success); }
.text-warning { color: var(--el-color-warning); }
.text-danger { color: var(--el-color-danger); }

.toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 16px;
    align-items: center;
}

.pagination-wrap {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
}

/* 设备分布 */
.distribution-list { display: flex; flex-direction: column; gap: 12px; }
.dist-item {
    display: flex;
    align-items: center;
    gap: 12px;
}
.dist-label { width: 60px; font-size: 13px; flex-shrink: 0; }
.dist-item .el-progress { flex: 1; }
.dist-count { width: 40px; text-align: right; font-weight: 600; }

/* 趋势图 */
.trend-chart {
    display: flex;
    align-items: flex-end;
    gap: 4px;
    height: 160px;
    padding: 8px 0;
}
.trend-bar-wrapper {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    height: 100%;
    justify-content: flex-end;
}
.trend-bar {
    width: 100%;
    max-width: 28px;
    background: var(--el-color-primary);
    border-radius: 3px 3px 0 0;
    min-height: 2px;
    transition: height 0.3s;
}
.trend-label {
    font-size: 10px;
    color: var(--el-text-color-secondary);
    margin-top: 4px;
    white-space: nowrap;
}
</style>
