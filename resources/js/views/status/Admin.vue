<template>
    <div class="admin-status-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('status_admin_page.title') }}</h2>
                <span class="header-subtitle">{{ t('status_admin_page.subtitle') }}</span>
            </div>
            <div class="header-actions">
                <el-button @click="runHealthCheck" :loading="checking">{{ t('status_admin_page.run_health_check') }}</el-button>
                <el-button type="primary" @click="activeTab = 'incidents'; showIncidentDialog = true">{{ t('status_admin_page.create_incident') }}</el-button>
            </div>
        </div>

        <!-- Stats -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6" v-for="s in statsData" :key="s.key">
                <el-card shadow="never">
                    <div class="stat-box">
                        <div class="stat-num" :style="{ color: s.color }">{{ s.value }}</div>
                        <div class="stat-lbl">{{ s.label }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab">
            <!-- Components Tab -->
            <el-tab-pane :label="t('status_admin_page.tabs.components')" name="components">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>{{ t('status_admin_page.components.list_title') }}</span>
                            <el-button type="primary" size="small" @click="showComponentDialog = true; editingComponent = null">{{ t('status_admin_page.components.add') }}</el-button>
                        </div>
                    </template>
                    <el-table :data="components" v-loading="loadingComponents" stripe>
                        <el-table-column prop="name" :label="t('status_admin_page.cols.name')" width="130" />
                        <el-table-column prop="slug" :label="t('status_admin_page.cols.slug')" width="120" />
                        <el-table-column prop="description" :label="t('status_admin_page.cols.description')" min-width="160" />
                        <el-table-column :label="t('status_admin_page.cols.group')" width="100">
                            <template #default="{ row }">{{ groupLabel(row.group) }}</template>
                        </el-table-column>
                        <el-table-column :label="t('status_admin_page.cols.status')" width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.status_tag_type || 'info'" size="small">{{ row.status_label || row.status }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('status_admin_page.cols.public')" width="60" align="center">
                            <template #default="{ row }">
                                <el-icon :color="row.is_public ? '#67c23a' : '#909399'">
                                    <CircleCheck v-if="row.is_public" /><CloseBold v-else />
                                </el-icon>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('status_admin_page.cols.sort_order')" width="60" prop="sort_order" />
                        <el-table-column :label="t('status_admin_page.cols.actions')" width="150" fixed="right">
                            <template #default="{ row }">
                                <el-button text size="small" @click="editComponent(row)">{{ t('actions.edit') }}</el-button>
                                <el-button text size="small" type="danger" @click="handleDeleteComponent(row)">{{ t('actions.delete') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>

            <!-- Incidents Tab -->
            <el-tab-pane :label="t('status_admin_page.tabs.incidents')" name="incidents">
                <el-card shadow="never">
                    <el-table :data="incidents" v-loading="loadingIncidents" stripe>
                        <el-table-column prop="title" :label="t('status_admin_page.cols.title')" min-width="200" />
                        <el-table-column :label="t('status_admin_page.cols.severity')" width="80">
                            <template #default="{ row }">{{ row.severity_label || row.severity }}</template>
                        </el-table-column>
                        <el-table-column :label="t('status_admin_page.cols.status')" width="90">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'resolved' ? 'success' : 'warning'" size="small">
                                    {{ row.status_label || row.status }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('status_admin_page.cols.affected_components')" min-width="140">
                            <template #default="{ row }">
                                <span v-for="(c, i) in row.components" :key="c.id">
                                    {{ c.name }}{{ i < row.components.length - 1 ? ', ' : '' }}
                                </span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('status_admin_page.cols.created_at')" width="160">
                            <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
                        </el-table-column>
                        <el-table-column :label="t('status_admin_page.cols.actions')" width="180" fixed="right">
                            <template #default="{ row }">
                                <el-button text size="small" @click="showIncidentDetail(row)">{{ t('status_admin_page.detail') }}</el-button>
                                <el-button text size="small" type="danger" @click="handleDeleteIncident(row)">{{ t('actions.delete') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>

            <!-- Subscribers Tab -->
            <el-tab-pane :label="t('status_admin_page.tabs.subscribers', { n: subscriberCount })" name="subscribers">
                <el-card shadow="never">
                    <el-table :data="subscribers" v-loading="loadingSubscribers" stripe>
                        <el-table-column prop="email" :label="t('status_admin_page.cols.email')" min-width="250" />
                        <el-table-column :label="t('status_admin_page.cols.status')" width="80">
                            <template #default="{ row }">
                                <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                                    {{ row.is_active ? t('status_admin_page.subscriber_status.active') : t('status_admin_page.subscriber_status.unsubscribed') }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('status_admin_page.cols.subscribed_at')" width="160">
                            <template #default="{ row }">{{ formatTime(row.subscribed_at) }}</template>
                        </el-table-column>
                        <el-table-column :label="t('status_admin_page.cols.unsubscribed_at')" width="160">
                            <template #default="{ row }">{{ row.unsubscribed_at ? formatTime(row.unsubscribed_at) : '—' }}</template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <!-- Component Dialog -->
        <el-dialog v-model="showComponentDialog" :title="editingComponent ? t('status_admin_page.dialogs.edit_component') : t('status_admin_page.dialogs.add_component')" width="500px">
            <el-form ref="componentForm" :model="componentForm" :rules="componentRules" label-width="80px">
                <el-form-item :label="t('status_admin_page.cols.name')" prop="name">
                    <el-input v-model="componentForm.name" />
                </el-form-item>
                <el-form-item :label="t('status_admin_page.cols.slug')" prop="slug">
                    <el-input v-model="componentForm.slug" :disabled="!!editingComponent" />
                </el-form-item>
                <el-form-item :label="t('status_admin_page.cols.description')">
                    <el-input v-model="componentForm.description" type="textarea" :rows="2" />
                </el-form-item>
                <el-form-item :label="t('status_admin_page.cols.group')">
                    <el-select v-model="componentForm.group">
                        <el-option v-for="opt in groupOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('status_admin_page.cols.sort_order')">
                    <el-input-number v-model="componentForm.sort_order" :min="0" />
                </el-form-item>
                <el-form-item :label="t('status_admin_page.cols.public')">
                    <el-switch v-model="componentForm.is_public" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showComponentDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleSaveComponent" :loading="savingComponent">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>

        <!-- Incident Create Dialog -->
        <el-dialog v-model="showIncidentDialog" :title="t('status_admin_page.dialogs.create_incident')" width="550px">
            <el-form ref="incidentForm" :model="incidentForm" :rules="incidentRules" label-width="100px">
                <el-form-item :label="t('status_admin_page.cols.title')" prop="title">
                    <el-input v-model="incidentForm.title" />
                </el-form-item>
                <el-form-item :label="t('status_admin_page.cols.description')">
                    <el-input v-model="incidentForm.description" type="textarea" :rows="3" />
                </el-form-item>
                <el-form-item :label="t('status_admin_page.cols.severity')" prop="severity">
                    <el-select v-model="incidentForm.severity">
                        <el-option v-for="opt in severityOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('status_admin_page.cols.affected_components')">
                    <el-select v-model="incidentForm.component_ids" multiple filterable>
                        <el-option v-for="c in components" :key="c.id" :label="c.name" :value="c.id" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('status_admin_page.cols.public')">
                    <el-switch v-model="incidentForm.is_public" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showIncidentDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleCreateIncident" :loading="savingIncident">{{ t('actions.create') }}</el-button>
            </template>
        </el-dialog>

        <!-- Incident Detail Dialog -->
        <el-dialog v-model="showIncidentDetailDialog" :title="t('status_admin_page.dialogs.incident_detail_title', { title: incidentDetail?.title || '' })" width="600px">
            <template v-if="incidentDetail">
                <el-descriptions :column="2" border size="small">
                    <el-descriptions-item :label="t('status_admin_page.cols.severity')">{{ incidentDetail.severity_label }}</el-descriptions-item>
                    <el-descriptions-item :label="t('status_admin_page.cols.status')">
                        <el-tag :type="incidentDetail.status === 'resolved' ? 'success' : 'warning'" size="small">
                            {{ incidentDetail.status_label }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('status_admin_page.cols.occurred_at')">{{ formatTime(incidentDetail.occurred_at) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('status_admin_page.cols.resolved_at')">{{ formatTime(incidentDetail.resolved_at) || '—' }}</el-descriptions-item>
                </el-descriptions>

                <h4 class="section-title">{{ t('status_admin_page.section.update_status') }}</h4>
                <div class="status-update-form">
                    <el-select v-model="updateStatus.status" :placeholder="t('status_admin_page.placeholders.select_status')">
                        <el-option v-for="opt in incidentStatusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                    <el-input v-model="updateStatus.message" type="textarea" :rows="2" :placeholder="t('status_admin_page.placeholders.update_message')" />
                    <el-button type="primary" @click="handleUpdateIncident">{{ t('actions.update') }}</el-button>
                </div>

                <h4 class="section-title">{{ t('status_admin_page.section.update_timeline') }}</h4>
                <el-timeline>
                    <el-timeline-item v-for="up in incidentDetail.updates" :key="up.created_at"
                        :type="up.status === 'resolved' ? 'success' : 'primary'"
                        :timestamp="formatTime(up.created_at)">
                        <strong>{{ statusLabel(up.status) }}</strong>
                        <p>{{ up.message }}</p>
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
import { CircleCheck, CloseBold } from '@element-plus/icons-vue';
import statusApi from '@/api/statusPage';

const { t, locale } = useI18n();

const activeTab = ref('components');

const statsValues = reactive({
    total_components: '0',
    open_incidents: '0',
    active_subscribers: '0',
    uptime_30d: '0%',
});

const statsData = computed(() => [
    { key: 'total_components', label: t('status_admin_page.stats.total_components'), color: '#0f172a', value: statsValues.total_components },
    { key: 'open_incidents', label: t('status_admin_page.stats.open_incidents'), color: '#e6a23c', value: statsValues.open_incidents },
    { key: 'active_subscribers', label: t('status_admin_page.stats.active_subscribers'), color: '#67c23a', value: statsValues.active_subscribers },
    { key: 'uptime_30d', label: t('status_admin_page.stats.uptime_30d'), color: '#67c23a', value: statsValues.uptime_30d },
]);

const groupOptions = computed(() => [
    { label: t('status_admin_page.groups.core'), value: 'core' },
    { label: t('status_admin_page.groups.services'), value: 'services' },
    { label: t('status_admin_page.groups.infrastructure'), value: 'infrastructure' },
    { label: t('status_admin_page.groups.third_party'), value: 'third_party' },
]);

const severityOptions = computed(() => [
    { label: t('status_admin_page.severity.minor'), value: 'minor' },
    { label: t('status_admin_page.severity.major'), value: 'major' },
    { label: t('status_admin_page.severity.critical'), value: 'critical' },
]);

const incidentStatusOptions = computed(() => [
    { label: t('status_admin_page.incident_status.investigating'), value: 'investigating' },
    { label: t('status_admin_page.incident_status.identified'), value: 'identified' },
    { label: t('status_admin_page.incident_status.monitoring'), value: 'monitoring' },
    { label: t('status_admin_page.incident_status.resolved'), value: 'resolved' },
    { label: t('status_admin_page.incident_status.postmortem'), value: 'postmortem' },
]);

const componentRules = computed(() => ({
    name: [{ required: true, message: t('status_admin_page.validation.name_required') }],
    slug: [{ required: true, message: t('status_admin_page.validation.slug_required') }],
}));

const incidentRules = computed(() => ({
    title: [{ required: true, message: t('status_admin_page.validation.title_required') }],
    severity: [{ required: true, message: t('status_admin_page.validation.severity_required') }],
}));

// Components
const components = ref([]);
const loadingComponents = ref(false);
const showComponentDialog = ref(false);
const editingComponent = ref(null);
const savingComponent = ref(false);
const componentForm = reactive({
    name: '', slug: '', description: '', group: 'core',
    sort_order: 0, is_public: true,
});

// Incidents
const incidents = ref([]);
const loadingIncidents = ref(false);
const showIncidentDialog = ref(false);
const savingIncident = ref(false);
const incidentForm = reactive({
    title: '', description: '', severity: 'minor',
    component_ids: [], is_public: true,
});
const incidentDetail = ref(null);
const showIncidentDetailDialog = ref(false);
const updateStatus = reactive({ status: 'identified', message: '' });

// Subscribers
const subscribers = ref([]);
const loadingSubscribers = ref(false);
const subscriberCount = ref(0);
const checking = ref(false);

function groupLabel(group) {
    const key = `status_admin_page.groups.${group}`;
    const translated = t(key);
    return translated !== key ? translated : group;
}

function statusLabel(s) {
    const key = `status_admin_page.incident_status.${s}`;
    const translated = t(key);
    return translated !== key ? translated : s;
}

function formatTime(value) {
    if (!value) return '';
    return new Date(value).toLocaleString(locale.value === 'en' ? 'en-US' : 'zh-CN');
}

async function fetchStats() {
    try {
        const { data: res } = await statusApi.getStats();
        if (res.success) {
            const d = res.data;
            statsValues.total_components = String(d.total_components || 0);
            statsValues.open_incidents = String(d.open_incidents || 0);
            statsValues.active_subscribers = String(d.active_subscribers || 0);
            statsValues.uptime_30d = (d.uptime_30d || 0) + '%';
        }
    } catch { /* ignore */ }
}

async function fetchComponents() {
    loadingComponents.value = true;
    try {
        const { data: res } = await statusApi.getComponents();
        if (res.success) components.value = res.data || [];
    } catch { ElMessage.error(t('status_admin_page.messages.fetch_components_failed')); }
    finally { loadingComponents.value = false; }
}

async function fetchIncidents() {
    loadingIncidents.value = true;
    try {
        const { data: res } = await statusApi.getIncidents({ per_page: 50 });
        if (res.success) incidents.value = res.data?.data || [];
    } catch { ElMessage.error(t('status_admin_page.messages.fetch_incidents_failed')); }
    finally { loadingIncidents.value = false; }
}

async function fetchSubscribers() {
    loadingSubscribers.value = true;
    try {
        const { data: res } = await statusApi.getSubscribers({ per_page: 100 });
        if (res.success) {
            subscribers.value = res.data?.data || [];
            subscriberCount.value = res.data?.total || 0;
        }
    } catch { /* ignore */ }
    finally { loadingSubscribers.value = false; }
}

function editComponent(comp) {
    editingComponent.value = comp;
    Object.assign(componentForm, {
        name: comp.name, slug: comp.slug, description: comp.description || '',
        group: comp.group || 'core', sort_order: comp.sort_order || 0, is_public: comp.is_public,
    });
    showComponentDialog.value = true;
}

function resetComponentForm() {
    editingComponent.value = null;
    Object.assign(componentForm, {
        name: '', slug: '', description: '', group: 'core',
        sort_order: 0, is_public: true,
    });
}

async function handleSaveComponent() {
    savingComponent.value = true;
    try {
        if (editingComponent.value) {
            await statusApi.updateComponent(editingComponent.value.id, componentForm);
            ElMessage.success(t('status_admin_page.messages.component_updated'));
        } else {
            await statusApi.createComponent(componentForm);
            ElMessage.success(t('status_admin_page.messages.component_created'));
        }
        showComponentDialog.value = false;
        resetComponentForm();
        await fetchComponents();
    } catch { ElMessage.error(t('status_admin_page.messages.save_failed')); }
    finally { savingComponent.value = false; }
}

async function handleDeleteComponent(comp) {
    try {
        await ElMessageBox.confirm(
            t('status_admin_page.confirm.delete_component', { name: comp.name }),
            t('actions.confirm'),
            { type: 'warning' },
        );
        await statusApi.deleteComponent(comp.id);
        ElMessage.success(t('status_admin_page.messages.deleted'));
        await fetchComponents();
    } catch { /* cancelled */ }
}

async function handleCreateIncident() {
    savingIncident.value = true;
    try {
        await statusApi.createIncident(incidentForm);
        ElMessage.success(t('status_admin_page.messages.incident_created'));
        showIncidentDialog.value = false;
        Object.assign(incidentForm, { title: '', description: '', severity: 'minor', component_ids: [], is_public: true });
        await fetchIncidents();
        await fetchComponents();
        await fetchStats();
    } catch { ElMessage.error(t('status_admin_page.messages.create_failed')); }
    finally { savingIncident.value = false; }
}

async function showIncidentDetail(inc) {
    try {
        const { data: res } = await statusApi.getIncident(inc.id);
        if (res.success) {
            incidentDetail.value = res.data;
            updateStatus.status = res.data.status === 'resolved' ? 'resolved' : res.data.status;
            updateStatus.message = '';
            showIncidentDetailDialog.value = true;
        }
    } catch { ElMessage.error(t('status_admin_page.messages.fetch_detail_failed')); }
}

async function handleUpdateIncident() {
    if (!updateStatus.status || !updateStatus.message.trim()) {
        ElMessage.warning(t('status_admin_page.messages.status_update_required'));
        return;
    }
    try {
        await statusApi.updateIncidentStatus(incidentDetail.value.id, {
            status: updateStatus.status,
            message: updateStatus.message,
        });
        ElMessage.success(t('status_admin_page.messages.incident_updated'));
        showIncidentDetailDialog.value = false;
        await fetchIncidents();
        if (activeTab.value === 'subscribers') await fetchSubscribers();
    } catch { ElMessage.error(t('status_admin_page.messages.update_failed')); }
}

async function handleDeleteIncident(inc) {
    try {
        await ElMessageBox.confirm(
            t('status_admin_page.confirm.delete_incident', { title: inc.title }),
            t('actions.confirm'),
            { type: 'warning' },
        );
        await statusApi.deleteIncident(inc.id);
        ElMessage.success(t('status_admin_page.messages.deleted'));
        await fetchIncidents();
        await fetchStats();
    } catch { /* cancelled */ }
}

async function runHealthCheck() {
    checking.value = true;
    try {
        const { data: res } = await statusApi.runChecks();
        ElMessage.success(t('status_admin_page.messages.health_check_done'));
        await fetchComponents();
        await fetchStats();
    } catch { ElMessage.error(t('status_admin_page.messages.check_failed')); }
    finally { checking.value = false; }
}

onMounted(() => {
    fetchStats();
    fetchComponents();
    fetchIncidents();
    fetchSubscribers();
});
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.header-left h2 { margin: 0 0 4px; }
.header-subtitle { font-size: 14px; color: #909399; }
.header-actions { display: flex; gap: 8px; }
.mb-4 { margin-bottom: 16px; }
.stat-box { text-align: center; padding: 8px; }
.stat-num { font-size: 28px; font-weight: 700; }
.stat-lbl { font-size: 13px; color: #909399; margin-top: 4px; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.section-title { margin: 16px 0 8px; }
.status-update-form { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
.status-update-form .el-select { width: 160px; }
.status-update-form .el-textarea { flex: 1; min-width: 200px; }
</style>
