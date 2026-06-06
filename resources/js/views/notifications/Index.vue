<template>
    <div class="notifications-page">
        <div class="page-header">
            <div class="header-left">
                <h2>通知中心</h2>
                <span class="header-subtitle">管理所有系统通知</span>
            </div>
            <div class="header-right">
                <el-button
                    v-if="hasUnread"
                    type="primary"
                    @click="handleMarkAllRead"
                >
                    <el-icon><Check /></el-icon>
                    全部已读
                </el-button>
            </div>
        </div>

        <!-- 筛选 -->
        <el-card shadow="never" class="filter-card">
            <el-form :model="filters" inline>
                <el-form-item label="通知类型">
                    <el-select v-model="filters.type" clearable placeholder="全部类型" style="width: 150px" @change="doSearch">
                        <el-option label="过期提醒" value="expiry_warning" />
                        <el-option label="状态变更" value="status_change" />
                        <el-option label="系统通知" value="system" />
                        <el-option label="激活通知" value="license_activation" />
                    </el-select>
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="filters.is_read" clearable placeholder="全部" style="width: 110px" @change="doSearch">
                        <el-option label="未读" :value="false" />
                        <el-option label="已读" :value="true" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="doSearch">
                        <el-icon><Search /></el-icon>
                        搜索
                    </el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 批量操作 -->
        <div class="batch-bar" v-if="selectedIds.length > 0">
            <span class="batch-info">已选择 {{ selectedIds.length }} 项</span>
            <el-button size="small" @click="clearSelection">取消选择</el-button>
            <el-button size="small" type="primary" @click="batchAction('read')">标记已读</el-button>
            <el-button size="small" type="danger" @click="batchAction('delete')">批量删除</el-button>
        </div>

        <!-- 通知列表 -->
        <el-card shadow="never">
            <div v-if="notifications.length === 0 && !loading" class="empty-state">
                <el-empty :image-size="80" description="暂无通知" />
            </div>

            <div v-loading="loading" class="notification-list">
                <div
                    v-for="item in notifications"
                    :key="item.id"
                    class="notification-card"
                    :class="{ unread: !item.is_read }"
                >
                    <el-checkbox
                        v-if="!item.is_read || selectedIds.includes(item.id)"
                        v-model="item._checked"
                        class="notif-checkbox"
                        @change="(val) => toggleSelect(item.id, val)"
                    />
                    <div class="notif-icon">
                        <el-tag
                            :type="typeTag(item.type)"
                            size="large"
                            effect="plain"
                            round
                            style="font-size: 18px; padding: 6px 10px;"
                        >
                            {{ typeIcon(item.type) }}
                        </el-tag>
                    </div>
                    <div class="notif-body">
                        <div class="notif-header">
                            <span class="notif-type-badge">
                                <el-tag :type="typeTag(item.type)" size="small" effect="light">
                                    {{ typeLabel(item.type) }}
                                </el-tag>
                            </span>
                            <span class="notif-time">{{ formatDate(item.created_at) }}</span>
                            <span v-if="!item.is_read" class="unread-badge">未读</span>
                        </div>
                        <div class="notif-title">{{ item.title }}</div>
                        <div class="notif-content-text">{{ item.content }}</div>
                        <div class="notif-footer">
                            <el-button
                                v-if="!item.is_read"
                                text
                                size="small"
                                type="primary"
                                @click="handleMarkRead(item)"
                            >
                                标记已读
                            </el-button>
                            <el-button
                                text
                                size="small"
                                type="danger"
                                @click="handleDelete(item)"
                            >
                                删除
                            </el-button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 分页 -->
            <div class="pagination-wrapper" v-if="total > 0">
                <el-pagination
                    v-model:current-page="page"
                    v-model:page-size="perPage"
                    :page-sizes="[10, 20, 50]"
                    :total="total"
                    layout="total, sizes, prev, pager, next, jumper"
                    @size-change="loadNotifications"
                    @current-change="loadNotifications"
                />
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, computed, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Search, Check } from '@element-plus/icons-vue';
import notificationApi from '@/api/notification';

const loading = ref(false);
const notifications = ref([]);
const total = ref(0);
const page = ref(1);
const perPage = ref(20);
const selectedIds = ref([]);

const filters = reactive({
    type: '',
    is_read: '',
});

const hasUnread = computed(() => notifications.value.some(n => !n.is_read));

function typeTag(type) {
    const map = {
        expiry_warning: 'warning',
        status_change: 'primary',
        system: 'info',
        license_activation: 'success',
    };
    return map[type] || 'info';
}

