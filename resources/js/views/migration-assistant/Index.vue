<template>
    <div class="migration-hub-page">
        <h2>{{ t('migration_hub_page.title') }}</h2>

        <el-tabs v-model="migMainTab" type="border-card">
            <!-- Tab 1: AI 迁移助手（原 migration-assistant） -->
            <el-tab-pane name="assistant" :label="t('migration_hub_page.tabs.assistant')">
                <el-row :gutter="20" class="stats-row">
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value info">{{ stats.total || 0 }}</div><div class="stat-label">{{ t('migration_assistant_page.stats.total_jobs') }}</div></div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value success">{{ stats.completed || 0 }}</div><div class="stat-label">{{ t('migration_assistant_page.stats.completed') }}</div></div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value danger">{{ stats.failed || 0 }}</div><div class="stat-label">{{ t('migration_assistant_page.stats.failed') }}</div></div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ stats.totalImported || 0 }}</div><div class="stat-label">{{ t('migration_assistant_page.stats.licenses_imported') }}</div></div></el-card></el-col>
                </el-row>

                <el-tabs v-model="activeTab" type="border-card">
                    <el-tab-pane :label="t('migration_assistant_page.tabs.jobs')" name="list">
                        <div class="toolbar">
                            <el-button type="primary" @click="showCreateDialog = true">{{ t('migration_assistant_page.btn_new') }}</el-button>
                            <el-button @click="loadJobs">{{ t('migration_assistant_page.btn_refresh') }}</el-button>
                        </div>
                        <el-table :data="jobs" v-loading="loading" stripe @row-click="showDetail">
                            <el-table-column prop="id" :label="t('migration_assistant_page.cols.id')" width="60" />
                            <el-table-column prop="source" :label="t('migration_assistant_page.cols.source')" width="120">
                                <template #default="{ row }"><el-tag size="small">{{ sourceLabel(row.source) }}</el-tag></template>
                            </el-table-column>
                            <el-table-column prop="status" :label="t('migration_assistant_page.cols.status')" width="100">
                                <template #default="{ row }">
                                    <el-tag :type="statusType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column prop="total_items" :label="t('migration_assistant_page.cols.total')" width="70" align="center" />
                            <el-table-column prop="imported_items" :label="t('migration_assistant_page.cols.imported')" width="70" align="center" />
                            <el-table-column prop="failed_items" :label="t('migration_assistant_page.cols.failed')" width="70" align="center" />
                            <el-table-column :label="t('migration_assistant_page.cols.actions')" width="120">
                                <template #default="{ row }">
                                    <el-button size="small" type="primary" @click.stop="handleRun(row)" :disabled="row.status === 'importing'">{{ t('migration_assistant_page.btn_run') }}</el-button>
                                </template>
                            </el-table-column>
                            <el-table-column prop="created_at" :label="t('migration_assistant_page.cols.created_at')" width="170" />
                        </el-table>

                        <el-dialog v-model="showCreateDialog" :title="t('migration_assistant_page.dialog_create_title')" width="500px">
                            <el-form :model="createForm" label-width="120px">
                                <el-form-item :label="t('migration_assistant_page.form.source')">
                                    <el-select v-model="createForm.source" style="width:100%">
                                        <el-option v-for="opt in sourceOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                    </el-select>
                                </el-form-item>
                                <el-form-item :label="t('migration_assistant_page.form.api_key')">
                                    <el-input v-model="createForm.api_key" type="password" show-password />
                                </el-form-item>
                            </el-form>
                            <template #footer>
                                <el-button @click="showCreateDialog = false">{{ t('actions.cancel') }}</el-button>
                                <el-button type="primary" @click="handleCreate">{{ t('actions.create') }}</el-button>
                            </template>
                        </el-dialog>
                    </el-tab-pane>

                    <el-tab-pane :label="t('migration_assistant_page.tabs.detail')" name="detail" :disabled="!selectedJob">
                        <div v-if="selectedJob">
                            <el-descriptions :column="2" border>
                                <el-descriptions-item :label="t('migration_assistant_page.detail.source')">{{ sourceLabel(selectedJob.source) }}</el-descriptions-item>
                                <el-descriptions-item :label="t('migration_assistant_page.detail.status')">{{ statusLabel(selectedJob.status) }}</el-descriptions-item>
                                <el-descriptions-item :label="t('migration_assistant_page.detail.total_rows')">{{ selectedJob.total_items }}</el-descriptions-item>
                                <el-descriptions-item :label="t('migration_assistant_page.detail.success_rate')">{{ summary.success_rate }}%</el-descriptions-item>
                                <el-descriptions-item :label="t('migration_assistant_page.detail.imported')">{{ selectedJob.imported_items }}</el-descriptions-item>
                                <el-descriptions-item :label="t('migration_assistant_page.detail.failed')">{{ selectedJob.failed_items }}</el-descriptions-item>
                            </el-descriptions>

                            <h4 style="margin-top:16px">{{ t('migration_assistant_page.detail.errors_title') }}</h4>
                            <el-table :data="failedItems" stripe size="small" v-if="failedItems.length">
                                <el-table-column prop="item_index" :label="t('migration_assistant_page.cols.index')" width="70" />
                                <el-table-column :label="t('migration_assistant_page.cols.original_data')" min-width="250">
                                    <template #default="{ row }">{{ JSON.stringify(row.original_data) }}</template>
                                </el-table-column>
                                <el-table-column :label="t('migration_assistant_page.cols.error')" min-width="200">
                                    <template #default="{ row }">{{ JSON.stringify(row.validation_errors) }}</template>
                                </el-table-column>
                            </el-table>
                            <el-empty v-else :description="t('migration_assistant_page.detail.no_errors')" />
                        </div>
                    </el-tab-pane>
                </el-tabs>
            </el-tab-pane>

            <!-- Tab 2: 竞品迁移（原 migration-enhancement） -->
            <el-tab-pane name="competitor" :label="t('migration_hub_page.tabs.competitor')">
                <div v-if="me_tabVisited">
                    <el-row :gutter="20" class="stats-row">
                        <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ me_stats.total || 0 }}</div><div class="stat-label">{{ t('migration_page.stats.total') }}</div></div></el-card></el-col>
                        <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value success">{{ me_stats.completed || 0 }}</div><div class="stat-label">{{ t('migration_page.stats.completed') }}</div></div></el-card></el-col>
                        <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value danger">{{ me_stats.failed || 0 }}</div><div class="stat-label">{{ t('migration_page.stats.failed') }}</div></div></el-card></el-col>
                        <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value info">{{ me_stats.totalLicenses || 0 }}</div><div class="stat-label">{{ t('migration_page.stats.licenses') }}</div></div></el-card></el-col>
                    </el-row>

                    <el-tabs v-model="me_activeTab" type="border-card">
                        <el-tab-pane :label="t('migration_page.tabs.list')" name="list">
                            <div class="toolbar">
                                <el-button type="primary" @click="me_showApiDialog = true">{{ t('migration_page.from_api') }}</el-button>
                                <el-button @click="me_showFileDialog = true">{{ t('migration_page.from_file') }}</el-button>
                                <el-button @click="me_loadImports">{{ t('actions.refresh') }}</el-button>
                            </div>
                            <el-table :data="me_imports" v-loading="me_loading" stripe @row-click="me_showDetail">
                                <el-table-column prop="id" label="ID" width="60" />
                                <el-table-column prop="source" :label="t('migration_page.cols.source')" width="120"><template #default="{row}"><el-tag size="small">{{ row.source }}</el-tag></template></el-table-column>
                                <el-table-column prop="status" :label="t('migration_page.cols.status')" width="100"><template #default="{row}"><el-tag :type="row.status==='completed'?'success':row.status==='failed'?'danger':'warning'" size="small">{{ row.status }}</el-tag></template></el-table-column>
                                <el-table-column prop="total_rows" :label="t('migration_page.cols.total')" width="70" align="center" />
                                <el-table-column prop="success" :label="t('migration_page.cols.success')" width="70" align="center" />
                                <el-table-column prop="failed" :label="t('migration_page.cols.failed')" width="70" align="center" />
                                <el-table-column prop="skipped" :label="t('migration_page.cols.skipped')" width="70" align="center" />
                                <el-table-column :label="t('migration_page.cols.actions')" width="120">
                                    <template #default="{row}">
                                        <el-button size="small" type="primary" @click.stop="me_handleRun(row)" :disabled="row.status==='running'">{{ t('migration_page.run') }}</el-button>
                                    </template>
                                </el-table-column>
                                <el-table-column prop="created_at" :label="t('migration_page.cols.created')" width="170" />
                            </el-table>

                            <el-dialog v-model="me_showApiDialog" :title="t('migration_page.api_dialog')" width="500px">
                                <el-form :model="me_apiForm" label-width="120px">
                                    <el-form-item :label="t('migration_page.cols.source')"><el-select v-model="me_apiForm.source" style="width:100%"><el-option label="Keygen.sh" value="keygen" /><el-option label="LicenseSpring" value="licensespring" /></el-select></el-form-item>
                                    <el-form-item label="API Key"><el-input v-model="me_apiForm.api_key" type="password" show-password /></el-form-item>
                                    <el-form-item label="Account ID" v-if="me_apiForm.source==='keygen'"><el-input v-model="me_apiForm.account_id" placeholder="Keygen Account ID" /></el-form-item>
                                </el-form>
                                <template #footer><el-button @click="me_showApiDialog = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="me_handleCreateApi">{{ t('migration_page.create_import') }}</el-button></template>
                            </el-dialog>

                            <el-dialog v-model="me_showFileDialog" :title="t('migration_page.file_dialog')" width="500px">
                                <el-alert :title="t('migration_page.file_hint')" type="info" show-icon :closable="false" style="margin-bottom:16px" />
                                <el-upload :auto-upload="false" :on-change="me_handleFileSelect" :show-file-list="true" accept=".csv,.json,.xlsx">
                                    <el-button>{{ t('migration_page.choose_file') }}</el-button>
                                </el-upload>
                                <div v-if="me_selectedFile" style="margin-top:12px;color:#606266">{{ t('migration_page.selected_file', { name: me_selectedFile.name }) }}</div>
                                <template #footer><el-button @click="me_showFileDialog = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="me_handleCreateFile" :disabled="!me_selectedFile">{{ t('migration_page.create_import') }}</el-button></template>
                            </el-dialog>
                        </el-tab-pane>

                        <el-tab-pane :label="t('migration_page.tabs.detail')" name="detail" :disabled="!me_selectedImport">
                            <div v-if="me_selectedImport">
                                <el-descriptions :column="2" border>
                                    <el-descriptions-item :label="t('migration_page.cols.source')">{{ me_selectedImport.source }}</el-descriptions-item>
                                    <el-descriptions-item :label="t('migration_page.cols.status')">{{ me_selectedImport.status }}</el-descriptions-item>
                                    <el-descriptions-item :label="t('migration_page.total_rows')">{{ me_selectedImport.total_rows }}</el-descriptions-item>
                                    <el-descriptions-item :label="t('migration_page.success_rate')">{{ me_selectedImport.result_summary?.success_rate || '0%' }}</el-descriptions-item>
                                    <el-descriptions-item :label="t('migration_page.cols.success')">{{ me_selectedImport.success }}</el-descriptions-item>
                                    <el-descriptions-item :label="t('migration_page.cols.failed')">{{ me_selectedImport.failed }}</el-descriptions-item>
                                </el-descriptions>

                                <h4 style="margin-top:16px">{{ t('migration_page.failed_rows') }}</h4>
                                <el-table :data="me_failedRows" stripe size="small" v-if="me_failedRows.length">
                                    <el-table-column prop="row_number" :label="t('migration_page.cols.row')" width="70" />
                                    <el-table-column :label="t('migration_page.cols.raw')" min-width="300"><template #default="{row}">{{ JSON.stringify(row.original_data) }}</template></el-table-column>
                                    <el-table-column prop="error_message" :label="t('migration_page.cols.error')" min-width="200" />
                                </el-table>
                                <el-empty v-else :description="t('migration_page.no_failed')" />
                            </div>
                        </el-tab-pane>
                    </el-tabs>
                </div>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { getMigrationAssistantDashboard, getMigrationJobs, createMigrationJob, runMigrationJob, getMigrationJobDetail } from '@/api/migrationAssistant';
