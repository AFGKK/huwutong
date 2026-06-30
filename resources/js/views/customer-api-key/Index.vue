<template>
    <div class="customer-api-keys-page">
        <div class="page-header">
            <h2>客户 API Key 管理 <small class="text-muted">M2-96</small></h2>
            <div class="header-actions">
                <el-button type="primary" @click="showCreate = true">
                    <el-icon><Plus /></el-icon> 创建 API Key
                </el-button>
            </div>
        </div>

        <el-tabs v-model="activeTab">
            <!-- ═══════════ 概览 ═══════════ -->
            <el-tab-pane label="概览" name="dashboard">
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item"><div class="stat-value text-primary">{{ dashboard.total || 0 }}</div><div class="stat-label">总 Key 数</div></div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item"><div class="stat-value text-success">{{ dashboard.active || 0 }}</div><div class="stat-label">活跃</div></div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item"><div class="stat-value text-warning">{{ dashboard.expired || 0 }}</div><div class="stat-label">已过期</div></div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item"><div class="stat-value text-info">{{ dashboard.recent || 0 }}</div><div class="stat-label">7日内使用</div></div>
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>

            <!-- ═══════════ Key 列表 ═══════════ -->
            <el-tab-pane label="API Key 列表" name="list">
                <el-card shadow="never">
                    <el-table v-loading="loading" :data="keys" stripe border style="width:100%">
                        <el-table-column prop="name" label="名称" width="160" />
                        <el-table-column label="Key" width="200">
                            <template #default="{ row }">
                                <span class="font-mono">{{ row.prefix }}****{{ row.key.slice(-6) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="权限" min-width="180">
                            <template #default="{ row }">
                                <el-tag v-if="!row.abilities || row.abilities.includes('*')" size="small" type="success">全部</el-tag>
                                <template v-else>
                                    <el-tag v-for="a in row.abilities" :key="a" size="small" class="mr-1">{{ a }}</el-tag>
                                </template>
                            </template>
                        </el-table-column>
                        <el-table-column label="IP 白名单" width="150" show-overflow-tooltip>
                            <template #default="{ row }">{{ row.ip_whitelist || '-' }}</template>
                        </el-table-column>
                        <el-table-column label="过期时间" width="160">
                            <template #default="{ row }">{{ row.expires_at ? formatDate(row.expires_at) : '永不过期' }}</template>
                        </el-table-column>
                        <el-table-column label="状态" width="80">
                            <template #default="{ row }">
                                <el-tag :type="row.is_active && !row.is_expired ? 'success' : 'danger'" size="small">
                                    {{ row.is_active && !row.is_expired ? '启用' : '禁用' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="最后使用" width="160">
                            <template #default="{ row }">{{ row.last_used_at ? formatDate(row.last_used_at) : '-' }}</template>
                        </el-table-column>
                        <el-table-column label="操作" width="160" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" @click="handleEdit(row)">编辑</el-button>
                                <el-button size="small" :type="row.is_active ? 'warning' : 'success'" @click="handleToggle(row)">
                                    {{ row.is_active ? '禁用' : '启用' }}
                                </el-button>
                                <el-popconfirm title="确定删除？" @confirm="handleDelete(row)">
                                    <template #reference>
                                        <el-button size="small" type="danger">删除</el-button>
                                    </template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <!-- 创建对话框 -->
        <el-dialog v-model="showCreate" title="创建 API Key" width="520px" :close-on-click-modal="false">
            <el-form ref="createForm" :model="form" :rules="rules" label-position="top">
                <el-form-item label="名称" prop="name">
                    <el-input v-model="form.name" placeholder="例如：生产环境集成" maxlength="100" />
                </el-form-item>
                <el-form-item label="权限">
                    <el-checkbox-group v-model="form.abilities">
                        <el-checkbox v-for="(label, key) in abilityOptions" :key="key" :label="key">{{ label }}</el-checkbox>
                    </el-checkbox-group>
                    <div class="text-muted" style="font-size:12px;margin-top:4px;">不选择表示全部权限</div>
                </el-form-item>
                <el-form-item label="IP 白名单（可选）">
                    <el-input v-model="form.ip_whitelist" placeholder="逗号分隔多个 IP，如：192.168.1.1,10.0.0.1" />
                </el-form-item>
                <el-form-item label="过期时间（可选）">
                    <el-date-picker v-model="form.expires_at" type="datetime" placeholder="选择过期时间" style="width:100%" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showCreate = false">取消</el-button>
                <el-button type="primary" @click="handleCreate">创建</el-button>
            </template>
        </el-dialog>

        <!-- 创建成功后显示明文 Key -->
        <el-dialog v-model="showKeyResult" title="API Key 创建成功" width="480px">
            <el-alert title="请妥善保管此密钥，创建后不再显示！" type="warning" :closable="false" show-icon class="mb-4" />
            <div class="key-result-box">
                <code class="font-mono">{{ newKeyPlainText }}</code>
                <el-button size="small" @click="copyKey">复制</el-button>
            </div>
        </el-dialog>

        <!-- 编辑对话框 -->
        <el-dialog v-model="showEdit" title="编辑 API Key" width="480px">
            <el-form label-position="top">
                <el-form-item label="名称">
                    <el-input v-model="editForm.name" maxlength="100" />
                </el-form-item>
                <el-form-item label="权限">
                    <el-checkbox-group v-model="editForm.abilities">
                        <el-checkbox v-for="(label, key) in abilityOptions" :key="key" :label="key">{{ label }}</el-checkbox>
                    </el-checkbox-group>
                </el-form-item>
                <el-form-item label="IP 白名单">
                    <el-input v-model="editForm.ip_whitelist" placeholder="逗号分隔" />
                </el-form-item>
                <el-form-item label="过期时间">
                    <el-date-picker v-model="editForm.expires_at" type="datetime" placeholder="永不过期" style="width:100%" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showEdit = false">取消</el-button>
                <el-button type="primary" @click="handleUpdate">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import { getDashboard, getMyKeys, createKey, updateKey, deleteKey, toggleKey, getAbilities } from '@/api/customerApiKey';

const activeTab = ref('dashboard');
const loading = ref(false);
const dashboard = ref({});
const keys = ref([]);
const abilityOptions = ref({});

const showCreate = ref(false);
const showEdit = ref(false);
const showKeyResult = ref(false);
const newKeyPlainText = ref('');

const form = reactive({
    name: '',
    abilities: [],
    ip_whitelist: '',
    expires_at: null,
});

const editForm = reactive({
    id: null,
    name: '',
    abilities: [],
    ip_whitelist: '',
    expires_at: null,
});

const rules = {
    name: [{ required: true, message: '请输入名称', trigger: 'blur' }],
};

async function loadDashboard() {
    try {
        const { data: res } = await getDashboard();
        dashboard.value = res.data || {};
    } catch { dashboard.value = {}; }
}

async function loadKeys() {
    loading.value = true;
    try {
        const { data: res } = await getMyKeys();
        keys.value = res.data?.data || [];
    } catch { keys.value = []; }
    finally { loading.value = false; }
}

async function loadAbilities() {
    try {
        const { data: res } = await getAbilities();
        abilityOptions.value = res.data || {};
    } catch { abilityOptions.value = {}; }
}

async function handleCreate() {
    if (!form.name) { ElMessage.warning('请输入名称'); return; }
    try {
        const { data: res } = await createKey({ ...form });
        newKeyPlainText.value = res.data?.plain_text_key || '';
        showKeyResult.value = true;
        showCreate.value = false;
        form.name = '';
        form.abilities = [];
        form.ip_whitelist = '';
        form.expires_at = null;
        loadKeys();
        loadDashboard();
    } catch { /* */ }
}

function copyKey() {
    navigator.clipboard.writeText(newKeyPlainText.value);
    ElMessage.success('已复制');
}

function handleEdit(row) {
    editForm.id = row.id;
    editForm.name = row.name;
    editForm.abilities = row.abilities || [];
    editForm.ip_whitelist = row.ip_whitelist || '';
    editForm.expires_at = row.expires_at;
    showEdit.value = true;
}

async function handleUpdate() {
    try {
        await updateKey(editForm.id, {
            name: editForm.name,
            abilities: editForm.abilities,
            ip_whitelist: editForm.ip_whitelist,
            expires_at: editForm.expires_at,
        });
        ElMessage.success('更新成功');
        showEdit.value = false;
        loadKeys();
    } catch { /* */ }
}

async function handleToggle(row) {
    try {
        const { data: res } = await toggleKey(row.id);
        ElMessage.success(res.message || (row.is_active ? '已禁用' : '已启用'));
        loadKeys();
    } catch { /* */ }
}

async function handleDelete(row) {
    try {
        await deleteKey(row.id);
        ElMessage.success('已删除');
        loadKeys();
        loadDashboard();
    } catch { /* */ }
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}

onMounted(() => {
    loadDashboard();
    loadKeys();
    loadAbilities();
});
</script>

<style scoped>
.customer-api-keys-page { padding: 20px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.page-header h2 { margin: 0; font-size: 20px; }
.text-muted { color: var(--el-text-color-secondary); }
.mb-4 { margin-bottom: 16px; }
.mr-1 { margin-right: 4px; }
.stat-item { text-align: center; padding: 12px 0; }
.stat-value { font-size: 28px; font-weight: 700; }
.stat-label { font-size: 13px; color: var(--el-text-color-secondary); margin-top: 4px; }
.text-primary { color: var(--el-color-primary); }
.text-success { color: var(--el-color-success); }
.text-warning { color: var(--el-color-warning); }
.text-info { color: var(--el-color-info); }
.font-mono { font-family: 'Courier New', Courier, monospace; }
.key-result-box {
    display: flex; align-items: center; gap: 12px;
    background: var(--el-bg-color-page); padding: 12px 16px; border-radius: 6px;
}
.key-result-box code { flex: 1; word-break: break-all; font-size: 14px; }
</style>
