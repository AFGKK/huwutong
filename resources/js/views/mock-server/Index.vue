<template>
    <div>
        <el-breadcrumb separator="/" class="mb-4">
            <el-breadcrumb-item :to="{ path: '/admin/dashboard' }">首页</el-breadcrumb-item>
            <el-breadcrumb-item>开发者工具</el-breadcrumb-item>
            <el-breadcrumb-item>API Mock Server</el-breadcrumb-item>
        </el-breadcrumb>

        <el-row :gutter="20" class="mb-4">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-gray-800">{{ rules.length }}</div>
                    <div class="text-sm text-gray-500 mt-1">Mock 规则数</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-success">{{ activeCount }}</div>
                    <div class="text-sm text-gray-500 mt-1">已启用</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-primary">{{ config.defaults?.delay_ms ?? 0 }}ms</div>
                    <div class="text-sm text-gray-500 mt-1">默认延迟</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-warning">{{ config.defaults?.error_rate ?? 0 }}%</div>
                    <div class="text-sm text-gray-500 mt-1">默认错误率</div>
                </el-card>
            </el-col>
        </el-row>

        <el-card>
            <template #header>
                <div class="flex items-center justify-between">
                    <span class="font-semibold">Mock 规则管理</span>
                    <div>
                        <el-button @click="handleImport" :loading="importing">导入预建规则</el-button>
                        <el-button type="primary" @click="showCreate = true">新建规则</el-button>
                    </div>
                </div>
            </template>

            <el-table :data="rules" v-loading="loading" stripe style="width:100%">
                <el-table-column label="方法" width="80">
                    <template #default="{ row }">
                        <el-tag :type="methodTag(row.method)" size="small">{{ row.method }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="path" label="路径" min-width="250" />
                <el-table-column prop="status_code" label="状态码" width="80" />
                <el-table-column prop="description" label="描述" min-width="180" />
                <el-table-column label="延迟" width="70">
                    <template #default="{ row }">{{ row.delay_ms || '-' }}ms</template>
                </el-table-column>
                <el-table-column label="状态" width="70">
                    <template #default="{ row }">
                        <el-tag :type="row.is_active ? 'success' : 'info'" size="small">{{ row.is_active ? '启用' : '禁用' }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="160" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="showEdit(row)">编辑</el-button>
                        <el-button size="small" type="danger" @click="handleDelete(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- 创建/编辑对话框 -->
        <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑 Mock 规则' : '新建 Mock 规则'" width="700px">
            <el-form :model="form" label-position="top">
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item label="HTTP 方法" required>
                            <el-select v-model="form.method" style="width:100%">
                                <el-option label="GET" value="GET" />
                                <el-option label="POST" value="POST" />
                                <el-option label="PUT" value="PUT" />
                                <el-option label="PATCH" value="PATCH" />
                                <el-option label="DELETE" value="DELETE" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="路径" required>
                            <el-input v-model="form.path" placeholder="/api/license/activate" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="4">
                        <el-form-item label="状态码">
                            <el-input-number v-model="form.status_code" :min="100" :max="599" style="width:100%" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item label="描述">
                    <el-input v-model="form.description" placeholder="规则说明" />
                </el-form-item>
                <el-form-item label="响应 JSON" required>
                    <el-input v-model="responseText" type="textarea" :rows="8" class="font-mono" placeholder='{"success": true, "data": {...}}' />
                </el-form-item>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="延迟 (ms)">
                            <el-input-number v-model="form.delay_ms" :min="0" :max="30000" style="width:100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="启用">
                            <el-switch v-model="form.is_active" />
                        </el-form-item>
                    </el-col>
                </el-row>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" @click="handleSave" :loading="saving">{{ isEdit ? '更新' : '创建' }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import mockServerApi from '../../api/mockServer';

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
            ElMessage.success('规则已更新');
        } else {
            await mockServerApi.createRule(form.value);
            ElMessage.success('规则已创建');
        }
        dialogVisible.value = false;
        await fetchRules();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败');
    } finally {
        saving.value = false;
    }
}

async function handleDelete(row) {
    try {
        await ElMessageBox.confirm(`确定删除规则 "${row.description || row.path}"？`, '确认', { type: 'warning' });
        await mockServerApi.deleteRule(row.id);
        ElMessage.success('规则已删除');
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
        ElMessage.error(e.response?.data?.message || '导入失败');
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
.text-primary { color: #409eff; }
.text-warning { color: #e6a23c; }
.text-success { color: #67c23a; }
</style>