import { getMigrationDashboard, getMigrationImports, createApiImport, createFileImport, runMigrationImport, getMigrationImportDetail } from '@/api/migrationEnhancement';

const { t, te } = useI18n();

// ─── 外层 Tab 控制 ───
const migMainTab = ref('assistant');
const me_tabVisited = ref(false);

watch(migMainTab, (val) => {
    if (val === 'competitor' && !me_tabVisited.value) {
        me_tabVisited.value = true;
        me_loadDashboard();
        me_loadImports();
    }
});

// ─── Tab 1: AI 迁移助手（原 migration-assistant） ───
const sourceKeys = ['cryptlex', 'localazy'];

const sourceOptions = computed(() =>
    sourceKeys.map((value) => ({
        value,
        label: t(`migration_assistant_page.sources.${value}`),
    }))
);

const STATUS_MAP = computed(() => ({
    draft: { type: 'info', label: t('migration_assistant_page.status.draft') },
    validating: { type: 'warning', label: t('migration_assistant_page.status.validating') },
    importing: { type: 'warning', label: t('migration_assistant_page.status.importing') },
    completed: { type: 'success', label: t('migration_assistant_page.status.completed') },
    failed: { type: 'danger', label: t('migration_assistant_page.status.failed') },
    rolled_back: { type: 'info', label: t('migration_assistant_page.status.rolled_back') },
}));

