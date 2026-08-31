<template>
    <div class="honeypot-page">
        <h2>{{ t('honeypot_page.title') }}</h2>

        <el-row :gutter="20" class="stats-row">
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ stats.total || 0 }}</div><div class="stat-label">{{ t('honeypot_page.stats.total') }}</div></div></el-card></el-col>
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value success">{{ stats.active || 0 }}</div><div class="stat-label">{{ t('honeypot_page.stats.active') }}</div></div></el-card></el-col>
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value danger">{{ stats.triggered || 0 }}</div><div class="stat-label">{{ t('honeypot_page.stats.triggered') }}</div></div></el-card></el-col>
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value warning">{{ stats.total_triggers || 0 }}</div><div class="stat-label">{{ t('honeypot_page.stats.total_triggers') }}</div></div></el-card></el-col>
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value info">{{ stats.recent_triggered || 0 }}</div><div class="stat-label">{{ t('honeypot_page.stats.recent_7d') }}</div></div></el-card></el-col>
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ stats.disabled || 0 }}</div><div class="stat-label">{{ t('honeypot_page.stats.disabled') }}</div></div></el-card></el-col>
        </el-row>

        <el-card shadow="never" style="margin-top:16px">
            <div class="toolbar">
                <el-button type="primary" @click="showCreateDialog = true">{{ t('honeypot_page.toolbar.generate') }}</el-button>
                <el-button @click="handleGenerateBatch">{{ t('honeypot_page.toolbar.batch_generate') }}</el-button>
                <el-button @click="loadList">{{ t('security_page.refresh') }}</el-button>
                <div style="flex:1" />
                <el-input v-model="search" :placeholder="t('honeypot_page.toolbar.search_ph')" clearable style="width:280px" @clear="loadList" @keyup.enter="loadList" />
                <el-select v-model="filterStatus" :placeholder="t('honeypot_page.toolbar.status_ph')" clearable style="width:120px;margin-left:8px" @change="loadList">
                    <el-option v-for="opt in statusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                </el-select>
            </div>

            <el-table :data="list" v-loading="loading" stripe>
                <el-table-column prop="id" :label="t('honeypot_page.cols.id')" width="60" />
                <el-table-column prop="license_key" :label="t('honeypot_page.cols.license_key')" width="240">
                    <template #default="{row}">
                        <code style="font-size:12px;cursor:pointer" @click="copyKey(row.license_key)">{{ row.license_key }}</code>
                    </template>
                </el-table-column>
                <el-table-column prop="label" :label="t('honeypot_page.cols.label')" width="140" />
                <el-table-column prop="status" :label="t('honeypot_page.cols.status')" width="100">
                    <template #default="{row}">
                        <el-tag v-if="row.status === 'active'" type="success" size="small">{{ statusLabel(row.status) }}</el-tag>
                        <el-tag v-else-if="row.status === 'triggered'" type="danger" size="small">{{ statusLabel(row.status) }}</el-tag>
                        <el-tag v-else type="info" size="small">{{ statusLabel(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="trigger_count" :label="t('honeypot_page.cols.trigger_count')" width="90" align="center" />
                <el-table-column prop="triggered_ip" :label="t('honeypot_page.cols.triggered_ip')" width="140" />
                <el-table-column prop="triggered_at" :label="t('honeypot_page.cols.triggered_at')" width="170" />
                <el-table-column prop="created_at" :label="t('honeypot_page.cols.created_at')" width="170" />
                <el-table-column :label="t('honeypot_page.cols.actions')" width="200" fixed="right">
                    <template #default="{row}">
                        <el-button link type="primary" size="small" @click="showDetail(row)">{{ t('honeypot_page.actions.detail') }}</el-button>
                        <el-button v-if="row.status === 'active'" link type="warning" size="small" @click="handleDisable(row)">{{ t('actions.disable') }}</el-button>
                        <el-button v-if="row.status === 'disabled'" link type="success" size="small" @click="handleReactivate(row)">{{ t('honeypot_page.actions.reactivate') }}</el-button>
                        <el-button link type="danger" size="small" @click="handleDelete(row)">{{ t('actions.delete') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div style="margin-top:16px;text-align:right">
                <el-pagination
                    v-model:current-page="page"
                    v-model:page-size="perPage"
                    :total="total"
                    :page-sizes="[10, 20, 50]"
                    layout="total, sizes, prev, pager, next"
                    @size-change="loadList"
                    @current-change="loadList"
                />
            </div>
        </el-card>

        <el-drawer v-model="detailVisible" :title="t('honeypot_page.detail.title')" :size="500">
            <template v-if="selectedItem">
                <el-descriptions :column="2" border>
                    <el-descriptions-item :label="t('honeypot_page.cols.id')" :span="2">{{ selectedItem.id }}</el-descriptions-item>
                    <el-descriptions-item :label="t('honeypot_page.detail.license_key')" :span="2">
                        <code>{{ selectedItem.license_key }}</code>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('honeypot_page.detail.label')">{{ selectedItem.label || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('honeypot_page.detail.status')">
                        <el-tag v-if="selectedItem.status === 'active'" type="success">{{ statusLabel(selectedItem.status) }}</el-tag>
                        <el-tag v-else-if="selectedItem.status === 'triggered'" type="danger">{{ statusLabel(selectedItem.status) }}</el-tag>
                        <el-tag v-else type="info">{{ statusLabel(selectedItem.status) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('honeypot_page.detail.trigger_count')">{{ selectedItem.trigger_count }}</el-descriptions-item>
                    <el-descriptions-item :label="t('honeypot_page.detail.triggered_ip')">{{ selectedItem.triggered_ip || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('honeypot_page.detail.triggered_at')">{{ selectedItem.triggered_at || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('honeypot_page.detail.created_at')" :span="2">{{ selectedItem.created_at }}</el-descriptions-item>
                    <el-descriptions-item :label="t('honeypot_page.detail.notes')" :span="2">{{ selectedItem.notes || '-' }}</el-descriptions-item>
                </el-descriptions>

                <el-card v-if="selectedItem.triggered_info" shadow="never" style="margin-top:16px">
                    <template #header><span>{{ t('honeypot_page.detail.trigger_context') }}</span></template>
                    <pre class="json-view">{{ JSON.stringify(selectedItem.triggered_info, null, 2) }}</pre>
                </el-card>
            </template>
        </el-drawer>

        <el-dialog v-model="showCreateDialog" :title="t('honeypot_page.create.title')" width="500px">
            <el-form :model="createForm" label-width="100px">
                <el-form-item :label="t('honeypot_page.create.label')">
                    <el-input v-model="createForm.label" :placeholder="t('honeypot_page.create.label_ph')" />
                </el-form-item>
                <el-form-item :label="t('honeypot_page.create.notes')">
                    <el-input v-model="createForm.notes" type="textarea" :rows="3" :placeholder="t('honeypot_page.create.notes_ph')" />
                </el-form-item>
                <el-form-item :label="t('honeypot_page.create.count')">
                    <el-input-number v-model="createForm.count" :min="1" :max="100" />
                    <span style="margin-left:8px;color:#909399;font-size:12px">{{ t('honeypot_page.create.count_hint') }}</span>
                </el-form-item>
                <el-form-item>
                    <el-alert type="info" show-icon :closable="false">
                        <template #title>
                            {{ t('honeypot_page.create.alert') }}
                        </template>
                    </el-alert>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showCreateDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleCreate">{{ t('honeypot_page.create.submit') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { getHoneypotDashboard, getHoneypotList, createHoneypot, disableHoneypot, reactivateHoneypot, deleteHoneypot } from '@/api/honeypot';

const { t } = useI18n();

const statusKeys = ['active', 'triggered', 'disabled'];
const statusMap = computed(() =>
    Object.fromEntries(statusKeys.map((key) => [key, t(`honeypot_page.status.${key}`)]))
);
const statusOptions = computed(() =>
    statusKeys.map((key) => ({ value: key, label: t(`honeypot_page.status.${key}`) }))
);

function statusLabel(status) {
    return statusMap.value[status] || status;
}

const stats = ref({});
const list = ref([]);
const loading = ref(false);
const page = ref(1);
const perPage = ref(20);
const total = ref(0);
const search = ref('');
const filterStatus = ref('');
const detailVisible = ref(false);
const selectedItem = ref(null);
const showCreateDialog = ref(false);
const createForm = ref({ label: '', notes: '', count: 1 });

async function loadDashboard() {
    try {
        const res = await getHoneypotDashboard();
        stats.value = res.data?.data || {};
    } catch (e) {
        console.error('Failed to load honeypot dashboard', e);
    }
}

async function loadList() {
    loading.value = true;
    try {
        const params = { page: page.value, per_page: perPage.value };
        if (search.value) params.search = search.value;
        if (filterStatus.value) params.status = filterStatus.value;
        const res = await getHoneypotList(params);
        list.value = res.data?.data?.data || [];
        total.value = res.data?.data?.total || 0;
    } catch (e) {
        console.error('Failed to load honeypot list', e);
    } finally {
        loading.value = false;
    }
}

function showDetail(row) {
    selectedItem.value = row;
    detailVisible.value = true;
}

async function handleCreate() {
    try {
        await createHoneypot(createForm.value);
        ElMessage.success(t('honeypot_page.messages.create_ok', { count: createForm.value.count }));
        showCreateDialog.value = false;
        createForm.value = { label: '', notes: '', count: 1 };
        loadList();
        loadDashboard();
    } catch (e) {
        ElMessage.error(t('honeypot_page.messages.create_fail'));
    }
}

async function handleGenerateBatch() {
    try {
        await createHoneypot({
            label: t('honeypot_page.batch.default_label'),
            notes: t('honeypot_page.batch.default_notes'),
            count: 10,
        });
        ElMessage.success(t('honeypot_page.messages.batch_ok'));
        loadList();
        loadDashboard();
    } catch (e) {
        ElMessage.error(t('honeypot_page.messages.batch_fail'));
    }
}

async function handleDisable(row) {
    try {
        await ElMessageBox.confirm(
            t('honeypot_page.messages.disable_confirm', { key: row.license_key }),
            t('honeypot_page.messages.disable_title')
        );
        await disableHoneypot(row.id);
        ElMessage.success(t('honeypot_page.messages.disabled_ok'));
        loadList();
        loadDashboard();
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('messages.failed'));
    }
}

async function handleReactivate(row) {
    try {
        await reactivateHoneypot(row.id);
        ElMessage.success(t('honeypot_page.messages.reactivate_ok'));
        loadList();
        loadDashboard();
    } catch (e) {
        ElMessage.error(t('messages.failed'));
    }
}

async function handleDelete(row) {
    try {
        await ElMessageBox.confirm(
            t('honeypot_page.messages.delete_confirm', { key: row.license_key }),
            t('honeypot_page.messages.delete_title'),
            { type: 'warning' }
        );
        await deleteHoneypot(row.id);
        ElMessage.success(t('honeypot_page.messages.deleted_ok'));
        loadList();
        loadDashboard();
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('messages.failed'));
    }
}

function copyKey(key) {
    navigator.clipboard.writeText(key).then(() => {
        ElMessage.success(t('portal.copied_clipboard'));
    }).catch(() => {
        ElMessage.warning(t('honeypot_page.messages.copy_fail'));
    });
}

onMounted(() => {
    loadDashboard();
    loadList();
});
</script>

<style scoped>
.honeypot-page { padding: 20px; }
.stats-row { margin-top: 16px; }
.stat-card { text-align: center; padding: 4px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: #0f172a; }
.stat-value.danger { color: #f56c6c; }
.stat-value.warning { color: #e6a23c; }
.stat-value.success { color: #67c23a; }
.stat-value.info { color: #909399; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.toolbar { display: flex; align-items: center; margin-bottom: 16px; gap: 8px; flex-wrap: wrap; }
.json-view { background: #f5f7fa; padding: 12px; border-radius: 4px; font-size: 12px; max-height: 400px; overflow: auto; white-space: pre-wrap; word-break: break-all; }
code { background: #f5f7fa; padding: 2px 6px; border-radius: 3px; font-size: 12px; color: #0f172a; }
</style>
