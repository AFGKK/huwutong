<template>
    <div class="collaboration-page">
        <div class="page-header">
            <div class="header-left">
                <h2>团队协作中心</h2>
                <span class="header-subtitle">活动流、内部笔记、快捷回复、关注管理</span>
            </div>
            <div class="header-right">
                <el-button @click="refreshAll">
                    <el-icon><Refresh /></el-icon> 刷新
                </el-button>
            </div>
        </div>

        <el-tabs v-model="activeTab" type="border-card">
            <!-- 活动流 -->
            <el-tab-pane label="活动流" name="activity">
                <div class="tab-toolbar">
                    <div class="toolbar-left">
                        <el-select v-model="activityFilter.types" placeholder="活动类型" clearable multiple style="width: 240px" @change="fetchActivityFeed">
                            <el-option v-for="t in activityTypes" :key="t.value" :label="t.label" :value="t.value" />
                        </el-select>
                        <el-date-picker
                            v-model="activityDateRange"
                            type="daterange"
                            range-separator="至"
                            start-placeholder="开始日期"
                            end-placeholder="结束日期"
                            value-format="YYYY-MM-DD"
                            style="width: 260px"
                            @change="fetchActivityFeed"
                        />
                    </div>
                    <div class="toolbar-right">
                        <el-radio-group v-model="activityScope" size="small" @change="fetchActivityFeed">
                            <el-radio-button value="all">全部</el-radio-button>
                            <el-radio-button value="mine">我的</el-radio-button>
                        </el-radio-group>
                    </div>
                </div>

                <div v-loading="loadingActivity" class="activity-list">
                    <div v-if="!activities.length" class="empty-state">暂无活动记录</div>
                    <div v-for="item in activities" :key="item.id" class="activity-item">
                        <div class="activity-avatar">
                            <el-avatar :size="36">{{ item.user?.name?.charAt(0) || '?' }}</el-avatar>
                        </div>
                        <div class="activity-body">
                            <div class="activity-header">
                                <span class="activity-user">{{ item.user?.name || '系统' }}</span>
                                <el-tag size="small" effect="plain" :type="activityTypeTag(item.type)">
                                    {{ activityTypeLabel(item.type) }}
                                </el-tag>
                            </div>
                            <div class="activity-desc">{{ item.description }}</div>
                            <div class="activity-meta">
                                <span class="activity-time">{{ formatTime(item.created_at) }}</span>
                                <span v-if="item.ip_address" class="activity-ip">IP: {{ item.ip_address }}</span>
                            </div>
                            <div v-if="item.metadata" class="activity-metadata">
                                <pre>{{ JSON.stringify(item.metadata, null, 2) }}</pre>
                            </div>
                        </div>
                    </div>

                    <div class="pagination-wrap" v-if="activityMeta.total > activityMeta.per_page">
                        <el-pagination
                            v-model:current-page="activityPage"
                            :page-size="activityMeta.per_page"
                            :total="activityMeta.total"
                            layout="total, prev, pager, next"
                            @current-change="fetchActivityFeed"
                            small
                        />
                    </div>
                </div>
            </el-tab-pane>

            <!-- 快捷回复 -->
            <el-tab-pane label="快捷回复" name="canned">
                <div class="tab-toolbar">
                    <div class="toolbar-left">
                        <el-select v-model="cannedCategory" placeholder="分类" clearable style="width: 140px" @change="fetchCannedReplies">
                            <el-option label="通用" value="general" />
                            <el-option label="License" value="license" />
                            <el-option label="工单" value="ticket" />
                            <el-option label="客户" value="customer" />
                        </el-select>
                    </div>
                    <div class="toolbar-right">
                        <el-button type="primary" @click="openCannedDialog()">
                            <el-icon><Plus /></el-icon> 新建回复
                        </el-button>
                    </div>
                </div>

                <el-table :data="cannedReplies" v-loading="loadingCanned" stripe size="small">
                    <el-table-column label="标题" min-width="180" prop="title" />
                    <el-table-column label="内容" min-width="300">
                        <template #default="{ row }">
                            <div class="canned-content">{{ row.content }}</div>
                        </template>
                    </el-table-column>
                    <el-table-column label="分类" width="100" align="center">
                        <template #default="{ row }">
                            <el-tag size="small" effect="plain">{{ row.category || 'general' }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="共享" width="80" align="center">
                        <template #default="{ row }">
                            <el-tag :type="row.is_shared ? 'success' : 'info'" size="small">
                                {{ row.is_shared ? '是' : '否' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="创建人" width="120" prop="user?.name" />
                    <el-table-column label="操作" width="150" fixed="right">
                        <template #default="{ row }">
                            <el-button text size="small" type="primary" @click="openCannedDialog(row)">编辑</el-button>
                            <el-popconfirm title="确定删除?" @confirm="handleDeleteCanned(row)">
                                <template #reference>
                                    <el-button text size="small" type="danger">删除</el-button>
                                </template>
                            </el-popconfirm>
                        </template>
                    </el-table-column>
                </el-table>
            </el-tab-pane>

            <!-- 关注列表 -->
            <el-tab-pane label="我的关注" name="watchlist">
                <div class="tab-toolbar">
                    <div class="toolbar-left">
                        <el-select v-model="watchType" placeholder="实体类型" clearable style="width: 160px" @change="fetchWatchlist">
                            <el-option v-for="t in watchableTypes" :key="t.value" :label="t.label" :value="t.value" />
                        </el-select>
                    </div>
                </div>

                <el-table :data="watchlist" v-loading="loadingWatchlist" stripe size="small">
                    <el-table-column label="实体类型" width="140">
                        <template #default="{ row }">
                            <el-tag size="small" effect="plain">{{ watchableLabel(row.watchable_type) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="实体" min-width="250">
                        <template #default="{ row }">
                            <span>{{ watchableSummary(row.watchable) }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column label="关注原因" width="120">
                        <template #default="{ row }">
                            <el-tag size="small" :type="row.reason === 'manual' ? 'primary' : 'warning'" effect="plain">
                                {{ row.reason === 'manual' ? '手动' : row.reason === 'mentioned' ? '@提及' : row.reason }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="关注时间" width="170">
                        <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
                    </el-table-column>
                    <el-table-column label="操作" width="120" fixed="right">
                        <template #default="{ row }">
                            <el-popconfirm title="取消关注?" @confirm="handleUnwatch(row)">
                                <template #reference>
                                    <el-button text size="small" type="danger">取消关注</el-button>
                                </template>
                            </el-popconfirm>
                        </template>
                    </el-table-column>
                </el-table>
            </el-tab-pane>

            <!-- 协作偏好设置 -->
            <el-tab-pane label="协作偏好" name="preferences">
                <el-card shadow="never" style="max-width: 600px">
                    <template #header>
                        <span>通知偏好设置</span>
                    </template>
                    <el-form :model="prefsForm" label-width="160px" size="default">
                        <el-form-item label="被@提及时通知">
                            <el-switch v-model="prefsForm.notify_on_mention" />
                        </el-form-item>
                        <el-form-item label="笔记回复时通知">
                            <el-switch v-model="prefsForm.notify_on_note_reply" />
                        </el-form-item>
                        <el-form-item label="状态变更时通知">
                            <el-switch v-model="prefsForm.notify_on_status_change" />
                        </el-form-item>
                        <el-divider />
                        <el-form-item label="每日摘要">
                            <el-switch v-model="prefsForm.daily_digest" />
                        </el-form-item>
                        <el-form-item v-if="prefsForm.daily_digest" label="摘要发送时间">
                            <el-time-picker
                                v-model="prefsDigestTime"
                                format="HH:mm"
                                value-format="HH:mm"
                                style="width: 120px"
                            />
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" :loading="savingPrefs" @click="handleSavePreferences">
                                保存偏好
                            </el-button>
                        </el-form-item>
                    </el-form>
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <!-- 快捷回复编辑对话框 -->
        <el-dialog v-model="showCannedDialog" :title="editingCannedId ? '编辑快捷回复' : '新建快捷回复'" width="600px">
            <el-form ref="cannedFormRef" :model="cannedForm" :rules="cannedRules" label-width="80px">
                <el-form-item label="标题" prop="title">
                    <el-input v-model="cannedForm.title" maxlength="200" />
                </el-form-item>
                <el-form-item label="内容" prop="content">
                    <el-input v-model="cannedForm.content" type="textarea" :rows="6" maxlength="5000" show-word-limit />
                </el-form-item>
                <el-form-item label="分类">
                    <el-select v-model="cannedForm.category" style="width: 100%">
                        <el-option label="通用" value="general" />
                        <el-option label="License" value="license" />
                        <el-option label="工单" value="ticket" />
                        <el-option label="客户" value="customer" />
                    </el-select>
                </el-form-item>
                <el-form-item label="团队共享">
                    <el-switch v-model="cannedForm.is_shared" active-text="是" inactive-text="否" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showCannedDialog = false">取消</el-button>
                <el-button type="primary" :loading="savingCanned" @click="handleSaveCanned">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Refresh, Plus } from '@element-plus/icons-vue';
import collabApi from '@/api/collaboration';

// ─── 标签 ───
const activeTab = ref('activity');

// ─── 活动流 ───
const activities = ref([]);
const activityMeta = reactive({ total: 0, per_page: 20, current_page: 1 });
const activityPage = ref(1);
const loadingActivity = ref(false);
const activityScope = ref('all');
const activityDateRange = ref(null);
const activityFilter = reactive({ types: [] });

const activityTypes = [
    { value: 'note_created', label: '创建笔记' },
    { value: 'note_updated', label: '更新笔记' },
    { value: 'note_deleted', label: '删除笔记' },
    { value: 'status_changed', label: '状态变更' },
];

function activityTypeTag(type) {
    const map = { note_created: 'success', note_updated: 'warning', note_deleted: 'danger', status_changed: 'primary' };
    return map[type] || 'info';
}

function activityTypeLabel(type) {
    const map = { note_created: '创建笔记', note_updated: '更新笔记', note_deleted: '删除笔记', status_changed: '状态变更' };
    return map[type] || type;
}

async function fetchActivityFeed() {
    loadingActivity.value = true;
    try {
        const params = { page: activityPage.value, per_page: 20 };
        if (activityScope.value === 'mine') {
            const res = await collabApi.getMyActivityFeed();
            if (res.success) activities.value = res.data || [];
            return;
        }
        if (activityFilter.types.length) params.types = activityFilter.types.join(',');
        if (activityDateRange.value) {
            params.date_from = activityDateRange.value[0];
            params.date_to = activityDateRange.value[1];
        }
        const res = await collabApi.getActivityFeed(params);
        if (res.success) {
            activities.value = res.data || [];
            Object.assign(activityMeta, res.meta || {});
        }
    } catch {
        ElMessage.error('加载活动流失败');
    } finally {
        loadingActivity.value = false;
    }
}

// ─── 快捷回复 ───
const cannedReplies = ref([]);
const loadingCanned = ref(false);
const showCannedDialog = ref(false);
const editingCannedId = ref(null);
const savingCanned = ref(false);
const cannedFormRef = ref(null);
const cannedCategory = ref('');
const cannedForm = reactive({
    title: '',
    content: '',
    category: 'general',
    is_shared: false,
});
const cannedRules = {
    title: [{ required: true, message: '请输入标题', trigger: 'blur' }],
    content: [{ required: true, message: '请输入内容', trigger: 'blur' }],
};

async function fetchCannedReplies() {
    loadingCanned.value = true;
    try {
        const params = {};
        if (cannedCategory.value) params.category = cannedCategory.value;
        const res = await collabApi.getCannedReplies(params);
        if (res.success) cannedReplies.value = res.data || [];
    } catch {
        ElMessage.error('加载快捷回复失败');
    } finally {
        loadingCanned.value = false;
    }
}

function openCannedDialog(row) {
    if (row) {
        editingCannedId.value = row.id;
        cannedForm.title = row.title;
        cannedForm.content = row.content;
        cannedForm.category = row.category || 'general';
        cannedForm.is_shared = row.is_shared;
    } else {
        editingCannedId.value = null;
        cannedForm.title = '';
        cannedForm.content = '';
        cannedForm.category = 'general';
        cannedForm.is_shared = false;
    }
    showCannedDialog.value = true;
}

async function handleSaveCanned() {
    if (!cannedFormRef.value) return;
    const valid = await cannedFormRef.value.validate().catch(() => false);
    if (!valid) return;

    savingCanned.value = true;
    try {
        if (editingCannedId.value) {
            await collabApi.updateCannedReply(editingCannedId.value, cannedForm);
            ElMessage.success('快捷回复已更新');
        } else {
            await collabApi.createCannedReply(cannedForm);
            ElMessage.success('快捷回复已创建');
        }
        showCannedDialog.value = false;
        await fetchCannedReplies();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || '操作失败');
    } finally {
        savingCanned.value = false;
    }
}

async function handleDeleteCanned(row) {
    try {
        await collabApi.deleteCannedReply(row.id);
        ElMessage.success('已删除');
        await fetchCannedReplies();
    } catch {
        ElMessage.error('删除失败');
    }
}

// ─── 关注列表 ───
const watchlist = ref([]);
const loadingWatchlist = ref(false);
const watchType = ref('');

const watchableTypes = [
    { value: 'App\\Models\\License', label: 'License' },
    { value: 'App\\Models\\Customer', label: '客户' },
    { value: 'App\\Models\\Ticket', label: '工单' },
    { value: 'App\\Models\\Product', label: '产品' },
    { value: 'App\\Models\\Subscription', label: '订阅' },
];

function watchableLabel(type) {
    const map = {
        'App\\Models\\License': 'License',
        'App\\Models\\Customer': '客户',
        'App\\Models\\Ticket': '工单',
        'App\\Models\\Product': '产品',
        'App\\Models\\Subscription': '订阅',
        'App\\Models\\Invoice': '发票',
        'App\\Models\\Device': '设备',
    };
    return map[type] || type?.split('\\').pop() || type;
}

function watchableSummary(watchable) {
    if (!watchable) return '—';
    if (watchable.name) return watchable.name;
    if (watchable.title) return watchable.title;
    if (watchable.subject) return watchable.subject;
    if (watchable.license_key) return watchable.license_key;
    return `#${watchable.id}`;
}

async function fetchWatchlist() {
    loadingWatchlist.value = true;
    try {
        const params = {};
        if (watchType.value) params.type = watchType.value;
        const res = await collabApi.getWatchlist(params);
        if (res.success) watchlist.value = res.data || [];
    } catch {
        ElMessage.error('加载关注列表失败');
    } finally {
        loadingWatchlist.value = false;
    }
}

async function handleUnwatch(row) {
    try {
        const typeMap = {
            'App\\Models\\License': 'licenses',
            'App\\Models\\Customer': 'customers',
            'App\\Models\\Ticket': 'tickets',
            'App\\Models\\Product': 'products',
            'App\\Models\\Subscription': 'subscriptions',
            'App\\Models\\Invoice': 'invoices',
            'App\\Models\\Device': 'devices',
        };
        const entityType = typeMap[row.watchable_type] || 'licenses';
        const res = await collabApi.toggleWatch(entityType, row.watchable_id);
        if (res.success) {
            ElMessage.success('已取消关注');
            await fetchWatchlist();
        }
    } catch {
        ElMessage.error('操作失败');
    }
}

// ─── 协作偏好 ───
const prefsForm = reactive({
    notify_on_mention: true,
    notify_on_note_reply: true,
    notify_on_status_change: true,
    daily_digest: false,
});
const prefsDigestTime = ref('09:00');
const savingPrefs = ref(false);

async function fetchPreferences() {
    try {
        const res = await collabApi.getPreferences();
        if (res.success && res.data) {
            prefsForm.notify_on_mention = res.data.notify_on_mention ?? true;
            prefsForm.notify_on_note_reply = res.data.notify_on_note_reply ?? true;
            prefsForm.notify_on_status_change = res.data.notify_on_status_change ?? true;
            prefsForm.daily_digest = res.data.daily_digest ?? false;
            prefsDigestTime.value = res.data.digest_time || '09:00';
        }
    } catch { /* ignore */ }
}

async function handleSavePreferences() {
    savingPrefs.value = true;
    try {
        const data = { ...prefsForm, digest_time: prefsDigestTime.value || '09:00' };
        const res = await collabApi.updatePreferences(data);
        if (res.success) ElMessage.success('偏好已保存');
    } catch {
        ElMessage.error('保存失败');
    } finally {
        savingPrefs.value = false;
    }
}

// ─── 工具函数 ───
function formatTime(time) {
    if (!time) return '—';
    return new Date(time).toLocaleString('zh-CN');
}

async function refreshAll() {
    await Promise.all([
        fetchActivityFeed(),
        fetchCannedReplies(),
        fetchWatchlist(),
        fetchPreferences(),
    ]);
}

onMounted(async () => {
    await Promise.all([
        fetchActivityFeed(),
        fetchCannedReplies(),
        fetchWatchlist(),
        fetchPreferences(),
    ]);
});
</script>

<style scoped>
.collaboration-page { padding: 20px; }

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

.tab-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
    gap: 12px;
}
.toolbar-left {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.toolbar-right {
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Activity Feed */
.activity-list { min-height: 300px; }

.activity-item {
    display: flex;
    gap: 12px;
    padding: 14px 0;
    border-bottom: 1px solid var(--el-border-color-lighter);
}
.activity-item:last-child { border-bottom: none; }

.activity-avatar { flex-shrink: 0; }

.activity-body { flex: 1; min-width: 0; }

.activity-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 4px;
}
.activity-user {
    font-weight: 600;
    font-size: 14px;
    color: var(--el-text-color-primary);
}
.activity-desc {
    font-size: 14px;
    color: var(--el-text-color-regular);
    line-height: 1.5;
    margin-bottom: 4px;
}
.activity-meta {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    display: flex;
    gap: 12px;
}
.activity-metadata {
    margin-top: 6px;
    background: var(--el-fill-color-light);
    border-radius: 4px;
    padding: 8px;
}
.activity-metadata pre {
    margin: 0;
    font-size: 11px;
    white-space: pre-wrap;
    color: var(--el-text-color-secondary);
}

.canned-content {
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    line-clamp: 2;
    font-size: 13px;
    color: var(--el-text-color-secondary);
}

.pagination-wrap {
    margin-top: 16px;
    display: flex;
    justify-content: flex-end;
}

.empty-state {
    text-align: center;
    padding: 60px 0;
    color: var(--el-text-color-placeholder);
    font-size: 14px;
}

:deep(.el-card__body) { padding: 16px; }
</style>