function statusType(s) {
    return STATUS_MAP.value[s]?.type || 'info';
}

function statusLabel(s) {
    return STATUS_MAP.value[s]?.label || s;
}

function sourceLabel(source) {
    const key = `migration_assistant_page.sources.${source}`;
    return te(key) ? t(key) : source;
}

const activeTab = ref('list');
const stats = ref({});
const jobs = ref([]);
const loading = ref(false);
const selectedJob = ref(null);
const failedItems = ref([]);
const showCreateDialog = ref(false);
const createForm = reactive({ source: 'cryptlex', api_key: '' });

const summary = computed(() => selectedJob.value?.summary || {});

async function loadDashboard() {
    try { stats.value = await getMigrationAssistantDashboard(); } catch (e) { console.error(e); }
}
async function loadJobs() {
    loading.value = true;
    try { const r = await getMigrationJobs({ per_page: 50 }); jobs.value = r.data || []; }
    catch (e) { console.error(e); } finally { loading.value = false; }
}
async function handleCreate() {
    try {
        await createMigrationJob(createForm);
        ElMessage.success(t('migration_assistant_page.messages.job_created'));
        showCreateDialog.value = false;
        loadJobs();
    } catch (e) {
        ElMessage.error(t('migration_assistant_page.messages.create_failed'));
    }
}
async function handleRun(row) {
    try {
        await runMigrationJob(row.id);
        ElMessage.success(t('migration_assistant_page.messages.run_finished'));
        loadJobs();
        loadDashboard();
    } catch (e) {
        ElMessage.error(t('migration_assistant_page.messages.run_failed'));
    }
}
async function showDetail(row) {
    try {
        const r = await getMigrationJobDetail(row.id);
        selectedJob.value = r.job || r;
        failedItems.value = r.failed_items || [];
        activeTab.value = 'detail';
    } catch (e) {
        ElMessage.error(t('migration_assistant_page.messages.load_detail_failed'));
    }
}