function typeLabel(type) {
    const map = {
        expiry_warning: '过期提醒',
        status_change: '状态变更',
        system: '系统通知',
        license_activation: '激活通知',
    };
    return map[type] || type;
}

function typeIcon(type) {
    const map = {
        expiry_warning: '⏰',
        status_change: '🔄',
        system: 'ℹ️',
        license_activation: '✅',
    };
    return map[type] || '📢';
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

async function loadNotifications() {
    loading.value = true;
    try {
        const params = {
            page: page.value,
            per_page: perPage.value,
            sort: '-created_at',
        };
        if (filters.type) params['filter.type'] = filters.type;
        if (filters.is_read !== '') params['filter.is_read'] = filters.is_read;

        const { data: res } = await notificationApi.list(params);
        notifications.value = (res.data?.data || []).map(n => ({ ...n, _checked: false }));
        total.value = res.data?.total || 0;
    } catch {
        notifications.value = [];
    } finally {
        loading.value = false;
    }
}

function doSearch() {
    page.value = 1;
    loadNotifications();
}

function clearSelection() {
    selectedIds.value = [];
    notifications.value.forEach(n => n._checked = false);
}

function toggleSelect(id, val) {
    if (val) {
        if (!selectedIds.value.includes(id)) selectedIds.value.push(id);
    } else {
        selectedIds.value = selectedIds.value.filter(i => i !== id);
    }
}

// 标记已读
async function handleMarkRead(item) {
    try {
        await notificationApi.markRead(item.id);
        item.is_read = true;
        ElMessage.success('已标记为已读');
    } catch { /* ignore */ }
}

async function handleMarkAllRead() {
    try {
        await notificationApi.markAllRead();
        notifications.value.forEach(n => n.is_read = true);
        ElMessage.success('已全部标记为已读');
    } catch { /* ignore */ }
}

// 删除
async function handleDelete(item) {
    try {
        await ElMessageBox.confirm('确定要删除该通知吗？', '确认删除', {
            confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning',
        });
        await notificationApi.destroy(item.id);
        ElMessage.success('通知已删除');
        loadNotifications();
    } catch { /* cancelled */ }
}

// 批量操作
async function batchAction(action) {
    if (selectedIds.value.length === 0) {
        ElMessage.warning('请先选择通知');
        return;
    }
    const labels = { read: '标记已读', delete: '删除' };
    const label = labels[action];

    try {
        if (action === 'delete') {
            await ElMessageBox.confirm(
                `确定要${label}选中的 ${selectedIds.value.length} 条通知吗？`,
                '批量操作',
                { confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning' }
            );
        }
        await notificationApi.batch(selectedIds.value, action);
        ElMessage.success(`批量${label}成功`);
        clearSelection();
        loadNotifications();
    } catch { /* cancelled */ }
}

onMounted(() => {
    loadNotifications();
});
</script>

<style scoped>
.notifications-page { padding: 20px; }

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

.filter-card { margin-bottom: 16px; }

.batch-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 0;
}
.batch-info {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-right: 8px;
}

.empty-state {
    padding: 40px 0;
}

.notification-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.notification-card {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 16px;
    border: 1px solid var(--el-border-color-light);
    border-radius: 8px;
    transition: all 0.2s;
}
.notification-card:hover {
    border-color: var(--el-color-primary-light-5);
    background: var(--el-color-primary-light-9);
}
.notification-card.unread {
    background: var(--el-color-info-light-9);
    border-left: 3px solid var(--el-color-primary);
}

.notif-checkbox {
    margin-top: 4px;
}

.notif-icon {
    flex-shrink: 0;
}

.notif-body {
    flex: 1;
    min-width: 0;
}
.notif-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
}
.notif-type-badge { flex-shrink: 0; }
.notif-time {
    font-size: 12px;
    color: var(--el-text-color-placeholder);
}
.unread-badge {
    font-size: 11px;
    color: var(--el-color-primary);
    font-weight: 500;
}

.notif-title {
    font-size: 15px;
    font-weight: 600;
    color: var(--el-text-color-primary);
    margin-bottom: 4px;
}
.notif-content-text {
    font-size: 13px;
    color: var(--el-text-color-regular);
    line-height: 1.5;
}
.notif-footer {
    margin-top: 8px;
    display: flex;
    gap: 8px;
}

.pagination-wrapper {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
}

:deep(.el-card__body) { padding: 16px; }
.filter-card :deep(.el-card__body) { padding: 12px 16px; }
</style>
