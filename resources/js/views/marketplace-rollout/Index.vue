<template>
    <div class="rollout-page">
        <div class="page-header">
            <div>
                <h2>{{ t('nav.marketplace_rollout') }}</h2>
                <p class="text-muted">{{ t(`${P}.subtitle`) }}</p>
            </div>
            <el-button type="primary" @click="showCreate = true">
                <el-icon><Plus /></el-icon> {{ t(`${P}.create_btn`) }}
            </el-button>
        </div>

        <el-card shadow="never">
            <el-table :data="rollouts" v-loading="loading" stripe>
                <el-table-column :label="t(`${P}.columns.app`)" min-width="200">
                    <template #default="{ row }">
                        <div class="app-cell">
                            <el-avatar :src="row.app?.icon_url" size="small" shape="square" />
                            <span>{{ row.app?.name || '-' }}</span>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.columns.name`)" prop="name" min-width="180" />
                <el-table-column :label="t(`${P}.columns.version`)" width="100">
                    <template #default="{ row }">{{ row.version?.version || '-' }}</template>
                </el-table-column>
                <el-table-column :label="t(`${P}.columns.percentage`)" width="80" align="center">
                    <template #default="{ row }">{{ row.percentage }}%</template>
                </el-table-column>
                <el-table-column :label="t(`${P}.columns.status`)" width="100">
                    <template #default="{ row }">
                        <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.columns.assigned_installed`)" width="120" align="center">
                    <template #default="{ row }">{{ row.assigned_count }}/{{ row.installed_count }}</template>
                </el-table-column>
                <el-table-column :label="t(`${P}.columns.error_rate`)" width="80" align="center">
                    <template #default="{ row }">
                        <span :class="{ 'text-danger': row.error_count > 0 }">{{ row.error_count }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.columns.created_at`)" width="160">
                    <template #default="{ row }">{{ fmtDate(row.created_at) }}</template>
                </el-table-column>
                <el-table-column :label="t(`${P}.columns.actions`)" width="300" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="viewDetail(row)">{{ t(`${P}.detail`) }}</el-button>
                        <el-button v-if="row.status === 'draft'" size="small" @click="editRollout(row)">{{ t('actions.edit') }}</el-button>
                        <el-button v-if="row.status === 'draft'" type="primary" size="small" @click="handleStart(row)">{{ t(`${P}.start`) }}</el-button>
                        <el-button v-if="row.status === 'active'" size="small" @click="handlePause(row)">{{ t(`${P}.pause`) }}</el-button>
                        <el-button v-if="row.status === 'active' || row.status === 'paused'" type="success" size="small" @click="handleComplete(row)">{{ t(`${P}.complete`) }}</el-button>
                        <el-button v-if="row.status === 'active' || row.status === 'paused'" type="danger" size="small" @click="handleRollback(row)">{{ t(`${P}.rollback`) }}</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div v-if="!rollouts.length && !loading" class="empty">
                <el-empty :description="t(`${P}.empty`)" :image-size="60" />
            </div>

            <div v-if="total > 0" class="pagination-wrap">
                <el-pagination
                    v-model:current-page="page"
                    :page-size="perPage"
                    :total="total"
                    layout="prev, pager, next, total"
                    @current-change="loadRollouts"
                />
            </div>
        </el-card>

        <!-- 创建/编辑 Dialog -->
        <el-dialog v-model="showCreate" :title="editingId ? t(`${P}.dialog.edit_title`) : t(`${P}.dialog.create_title`)" width="650px" :close-on-click-modal="false">
            <el-form :model="form" label-width="120px" v-loading="formLoading">
                <el-form-item :label="t(`${P}.form.target_app`)" required v-if="!editingId">
                    <el-select v-model="form.app_id" filterable style="width:100%" @change="onAppChange">
                        <el-option v-for="app in availableApps" :key="app.id" :label="`${app.name} (v${app.current_version || '-'})`" :value="app.id" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t(`${P}.form.target_version`)" required v-if="!editingId">
                    <el-select v-model="form.version_id" filterable style="width:100%">
                        <el-option v-for="v in filteredVersions" :key="v.id" :label="`v${v.version}`" :value="v.id" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t(`${P}.form.name`)" required>
                    <el-input v-model="form.name" :placeholder="t(`${P}.form.name_ph`)" maxlength="200" />
                </el-form-item>
                <el-form-item :label="t(`${P}.form.description`)">
                    <el-input v-model="form.description" type="textarea" :rows="3" maxlength="2000" />
                </el-form-item>
                <el-form-item :label="t(`${P}.form.rollout_type`)">
                    <el-radio-group v-model="form.rollout_type">
                        <el-radio value="percentage">{{ t(`${P}.form.type_percentage`) }}</el-radio>
                        <el-radio value="tenant_group">{{ t(`${P}.form.type_tenant_group`) }}</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item v-if="form.rollout_type === 'percentage'" :label="t(`${P}.form.percentage`)">
                    <el-slider v-model="form.percentage" :min="1" :max="100" show-input style="width:300px" />
                </el-form-item>
                <el-form-item v-if="form.rollout_type === 'tenant_group'" :label="t(`${P}.form.include_tenants`)">
                    <el-select v-model="form.tenant_ids" multiple filterable remote :remote-method="searchTenants" style="width:100%">
                        <el-option v-for="t in tenantOptions" :key="t.id" :label="`${t.name} (${t.domain || '-'})`" :value="t.id" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t(`${P}.form.exclude_tenants`)">
                    <el-select v-model="form.excluded_tenant_ids" multiple filterable remote :remote-method="searchTenantsExclude" style="width:100%">
                        <el-option v-for="t in tenantExcludeOptions" :key="t.id" :label="`${t.name} (${t.domain || '-'})`" :value="t.id" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t(`${P}.form.auto_rollback`)">
                    <el-switch v-model="form.auto_rollback" />
                    <span class="text-muted ml-2">{{ t(`${P}.form.auto_rollback_hint`) }}</span>
                </el-form-item>
                <el-form-item v-if="form.auto_rollback" :label="t(`${P}.form.error_threshold`)">
                    <el-input-number v-model="form.error_threshold" :min="0.1" :max="100" :step="0.5" :precision="1" /> %
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showCreate = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="saving" @click="saveRollout">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>

        <!-- 详情 Dialog -->
        <el-dialog v-model="showDetail" :title="t(`${P}.dialog.detail_title`)" width="750px">
            <template v-if="detail">
                <el-descriptions :column="2" border size="small">
                    <el-descriptions-item :label="t(`${P}.detail_fields.app`)">{{ detail.app?.name }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.detail_fields.version`)">v{{ detail.version?.version }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.detail_fields.name`)" :span="2">{{ detail.name }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.detail_fields.status`)">
                        <el-tag :type="statusTag(detail.status)" size="small">{{ statusLabel(detail.status) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.detail_fields.percentage`)">{{ detail.percentage }}%</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.detail_fields.assigned_installed`)">{{ detail.assigned_count }}/{{ detail.installed_count }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.detail_fields.error_count`)">
                        <span :class="{ 'text-danger': detail.error_count > 0 }">{{ detail.error_count }}</span>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.detail_fields.creator`)">{{ detail.creator?.name }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.detail_fields.started_at`)">{{ fmtDate(detail.started_at) }}</el-descriptions-item>
                    <el-descriptions-item v-if="detail.completed_at" :label="t(`${P}.detail_fields.completed_at`)">{{ fmtDate(detail.completed_at) }}</el-descriptions-item>
                    <el-descriptions-item v-if="detail.rolled_back_at" :label="t(`${P}.detail_fields.rolled_back_at`)">{{ fmtDate(detail.rolled_back_at) }}</el-descriptions-item>
                    <el-descriptions-item v-if="detail.description" :label="t(`${P}.detail_fields.description`)" :span="2">{{ detail.description }}</el-descriptions-item>
                </el-descriptions>

                <!-- Stats -->
                <el-row :gutter="16" class="mt-4" v-if="detailStats">
                    <el-col :span="6"><el-card shadow="never"><div class="stat-value">{{ detailStats.progress }}%</div><div class="stat-label">{{ t(`${P}.detail_fields.install_progress`) }}</div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="never"><div class="stat-value" :class="{ 'text-danger': detailStats.error_rate > 0 }">{{ detailStats.error_rate }}%</div><div class="stat-label">{{ t(`${P}.detail_fields.error_rate`) }}</div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="never"><div class="stat-value">{{ detailStats.event_counts?.assigned || 0 }}</div><div class="stat-label">{{ t(`${P}.detail_fields.assigned`) }}</div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="never"><div class="stat-value">{{ detailStats.event_counts?.installed || 0 }}</div><div class="stat-label">{{ t(`${P}.detail_fields.installed`) }}</div></el-card></el-col>
                </el-row>

                <!-- Events -->
                <h4 class="mt-4">{{ t(`${P}.detail_fields.events`) }}</h4>
                <el-timeline>
                    <el-timeline-item v-for="evt in (detail.events || [])" :key="evt.id" :timestamp="fmtDate(evt.created_at)" :type="evt.event_type === 'error' ? 'danger' : evt.event_type === 'rollback' ? 'warning' : 'primary'">
                        <span :class="{ 'text-danger': evt.event_type === 'error' }">{{ evt.message || evt.event_type }}</span>
                    </el-timeline-item>
                </el-timeline>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import api from '@/api/marketplaceRollout';