// ─── Tab 2: 竞品迁移（原 migration-enhancement，加 me_ 前缀） ───
const me_activeTab = ref('list');
const me_stats = ref({});
const me_imports = ref([]);
const me_loading = ref(false);
const me_selectedImport = ref(null);
const me_failedRows = ref([]);
const me_selectedFile = ref(null);
const me_showApiDialog = ref(false);
const me_showFileDialog = ref(false);

const me_apiForm = reactive({ source: 'keygen', api_key: '', account_id: '' });

async function me_loadDashboard() {
    try { me_stats.value = await getMigrationDashboard(); } catch (e) { console.error(e); }
}
async function me_loadImports() {
    me_loading.value = true;
    try { const r = await getMigrationImports({ per_page: 50 }); me_imports.value = r.data || []; }
    catch (e) { console.error(e); } finally { me_loading.value = false; }
}
async function me_handleCreateApi() {
    try {
        await createApiImport(me_apiForm);
        ElMessage.success(t('migration_page.messages.created'));
        me_showApiDialog.value = false;
        me_loadImports();
    } catch (e) { ElMessage.error(t('migration_page.messages.create_failed')); }
}
function me_handleFileSelect(file) { me_selectedFile.value = file.raw; }
async function me_handleCreateFile() {
    if (!me_selectedFile.value) return;
    try {
        await createFileImport({ file: me_selectedFile.value });
        ElMessage.success(t('migration_page.messages.file_created'));
        me_showFileDialog.value = false;
        me_selectedFile.value = null;
        me_loadImports();
    } catch (e) { ElMessage.error(t('migration_page.messages.create_failed')); }
}
async function me_handleRun(row) {
    try {
        await runMigrationImport(row.id);
        ElMessage.success(t('migration_page.messages.run_ok'));
        me_loadImports();
        me_loadDashboard();
    } catch (e) { ElMessage.error(t('migration_page.messages.run_failed')); }
}
async function me_showDetail(row) {
    try {
        const r = await getMigrationImportDetail(row.id);
        me_selectedImport.value = r.import || r;
        me_failedRows.value = r.failed_rows || [];
        me_activeTab.value = 'detail';
    } catch (e) { ElMessage.error(t('migration_page.messages.detail_failed')); }
}

onMounted(() => { loadDashboard(); loadJobs(); });
</script>

<style scoped>
.migration-hub-page { padding: 20px; }
.stats-row { margin-bottom: 20px; }
.stat-card { text-align: center; padding: 6px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: #0f172a; }
.stat-value.success { color: #67c23a; }
.stat-value.danger { color: #f56c6c; }
.stat-value.info { color: #909399; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.toolbar { margin-bottom: 16px; display: flex; align-items: center; }
</style>
