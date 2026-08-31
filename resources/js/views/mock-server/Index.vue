<template>
    <div>
        <el-breadcrumb separator="/" class="mb-4">
            <el-breadcrumb-item :to="{ path: '/admin/dashboard' }">{{ t('nav.home') }}</el-breadcrumb-item>
            <el-breadcrumb-item>{{ t('mock_server_page.breadcrumb_dev_tools') }}</el-breadcrumb-item>
            <el-breadcrumb-item>{{ t('nav.mock_server') }}</el-breadcrumb-item>
        </el-breadcrumb>

        <el-row :gutter="20" class="mb-4">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-gray-800">{{ rules.length }}</div>
                    <div class="text-sm text-gray-500 mt-1">{{ t('mock_server_page.stats.rules') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-success">{{ activeCount }}</div>
                    <div class="text-sm text-gray-500 mt-1">{{ t('mock_server_page.stats.active') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-primary">{{ config.defaults?.delay_ms ?? 0 }}ms</div>
                    <div class="text-sm text-gray-500 mt-1">{{ t('mock_server_page.stats.default_delay') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-warning">{{ config.defaults?.error_rate ?? 0 }}%</div>
                    <div class="text-sm text-gray-500 mt-1">{{ t('mock_server_page.stats.default_error_rate') }}</div>
                </el-card>
            </el-col>
        </el-row>

        <el-card>
            <template #header>
                <div class="flex items-center justify-between">
                    <span class="font-semibold">{{ t('mock_server_page.rules_title') }}</span>
                    <div>
                        <el-button @click="handleImport" :loading="importing">{{ t('mock_server_page.import_prebuilt') }}</el-button>
                        <el-button type="primary" @click="showCreate = true">{{ t('mock_server_page.create_rule') }}</el-button>
                    </div>
                </div>
            </template>

            <el-table :data="rules" v-loading="loading" stripe style="width:100%">
                <el-table-column :label="t('mock_server_page.columns.method')" width="80">
                    <template #default="{ row }">
                        <el-tag :type="methodTag(row.method)" size="small">{{ row.method }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="path" :label="t('mock_server_page.columns.path')" min-width="250" />
                <el-table-column prop="status_code" :label="t('mock_server_page.columns.status_code')" width="80" />
                <el-table-column prop="description" :label="t('mock_server_page.columns.description')" min-width="180" />
                <el-table-column :label="t('mock_server_page.columns.delay')" width="70">
                    <template #default="{ row }">{{ row.delay_ms || '-' }}ms</template>
                </el-table-column>
                <el-table-column :label="t('mock_server_page.columns.status')" width="70">
                    <template #default="{ row }">
                        <el-tag :type="row.is_active ? 'success' : 'info'" size="small">{{ activeStatusLabel(row.is_active) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('mock_server_page.columns.actions')" width="160" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="showEdit(row)">{{ t('actions.edit') }}</el-button>
                        <el-button size="small" type="danger" @click="handleDelete(row)">{{ t('actions.delete') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- 创建/编辑对话框 -->
        <el-dialog v-model="dialogVisible" :title="dialogTitle" width="700px">
            <el-form :model="form" label-position="top">
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item :label="t('mock_server_page.form.method')" required>
                            <el-select v-model="form.method" style="width:100%">
                                <el-option v-for="opt in httpMethodOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('mock_server_page.form.path')" required>
                            <el-input v-model="form.path" :placeholder="t('mock_server_page.form.path_ph')" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="4">
                        <el-form-item :label="t('mock_server_page.form.status_code')">
                            <el-input-number v-model="form.status_code" :min="100" :max="599" style="width:100%" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item :label="t('mock_server_page.form.description')">
                    <el-input v-model="form.description" :placeholder="t('mock_server_page.form.description_ph')" />
                </el-form-item>
                <el-form-item :label="t('mock_server_page.form.response_json')" required>
                    <el-input v-model="responseText" type="textarea" :rows="8" class="font-mono" :placeholder="t('mock_server_page.form.response_ph')" />
                </el-form-item>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t('mock_server_page.form.delay_ms')">
                            <el-input-number v-model="form.delay_ms" :min="0" :max="30000" style="width:100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('mock_server_page.form.enabled')">
                            <el-switch v-model="form.is_active" />
                        </el-form-item>
                    </el-col>
                </el-row>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleSave" :loading="saving">{{ isEdit ? t('actions.update') : t('actions.create') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import mockServerApi from '../../api/mockServer';

const { t } = useI18n();

const httpMethodKeys = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

const httpMethodOptions = computed(() =>
    httpMethodKeys.map((value) => ({ value, label: value }))
);

const dialogTitle = computed(() =>
    isEdit.value ? t('mock_server_page.edit_dialog_title') : t('mock_server_page.create_dialog_title')
);

function activeStatusLabel(isActive) {
    return isActive ? t('actions.enable') : t('actions.disable');
}

const rules = ref([]);
const loading = ref(false);
const importing = ref(false);
const saving = ref(false);
const showCreate = ref(false);
const dialogVisible = ref(false);
const isEdit = ref(false);
const editingId = ref(null);
const config = ref({});

const form = ref({
    method: 'GET',
    path: '',
    status_code: 200,
    response: { success: true, data: null },
    description: '',
    delay_ms: 0,
    is_active: true,
});

const responseText = computed({
    get: () => JSON.stringify(form.value.response, null, 2),
    set: (val) => {
        try { form.value.response = JSON.parse(val); }
        catch { /* invalid JSON */ }
    },
});

const activeCount = computed(() => rules.value.filter(r => r.is_active).length);

function methodTag(m) {
    return { GET: 'success', POST: 'primary', PUT: 'warning', PATCH: 'warning', DELETE: 'danger' }[m] || 'info';
}

async function fetchRules() {
    loading.value = true;
    try {
        const res = await mockServerApi.getRules();
        rules.value = res.data.data || [];
    } catch { rules.value = []; }
    finally { loading.value = false; }
}

async function fetchConfig() {
    try {
        const res = await mockServerApi.getConfig();
        config.value = res.data;
    } catch { /* ignore */ }
}

function showEdit(row) {
    isEdit.value = true;
    editingId.value = row.id;
    form.value = {
        method: row.method,
        path: row.path,
        status_code: row.status_code,
        response: row.response,
        description: row.description || '',
        delay_ms: row.delay_ms || 0,
        is_active: row.is_active,
    };
    dialogVisible.value = true;
}

async function handleSave() {
    saving.value = true;
    try {
        if (isEdit.value) {
            await mockServerApi.updateRule(editingId.value, form.value);
            ElMessage.success(t('mock_server_page.messages.updated'));
        } else {
            await mockServerApi.createRule(form.value);
            ElMessage.success(t('mock_server_page.messages.created'));
        }
        dialogVisible.value = false;
        await fetchRules();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('messages.failed'));
    } finally {
        saving.value = false;
    }
}

async function handleDelete(row) {
    try {
        await ElMessageBox.confirm(
            t('mock_server_page.delete_confirm', { name: row.description || row.path }),
            t('actions.confirm'),
            { type: 'warning' }
        );
        await mockServerApi.deleteRule(row.id);
        ElMessage.success(t('mock_server_page.messages.deleted'));
        await fetchRules();
    } catch { /* cancelled */ }
}

async function handleImport() {
    importing.value = true;
    try {
        const res = await mockServerApi.importPrebuilt();
        ElMessage.success(res.data.message);
        await fetchRules();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('mock_server_page.messages.import_failed'));
    } finally {
        importing.value = false;
    }
}

onMounted(() => {
    fetchRules();
    fetchConfig();
});
</script>

<style scoped>
.font-mono :deep(textarea) { font-family: 'Courier New', Courier, monospace; }
.text-primary { color: #0f172a; }
.text-warning { color: #e6a23c; }
.text-success { color: #67c23a; }
</style>
