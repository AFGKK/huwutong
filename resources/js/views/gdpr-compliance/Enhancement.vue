<template>
    <div class="gdpr-page">
        <div class="page-header">
            <h2>{{ t('gdpr_enhancement_page.title') }}</h2>
            <el-button-group>
                <el-button type="primary" @click="activeTab = 'overview'">{{ t('gdpr_enhancement_page.tabs.overview') }}</el-button>
                <el-button @click="activeTab = 'requests'">{{ t('gdpr_enhancement_page.tabs.requests') }}</el-button>
                <el-button @click="activeTab = 'dpa'">{{ t('gdpr_enhancement_page.tabs.dpa') }}</el-button>
                <el-button @click="activeTab = 'retention'">{{ t('gdpr_enhancement_page.tabs.retention') }}</el-button>
            </el-button-group>
        </div>

        <!-- ─── 概览 ─── -->
        <div v-if="activeTab === 'overview'">
            <el-row :gutter="16" class="mb-4">
                <el-col :span="8" v-for="s in overviewStats" :key="s.label">
                    <el-card shadow="hover" class="stat-card">
                        <div class="stat-value" :style="{ color: s.color }">{{ s.value }}</div>
                        <div class="stat-label">{{ s.label }}</div>
                    </el-card>
                </el-col>
            </el-row>

            <el-card class="mb-4">
                <template #header><span>{{ t('gdpr_enhancement_page.checklist.title') }}</span></template>
                <el-table :data="checklist" stripe>
                    <el-table-column prop="item" :label="t('gdpr_enhancement_page.checklist.col_item')" min-width="220" />
                    <el-table-column prop="status" :label="t('gdpr_enhancement_page.checklist.col_status')" width="100">
                        <template #default="{ row }">
                            <el-tag :type="row.status === 'done' ? 'success' : row.status === 'partial' ? 'warning' : 'danger'" size="small">
                                {{ checklistStatusLabel(row.status) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="note" :label="t('gdpr_enhancement_page.checklist.col_note')" min-width="280" />
                </el-table>
            </el-card>
        </div>

        <!-- ─── 数据主体请求 ─── -->
        <div v-if="activeTab === 'requests'">
            <el-card>
                <template #header>
                    <div class="card-header">
                        <span>{{ t('gdpr_enhancement_page.requests.title') }}</span>
                        <el-button size="small" @click="openNewRequest">{{ t('gdpr_enhancement_page.requests.new_btn') }}</el-button>
                    </div>
                </template>
                <el-table :data="requests" stripe v-loading="requestsLoading">
                    <el-table-column prop="id" label="ID" width="60" />
                    <el-table-column prop="user.email" :label="t('gdpr_enhancement_page.requests.col_user')" min-width="150" />
                    <el-table-column :label="t('gdpr_enhancement_page.requests.col_type')" width="120">
                        <template #default="{ row }">{{ requestTypeLabel(row.type) }}</template>
                    </el-table-column>
                    <el-table-column :label="t('gdpr_enhancement_page.requests.col_status')" width="100">
                        <template #default="{ row }">
                            <el-tag :type="statusType(row.status)" size="small">{{ requestStatusLabel(row.status) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="created_at" :label="t('gdpr_enhancement_page.requests.col_submitted_at')" width="170" />
                    <el-table-column :label="t('gdpr_enhancement_page.requests.col_actions')" width="120">
                        <template #default="{ row }">
                            <el-button size="small" v-if="row.status === 'pending'" @click="processRequest(row)">{{ t('gdpr_enhancement_page.requests.process') }}</el-button>
                            <el-button size="small" v-if="row.output_file" @click="downloadExport(row)">{{ t('actions.download') }}</el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </el-card>
        </div>

        <!-- ─── DPA 协议管理 ─── -->
        <div v-if="activeTab === 'dpa'">
            <el-card>
                <template #header>
                    <div class="card-header">
                        <span>{{ t('gdpr_enhancement_page.dpa.title') }}</span>
                        <el-button size="small" type="primary" @click="openNewDpa">{{ t('gdpr_enhancement_page.dpa.new_btn') }}</el-button>
                    </div>
                </template>

                <el-table :data="dpas" stripe v-loading="dpasLoading">
                    <el-table-column prop="id" label="ID" width="60" />
                    <el-table-column prop="title" :label="t('gdpr_enhancement_page.dpa.col_title')" min-width="200" />
                    <el-table-column prop="version" :label="t('gdpr_enhancement_page.dpa.col_version')" width="80" />
                    <el-table-column :label="t('gdpr_enhancement_page.dpa.col_status')" width="100">
                        <template #default="{ row }">
                            <el-tag :type="row.status === 'published' ? 'success' : row.status === 'draft' ? 'info' : 'danger'" size="small">
                                {{ dpaStatusLabel(row.status) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="signatures_count" :label="t('gdpr_enhancement_page.dpa.col_signatures')" width="80" align="center" />
                    <el-table-column :label="t('gdpr_enhancement_page.dpa.col_actions')" width="200">
                        <template #default="{ row }">
                            <el-button size="small" @click="previewDpa(row)">{{ t('gdpr_enhancement_page.dpa.preview') }}</el-button>
                            <el-button size="small" v-if="row.status === 'draft'" @click="publishDpaRow(row)">{{ t('gdpr_enhancement_page.dpa.publish') }}</el-button>
                            <el-button size="small" @click="editDpa(row)">{{ t('actions.edit') }}</el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </el-card>
        </div>

        <!-- ─── 留存策略 ─── -->
        <div v-if="activeTab === 'retention'">
            <el-card>
                <template #header><span>{{ t('gdpr_enhancement_page.retention.title') }}</span></template>
                <el-table :data="retentionPolicies" stripe>
                    <el-table-column prop="category" :label="t('gdpr_enhancement_page.retention.col_category')" min-width="200" />
                    <el-table-column prop="retention_days" :label="t('gdpr_enhancement_page.retention.col_days')" width="120" align="center" />
                    <el-table-column prop="action" :label="t('gdpr_enhancement_page.retention.col_action')" width="120" />
                    <el-table-column prop="legal_basis" :label="t('gdpr_enhancement_page.retention.col_legal_basis')" min-width="200" />
                </el-table>
            </el-card>
        </div>

        <!-- 新建请求弹窗 -->
        <el-dialog v-model="showNewRequest" :title="t('gdpr_enhancement_page.requests.dialog_title')" width="450px">
            <el-form label-width="100px">
                <el-form-item :label="t('gdpr_enhancement_page.requests.user_id_label')">
                    <el-input-number v-model="newRequest.userId" :min="1" />
                </el-form-item>
                <el-form-item :label="t('gdpr_enhancement_page.requests.type_label')">
                    <el-select v-model="newRequest.type" style="width:100%">
                        <el-option v-for="opt in requestTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('gdpr_enhancement_page.requests.reason_label')">
                    <el-input v-model="newRequest.reason" type="textarea" :rows="3" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showNewRequest = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="submitRequest" :loading="submitLoading">{{ t('actions.submit') }}</el-button>
            </template>
        </el-dialog>

        <!-- 新建 DPA 弹窗 -->
        <el-dialog v-model="showNewDpa" :title="t('gdpr_enhancement_page.dpa.dialog_title')" width="650px">
            <el-form label-width="100px">
                <el-form-item :label="t('gdpr_enhancement_page.dpa.title_label')">
                    <el-input v-model="dpaForm.title" :placeholder="t('gdpr_enhancement_page.dpa.title_ph')" />
                </el-form-item>
                <el-form-item :label="t('gdpr_enhancement_page.dpa.version_label')">
                    <el-input v-model="dpaForm.version" placeholder="1.0.0" style="width:120px" />
                </el-form-item>
                <el-form-item :label="t('gdpr_enhancement_page.dpa.content_label')">
                    <el-input v-model="dpaForm.content" type="textarea" :rows="15" />
                </el-form-item>
            </el-form>
            <div class="dpa-preview" v-if="dpaForm.content">
                <div class="preview-label">{{ t('gdpr_enhancement_page.dpa.preview_label') }}</div>
                <div class="preview-content">{{ dpaForm.content.substring(0, 300) }}...</div>
            </div>
            <template #footer>
                <el-button @click="showNewDpa = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="saveDpa" :loading="dpaLoading">{{ t('gdpr_enhancement_page.dpa.save_draft') }}</el-button>
            </template>
        </el-dialog>

        <!-- DPA 预览弹窗 -->
        <el-dialog v-model="showDpaPreview" :title="previewDpaData?.title || 'DPA'" width="700px">
            <div class="dpa-meta">
                <el-tag size="small">v{{ previewDpaData?.version }}</el-tag>
                <el-tag size="small" :type="previewDpaData?.status === 'published' ? 'success' : 'info'" class="ml-2">
                    {{ dpaStatusLabel(previewDpaData?.status) }}
                </el-tag>
                <span class="ml-2 text-secondary">{{ t('gdpr_enhancement_page.dpa.signatures_count', { n: previewDpaData?.signatures_count || 0 }) }}</span>
            </div>
            <div class="dpa-body">
                <pre>{{ previewDpaData?.content }}</pre>
            </div>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import apiClient from '@/api/client';

const { t } = useI18n();

const activeTab = ref('overview');
const requests = ref([]);
const requestsLoading = ref(false);
const dpas = ref([]);
const dpasLoading = ref(false);
const submitLoading = ref(false);
const dpaLoading = ref(false);
const showNewRequest = ref(false);
const showNewDpa = ref(false);
const showDpaPreview = ref(false);
const previewDpaData = ref({});

const newRequest = ref({ userId: 1, type: 'access', reason: '' });
const dpaForm = ref({ title: '', version: '1.0.0', content: '' });

const statValues = ref({
    pending: '0',
    signedDpa: '0',
    exports: '0',
});

const checklistMeta = [
    { id: 'cookie_banner', status: 'done' },
    { id: 'privacy_terms', status: 'done' },
    { id: 'access_request', status: 'done' },
    { id: 'erasure', status: 'done' },
    { id: 'portability', status: 'done' },
    { id: 'dpa', status: 'partial' },
    { id: 'retention', status: 'partial' },
    { id: 'breach_notify', status: 'partial' },
    { id: 'dpia', status: 'partial' },
    { id: 'dpo', status: 'done' },
];

const checklist = computed(() => checklistMeta.map(({ id, status }) => ({
    item: t(`gdpr_enhancement_page.checklist.${id}_item`),
    status,
    note: t(`gdpr_enhancement_page.checklist.${id}_note`),
})));

const checklistDoneCount = computed(() => checklistMeta.filter((item) => item.status === 'done').length);

const overviewStats = computed(() => [
    { label: t('gdpr_enhancement_page.stats.pending_requests'), value: statValues.value.pending, color: '#e6a23c' },
    { label: t('gdpr_enhancement_page.stats.signed_dpa'), value: statValues.value.signedDpa, color: '#67c23a' },
    { label: t('gdpr_enhancement_page.stats.checklist_progress'), value: `${checklistDoneCount.value}/${checklistMeta.length}`, color: '#0f172a' },
    { label: t('gdpr_enhancement_page.stats.export_count'), value: statValues.value.exports, color: '#909399' },
]);

const requestTypeOptions = computed(() => [
    { label: t('gdpr_enhancement_page.requests.type_access'), value: 'access' },
    { label: t('gdpr_enhancement_page.requests.type_portability'), value: 'portability' },
    { label: t('gdpr_enhancement_page.requests.type_erasure'), value: 'erasure' },
    { label: t('gdpr_enhancement_page.requests.type_restrict'), value: 'restrict' },
    { label: t('gdpr_enhancement_page.requests.type_rectify'), value: 'rectify' },
]);

const retentionPolicies = computed(() => [
    { category: t('gdpr_enhancement_page.retention.cat_audit_log'), retention_days: 365, action: t('gdpr_enhancement_page.retention.action_archive'), legal_basis: t('gdpr_enhancement_page.retention.basis_storage_limit') },
    { category: t('gdpr_enhancement_page.retention.cat_user_account'), retention_days: 1095, action: t('gdpr_enhancement_page.retention.action_anonymize'), legal_basis: t('gdpr_enhancement_page.retention.basis_contract') },
    { category: t('gdpr_enhancement_page.retention.cat_license_activation'), retention_days: 730, action: t('gdpr_enhancement_page.retention.action_anonymize'), legal_basis: t('gdpr_enhancement_page.retention.basis_legal_obligation') },
    { category: t('gdpr_enhancement_page.retention.cat_deleted_account'), retention_days: 90, action: t('gdpr_enhancement_page.retention.action_purge'), legal_basis: t('gdpr_enhancement_page.retention.basis_erasure_buffer') },
    { category: t('gdpr_enhancement_page.retention.cat_payment'), retention_days: 2555, action: t('gdpr_enhancement_page.retention.action_archive'), legal_basis: t('gdpr_enhancement_page.retention.basis_tax') },
    { category: t('gdpr_enhancement_page.retention.cat_cookie_prefs'), retention_days: 365, action: t('gdpr_enhancement_page.retention.action_purge'), legal_basis: t('gdpr_enhancement_page.retention.basis_consent') },
    { category: t('gdpr_enhancement_page.retention.cat_session'), retention_days: 30, action: t('gdpr_enhancement_page.retention.action_purge'), legal_basis: t('gdpr_enhancement_page.retention.basis_storage_limit') },
]);

function checklistStatusLabel(status) {
    if (status === 'done') return t('gdpr_enhancement_page.checklist.status_done');
    if (status === 'partial') return t('gdpr_enhancement_page.checklist.status_partial');
    return t('gdpr_enhancement_page.checklist.status_missing');
}

function requestTypeLabel(type) {
    const map = {
        access: t('gdpr_enhancement_page.requests.type_access'),
        portability: t('gdpr_enhancement_page.requests.type_portability'),
        erasure: t('gdpr_enhancement_page.requests.type_erasure'),
        restrict: t('gdpr_enhancement_page.requests.type_restrict'),
        rectify: t('gdpr_enhancement_page.requests.type_rectify'),
    };
    return map[type] || type;
}

function requestStatusLabel(status) {
    const map = {
        pending: t('gdpr_enhancement_page.requests.status_pending'),
        processing: t('gdpr_enhancement_page.requests.status_processing'),
        completed: t('gdpr_enhancement_page.requests.status_completed'),
        failed: t('gdpr_enhancement_page.requests.status_failed'),
    };
    return map[status] || status;
}

function dpaStatusLabel(status) {
    if (status === 'published') return t('gdpr_enhancement_page.dpa.status_published');
    if (status === 'draft') return t('gdpr_enhancement_page.dpa.status_draft');
    if (status === 'archived') return t('gdpr_enhancement_page.dpa.status_archived');
    return status || '';
}

function statusType(s) { return s === 'completed' ? 'success' : s === 'pending' ? 'warning' : s === 'failed' ? 'danger' : 'info'; }

function openNewRequest() {
    newRequest.value = { userId: 1, type: 'access', reason: '' };
    showNewRequest.value = true;
}

function openNewDpa() {
    dpaForm.value = { title: t('gdpr_enhancement_page.dpa.default_title'), version: '1.0.0', content: '' };
    showNewDpa.value = true;
}

async function loadRequests() {
    requestsLoading.value = true;
    try {
        const { data } = await apiClient.get('/gdpr/requests');
        requests.value = data?.data || [];
        statValues.value.pending = String((data?.data || []).filter((r) => r.status === 'pending').length);
    } catch { requests.value = []; }
    finally { requestsLoading.value = false; }
}

async function loadDpas() {
    dpasLoading.value = true;
    try {
        const { data } = await apiClient.get('/gdpr/dpas');
        dpas.value = data?.data || [];
        statValues.value.signedDpa = String(dpas.value.reduce((s, d) => s + (d.signatures_count || 0), 0));
    } catch { dpas.value = []; }
    finally { dpasLoading.value = false; }
}

async function submitRequest() {
    submitLoading.value = true;
    try {
        await apiClient.post('/gdpr/requests', newRequest.value);
        ElMessage.success(t('gdpr_enhancement_page.requests.submit_ok'));
        showNewRequest.value = false;
        loadRequests();
    } catch { ElMessage.error(t('gdpr_enhancement_page.requests.submit_fail')); }
    finally { submitLoading.value = false; }
}

async function processRequest(row) {
    try {
        await apiClient.post(`/gdpr/requests/${row.id}/process`);
        ElMessage.success(t('gdpr_enhancement_page.requests.process_ok'));
        loadRequests();
    } catch { ElMessage.error(t('gdpr_enhancement_page.requests.process_fail')); }
}

function downloadExport(row) {
    window.open(`/api/gdpr/requests/${row.id}/download`, '_blank');
}

async function saveDpa() {
    dpaLoading.value = true;
    try {
        await apiClient.post('/gdpr/dpas', dpaForm.value);
        ElMessage.success(t('gdpr_enhancement_page.dpa.save_ok'));
        showNewDpa.value = false;
        loadDpas();
    } catch { ElMessage.error(t('gdpr_enhancement_page.dpa.save_fail')); }
    finally { dpaLoading.value = false; }
}

async function publishDpaRow(row) {
    try {
        await ElMessageBox.confirm(
            t('gdpr_enhancement_page.dpa.publish_confirm'),
            t('gdpr_enhancement_page.dpa.publish_title'),
            { confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel'), type: 'warning' },
        );
        await apiClient.post(`/gdpr/dpas/${row.id}/publish`);
        ElMessage.success(t('gdpr_enhancement_page.dpa.publish_ok'));
        loadDpas();
    } catch { /* cancelled */ }
}

function previewDpa(row) {
    previewDpaData.value = row;
    showDpaPreview.value = true;
}

function editDpa(row) {
    dpaForm.value = { title: row.title, version: row.version, content: row.content };
    showNewDpa.value = true;
}

onMounted(() => {
    loadRequests();
    loadDpas();
});
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 8px; }
.mb-4 { margin-bottom: 16px; }
.stat-card { text-align: center; padding: 8px; }
.stat-value { font-size: 28px; font-weight: 700; }
.stat-label { font-size: 12px; color: #909399; margin-top: 4px; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.ml-2 { margin-left: 8px; }
.text-secondary { color: #909399; font-size: 13px; }
.dpa-preview { margin-top: 12px; padding: 12px; background: #f5f7fa; border-radius: 4px; }
.preview-label { font-size: 13px; font-weight: 600; margin-bottom: 4px; }
.preview-content { font-size: 12px; color: #606266; white-space: pre-wrap; }
.dpa-meta { margin-bottom: 16px; }
.dpa-body pre { white-space: pre-wrap; word-break: break-word; font-size: 13px; background: #f5f7fa; padding: 16px; border-radius: 4px; max-height: 400px; overflow-y: auto; }
</style>
