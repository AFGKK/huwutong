<template>
    <div class="portal-sessions">
        <div class="page-header">
            <div>
                <h2>{{ $t('portal.sessions_title') }}</h2>
                <p class="subtitle">{{ $t('portal.sessions_subtitle') }}</p>
            </div>
            <el-button @click="refreshAll" :loading="loadingSessions || loadingHistory">
                <el-icon><Refresh /></el-icon>
                {{ $t('portal.refresh') }}
            </el-button>
        </div>

        <el-card class="mb-4" shadow="never">
            <template #header>
                <span>{{ $t('portal.active_sessions') }}</span>
            </template>
            <el-table :data="sessions" v-loading="loadingSessions" stripe :empty-text="$t('portal.no_active_sessions')">
                <el-table-column :label="$t('portal.device_or_name')" min-width="160">
                    <template #default="{ row }">
                        {{ row.name || $t('portal.unknown_device') }}
                        <el-tag v-if="row.is_current" type="success" size="small" class="ml-2">{{ $t('portal.current_session') }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="IP" prop="ip_address" width="140">
                    <template #default="{ row }">{{ row.ip_address || '-' }}</template>
                </el-table-column>
                <el-table-column :label="$t('portal.last_used')" width="180">
                    <template #default="{ row }">{{ formatTime(row.last_used_at) }}</template>
                </el-table-column>
                <el-table-column :label="$t('portal.created_at')" width="180">
                    <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
                </el-table-column>
                <el-table-column :label="$t('portal.actions')" width="120" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            v-if="!row.is_current"
                            type="danger"
                            link
                            size="small"
                            @click="revokeSession(row)"
                        >
                            {{ $t('portal.kick') }}
                        </el-button>
                        <span v-else class="text-muted">—</span>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <el-card shadow="never">
            <template #header>
                <span>{{ $t('portal.login_history') }}</span>
            </template>
            <el-table :data="history" v-loading="loadingHistory" stripe :empty-text="$t('portal.no_login_history')">
                <el-table-column :label="$t('portal.time')" width="180">
                    <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
                </el-table-column>
                <el-table-column :label="$t('portal.method')" width="120">
                    <template #default="{ row }">{{ row.provider || 'email' }}</template>
                </el-table-column>
                <el-table-column :label="$t('portal.status')" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.success ? 'success' : 'danger'" size="small">
                            {{ row.success ? $t('portal.success') : $t('portal.failure') }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="IP" prop="ip_address" width="140">
                    <template #default="{ row }">{{ row.ip_address || row.ip || '-' }}</template>
                </el-table-column>
                <el-table-column :label="$t('portal.device')" min-width="200" show-overflow-tooltip>
                    <template #default="{ row }">{{ row.user_agent || '-' }}</template>
                </el-table-column>
            </el-table>
            <div class="pager" v-if="historyTotal > historyPageSize">
                <el-pagination
                    layout="prev, pager, next"
                    :total="historyTotal"
                    :page-size="historyPageSize"
                    :current-page="historyPage"
                    @current-change="loadHistory"
                />
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh } from '@element-plus/icons-vue';
import apiClient from '@/api/client';

const { t, locale } = useI18n();

const sessions = ref([]);
const history = ref([]);
const loadingSessions = ref(false);
const loadingHistory = ref(false);
const historyPage = ref(1);
const historyPageSize = 20;
const historyTotal = ref(0);

function formatTime(v) {
    if (!v) return '-';
    try {
        const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
        return new Date(v).toLocaleString(loc);
    } catch {
        return String(v);
    }
}

async function loadSessions() {
    loadingSessions.value = true;
    try {
        const { data: res } = await apiClient.get('/sessions');
        sessions.value = res.data || [];
    } catch {
        sessions.value = [];
        ElMessage.error(t('portal.sessions_load_failed'));
    } finally {
        loadingSessions.value = false;
    }
}

async function loadHistory(page = 1) {
    historyPage.value = page;
    loadingHistory.value = true;
    try {
        const { data: res } = await apiClient.get('/login-history', {
            params: { page, per_page: historyPageSize },
        });
        const paginated = res.data || {};
        history.value = paginated.data || paginated || [];
        historyTotal.value = paginated.total || history.value.length;
    } catch {
        history.value = [];
        ElMessage.error(t('portal.history_load_failed'));
    } finally {
        loadingHistory.value = false;
    }
}

async function revokeSession(row) {
    try {
        await ElMessageBox.confirm(t('portal.kick_confirm'), t('actions.confirm'), {
            type: 'warning',
            confirmButtonText: t('portal.kick'),
            cancelButtonText: t('actions.cancel'),
        });
        await apiClient.delete(`/sessions/${row.id}`);
        ElMessage.success(t('portal.kick_ok'));
        loadSessions();
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error(e.response?.data?.error?.message || e.response?.data?.message || t('messages.failed'));
        }
    }
}

function refreshAll() {
    loadSessions();
    loadHistory(historyPage.value);
}

onMounted(() => {
    refreshAll();
});
</script>

<style scoped>
.portal-sessions { padding: 4px; }
.page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 16px;
}
.page-header h2 { margin: 0 0 4px; font-size: 20px; }
.subtitle { margin: 0; color: #909399; font-size: 13px; }
.mb-4 { margin-bottom: 16px; }
.ml-2 { margin-left: 8px; }
.text-muted { color: #c0c4cc; }
.pager { margin-top: 16px; display: flex; justify-content: flex-end; }
</style>