const P = 'marketplace_rollout_page';
const { t, locale } = useI18n();

const rollouts = ref([]);
const loading = ref(false);
const total = ref(0);
const page = ref(1);
const perPage = ref(20);
const showCreate = ref(false);
const editingId = ref(null);
const saving = ref(false);
const formLoading = ref(false);
const availableApps = ref([]);
const allVersions = ref([]);
const tenantOptions = ref([]);
const tenantExcludeOptions = ref([]);
const showDetail = ref(false);
const detail = ref(null);
const detailStats = ref(null);
const form = reactive({
    app_id: null, version_id: null, name: '', description: '',
    rollout_type: 'percentage', percentage: 10, auto_rollback: false,
    error_threshold: 5, tenant_ids: [], excluded_tenant_ids: [],
});

const filteredVersions = computed(() => allVersions.value.filter(v => v.app_id === form.app_id));

const statusLabels = computed(() => ({
    draft: t(`${P}.status.draft`),
    active: t(`${P}.status.active`),
    paused: t(`${P}.status.paused`),
    completed: t(`${P}.status.completed`),
    rolled_back: t(`${P}.status.rolled_back`),
}));

function statusTag(s) {
    return { draft: 'info', active: 'success', paused: 'warning', completed: '', rolled_back: 'danger' }[s] || '';
}
function statusLabel(s) {
    return statusLabels.value[s] || s;
}
function fmtDate(d) {
    return d ? new Date(d).toLocaleString(locale.value === 'zh_CN' ? 'zh-CN' : 'en-US') : '-';
}

