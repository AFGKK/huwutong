<template>
    <div class="public-key-page">
        <div class="page-header">
            <div>
                <h2>{{ t(`${P}.title`) }}</h2>
                <p class="text-muted">{{ t(`${P}.subtitle`) }}</p>
            </div>
            <div class="header-actions">
                <el-button @click="loadAll" :loading="loading" :icon="Refresh">{{ t(`${P}.refresh`) }}</el-button>
                <el-button type="primary" @click="showCreateForm = true" :icon="Plus">{{ t(`${P}.create_key`) }}</el-button>
                <el-button @click="testSigningDialog" :icon="CircleCheck">{{ t(`${P}.signing_test`) }}</el-button>
            </div>
        </div>

        <!-- 概览 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :xs="12" :sm="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t(`${P}.stats.total_versions`) }}</div><div class="metric-value">{{ stats.total_versions }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t(`${P}.stats.active_versions`) }}</div><div class="metric-value success">{{ stats.active_versions }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t(`${P}.stats.compat_window`) }}</div><div class="metric-value warning">{{ stats.compat_mode_versions || 0 }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t(`${P}.stats.revoked`) }}</div><div class="metric-value danger">{{ stats.revoked_versions }}</div></el-card></el-col>
        </el-row>

        <!-- 轮换提醒 -->
        <el-alert v-if="rotationAlert" :title="rotationAlert" type="warning" show-icon :closable="true" class="mb-4" />

        <!-- 版本列表 -->
        <el-card shadow="hover">
            <template #header><span><el-icon><Key /></el-icon> {{ t(`${P}.version_list`) }}</span></template>
            <el-table :data="versions" stripe v-loading="listLoading" size="small">
                <el-table-column prop="key_version" :label="colLabels.version" width="70" />
                <el-table-column prop="algorithm" :label="colLabels.algorithm" width="90" />
                <el-table-column prop="public_key_preview" :label="colLabels.public_key" min-width="200" show-overflow-tooltip>
                    <template #default="{row}"><code style="font-size:11px">{{ row.public_key?.substring(0, 40) }}...</code></template>
                </el-table-column>
                <el-table-column :label="colLabels.status" width="100">
                    <template #default="{row}">
                        <el-tag v-if="row.is_active && !row.is_revoked" type="success" size="small">{{ statusLabels.active }}</el-tag>
                        <el-tag v-else-if="row.is_revoked" type="danger" size="small">{{ statusLabels.revoked }}</el-tag>
                        <el-tag v-else-if="row.is_compat_mode" type="warning" size="small">{{ statusLabels.compat_mode }}</el-tag>
                        <el-tag v-else type="info" size="small">{{ statusLabels.expired }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="colLabels.compat_window" width="90">
                    <template #default="{row}">
                        <el-tag v-if="row.is_compat_mode" type="warning" size="small">{{ flagLabels.yes }}</el-tag>
                        <span v-else class="text-muted">{{ emDash }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="colLabels.activated_at" width="150"><template #default="{row}">{{ fmtTime(row.activated_at) }}</template></el-table-column>
                <el-table-column :label="colLabels.expires_at" width="150"><template #default="{row}">{{ fmtTime(row.expires_at) }}</template></el-table-column>
                <el-table-column :label="colLabels.actions" width="160" fixed="right">
                    <template #default="{row}">
                        <el-button size="small" @click="showDetail(row)">{{ t(`${P}.detail`) }}</el-button>
                        <el-button v-if="!row.is_revoked && !row.is_active" size="small" type="warning" @click="showRevokeDialog(row)">{{ t('licenses_page.revoke') }}</el-button>
                        <el-button v-if="row.is_revoked" size="small" disabled>{{ statusLabels.revoked }}</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <el-empty v-if="!versions.length && !listLoading" :description="t(`${P}.empty`)" />
        </el-card>

        <!-- 创建对话框 -->
        <el-dialog v-model="showCreateForm" :title="t(`${P}.create_dialog.title`)" width="500px">
            <el-form :model="createForm" label-width="120px">
                <el-form-item :label="t(`${P}.create_dialog.algorithm`)" :rules="[{required:true}]">
                    <el-select v-model="createForm.algorithm" style="width:100%">
                        <el-option label="Ed25519" value="ED25519" />
                        <el-option label="RSA 2048" value="RSA2048" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t(`${P}.create_dialog.public_key_b64`)"><el-input v-model="createForm.public_key" type="textarea" :rows="4" :placeholder="t(`${P}.create_dialog.public_key_b64_ph`)" /></el-form-item>
                <el-form-item :label="t(`${P}.create_dialog.pem`)"><el-input v-model="createForm.public_key_pem" type="textarea" :rows="3" :placeholder="t(`${P}.create_dialog.optional_ph`)" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showCreateForm = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="submitCreate" :loading="saving">{{ t('actions.create') }}</el-button>
            </template>
        </el-dialog>

        <!-- 吊销对话框 -->
        <el-dialog v-model="showRevokeForm" :title="t(`${P}.revoke_dialog.title`)" width="450px">
            <p>{{ t(`${P}.revoke_dialog.confirm`, { version: revokeTarget?.key_version, algorithm: revokeTarget?.algorithm }) }}</p>
            <el-form :model="revokeForm">
                <el-form-item :label="t(`${P}.revoke_dialog.reason`)"><el-input v-model="revokeForm.reason" type="textarea" :rows="2" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showRevokeForm = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="danger" @click="submitRevoke" :loading="revoking">{{ t(`${P}.revoke_dialog.confirm_btn`) }}</el-button>
            </template>
        </el-dialog>

        <!-- 详情对话框 -->
        <el-dialog v-model="showDetailForm" :title="t(`${P}.detail_dialog.title`, { version: detail?.key_version || '' })" width="650px">
            <el-descriptions :column="2" border size="small" v-if="detail">
                <el-descriptions-item :label="detailLabels.version">{{ detail.key_version }}</el-descriptions-item>
                <el-descriptions-item :label="detailLabels.algorithm">{{ detail.algorithm }}</el-descriptions-item>
                <el-descriptions-item :label="detailLabels.status">
                    <el-tag :type="detail.is_active ? 'success' : (detail.is_revoked ? 'danger' : 'info')" size="small">
                        {{ detailStatusLabel }}
                    </el-tag>
                </el-descriptions-item>
                <el-descriptions-item :label="detailLabels.compat_mode">
                    <el-tag v-if="detail.is_compat_mode" type="warning" size="small">{{ flagLabels.yes }}</el-tag>
                    <span v-else>{{ flagLabels.no }}</span>
                </el-descriptions-item>
                <el-descriptions-item :label="detailLabels.activated_at">{{ fmtTime(detail.activated_at) }}</el-descriptions-item>
                <el-descriptions-item :label="detailLabels.expires_at">{{ fmtTime(detail.expires_at) }}</el-descriptions-item>
                <el-descriptions-item :label="detailLabels.revoked_at">{{ fmtTime(detail.revoked_at) || emDash }}</el-descriptions-item>
                <el-descriptions-item :label="detailLabels.revoke_reason">{{ detail.revoke_reason || emDash }}</el-descriptions-item>
                <el-descriptions-item :label="detailLabels.public_key" :span="2"><code style="font-size:11px;word-break:break-all">{{ detail.public_key }}</code></el-descriptions-item>
            </el-descriptions>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh, Plus, CircleCheck, Key } from '@element-plus/icons-vue';
import publicKeyApi from '@/api/publicKey';

const P = 'public_key_page';
const { t, locale } = useI18n();
const emDash = '—';

const loading = ref(false);
const saving = ref(false);
const revoking = ref(false);
const listLoading = ref(false);
const showCreateForm = ref(false);
const showRevokeForm = ref(false);
const showDetailForm = ref(false);
const revokeTarget = ref(null);
const detail = ref(null);

const stats = reactive({ total_versions: 0, active_versions: 0, revoked_versions: 0, compat_mode_versions: 0 });
const versions = ref([]);
const rotationAlert = ref('');
const createForm = reactive({ algorithm: 'ED25519', public_key: '', public_key_pem: '' });
const revokeForm = reactive({ reason: '' });

const colLabels = computed(() => ({
    version: t(`${P}.cols.version`),
    algorithm: t('license_files_page.col_algorithm'),
    public_key: t('offline_page.label_public_key'),
    status: t(`${P}.cols.status`),
    compat_window: t(`${P}.cols.compat_window`),
    activated_at: t(`${P}.cols.activated_at`),
    expires_at: t('licenses_page.col_expires_at'),
    actions: t('licenses_page.col_actions'),
}));

const statusLabels = computed(() => ({
    active: t(`${P}.status.active`),
    revoked: t(`${P}.status.revoked`),
    compat_mode: t(`${P}.status.compat_mode`),
    expired: t(`${P}.status.expired`),
    inactive: t(`${P}.status.inactive`),
}));

const flagLabels = computed(() => ({
    yes: t(`${P}.flags.yes`),
    no: t(`${P}.flags.no`),
}));

const detailLabels = computed(() => ({
    version: t(`${P}.cols.version`),
    algorithm: t('license_files_page.col_algorithm'),
    status: t(`${P}.cols.status`),
    compat_mode: t(`${P}.detail_dialog.compat_mode`),
    activated_at: t(`${P}.cols.activated_at`),
    expires_at: t('licenses_page.col_expires_at'),
    revoked_at: t(`${P}.detail_dialog.revoked_at`),
    revoke_reason: t(`${P}.revoke_dialog.reason`),
    public_key: t('offline_page.label_public_key'),
}));

const detailStatusLabel = computed(() => {
    if (!detail.value) return '';
    if (detail.value.is_active) return statusLabels.value.active;
    if (detail.value.is_revoked) return statusLabels.value.revoked;
    return statusLabels.value.inactive;
});

onMounted(loadAll);

async function loadAll() {
    loading.value = true;
    try { await Promise.all([loadStats(), loadVersions(), loadRotationCheck()]); } finally { loading.value = false; }
}
async function loadStats() {
    try { const r = await publicKeyApi.stats(); Object.assign(stats, r.data?.data || {}); } catch {}
}
async function loadVersions() {
    listLoading.value = true;
    try { const r = await publicKeyApi.index(); versions.value = r.data?.data || []; } finally { listLoading.value = false; }
}
async function loadRotationCheck() {
    try {
        const r = await publicKeyApi.rotationCheck();
        const d = r.data?.data;
        if (d?.needs_rotation) rotationAlert.value = t(`${P}.rotation_alert`, { reason: d.reason });
        else rotationAlert.value = '';
    } catch {}
}

async function submitCreate() {
    saving.value = true;
    try {
        await publicKeyApi.store(createForm);
        ElMessage.success(t(`${P}.messages.create_ok`)); showCreateForm.value = false; loadAll();
    } catch { ElMessage.error(t(`${P}.messages.create_fail`)); } finally { saving.value = false; }
}

function showRevokeDialog(row) {
    revokeTarget.value = row;
    revokeForm.reason = '';
    showRevokeForm.value = true;
}
async function submitRevoke() {
    revoking.value = true;
    try {
        await publicKeyApi.revoke(revokeTarget.value.key_version, { reason: revokeForm.reason });
        ElMessage.success(t(`${P}.messages.revoke_ok`)); showRevokeForm.value = false; loadAll();
    } catch { ElMessage.error(t(`${P}.messages.revoke_fail`)); } finally { revoking.value = false; }
}

async function showDetail(row) {
    try {
        const r = await publicKeyApi.show(row.key_version);
        detail.value = r.data?.data; showDetailForm.value = true;
    } catch { ElMessage.error(t('messages.load_failed')); }
}

async function testSigningDialog() {
    const { value } = await ElMessageBox.prompt(t(`${P}.messages.signing_test_prompt`), t(`${P}.messages.signing_test_title`));
    if (value) {
        try {
            const r = await publicKeyApi.testSigning({ data: value });
            ElMessage.success(t(`${P}.messages.signing_test_ok`, { result: r.data?.data?.result || t('messages.success') }));
        } catch { ElMessage.error(t(`${P}.messages.signing_test_fail`)); }
    }
}

function fmtTime(time) {
    if (!time) return emDash;
    return new Date(time).toLocaleString(locale.value === 'zh_CN' ? 'zh-CN' : 'en-US', { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}
</script>

<style scoped>
.public-key-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.page-header h2 { margin: 0 0 4px; font-size: 20px; }
.header-actions { display: flex; gap: 8px; }
.mb-4 { margin-bottom: 16px; }
.metric-card { padding: 8px; }
.metric-card .metric-label { font-size: 12px; color: #909399; margin-bottom: 4px; }
.metric-card .metric-value { font-size: 20px; font-weight: 700; }
.success { color: #67c23a; } .warning { color: #e6a23c; } .danger { color: #f56c6c; }
.text-muted { color: #c0c4cc; }
</style>
