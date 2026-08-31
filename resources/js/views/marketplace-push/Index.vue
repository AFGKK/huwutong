<template>
    <div class="push-page">
        <div class="page-header">
            <div>
                <h2>{{ t('nav.marketplace_push') }}</h2>
                <p class="text-muted">{{ t(`${P}.subtitle`) }}</p>
            </div>
            <el-button type="primary" @click="openCreateDialog"><el-icon><Plus /></el-icon> {{ t(`${P}.create_btn`) }}</el-button>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="4"><el-card shadow="never"><div class="stat-value">{{ stats.total_campaigns || 0 }}</div><div class="stat-label">{{ t(`${P}.stats.total_campaigns`) }}</div></el-card></el-col>
            <el-col :span="5"><el-card shadow="never"><div class="stat-value primary">{{ stats.total_sent || 0 }}</div><div class="stat-label">{{ t(`${P}.stats.total_sent`) }}</div></el-card></el-col>
            <el-col :span="5"><el-card shadow="never"><div class="stat-value success">{{ stats.total_read || 0 }}</div><div class="stat-label">{{ t(`${P}.stats.total_read`) }}</div></el-card></el-col>
            <el-col :span="5"><el-card shadow="never"><div class="stat-value warning">{{ stats.scheduled_count || 0 }}</div><div class="stat-label">{{ t(`${P}.stats.scheduled_count`) }}</div></el-card></el-col>
            <el-col :span="5"><el-card shadow="never"><div class="stat-value">{{ stats.draft_count || 0 }}</div><div class="stat-label">{{ t(`${P}.stats.draft_count`) }}</div></el-card></el-col>
        </el-row>

        <el-card shadow="never">
            <div class="toolbar">
                <el-select v-model="filter.status" clearable :placeholder="t(`${P}.filter.status_ph`)" style="width:130px" @change="loadCampaigns">
                    <el-option :label="t(`${P}.filter.all`)" value="" />
                    <el-option v-for="s in statusFilterOptions" :key="s.value" :label="s.label" :value="s.value" />
                </el-select>
            </div>

            <el-table :data="campaigns" v-loading="loading" stripe>
                <el-table-column :label="t(`${P}.columns.title`)" prop="title" min-width="200" />
                <el-table-column :label="t(`${P}.columns.type`)" width="100">
                    <template #default="{ row }"><el-tag :type="typeTag(row.type)" size="small">{{ typeLabel(row.type) }}</el-tag></template>
                </el-table-column>
                <el-table-column :label="t(`${P}.columns.target`)" width="120">
                    <template #default="{ row }">{{ targetLabel(row.target_type) }}</template>
                </el-table-column>
                <el-table-column :label="t(`${P}.columns.sent_read`)" width="120">
                    <template #default="{ row }">{{ row.sent_count }}/{{ row.read_count }}</template>
                </el-table-column>
                <el-table-column :label="t(`${P}.columns.target_count`)" width="80" prop="target_count" align="center" />
                <el-table-column :label="t(`${P}.columns.status`)" width="100">
                    <template #default="{ row }">
                        <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.columns.created_at`)" width="160">
                    <template #default="{ row }">{{ fmtDate(row.created_at) }}</template>
                </el-table-column>
                <el-table-column :label="t(`${P}.columns.actions`)" width="260" fixed="right">
                    <template #default="{ row }">
                        <el-button v-if="row.status === 'draft' || row.status === 'scheduled'" text size="small" type="primary" @click="openEditDialog(row)">{{ t('actions.edit') }}</el-button>
                        <el-button v-if="row.status === 'draft'" text size="small" type="success" @click="handleSend(row)">{{ t(`${P}.send_btn`) }}</el-button>
                        <el-button v-if="row.status === 'draft' || row.status === 'scheduled'" text size="small" type="danger" @click="handleCancel(row)">{{ t(`${P}.cancel_btn`) }}</el-button>
                        <el-button v-if="row.status === 'sent'" text size="small" @click="showDetail(row)">{{ t(`${P}.detail_btn`) }}</el-button>
                        <el-button v-if="row.status === 'draft'" text size="small" type="danger" @click="handleDelete(row)">{{ t('actions.delete') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- 新建/编辑 Dialog -->
        <el-dialog v-model="dialogVisible" :title="editingId ? t(`${P}.dialog.edit_title`) : t(`${P}.dialog.create_title`)" width="580px">
            <el-form ref="formRef" :model="form" :rules="formRules" label-width="100px">
                <el-form-item :label="t(`${P}.form.title`)" prop="title">
                    <el-input v-model="form.title" maxlength="200" :placeholder="t(`${P}.form.title_ph`)" />
                </el-form-item>
                <el-form-item :label="t(`${P}.form.content`)" prop="content">
                    <el-input v-model="form.content" type="textarea" :rows="4" maxlength="2000" :placeholder="t(`${P}.form.content_ph`)" show-word-limit />
                </el-form-item>
                <el-row :gutter="12">
                    <el-col :span="12">
                        <el-form-item :label="t(`${P}.form.type`)" prop="type">
                            <el-select v-model="form.type" style="width:100%">
                                <el-option v-for="opt in typeFormOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t(`${P}.form.target_type`)" prop="target_type">
                            <el-select v-model="form.target_type" style="width:100%" @change="onTargetChange">
                                <el-option v-for="opt in targetFormOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item v-if="showAppSelector" :label="t(`${P}.form.select_app`)" prop="target_app_id">
                    <el-select v-model="form.target_app_id" filterable style="width:100%">
                        <el-option v-for="a in availableApps" :key="a.id" :label="a.name" :value="a.id" />
                    </el-select>
                </el-form-item>
                <el-form-item v-if="form.target_type === 'category'" :label="t(`${P}.form.select_category`)" prop="target_category">
                    <el-select v-model="form.target_category" style="width:100%">
                        <el-option v-for="(label, val) in categoryMap" :key="val" :label="label" :value="val" />
                    </el-select>
                </el-form-item>
                <el-row :gutter="12">
                    <el-col :span="12">
                        <el-form-item :label="t(`${P}.form.link_type`)">
                            <el-select v-model="form.link_type" clearable :placeholder="t(`${P}.form.no_link`)" style="width:100%">
                                <el-option :label="t(`${P}.form.link_app`)" value="app" />
                                <el-option :label="t(`${P}.form.link_url`)" value="url" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item v-if="form.link_type" :label="form.link_type === 'app' ? t(`${P}.form.app_id`) : t(`${P}.form.link_address`)">
                            <el-input v-model="form.link_value" :placeholder="form.link_type === 'app' ? t(`${P}.form.app_id_ph`) : t(`${P}.form.link_url_ph`)" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item :label="t(`${P}.form.scheduled_at`)">
                    <el-date-picker v-model="form.scheduled_at" type="datetime" :placeholder="t(`${P}.form.send_now`)" style="width:100%" :disabled-date="d => d < new Date()" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="submitting" @click="submitForm">{{ editingId ? t('actions.save') : t('actions.create') }}</el-button>
            </template>
        </el-dialog>

        <!-- 详情 Dialog -->
        <el-dialog v-model="detailVisible" :title="t(`${P}.dialog.detail_title`)" width="500px">
            <div v-if="detail">
                <el-descriptions :column="1" border size="small">
                    <el-descriptions-item :label="t(`${P}.detail_fields.title`)">{{ detail.title }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.detail_fields.content`)">{{ detail.content }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.detail_fields.type`)"><el-tag :type="typeTag(detail.type)" size="small">{{ typeLabel(detail.type) }}</el-tag></el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.detail_fields.target`)">{{ targetLabel(detail.target_type) }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.detail_fields.status`)"><el-tag :type="statusTag(detail.status)" size="small">{{ statusLabel(detail.status) }}</el-tag></el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.detail_fields.target_count`)">{{ detail.target_count }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.detail_fields.sent_count`)">{{ detail.sent_count }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.detail_fields.read_count`)">{{ detail.read_count }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.detail_fields.sent_at`)">{{ fmtDate(detail.sent_at) || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.detail_fields.completed_at`)">{{ fmtDate(detail.completed_at) || '-' }}</el-descriptions-item>
                </el-descriptions>
            </div>
            <template #footer><el-button @click="detailVisible = false">{{ t('actions.close') }}</el-button></template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import api from '@/api/marketplacePush';
import appApi from '@/api/openPlatform';

const P = 'marketplace_push_page';
const { t, locale } = useI18n();

const loading = ref(false);
const campaigns = ref([]);
const stats = ref({});
const filter = reactive({ status: '' });
const dialogVisible = ref(false);
const submitting = ref(false);
const editingId = ref(null);
const formRef = ref(null);
const detailVisible = ref(false);
const detail = ref(null);
const availableApps = ref([]);

const showAppSelector = computed(() =>
    ['installed_app', 'specific_app'].includes(form.target_type)
);

const statusLabels = computed(() => ({
    draft: t(`${P}.status.draft`),
    scheduled: t(`${P}.status.scheduled`),
    sending: t(`${P}.status.sending`),
    sent: t(`${P}.status.sent`),
    cancelled: t(`${P}.status.cancelled`),
}));

const typeShortLabels = computed(() => ({
    marketing: t(`${P}.type_short.marketing`),
    update: t(`${P}.type_short.update`),
    promo: t(`${P}.type_short.promo`),
    info: t(`${P}.type_short.info`),
}));

const targetShortLabels = computed(() => ({
    all: t(`${P}.target_short.all`),
    installed_app: t(`${P}.target_short.installed_app`),
    category: t(`${P}.target_short.category`),
    specific_app: t(`${P}.target_short.specific_app`),
}));

const categoryMap = computed(() => ({
    integration: t(`${P}.categories.integration`),
    automation: t(`${P}.categories.automation`),
    analytics: t(`${P}.categories.analytics`),
    security: t(`${P}.categories.security`),
    billing: t(`${P}.categories.billing`),
    other: t(`${P}.categories.other`),
}));

const statusFilterOptions = computed(() => [
    { value: 'draft', label: statusLabels.value.draft },
    { value: 'scheduled', label: statusLabels.value.scheduled },
    { value: 'sending', label: statusLabels.value.sending },
    { value: 'sent', label: statusLabels.value.sent },
    { value: 'cancelled', label: statusLabels.value.cancelled },
]);

const typeFormOptions = computed(() => [
    { value: 'marketing', label: t(`${P}.type.marketing`) },
    { value: 'update', label: t(`${P}.type.update`) },
    { value: 'promo', label: t(`${P}.type.promo`) },
    { value: 'info', label: t(`${P}.type.info`) },
]);

const targetFormOptions = computed(() => [
    { value: 'all', label: t(`${P}.target.all`) },
    { value: 'installed_app', label: t(`${P}.target.installed_app`) },
    { value: 'category', label: t(`${P}.target.category`) },
    { value: 'specific_app', label: t(`${P}.target.specific_app`) },
]);

const form = reactive({
    title: '', content: '', type: 'marketing', target_type: 'all',
    target_app_id: null, target_category: null,
    link_type: '', link_value: '', scheduled_at: null,
});

const formRules = computed(() => ({
    title: [{ required: true, message: t(`${P}.validation.title_required`), trigger: 'blur' }],
    content: [{ required: true, message: t(`${P}.validation.content_required`), trigger: 'blur' }],
    type: [{ required: true, message: t(`${P}.validation.type_required`), trigger: 'change' }],
    target_type: [{ required: true, message: t(`${P}.validation.target_required`), trigger: 'change' }],
}));

function onTargetChange() {
    form.target_app_id = null;
    form.target_category = null;
    if (showAppSelector.value) loadAvailableApps();
}

async function loadAvailableApps() {
    try { const { data: r } = await appApi.apps({ per_page: 100 }); availableApps.value = r.data || []; } catch {}
}

async function loadCampaigns() {
    loading.value = true;
    try {
        const params = { per_page: 50 };
        if (filter.status) params.status = filter.status;
        const { data: r } = await api.campaigns(params);
        campaigns.value = r.data || [];
    } catch { campaigns.value = []; }
    finally { loading.value = false; }
}

async function loadStats() {
    try { const { data: r } = await api.stats(); if (r.success) stats.value = r.data; } catch {}
}

function openCreateDialog() {
    editingId.value = null;
    form.title = ''; form.content = ''; form.type = 'marketing';
    form.target_type = 'all'; form.target_app_id = null;
    form.target_category = null; form.link_type = '';
    form.link_value = ''; form.scheduled_at = null;
    dialogVisible.value = true;
}

function openEditDialog(row) {
    editingId.value = row.id;
    Object.assign(form, {
        title: row.title, content: row.content, type: row.type,
        target_type: row.target_type, target_app_id: row.target_app_id,
        target_category: row.target_category, link_type: row.link_type || '',
        link_value: row.link_value || '', scheduled_at: row.scheduled_at || null,
    });
    dialogVisible.value = true;
    if (showAppSelector.value) loadAvailableApps();
}

async function submitForm() {
    const valid = await formRef.value.validate().catch(() => false);
    if (!valid) return;
    submitting.value = true;
    try {
        const payload = { ...form, link_type: form.link_type || null, link_value: form.link_value || null, scheduled_at: form.scheduled_at || null };
        if (editingId.value) {
            await api.campaignUpdate(editingId.value, payload);
            ElMessage.success(t(`${P}.messages.updated`));
        } else {
            await api.campaignCreate(payload);
            ElMessage.success(t(`${P}.messages.created`));
        }
        dialogVisible.value = false;
        loadCampaigns();
        loadStats();
    } catch {} finally { submitting.value = false; }
}

async function handleSend(row) {
    try {
        await ElMessageBox.confirm(
            t(`${P}.confirm.send_msg`, { title: row.title, count: row.target_count }),
            t(`${P}.confirm.send_title`),
        );
        await api.campaignSend(row.id);
        ElMessage.success(t(`${P}.messages.sent`));
        loadCampaigns();
        loadStats();
    } catch {}
}

async function handleCancel(row) {
    try {
        await ElMessageBox.confirm(
            t(`${P}.confirm.cancel_msg`, { title: row.title }),
            t(`${P}.confirm.cancel_title`),
            { type: 'warning' },
        );
        await api.campaignCancel(row.id);
        ElMessage.success(t(`${P}.messages.cancelled`));
        loadCampaigns();
    } catch {}
}

async function handleDelete(row) {
    try {
        await ElMessageBox.confirm(
            t(`${P}.confirm.delete_msg`, { title: row.title }),
            t(`${P}.confirm.delete_title`),
            { type: 'warning' },
        );
        await api.campaignDelete(row.id);
        ElMessage.success(t(`${P}.messages.deleted`));
        loadCampaigns();
    } catch {}
}

function showDetail(row) {
    detail.value = row;
    detailVisible.value = true;
}

function typeTag(type) { return { marketing: '', update: 'primary', promo: 'warning', info: 'info' }[type] || ''; }
function typeLabel(type) { return typeShortLabels.value[type] || type; }
function targetLabel(target) { return targetShortLabels.value[target] || target; }
function statusTag(s) { return { draft: 'info', scheduled: 'warning', sending: 'warning', sent: 'success', cancelled: 'danger' }[s] || ''; }
function statusLabel(s) { return statusLabels.value[s] || s; }
function fmtDate(d) {
    if (!d) return '-';
    return new Date(d).toLocaleString(locale.value === 'zh_CN' ? 'zh-CN' : 'en-US', {
        year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit',
    });
}

onMounted(() => { loadCampaigns(); loadStats(); });
</script>

<style scoped>
.push-page { padding: 20px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.page-header h2 { margin: 0; font-size: 20px; }
.text-muted { color: var(--el-text-color-secondary); font-size: 13px; }
.mb-4 { margin-bottom: 16px; }
.toolbar { display: flex; gap: 8px; margin-bottom: 16px; }
.stat-value { font-size: 22px; font-weight: 600; color: #303133; }
.stat-value.primary { color: #0f172a; }
.stat-value.success { color: #67c23a; }
.stat-value.warning { color: #e6a23c; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
</style>
