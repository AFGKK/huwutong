<template>
    <div class="crl-page">
        <div class="page-header">
            <h2>
                <el-icon style="vertical-align:middle;margin-right:8px"><RemoveFilled /></el-icon>
                {{ t('crl_page.title') }}
            </h2>
            <div class="header-actions">
                <el-button @click="handleAutoVerify" :loading="verifying">
                    <el-icon><Refresh /></el-icon> {{ t('crl_page.auto_verify') }}
                </el-button>
                <el-button type="primary" @click="showRevokeDialog = true">
                    <el-icon><Remove /></el-icon> {{ t('crl_page.revoke_license') }}
                </el-button>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-danger">{{ stats.total_revoked }}</div>
                    <div class="stat-label">{{ t('crl_page.stats.total_revoked') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-warning">{{ stats.recent_7d_revoked }}</div>
                    <div class="stat-label">{{ t('crl_page.stats.recent_7d') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-info">{{ stats.pending_auto_verify }}</div>
                    <div class="stat-label">{{ t('crl_page.stats.pending_verify') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" style="cursor:pointer" @click="handleAutoVerify">
                    <div class="stat-value text-success">{{ t('crl_page.stats.run_verify') }}</div>
                    <div class="stat-label">{{ t('crl_page.stats.one_click_verify') }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 吊销列表 -->
        <el-card shadow="hover">
            <div class="tab-toolbar">
                <el-input v-model="searchQuery" :placeholder="t('crl_page.search_ph')" clearable style="width:260px" @clear="loadEntries" @keyup.enter="loadEntries" />
            </div>
            <el-table :data="entries" v-loading="loading" stripe>
                <el-table-column prop="license_key" :label="t('crl_page.columns.license_key')" min-width="200">
                    <template #default="{ row }">
                        <span class="font-mono">{{ row.license_key }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="reason" :label="t('crl_page.columns.reason')" min-width="200" />
                <el-table-column :label="t('crl_page.columns.revoked_at')" width="170">
                    <template #default="{ row }">{{ formatTime(row.revoked_at) }}</template>
                </el-table-column>
                <el-table-column :label="t('crl_page.columns.cert_version')" width="100">
                    <template #default="{ row }">{{ row.certificate?.key_version || '-' }}</template>
                </el-table-column>
                <el-table-column :label="t('crl_page.columns.actions')" width="120" fixed="right">
                    <template #default="{ row }">
                        <el-popconfirm :title="t('crl_page.restore_confirm')" @confirm="handleRestore(row)">
                            <template #reference>
                                <el-button size="small" type="success">{{ t('crl_page.restore') }}</el-button>
                            </template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>
            <div class="pagination-wrap" v-if="pagination.total > pagination.per_page">
                <el-pagination
                    v-model:current-page="pagination.current_page"
                    :page-size="pagination.per_page"
                    :total="pagination.total"
                    layout="total, prev, pager, next"
                    @current-change="loadEntries"
                />
            </div>
        </el-card>

        <!-- 吊销对话框 -->
        <el-dialog v-model="showRevokeDialog" :title="t('crl_page.revoke_dialog.title')" width="480px">
            <el-form ref="revokeFormRef" :model="revokeForm" :rules="revokeRules" label-position="top">
                <el-form-item :label="t('crl_page.revoke_dialog.license_key')" prop="license_key">
                    <el-input v-model="revokeForm.license_key" :placeholder="t('crl_page.revoke_dialog.license_key_ph')" />
                </el-form-item>
                <el-form-item :label="t('crl_page.revoke_dialog.reason')" prop="reason">
                    <el-input v-model="revokeForm.reason" :placeholder="t('crl_page.revoke_dialog.reason_ph')" maxlength="500" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showRevokeDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="danger" @click="handleRevoke" :loading="revoking">{{ t('crl_page.revoke_dialog.confirm') }}</el-button>
            </template>
        </el-dialog>

        <!-- 批量吊销对话框 -->
        <el-dialog v-model="showBatchRevokeDialog" :title="t('crl_page.batch_dialog.title')" width="520px">
            <el-form ref="batchRevokeFormRef" :model="batchRevokeForm" :rules="batchRevokeRules" label-position="top">
                <el-form-item :label="t('crl_page.batch_dialog.license_keys')" prop="license_keys">
                    <el-input v-model="batchRevokeForm.license_keys" type="textarea" :rows="5" :placeholder="t('crl_page.batch_dialog.license_keys_ph')" />
                </el-form-item>
                <el-form-item :label="t('crl_page.batch_dialog.reason')" prop="reason">
                    <el-input v-model="batchRevokeForm.reason" :placeholder="t('crl_page.batch_dialog.reason_ph')" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showBatchRevokeDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="danger" @click="handleBatchRevoke" :loading="revoking">{{ t('crl_page.batch_dialog.confirm') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { RemoveFilled, Remove, Refresh } from '@element-plus/icons-vue';
import crlApi from '@/api/crl';

const { t, locale } = useI18n();

const loading = ref(false);
const revoking = ref(false);
const verifying = ref(false);
const searchQuery = ref('');
const showRevokeDialog = ref(false);
const showBatchRevokeDialog = ref(false);
const revokeFormRef = ref(null);
const batchRevokeFormRef = ref(null);

const entries = ref([]);
const stats = reactive({ total_revoked: 0, recent_7d_revoked: 0, pending_auto_verify: 0 });
const pagination = reactive({ current_page: 1, per_page: 20, total: 0 });
const revokeForm = reactive({ license_key: '', reason: '' });
const batchRevokeForm = reactive({ license_keys: '', reason: '' });

const revokeRules = computed(() => ({
    license_key: [{ required: true, message: t('crl_page.rules.license_key_required'), trigger: 'blur' }],
}));
const batchRevokeRules = computed(() => ({
    license_keys: [{ required: true, message: t('crl_page.rules.license_keys_required'), trigger: 'blur' }],
}));

onMounted(() => {
    loadDashboard();
    loadEntries();
});

async function loadDashboard() {
    try {
        const res = await crlApi.dashboard();
        Object.assign(stats, res.data || {});
    } catch { /* ignore */ }
}

async function loadEntries() {
    loading.value = true;
    try {
        const res = await crlApi.entries({ page: pagination.current_page, search: searchQuery.value || undefined });
        entries.value = res.data?.data || [];
        Object.assign(pagination, res.data || {});
    } catch {
        ElMessage.error(t('messages.load_failed'));
    } finally {
        loading.value = false;
    }
}

async function handleRevoke() {
    const valid = await revokeFormRef.value?.validate().catch(() => false);
    if (!valid) return;
    revoking.value = true;
    try {
        await crlApi.revoke({
            license_key: revokeForm.license_key,
            reason: revokeForm.reason || t('crl_page.messages.default_reason'),
        });
        ElMessage.success(t('crl_page.messages.revoked'));
        showRevokeDialog.value = false;
        revokeForm.license_key = '';
        revokeForm.reason = '';
        loadDashboard();
        loadEntries();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('crl_page.messages.revoke_failed'));
    } finally {
        revoking.value = false;
    }
}

async function handleRestore(row) {
    try {
        await crlApi.restore({ license_key: row.license_key });
        ElMessage.success(t('crl_page.messages.restored'));
        loadDashboard();
        loadEntries();
    } catch (err) {
        ElMessage.error(t('crl_page.messages.restore_failed'));
    }
}

async function handleAutoVerify() {
    verifying.value = true;
    try {
        const res = await crlApi.autoVerify();
        ElMessage.success(res.data?.message || t('crl_page.messages.verify_done'));
        loadDashboard();
    } catch {
        ElMessage.error(t('crl_page.messages.verify_failed'));
    } finally {
        verifying.value = false;
    }
}

function formatTime(time) {
    if (!time) return '-';
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
    return new Date(time).toLocaleString(loc, {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}
</script>

<style scoped>
.crl-page { padding: 0 4px; }
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.page-header h2 { margin: 0; font-size: 20px; }
.mb-4 { margin-bottom: 16px; }
.stat-value { font-size: 28px; font-weight: 700; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.text-danger { color: #f56c6c; }
.text-warning { color: #e6a23c; }
.text-info { color: #909399; }
.text-success { color: #67c23a; }
.tab-toolbar { margin-bottom: 16px; }
.font-mono { font-family: 'SFMono-Regular', Consolas, monospace; font-size: 13px; }
.pagination-wrap { margin-top: 16px; display: flex; justify-content: flex-end; }
</style>
