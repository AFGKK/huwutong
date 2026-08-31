<template>
    <div class="ssl-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('ssl_certificates_page.title') }}</h2>
                <span class="header-subtitle">{{ t('ssl_certificates_page.subtitle') }}</span>
            </div>
            <div class="header-right">
                <el-button type="primary" @click="showCreateDialog = true">
                    <el-icon><Plus /></el-icon>
                    {{ t('ssl_certificates_page.btn_apply_new') }}
                </el-button>
                <el-button @click="loadAll">
                    <el-icon><Refresh /></el-icon>
                    {{ t('ssl_certificates_page.refresh_btn') }}
                </el-button>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-value">{{ stats.total_certificates || '-' }}</div>
                        <div class="stat-label">{{ t('ssl_certificates_page.stat_total') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-value text-success">{{ stats.valid || '-' }}</div>
                        <div class="stat-label">{{ t('ssl_certificates_page.stat_valid') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-value text-warning">{{ stats.expiring_soon || '-' }}</div>
                        <div class="stat-label">{{ t('ssl_certificates_page.stat_expiring_soon') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-value text-warning">{{ stats.needs_renewal || '-' }}</div>
                        <div class="stat-label">{{ t('ssl_certificates_page.stat_needs_renewal') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-value text-danger">{{ stats.failed || '-' }}</div>
                        <div class="stat-label">{{ t('ssl_certificates_page.stat_failed') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-value text-primary">{{ stats.pending || '-' }}</div>
                        <div class="stat-label">{{ t('ssl_certificates_page.stat_pending') }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 证书列表 -->
        <el-card shadow="never">
            <template #header>
                <div class="card-header">
                    <span>{{ t('ssl_certificates_page.list_title') }}</span>
                    <div class="filter-row">
                        <el-select v-model="filterStatus" :placeholder="t('ssl_certificates_page.filter_status_ph')" size="small" clearable style="width: 120px;" @change="loadCertificates">
                            <el-option v-for="opt in statusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                        </el-select>
                        <el-input v-model="searchText" size="small" :placeholder="t('ssl_certificates_page.search_domain_ph')" clearable style="width: 200px; margin-left: 8px;" @clear="loadCertificates" @keyup.enter="loadCertificates" />
                    </div>
                </div>
            </template>

            <el-table :data="certificates" v-loading="loading" stripe>
                <el-table-column prop="id" :label="t('ssl_certificates_page.col_id')" width="60" />
                <el-table-column :label="t('ssl_certificates_page.col_domain')" min-width="180">
                    <template #default="{ row }">
                        <span class="mono">{{ row.domain }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="t('ssl_certificates_page.col_tenant')" width="150">
                    <template #default="{ row }">
                        {{ row.tenant_name || '-' }}
                    </template>
                </el-table-column>
                <el-table-column :label="t('ssl_certificates_page.col_status')" width="100">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row)" size="small">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('ssl_certificates_page.col_expires')" width="110">
                    <template #default="{ row }">
                        <span :class="{ 'text-danger': row.expiring_soon }">
                            {{ row.expires_at ? formatDate(row.expires_at) : '-' }}
                        </span>
                    </template>
                </el-table-column>
                <el-table-column :label="t('ssl_certificates_page.col_issued_at')" width="110">
                    <template #default="{ row }">
                        {{ row.issued_at ? formatDate(row.issued_at) : '-' }}
                    </template>
                </el-table-column>
                <el-table-column :label="t('ssl_certificates_page.col_auto_renew')" width="90">
                    <template #default="{ row }">
                        <el-switch
                            v-model="row.auto_renew"
                            :loading="row._toggling"
                            size="small"
                            @change="(val) => handleToggleAutoRenew(row, val)"
                        />
                    </template>
                </el-table-column>
                <el-table-column :label="t('ssl_certificates_page.col_error')" min-width="180">
                    <template #default="{ row }">
                        <span class="error-text" v-if="row.error_message">{{ row.error_message }}</span>
                        <span v-else>-</span>
                    </template>
                </el-table-column>
                <el-table-column :label="t('ssl_certificates_page.col_actions')" width="220" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            v-if="row.status === 'issued'"
                            size="small"
                            type="warning"
                            plain
                            @click="handleRenew(row)"
                            :loading="row._renewing"
                        >
                            {{ t('ssl_certificates_page.btn_renew') }}
                        </el-button>
                        <el-button
                            v-if="row.status === 'failed' || row.status === 'pending'"
                            size="small"
                            type="primary"
                            plain
                            @click="handleRetry(row)"
                            :loading="row._renewing"
                        >
                            {{ t('actions.retry') }}
                        </el-button>
                        <el-popconfirm
                            v-if="row.status !== 'revoked'"
                            :title="t('ssl_certificates_page.revoke_confirm_title')"
                            @confirm="handleRevoke(row)"
                        >
                            <template #reference>
                                <el-button size="small" type="danger" plain>{{ t('ssl_certificates_page.btn_revoke') }}</el-button>
                            </template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>

            <div class="pagination-wrap" v-if="pagination">
                <el-pagination
                    v-model:current-page="pagination.current_page"
                    :page-size="pagination.per_page"
                    :total="pagination.total"
                    layout="total, prev, pager, next"
                    @current-change="loadCertificates"
                />
            </div>
        </el-card>

        <!-- 即将到期证书 -->
        <el-card v-if="stats.expiring_certificates?.length" shadow="never" class="mt-4">
            <template #header>
                <span class="text-warning">{{ t('ssl_certificates_page.expiring_title', { count: stats.expiring_certificates.length }) }}</span>
            </template>
            <div v-for="c in stats.expiring_certificates" :key="c.id" class="expiring-item">
                <span class="mono">{{ c.domain }}</span>
                <el-tag size="small" type="danger">{{ t('ssl_certificates_page.days_left', { days: c.days_left }) }}</el-tag>
                <el-button size="small" text type="primary" @click="quickRenew(c.id)">{{ t('ssl_certificates_page.btn_renew_now') }}</el-button>
            </div>
        </el-card>

        <!-- 申请证书 Dialog -->
        <el-dialog v-model="showCreateDialog" :title="t('ssl_certificates_page.create_dialog_title')" width="500px">
            <el-form ref="createFormRef" :model="createForm" :rules="createRules" label-position="top">
                <el-alert type="info" :closable="false" class="mb-3">
                    <template #title>{{ t('ssl_certificates_page.create_alert') }}</template>
                </el-alert>
                <el-form-item :label="t('ssl_certificates_page.form_domain_label')" prop="custom_domain_id">
                    <el-select v-model="createForm.custom_domain_id" filterable :placeholder="t('ssl_certificates_page.form_domain_ph')" style="width:100%">
                        <el-option
                            v-for="d in domainOptions"
                            :key="d.id"
                            :label="d.domain"
                            :value="d.id"
                        >
                            <span>{{ d.domain }}</span>
                            <span class="domain-status ml-2">
                                <el-tag :type="d.verified ? 'success' : 'warning'" size="small">
                                    {{ d.verified ? t('ssl_certificates_page.domain_verified') : t('ssl_certificates_page.domain_unverified') }}
                                </el-tag>
                            </span>
                        </el-option>
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-switch v-model="createForm.auto_renew" :active-text="t('ssl_certificates_page.auto_renew_label')" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showCreateDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleCreate" :loading="creating">{{ t('ssl_certificates_page.btn_submit_apply') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Refresh } from '@element-plus/icons-vue';
import sslApi from '@/api/sslCertificate';
import domainApi from '@/api/domain';

const { t, locale } = useI18n();

const loading = ref(false);
const creating = ref(false);
const certificates = ref([]);
const pagination = ref(null);
const stats = reactive({
    total_certificates: 0, issued: 0, valid: 0,
    expiring_soon: 0, needs_renewal: 0, failed: 0,
    renewing: 0, pending: 0, expiring_certificates: [],
});
const filterStatus = ref('');
const searchText = ref('');
const showCreateDialog = ref(false);
const createFormRef = ref(null);
const domainOptions = ref([]);

const createForm = reactive({
    custom_domain_id: null,
    auto_renew: true,
});

const statusKeys = ['issued', 'pending', 'renewing', 'failed', 'revoked'];

const statusLabels = computed(() => ({
    issued: t('ssl_certificates_page.status.issued'),
    pending: t('ssl_certificates_page.status.pending'),
    renewing: t('ssl_certificates_page.status.renewing'),
    failed: t('ssl_certificates_page.status.failed'),
    revoked: t('ssl_certificates_page.status.revoked'),
    expired: t('ssl_certificates_page.status.expired'),
}));

const statusOptions = computed(() =>
    statusKeys.map((value) => ({ value, label: statusLabels.value[value] }))
);

const createRules = computed(() => ({
    custom_domain_id: [{ required: true, message: t('ssl_certificates_page.validation.domain_required'), trigger: 'change' }],
}));

function statusType(row) {
    if (row.status === 'issued' && row.is_valid) return 'success';
    if (row.status === 'issued' && row.expiring_soon) return 'warning';
    if (row.status === 'renewing') return 'primary';
    if (row.status === 'failed') return 'danger';
    if (row.status === 'revoked') return 'info';
    if (row.status === 'pending') return 'warning';
    return 'info';
}

function statusLabel(status) {
    return statusLabels.value[status] || status;
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
    return new Date(dateStr).toLocaleString(loc, {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

async function loadCertificates(page = 1) {
    loading.value = true;
    try {
        const params = { page, per_page: 20 };
        if (filterStatus.value) params.status = filterStatus.value;
        if (searchText.value) params.search = searchText.value;

        const { data: res } = await sslApi.list(params);
        const paginated = res.data;
        certificates.value = paginated?.data || paginated || [];
        if (paginated?.current_page) {
            pagination.value = paginated;
        }
    } catch {
        certificates.value = [];
    } finally {
        loading.value = false;
    }
}

async function loadStats() {
    try {
        const { data: res } = await sslApi.stats();
        Object.assign(stats, res.data || {});
    } catch { /* ignore */ }
}

async function loadDomains() {
    try {
        const { data: res } = await domainApi.list({ per_page: 200 });
        // 处理两种响应格式：{ data: [...] } 或 { data: { data: [...] } }
        const raw = res.data;
        domainOptions.value = Array.isArray(raw) ? raw : (raw?.data || []);
    } catch {
        domainOptions.value = [];
    }
}

async function handleCreate() {
    const valid = await createFormRef.value?.validate().catch(() => false);
    if (!valid) return;

    creating.value = true;
    try {
        await sslApi.create({
            custom_domain_id: createForm.custom_domain_id,
            auto_renew: createForm.auto_renew,
        });
        ElMessage.success(t('ssl_certificates_page.messages.create_success'));
        showCreateDialog.value = false;
        loadAll();
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || t('ssl_certificates_page.messages.create_failed'));
    } finally {
        creating.value = false;
    }
}

async function handleRenew(row) {
    try {
        await ElMessageBox.confirm(
            t('ssl_certificates_page.renew_confirm_msg', { domain: row.domain }),
            t('ssl_certificates_page.renew_confirm_title'),
            {
                confirmButtonText: t('ssl_certificates_page.renew_confirm_btn'),
                cancelButtonText: t('actions.cancel'),
                type: 'info',
            },
        );
        row._renewing = true;
        await sslApi.renew(row.id);
        ElMessage.success(t('ssl_certificates_page.messages.renew_success'));
        loadAll();
    } catch (err) {
        if (err?.response?.data?.message) {
            ElMessage.error(err.response.data.message);
        }
    } finally {
        row._renewing = false;
    }
}

async function handleRetry(row) {
    row._renewing = true;
    try {
        await sslApi.renew(row.id);
        ElMessage.success(t('ssl_certificates_page.messages.retry_success'));
        loadAll();
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || t('ssl_certificates_page.messages.retry_failed'));
    } finally {
        row._renewing = false;
    }
}

async function handleRevoke(row) {
    try {
        const { value: reason } = await ElMessageBox.prompt(
            t('ssl_certificates_page.revoke_prompt_msg'),
            t('ssl_certificates_page.revoke_dialog_title'),
            {
                confirmButtonText: t('ssl_certificates_page.revoke_confirm_btn'),
                cancelButtonText: t('actions.cancel'),
                inputPlaceholder: t('ssl_certificates_page.revoke_reason_ph'),
                inputType: 'textarea',
                type: 'warning',
            },
        );
        await sslApi.revoke(row.id, { reason: reason || t('ssl_certificates_page.revoke_default_reason') });
        ElMessage.success(t('ssl_certificates_page.messages.revoke_success'));
        loadAll();
    } catch (e) {
        if (e !== 'cancel' && e !== 'close' && e?.message) {
            ElMessage.error(e.message || t('ssl_certificates_page.messages.revoke_failed'));
        }
    }
}

async function handleToggleAutoRenew(row, val) {
    row._toggling = true;
    try {
        await sslApi.update(row.id, { auto_renew: val });
        ElMessage.success(val ? t('ssl_certificates_page.messages.auto_renew_on') : t('ssl_certificates_page.messages.auto_renew_off'));
    } catch {
        row.auto_renew = !val;
        ElMessage.error(t('messages.failed'));
    } finally {
        row._toggling = false;
    }
}

function quickRenew(id) {
    const cert = certificates.value.find(c => c.id === id);
    if (cert) handleRenew(cert);
}

function loadAll() {
    loadCertificates();
    loadStats();
}

onMounted(() => {
    loadAll();
    loadDomains();
});
</script>

<style scoped>
.ssl-page { padding: 20px; }

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 8px;
}
.header-left h2 { margin: 0; font-size: 20px; }
.header-subtitle {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-left: 12px;
}

.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.ml-2 { margin-left: 8px; }

.stat-item { text-align: center; padding: 8px 0; }
.stat-label {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-bottom: 6px;
}
.stat-value { font-size: 24px; font-weight: 700; color: var(--el-text-color-primary); }
.text-success { color: var(--el-color-success); }
.text-danger { color: var(--el-color-danger); }
.text-warning { color: var(--el-color-warning); }
.text-primary { color: var(--el-color-primary); }

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.filter-row {
    display: flex;
    align-items: center;
}

.mono {
    font-family: 'Courier New', monospace;
    font-size: 13px;
}

.error-text {
    font-size: 12px;
    color: var(--el-color-danger);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.pagination-wrap {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
}

.expiring-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 0;
    border-bottom: 1px solid var(--el-border-color-light);
}
.expiring-item:last-child { border-bottom: none; }

.domain-status { font-size: 12px; }

:deep(.el-card__body) { padding: 16px; }
</style>