async function loadRollouts() {
    loading.value = true;
    try { const { data: r } = await api.list({ page: page.value, per_page: perPage.value }); rollouts.value = r.data?.data || []; total.value = r.data?.total || 0; }
    catch {} finally { loading.value = false; }
}

async function loadAvailableApps() {
    try { const { data: r } = await api.availableApps();
        availableApps.value = r.data || [];
        allVersions.value = (r.data || []).flatMap(a => (a.versions || []).map(v => ({ ...v, app_id: a.id })));
    } catch {}
}

function onAppChange(appId) {
    form.version_id = null;
}

async function searchTenants(q) {
    try { const { data: r } = await api.availableTenants({ search: q }); tenantOptions.value = r.data || []; } catch {}
}
async function searchTenantsExclude(q) {
    try { const { data: r } = await api.availableTenants({ search: q }); tenantExcludeOptions.value = r.data || []; } catch {}
}

async function saveRollout() {
    if (!form.name || !form.app_id || !form.version_id) { ElMessage.warning(t(`${P}.messages.required_fields`)); return; }
    saving.value = true;
    try {
        const payload = { ...form };
        if (form.rollout_type !== 'tenant_group') { payload.tenant_ids = []; }
        if (editingId.value) {
            await api.update(editingId.value, payload);
            ElMessage.success(t(`${P}.messages.updated`));
        } else {
            await api.create(payload);
            ElMessage.success(t(`${P}.messages.created`));
        }
        showCreate.value = false; resetForm(); loadRollouts();
    } catch {} finally { saving.value = false; }
}

