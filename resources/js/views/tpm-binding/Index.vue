<template>
    <div class="tpm-page">
        <div class="page-header">
            <div>
                <h2>{{ t(`${P}.title`) }}</h2>
                <p class="text-muted">{{ t(`${P}.subtitle`) }}</p>
            </div>
            <div class="header-actions">
                <el-button @click="loadAll" :loading="loading" :icon="Refresh">{{ t(`${P}.refresh`) }}</el-button>
                <el-button @click="showRegisterForm = true" type="primary" :icon="Key">{{ t(`${P}.register_binding`) }}</el-button>
            </div>
        </div>

        <!-- ── 概览卡片 ── -->
        <el-row :gutter="16" class="mb-4">
            <el-col :xs="12" :sm="6" :md="3"><el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t(`${P}.stats.total_bindings`) }}</div><div class="metric-value">{{ dash.total_bindings }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6" :md="3"><el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t(`${P}.stats.active_bindings`) }}</div><div class="metric-value success">{{ dash.active_bindings }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6" :md="3"><el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t(`${P}.stats.revoked_bindings`) }}</div><div class="metric-value text-muted">{{ dash.revoked_bindings }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6" :md="3"><el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t(`${P}.stats.locked_bindings`) }}</div><div class="metric-value danger">{{ dash.locked_bindings }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6" :md="3"><el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t(`${P}.stats.tpm2_bindings`) }}</div><div class="metric-value">{{ dash.tpm2_bindings }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6" :md="3"><el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t(`${P}.stats.sgx_bindings`) }}</div><div class="metric-value">{{ dash.sgx_bindings }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6" :md="3"><el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t(`${P}.stats.today_verifications`) }}</div><div class="metric-value">{{ dash.today_verifications }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6" :md="3"><el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t(`${P}.stats.failed_today`) }}</div><div class="metric-value danger">{{ dash.failed_today }}</div></el-card></el-col>
        </el-row>

        <!-- ── 绑定列表 ── -->
        <el-card shadow="hover">
            <template #header>
                <div class="card-header">
                    <span><el-icon><Key /></el-icon> {{ t(`${P}.list.title`) }}</span>
                    <div style="display:flex;gap:8px">
                        <el-select v-model="filterStatus" :placeholder="t(`${P}.filters.status`)" clearable size="small" style="width:120px" @change="loadList">
                            <el-option v-for="opt in statusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                        </el-select>
                        <el-select v-model="filterType" :placeholder="t(`${P}.filters.type`)" clearable size="small" style="width:120px" @change="loadList">
                            <el-option v-for="opt in typeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                        </el-select>
                    </div>
                </div>
            </template>
            <el-table :data="bindings" stripe v-loading="listLoading" size="small">
                <el-table-column prop="id" label="ID" width="60" />
                <el-table-column :label="t(`${P}.cols.license`)" width="120"><template #default="{row}">{{ row.license?.license_key || '—' }}</template></el-table-column>
                <el-table-column prop="tpm_manufacturer" :label="t(`${P}.cols.tpm_manufacturer`)" width="100" />
                <el-table-column :label="t(`${P}.cols.type`)" width="80"><template #default="{row}"><el-tag size="small">{{ typeLabel(row.binding_type) }}</el-tag></template></el-table-column>
                <el-table-column :label="t(`${P}.cols.ak_name`)" min-width="140" show-overflow-tooltip><template #default="{row}"><code style="font-size:11px">{{ row.ak_name ? row.ak_name.substring(0, 20)+'...' : '—' }}</code></template></el-table-column>
                <el-table-column :label="t(`${P}.cols.status`)" width="80"><template #default="{row}">
                    <el-tag :type="row.status === 'active' ? 'success' : (row.status === 'locked' ? 'danger' : 'info')" size="small">{{ statusLabel(row.status) }}</el-tag>
                </template></el-table-column>
                <el-table-column :label="t(`${P}.cols.verification_logs`)" width="70" prop="verification_logs_count" />
                <el-table-column :label="t(`${P}.cols.failed_attempts`)" width="70" prop="failed_attempts" />
                <el-table-column :label="t(`${P}.cols.last_verified`)" width="140"><template #default="{row}">{{ row.last_verified_at ? fmtDate(row.last_verified_at) : '—' }}</template></el-table-column>
                <el-table-column :label="t(`${P}.cols.bound_at`)" width="140"><template #default="{row}">{{ fmtDate(row.bound_at) }}</template></el-table-column>
                <el-table-column :label="t(`${D}.col_actions`)" width="200" fixed="right">
                    <template #default="{row}">
                        <el-button size="small" @click="showDetail(row)">{{ t(`${D}.detail`) }}</el-button>
                        <el-button size="small" type="warning" @click="showVerifyDialog(row)" v-if="row.status === 'active'">{{ t(`${P}.actions.verify`) }}</el-button>
                        <el-button size="small" type="primary" @click="unlockBinding(row)" v-if="row.status === 'locked'">{{ t(`${P}.actions.unlock`) }}</el-button>
                        <el-button size="small" type="danger" @click="revokeBinding(row)" v-if="row.status === 'active'">{{ t(`${P}.actions.revoke`) }}</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <el-empty v-if="!bindings.length && !listLoading" :description="t(`${P}.list.empty`)" />
        </el-card>

        <!-- 注册绑定对话框 -->
        <el-dialog v-model="showRegisterForm" :title="t(`${P}.dialogs.register_title`)" width="550px">
            <el-form :model="regForm" label-width="120px">
                <el-form-item :label="t(`${P}.form.license_id`)" :rules="[{required:true}]"><el-input-number v-model="regForm.license_id" :min="1" style="width:100%" /></el-form-item>
                <el-form-item :label="t(`${P}.form.device_id`)"><el-input-number v-model="regForm.device_id" :min="1" style="width:100%" /></el-form-item>
                <el-row :gutter="12">
                    <el-col :span="12"><el-form-item :label="t(`${P}.form.tpm_manufacturer`)"><el-input v-model="regForm.tpm_manufacturer" /></el-form-item></el-col>
                    <el-col :span="12"><el-form-item :label="t(`${P}.form.binding_type`)" :rules="[{required:true}]">
                        <el-select v-model="regForm.binding_type" style="width:100%">
                            <el-option v-for="opt in typeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                        </el-select>
                    </el-form-item></el-col>
                </el-row>
                <el-form-item :label="t(`${P}.form.ak_public_key`)"><el-input v-model="regForm.ak_public_key" type="textarea" :rows="2" /></el-form-item>
                <el-form-item :label="t(`${P}.form.ek_certificate`)"><el-input v-model="regForm.ek_certificate" type="textarea" :rows="2" /></el-form-item>
            </el-form>
            <template #footer><el-button @click="showRegisterForm = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="registerBinding" :loading="saving">{{ t(`${P}.form.register`) }}</el-button></template>
        </el-dialog>

        <!-- 详情对话框 -->
        <el-dialog v-model="showDetailDialog" :title="t(`${P}.dialogs.detail_title`, { id: detail?.id || '' })" width="750px" top="5vh">
            <template v-if="detail">
                <el-descriptions :column="2" border size="small">
                    <el-descriptions-item :label="t(`${P}.cols.license`)">{{ detail.license?.license_key }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.detail.binding_type`)">{{ typeLabel(detail.binding_type) }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.cols.tpm_manufacturer`)">{{ detail.tpm_manufacturer || '—' }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.detail.tpm_version`)">{{ detail.tpm_version || '—' }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.cols.status`)"><el-tag :type="detail.status === 'active' ? 'success' : (detail.status === 'locked' ? 'danger' : 'info')" size="small">{{ statusLabel(detail.status) }}</el-tag></el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.detail.failed_attempts`)">{{ t(`${P}.detail.failed_attempts_fmt`, { count: detail.failed_attempts, max: maxAttempts }) }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.detail.last_verified`)">{{ detail.last_verified_at ? fmtDate(detail.last_verified_at) : '—' }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.detail.last_attestation`)">{{ detail.last_attestation_at ? fmtDate(detail.last_attestation_at) : '—' }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.detail.bound_ip`)">{{ detail.bound_ip || '—' }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.detail.bound_at`)">{{ fmtDate(detail.bound_at) }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.cols.ak_name`)" :span="2"><code style="font-size:11px">{{ detail.ak_name || '—' }}</code></el-descriptions-item>
                </el-descriptions>
                <!-- 验证日志 -->
                <div class="detail-section" v-if="detail.verification_logs?.length">
                    <h4>{{ t(`${P}.detail.recent_verifications`) }}</h4>
                    <el-table :data="detail.verification_logs" size="small" max-height="200">
                        <el-table-column :label="t(`${P}.verify_log.result`)" width="80"><template #default="{row}"><el-tag :type="row.result === 'passed' ? 'success' : 'danger'" size="small">{{ resultLabel(row.result) }}</el-tag></template></el-table-column>
                        <el-table-column prop="duration_ms" :label="t(`${P}.verify_log.duration`)" width="80" />
                        <el-table-column prop="error_message" :label="t(`${P}.verify_log.error`)" min-width="200" show-overflow-tooltip />
                        <el-table-column :label="t(`${P}.verify_log.time`)" width="150"><template #default="{row}">{{ fmtDate(row.verified_at) }}</template></el-table-column>
                    </el-table>
                </div>
            </template>
            <template #footer>
                <el-button @click="showDetailDialog = false">{{ t('actions.close') }}</el-button>
            </template>
        </el-dialog>

        <!-- 验证对话框 -->
        <el-dialog v-model="showVerifyDialog_" :title="t(`${P}.dialogs.verify_title`)" width="450px">
            <el-form :model="verifyForm" label-width="100px">
                <el-form-item :label="t(`${P}.form.nonce`)" :rules="[{required:true}]"><el-input v-model="verifyForm.nonce" /></el-form-item>
                <el-form-item :label="t(`${P}.form.timestamp`)"><el-input-number v-model="verifyForm.timestamp" :min="0" style="width:100%" /></el-form-item>
            </el-form>
            <template #footer><el-button @click="showVerifyDialog_ = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="submitVerify" :loading="verifyLoading">{{ t(`${P}.actions.verify`) }}</el-button></template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh, Key } from '@element-plus/icons-vue';
import tpmBindingApi from '@/api/tpmBinding';

const P = 'tpm_binding_page';
const D = 'devices_page';
const { t, locale } = useI18n();

const STATUS_KEYS = ['active', 'revoked', 'locked'];
const TYPE_KEYS = ['tpm2', 'sgx', 'hybrid'];
const RESULT_KEYS = ['passed', 'failed'];

const statusOptions = computed(() => STATUS_KEYS.map((value) => ({
    value,
    label: t(`${P}.status.${value}`),
})));

const typeOptions = computed(() => TYPE_KEYS.map((value) => ({
    value,
    label: t(`${P}.binding_types.${value}`),
})));

function statusLabel(status) {
    return t(`${P}.status.${status}`, status);
}

function typeLabel(type) {
    return t(`${P}.binding_types.${type}`, type);
}

function resultLabel(result) {
    return t(`${P}.results.${result}`, result);
}

const loading = ref(false);
const saving = ref(false);
const listLoading = ref(false);
const verifyLoading = ref(false);
const dash = reactive({ total_bindings: 0, active_bindings: 0, revoked_bindings: 0, locked_bindings: 0, tpm2_bindings: 0, sgx_bindings: 0, hybrid_bindings: 0, today_verifications: 0, failed_today: 0, tpm_available_devices: 0, hardware_bound_devices: 0 });
const bindings = ref([]);
const detail = ref(null);
const filterStatus = ref('');
const filterType = ref('');
const maxAttempts = ref(5);

// Dialogs
const showRegisterForm = ref(false);
const showDetailDialog = ref(false);
const showVerifyDialog_ = ref(false);
const verifyTargetId = ref(null);
const regForm = reactive({ license_id: null, device_id: null, tpm_manufacturer: '', binding_type: 'tpm2', ak_public_key: '', ek_certificate: '' });
const verifyForm = reactive({ nonce: '', timestamp: 0 });

onMounted(loadAll);

async function loadAll() {
    loading.value = true;
    try { await Promise.all([loadDashboard(), loadList()]); } finally { loading.value = false; }
}

async function loadDashboard() {
    try { const r = await tpmBindingApi.dashboard(); Object.assign(dash, r.data?.data || {}); } catch {}
}
async function loadList() {
    listLoading.value = true;
    try {
        const params = { per_page: 50 };
        if (filterStatus.value) params.status = filterStatus.value;
        if (filterType.value) params.binding_type = filterType.value;
        const r = await tpmBindingApi.listBindings(params);
        bindings.value = r.data?.data?.items || [];
    } finally { listLoading.value = false; }
}

async function registerBinding() {
    saving.value = true;
    try {
        await tpmBindingApi.registerBinding(regForm);
        ElMessage.success(t(`${P}.messages.register_success`)); showRegisterForm.value = false; loadAll();
    } catch (e) { ElMessage.error(e.response?.data?.message || t(`${P}.messages.register_failed`)); }
    finally { saving.value = false; }
}

async function showDetail(row) {
    try {
        const r = await tpmBindingApi.showBinding(row.id);
        detail.value = r.data?.data;
        showDetailDialog.value = true;
    } catch { ElMessage.error(t(`${P}.messages.detail_load_failed`)); }
}

function showVerifyDialog(row) {
    verifyTargetId.value = row.id;
    verifyForm.nonce = Array.from({ length: 32 }, () => Math.random().toString(16)[2]).join('');
    verifyForm.timestamp = Math.floor(Date.now() / 1000);
    showVerifyDialog_.value = true;
}

async function submitVerify() {
    verifyLoading.value = true;
    try {
        const r = await tpmBindingApi.verifyBinding(verifyTargetId.value, verifyForm);
        if (r.data?.data?.result === 'passed') ElMessage.success(t(`${P}.messages.verify_passed`));
        else ElMessage.error(t(`${P}.messages.verify_failed_with_error`, { error: r.data?.data?.error || '' }));
        showVerifyDialog_.value = false; loadList();
    } catch { ElMessage.error(t(`${P}.messages.verify_failed`)); }
    finally { verifyLoading.value = false; }
}

async function unlockBinding(row) {
    await tpmBindingApi.unlockBinding(row.id);
    ElMessage.success(t(`${P}.messages.unlocked`)); loadList();
}

async function revokeBinding(row) {
    try {
        const { value } = await ElMessageBox.prompt(t(`${P}.dialogs.revoke_prompt`), t(`${P}.dialogs.revoke_title`));
        await tpmBindingApi.revokeBinding(row.id, value);
        ElMessage.success(t(`${P}.messages.revoked`)); loadList();
    } catch { if (value !== null) ElMessage.error(t(`${P}.messages.revoke_failed`)); }
}

function fmtDate(tVal) {
    if (!tVal) return '—';
    const loc = locale.value === 'zh_CN' ? 'zh-CN' : 'en-US';
    return new Date(tVal).toLocaleString(loc, { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}
</script>

<style scoped>
.tpm-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.page-header h2 { margin: 0 0 4px; font-size: 20px; }
.header-actions { display: flex; gap: 8px; }
.mb-4 { margin-bottom: 16px; }
.metric-card { padding: 8px; }
.metric-card .metric-label { font-size: 12px; color: #909399; margin-bottom: 4px; }
.metric-card .metric-value { font-size: 20px; font-weight: 700; }
.success { color: #67c23a; } .danger { color: #f56c6c; }
.text-muted { color: #c0c4cc; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.detail-section { margin-top: 16px; }
.detail-section h4 { margin: 0 0 8px; font-size: 14px; }
</style>
