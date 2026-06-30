<template>
    <div class="quota-alert-container">
        <el-page-header :content="'License 用量配额预警'" @back="$router.push('/admin/dashboard')" />

        <el-alert
            title="监控设备数/API调用次数等用量配额，80%/90%/100% 三级阈值预警，支持站内信+邮件+IM多渠道通知和一键扩容。"
            type="info"
            show-icon
            :closable="false"
            class="alert-info"
        />

        <el-card class="filter-card">
            <el-form :inline="true" :model="filters" size="default">
                <el-form-item label="开始日期">
                    <el-date-picker v-model="filters.start_date" type="date" placeholder="开始日期" format="YYYY-MM-DD" value-format="YYYY-MM-DD" />
                </el-form-item>
                <el-form-item label="结束日期">
                    <el-date-picker v-model="filters.end_date" type="date" placeholder="结束日期" format="YYYY-MM-DD" value-format="YYYY-MM-DD" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadData">查询</el-button>
                    <el-button @click="handleCheckAll">批量检查</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 概览卡片 -->
        <el-row :gutter="20" class="stat-cards">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value">{{ dashboard.stats.total }}</div>
                    <div class="stat-label">总配额数</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-success">{{ dashboard.stats.normal }}</div>
                    <div class="stat-label">正常</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-warning">{{ dashboard.stats.warning }}</div>
                    <div class="stat-label">警告 ({{ cfg.thresholds?.warning || 80 }}%)</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-danger">{{ dashboard.stats.critical + dashboard.stats.exceeded }}</div>
                    <div class="stat-label">严重+超限</div>
                </el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab">
            <el-tab-pane label="配额列表" name="list">
                <el-card>
                    <template #header>
                        <el-space>
                            <span>配额预警列表</span>
                            <el-select v-model="levelFilter" placeholder="级别" clearable size="small" style="width:120px" @change="loadList">
                                <el-option label="全部" value="" />
                                <el-option label="正常" value="normal" />
                                <el-option label="警告" value="warning" />
                                <el-option label="严重" value="critical" />
                                <el-option label="超限" value="exceeded" />
                            </el-select>
                            <el-select v-model="typeFilter" placeholder="类型" clearable size="small" style="width:140px" @change="loadList">
                                <el-option label="全部" value="" />
                                <el-option v-for="(name, key) in cfg.types" :key="key" :label="name" :value="key" />
                            </el-select>
                        </el-space>
                    </template>
                    <el-table :data="alerts" stripe v-loading="loading">
                        <el-table-column prop="id" label="ID" width="60" />
                        <el-table-column label="类型" width="120">
                            <template #default="{ row }">{{ cfg.types[row.quota_type] || row.quota_type }}</template>
                        </el-table-column>
                        <el-table-column label="级别" width="80">
                            <template #default="{ row }">
                                <el-tag :type="levelType(row.level)" size="small">{{ levelLabel(row.level) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="用量" min-width="200">
                            <template #default="{ row }">
                                <div class="usage-bar">
                                    <el-progress :percentage="row.usage_percent" :color="progressColor(row.usage_percent)" :stroke-width="16">
                                        <span>{{ row.current_usage }} / {{ row.quota_limit }}</span>
                                    </el-progress>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column label="通知" width="80" align="center">
                            <template #default="{ row }">
                                <el-switch :model-value="row.notifications_enabled" @change="toggleNotifications(row)" />
                            </template>
                        </el-table-column>
                        <el-table-column label="最近检查" width="160">
                            <template #default="{ row }">{{ row.last_checked_at || '-' }}</template>
                        </el-table-column>
                        <el-table-column label="操作" width="200" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" @click="showDetail(row)">详情</el-button>
                                <el-button size="small" @click="openEditLimit(row)">调整上限</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-pagination v-if="pagination.total > 0" v-model:current-page="pagination.page" :page-size="pagination.per_page" :total="pagination.total" layout="prev, pager, next" @current-change="loadList" class="pagination" />
                </el-card>
            </el-tab-pane>

            <el-tab-pane label="预警日志" name="logs">
                <el-card>
                    <template #header>
                        <el-space>
                            <span>预警通知日志</span>
                            <el-select v-model="logLevelFilter" placeholder="级别" clearable size="small" style="width:120px" @change="loadLogs">
                                <el-option label="全部" value="" />
                                <el-option label="警告" value="warning" />
                                <el-option label="严重" value="critical" />
                                <el-option label="超限" value="exceeded" />
                            </el-select>
                        </el-space>
                    </template>
                    <el-table :data="logs" stripe v-loading="logsLoading">
                        <el-table-column prop="created_at" label="时间" width="160" />
                        <el-table-column label="类型" width="120">
                            <template #default="{ row }">{{ cfg.types[row.quota_type] || row.quota_type }}</template>
                        </el-table-column>
                        <el-table-column label="级别" width="80">
                            <template #default="{ row }">
                                <el-tag :type="levelType(row.level)" size="small">{{ levelLabel(row.level) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="用量" width="160">
                            <template #default="{ row }">{{ row.current_usage }} / {{ row.quota_limit }} ({{ row.usage_percent }}%)</template>
                        </el-table-column>
                        <el-table-column label="渠道" width="80">
                            <template #default="{ row }">{{ cfg.channels[row.channel] || row.channel }}</template>
                        </el-table-column>
                        <el-table-column label="状态" width="80">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'sent' ? 'success' : 'danger'" size="small">{{ row.status === 'sent' ? '已发送' : '失败' }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="message" label="消息" min-width="300" show-overflow-tooltip />
                    </el-table>
                    <el-pagination v-if="logTotal > logPerPage" v-model:current-page="logPage" :page-size="logPerPage" :total="logTotal" layout="prev, pager, next" @current-change="loadLogs" class="pagination" />
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <!-- 详情对话框 -->
        <el-dialog v-model="detailVisible" :title="'配额详情'" width="600px">
            <template v-if="detailData">
                <el-descriptions :column="2" border>
                    <el-descriptions-item label="类型">{{ cfg.types[detailData.quota_type] }}</el-descriptions-item>
                    <el-descriptions-item label="级别">
                        <el-tag :type="levelType(detailData.level)" size="small">{{ levelLabel(detailData.level) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="当前用量">{{ detailData.current_usage }}</el-descriptions-item>
                    <el-descriptions-item label="配额上限">{{ detailData.quota_limit }}</el-descriptions-item>
                    <el-descriptions-item label="使用率">{{ detailData.usage_percent }}%</el-descriptions-item>
                    <el-descriptions-item label="通知">
                        <el-switch :model-value="detailData.notifications_enabled" disabled />
                    </el-descriptions-item>
                    <el-descriptions-item label="最近检查">{{ detailData.last_checked_at || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="最近通知">{{ detailData.last_notified_at || '-' }}</el-descriptions-item>
                </el-descriptions>
            </template>
        </el-dialog>

        <!-- 调整上限对话框 -->
        <el-dialog v-model="limitDialogVisible" title="调整配额上限" width="400px">
            <el-form label-position="top">
                <el-form-item label="当前上限">
                    <el-input :model-value="editingAlert?.quota_limit" disabled />
                </el-form-item>
                <el-form-item label="新上限" required>
                    <el-input-number v-model="newLimit" :min="1" style="width:100%" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="limitDialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="saving" @click="saveLimit">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import quotaAlert from '@/api/quotaAlert';

const activeTab = ref('list');
const loading = ref(false);
const logsLoading = ref(false);
const saving = ref(false);
const detailVisible = ref(false);
const limitDialogVisible = ref(false);
const levelFilter = ref('');
const typeFilter = ref('');
const logLevelFilter = ref('');

const filters = reactive({ start_date: '', end_date: '' });
const dashboard = reactive({ stats: { total: 0, normal: 0, warning: 0, critical: 0, exceeded: 0 }, by_type: [], recent_logs: [], thresholds: {}, types: {}, channels: {} });
const cfg = reactive({ types: {}, thresholds: {}, channels: {} });
const alerts = ref([]);
const logs = ref([]);
const detailData = ref(null);
const editingAlert = ref(null);
const newLimit = ref(0);

const pagination = reactive({ total: 0, page: 1, per_page: 20 });
const logTotal = ref(0);
const logPage = ref(1);
const logPerPage = ref(20);

function setDefaultDates() {
    const now = new Date();
    const thirtyDaysAgo = new Date(now); thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
    filters.start_date = thirtyDaysAgo.toISOString().slice(0, 10);
    filters.end_date = now.toISOString().slice(0, 10);
}

function levelType(l) {
    return { normal: 'success', warning: 'warning', critical: 'danger', exceeded: 'danger' }[l] || 'info';
}
function levelLabel(l) {
    return { normal: '正常', warning: '警告', critical: '严重', exceeded: '超限' }[l] || l;
}
function progressColor(p) {
    if (p >= 100) return '#f56c6c';
    if (p >= 90) return '#e6a23c';
    if (p >= 80) return '#f56c6c';
    return '#67c23a';
}

async function loadConfig() {
    try { const res = await quotaAlert.config(); Object.assign(cfg, res.data.data); } catch (e) { console.error(e); }
}

async function loadData() {
    await Promise.all([loadDashboard(), loadList()]);
}

async function loadDashboard() {
    try {
        const res = await quotaAlert.dashboard({ start_date: filters.start_date, end_date: filters.end_date });
        Object.assign(dashboard, res.data.data);
    } catch (e) { console.error(e); }
}

async function loadList(page) {
    loading.value = true;
    try {
        const params = { page: page || pagination.page, per_page: pagination.per_page };
        if (levelFilter.value) params.level = levelFilter.value;
        if (typeFilter.value) params.quota_type = typeFilter.value;
        const res = await quotaAlert.list(params);
        alerts.value = res.data.data.items || [];
        pagination.total = res.data.data.total;
        pagination.page = res.data.data.page;
    } catch (e) { console.error(e); } finally { loading.value = false; }
}

async function loadLogs(page) {
    logsLoading.value = true;
    try {
        const params = { page: page || logPage.value, per_page: logPerPage.value };
        if (logLevelFilter.value) params.level = logLevelFilter.value;
        const res = await quotaAlert.logs(params);
        logs.value = res.data.data.items || [];
        logTotal.value = res.data.data.total;
        logPage.value = res.data.data.page;
    } catch (e) { console.error(e); } finally { logsLoading.value = false; }
}

async function showDetail(row) {
    try { const res = await quotaAlert.detail(row.id); detailData.value = res.data.data; detailVisible.value = true; } catch (e) { console.error(e); }
}

function openEditLimit(row) {
    editingAlert.value = row;
    newLimit.value = row.quota_limit;
    limitDialogVisible.value = true;
}

async function saveLimit() {
    if (!newLimit.value || newLimit.value < 1) { ElMessage.warning('请输入有效上限值'); return; }
    saving.value = true;
    try {
        await quotaAlert.updateLimit(editingAlert.value.id, { quota_limit: newLimit.value });
        ElMessage.success('配额上限已更新');
        limitDialogVisible.value = false;
        loadList();
    } catch (e) { console.error(e); } finally { saving.value = false; }
}

async function toggleNotifications(row) {
    try {
        await quotaAlert.toggleNotifications(row.id);
        ElMessage.success(row.notifications_enabled ? '通知已关闭' : '通知已开启');
        loadList();
    } catch (e) { console.error(e); }
}

async function handleCheckAll() {
    try {
        const res = await quotaAlert.checkAll();
        ElMessage.success(`检查完成，处理 ${res.data.data.checked} 个配额`);
        loadData();
    } catch (e) { console.error(e); }
}

onMounted(() => { setDefaultDates(); loadConfig(); loadData(); });
</script>

<style scoped>
.quota-alert-container { padding: 20px; }
.alert-info { margin: 16px 0; }
.filter-card { margin-bottom: 16px; }
.stat-cards { margin-bottom: 16px; }
.stat-cards .el-card { text-align: center; }
.stat-value { font-size: 28px; font-weight: bold; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.text-success { color: #67c23a; }
.text-warning { color: #e6a23c; }
.text-danger { color: #f56c6c; }
.pagination { margin-top: 16px; text-align: center; }
.usage-bar { padding: 4px 0; }
</style>