function editRollout(row) {
    editingId.value = row.id;
    Object.assign(form, {
        app_id: row.app_id, version_id: row.version_id, name: row.name, description: row.description || '',
        rollout_type: row.rollout_type, percentage: row.percentage, auto_rollback: !!row.auto_rollback,
        error_threshold: Number(row.error_threshold) || 5, tenant_ids: [], excluded_tenant_ids: [],
    });
    showCreate.value = true;
}

function resetForm() {
    editingId.value = null;
    Object.assign(form, { app_id: null, version_id: null, name: '', description: '', rollout_type: 'percentage', percentage: 10, auto_rollback: false, error_threshold: 5, tenant_ids: [], excluded_tenant_ids: [] });
}

async function viewDetail(row) {
    showDetail.value = true;
    detail.value = null; detailStats.value = null;
    try {
        const [detailRes, statsRes] = await Promise.all([api.show(row.id), api.stats(row.id)]);
        detail.value = detailRes.data?.data;
        detailStats.value = statsRes.data?.data;
    } catch {}
}

async function handleStart(row) {
    try {
        await ElMessageBox.confirm(
            t(`${P}.confirm.start_msg`, { name: row.name, version: row.version?.version, percentage: row.percentage }),
            t(`${P}.confirm.start_title`),
        );
        await api.start(row.id);
        ElMessage.success(t(`${P}.messages.started`));
        loadRollouts();
    } catch {}
}
async function handlePause(row) {
    try {
        await ElMessageBox.confirm(t(`${P}.confirm.pause_msg`, { name: row.name }), t(`${P}.confirm.pause_title`));
        await api.pause(row.id);
        ElMessage.success(t(`${P}.messages.paused`));
        loadRollouts();
    } catch {}
}
async function handleComplete(row) {
    try {
        await ElMessageBox.confirm(
            t(`${P}.confirm.complete_msg`, { name: row.name, version: row.version?.version }),
            t(`${P}.confirm.complete_title`),
        );
        await api.complete(row.id);
        ElMessage.success(t(`${P}.messages.completed`));
        loadRollouts();
    } catch {}
}
async function handleRollback(row) {
    try {
        await ElMessageBox.confirm(
            t(`${P}.confirm.rollback_msg`, { name: row.name }),
            t(`${P}.confirm.rollback_title`),
            { confirmButtonText: t(`${P}.confirm.rollback_btn`), confirmButtonClass: 'el-button--danger' },
        );
        await api.rollback(row.id);
        ElMessage.success(t(`${P}.messages.rolled_back`));
        loadRollouts();
    } catch {}
}

onMounted(() => { loadRollouts(); loadAvailableApps(); });
</script>

<style scoped>
.rollout-page { padding: 20px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.page-header h2 { margin: 0; font-size: 20px; }
.text-muted { color: var(--el-text-color-secondary); font-size: 13px; }
.mt-4 { margin-top: 16px; }
.ml-2 { margin-left: 8px; }
.text-danger { color: #f56c6c; font-weight: 600; }
.app-cell { display: flex; align-items: center; gap: 8px; }
.pagination-wrap { display: flex; justify-content: center; margin-top: 16px; }
.empty { padding: 40px 0; }
.stat-value { font-size: 20px; font-weight: 600; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
</style>
