<template>
    <div class="api-keys-page">
        <div class="page-header">
            <div class="header-left">
                <h2>API 密钥管理</h2>
                <span class="header-subtitle">管理用于 API 调用的密钥，创建、轮换和吊销访问凭证</span>
            </div>
            <div class="header-right">
                <el-button @click="fetchKeys">
                    <el-icon><Refresh /></el-icon>
                    刷新
                </el-button>
                <el-button type="primary" @click="showCreate = true">
                    <el-icon><Plus /></el-icon>
                    创建密钥
                </el-button>
            </div>
        </div>

        <!-- 概览统计 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">密钥总数</div>
                        <div class="stat-value">{{ keys.length }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">已启用</div>
                        <div class="stat-value text-success">{{ activeCount }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">已禁用</div>
                        <div class="stat-value text-danger">{{ keys.length - activeCount }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">最大限额</div>
                        <div class="stat-value">{{ maxKeys }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 密钥列表 -->
        <el-card shadow="never">
            <el-table :data="keys" v-loading="loading" stripe>
                <el-table-column label="名称" min-width="160">
                    <template #default="{ row }">
                        <div class="key-name">
                            <span class="name-text">{{ row.name }}</span>
                            <el-tag v-if="!row.is_active" size="small" type="danger" effect="dark">已禁用</el-tag>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="Key ID" min-width="200">
                    <template #default="{ row }">
                        <div class="key-id-cell">
                            <code class="key-id-text">{{ row.key_id }}</code>
                            <el-button text size="small" @click="copyText(row.key_id)">
                                <el-icon><CopyDocument /></el-icon>
                            </el-button>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="最近使用" width="170">
                    <template #default="{ row }">
                        {{ row.last_used_at ? formatTime(row.last_used_at) : '从未使用' }}
                    </template>
                </el-table-column>
                <el-table-column label="过期时间" width="170">
                    <template #default="{ row }">
                        <span v-if="row.expires_at" :class="isExpired(row.expires_at) ? 'text-danger' : ''">
                            {{ formatTime(row.expires_at) }}
                            <el-tag v-if="isExpired(row.expires_at)" size="small" type="danger">已过期</el-tag>
                        </span>
                        <span v-else class="text-muted">永不过期</span>
                    </template>
                </el-table-column>
                <el-table-column label="创建时间" width="170">
                    <template #default="{ row }">
                        {{ formatTime(row.created_at) }}
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="90">
                    <template #default="{ row }">
                        <el-switch
                            :model-value="row.is_active"
                            :loading="togglingId === row.id"
                            size="small"
                            @change="toggleActive(row)"
                        />
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button text size="small" @click="showEdit(row)">编辑</el-button>
                        <el-button text size="small" type="warning" @click="handleRegenerate(row)">重新生成</el-button>
                        <el-popconfirm
                            title="确定要删除此密钥吗？"
                            confirm-button-text="删除"
                            @confirm="handleDelete(row)"
                        >
                            <template #reference>
                                <el-button text size="small" type="danger">删除</el-button>
                            </template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>
            <el-empty v-if="!loading && keys.length === 0" :image-size="80" description="暂无 API 密钥，点击上方按钮创建" />
        </el-card>

        <!-- 创建密钥 Dialog -->
        <el-dialog v-model="showCreate" title="创建 API 密钥" width="480px" :close-on-click-modal="false" @close="resetForm">
            <el-form :model="createForm" ref="createFormRef" :rules="createRules" label-width="100px">
                <el-form-item label="密钥名称" prop="name">
                    <el-input v-model="createForm.name" placeholder="如：生产环境、测试环境" maxlength="100" show-word-limit />
                </el-form-item>
                <el-form-item label="过期时间" prop="expires_at">
                    <el-date-picker
                        v-model="createForm.expires_at"
                        type="datetime"
                        placeholder="留空则永不过期"
                        clearable
                        style="width: 100%"
                        value-format="YYYY-MM-DD HH:mm:ss"
                        :disabled-date="(time) => time <= Date.now()"
                    />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showCreate = false">取消</el-button>
                <el-button type="primary" @click="handleCreate" :loading="creating">创建</el-button>
            </template>
        </el-dialog>

        <!-- 创建成功 - 显示密钥 Dialog -->
        <el-dialog v-model="showSecret" title="密钥创建成功" width="500px" :close-on-click-modal="false">
            <el-alert
                title="请立即复制并安全保存此密钥"
                type="warning"
                :closable="false"
                show-icon
                description="关闭此对话框后将无法再次查看密钥内容。"
            />
            <div class="secret-display">
                <div class="secret-label">Key ID</div>
                <div class="secret-row">
                    <code class="secret-text">{{ newKeyData.key_id }}</code>
                    <el-button text @click="copyText(newKeyData.key_id)"><el-icon><CopyDocument /></el-icon></el-button>
                </div>
                <div class="secret-label mt-3">Secret Key</div>
                <div class="secret-row">
                    <code class="secret-text secret-value">{{ showSecretText ? newKeyData.secret : '••••••••••••••••••••••••••••' }}</code>
                    <el-button text @click="copyText(newKeyData.secret)"><el-icon><CopyDocument /></el-icon></el-button>
                    <el-button text @click="showSecretText = !showSecretText">
                        <el-icon><View v-if="!showSecretText" /><Hide v-else /></el-icon>
                    </el-button>
                </div>
                <div class="secret-info mt-3">
                    <div>名称: {{ newKeyData.name }}</div>
                    <div v-if="newKeyData.expires_at">过期: {{ formatTime(newKeyData.expires_at) }}</div>
                    <div>创建时间: {{ formatTime(newKeyData.created_at) }}</div>
                </div>
            </div>
            <template #footer>
                <el-button type="primary" @click="showSecret = false; resetAndRefresh()">我已安全保存</el-button>
            </template>
        </el-dialog>

        <!-- 编辑密钥 Dialog -->
        <el-dialog v-model="showEditDialog" title="编辑密钥" width="480px">
            <el-form :model="editForm" ref="editFormRef" :rules="editRules" label-width="100px">
                <el-form-item label="密钥名称" prop="name">
                    <el-input v-model="editForm.name" maxlength="100" show-word-limit />
                </el-form-item>
                <el-form-item label="过期时间" prop="expires_at">
                    <el-date-picker
                        v-model="editForm.expires_at"
                        type="datetime"
                        placeholder="留空则永不过期"
                        clearable
                        style="width: 100%"
                        value-format="YYYY-MM-DD HH:mm:ss"
                    />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showEditDialog = false">取消</el-button>
                <el-button type="primary" @click="handleUpdate" :loading="updating">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Refresh, CopyDocument, View, Hide } from '@element-plus/icons-vue';
import apiKeyApi from '@/api/apiKey';

const loading = ref(false);
const creating = ref(false);
const updating = ref(false);
const togglingId = ref(null);
const showCreate = ref(false);
const showSecret = ref(false);
const showSecretText = ref(false);
const showEditDialog = ref(false);
const maxKeys = 10;

const keys = ref([]);
const newKeyData = ref({});
const editForm = ref({});
const currentEditKey = ref(null);
const createFormRef = ref(null);
const editFormRef = ref(null);

const createForm = ref({
    name: '',
    expires_at: null,
});

const createRules = {
    name: [{ required: true, message: '请输入密钥名称', trigger: 'blur' }],
};

const editRules = {
    name: [{ required: true, message: '请输入密钥名称', trigger: 'blur' }],
};

const activeCount = computed(() => keys.value.filter(k => k.is_active).length);

function formatTime(time) {
    if (!time) return '—';
    return new Date(time).toLocaleString('zh-CN');
}

function isExpired(date) {
    if (!date) return false;
    return new Date(date) < new Date();
}

function resetForm() {
    createForm.value = { name: '', expires_at: null };
    createFormRef.value?.resetFields();
}

function resetAndRefresh() {
    resetForm();
    fetchKeys();
}

function copyText(text) {
    navigator.clipboard.writeText(text).then(() => {
        ElMessage.success('已复制到剪贴板');
    }).catch(() => {
        // fallback
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        ElMessage.success('已复制到剪贴板');
    });
}

function showEdit(row) {
    currentEditKey.value = row;
    editForm.value = {
        name: row.name,
        expires_at: row.expires_at,
    };
    showEditDialog.value = true;
}

async function fetchKeys() {
    loading.value = true;
    try {
        const { data: res } = await apiKeyApi.list();
        if (res.success) {
            keys.value = res.data || [];
        }
    } catch {
        ElMessage.error('加载 API 密钥列表失败');
    } finally {
        loading.value = false;
    }
}

async function handleCreate() {
    const valid = await createFormRef.value?.validate().catch(() => false);
    if (!valid) return;

    creating.value = true;
    try {
        const { data: res } = await apiKeyApi.create(createForm.value);
        if (res.success) {
            newKeyData.value = res.data;
            showSecret.value = true;
            showSecretText.value = true;
            showCreate.value = false;
            ElMessage.success('密钥创建成功');
        } else {
            ElMessage.error(res.message || '创建失败');
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.error?.message || '创建失败');
    } finally {
        creating.value = false;
    }
}

async function handleUpdate() {
    const valid = await editFormRef.value?.validate().catch(() => false);
    if (!valid) return;

    updating.value = true;
    try {
        const { data: res } = await apiKeyApi.update(currentEditKey.value.id, {
            name: editForm.value.name,
            expires_at: editForm.value.expires_at || null,
        });
        if (res.success) {
            ElMessage.success('密钥已更新');
            showEditDialog.value = false;
            fetchKeys();
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.error?.message || '更新失败');
    } finally {
        updating.value = false;
    }
}

async function handleDelete(row) {
    try {
        const { data: res } = await apiKeyApi.delete(row.id);
        if (res.success) {
            ElMessage.success('密钥已删除');
            fetchKeys();
        }
    } catch {
        ElMessage.error('删除失败');
    }
}

async function handleRegenerate(row) {
    try {
        await ElMessageBox.confirm(
            `重新生成后，旧的 "Secret Key" 将立即失效。确认重新生成 "${row.name}" 的密钥？`,
            '重新生成密钥',
            { confirmButtonText: '确认重新生成', cancelButtonText: '取消', type: 'warning' },
        );

        const { data: res } = await apiKeyApi.regenerate(row.id);
        if (res.success) {
            newKeyData.value = {
                key_id: res.data.key_id,
                secret: res.data.secret,
                name: row.name,
                created_at: new Date().toISOString(),
            };
            showSecret.value = true;
            showSecretText.value = true;
            ElMessage.success('密钥已重新生成');
            fetchKeys();
        }
    } catch {
        // cancelled
    }
}

async function toggleActive(row) {
    togglingId.value = row.id;
    try {
        const { data: res } = await apiKeyApi.update(row.id, { is_active: !row.is_active });
        if (res.success) {
            ElMessage.success(row.is_active ? '密钥已禁用' : '密钥已启用');
            fetchKeys();
        }
    } catch {
        ElMessage.error('操作失败');
    } finally {
        togglingId.value = null;
    }
}

onMounted(() => {
    fetchKeys();
});
</script>

<style scoped>
.api-keys-page { padding: 20px; }

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}
.header-left h2 { margin: 0; font-size: 20px; }
.header-subtitle {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-left: 12px;
}

.mb-4 { margin-bottom: 16px; }
.mt-3 { margin-top: 12px; }

.stat-item {
    text-align: center;
    padding: 8px 0;
}
.stat-label {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-bottom: 6px;
}
.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--el-text-color-primary);
}
.text-success { color: var(--el-color-success); }
.text-danger { color: var(--el-color-danger); }
.text-muted { color: var(--el-text-color-placeholder); }

.key-name {
    display: flex;
    align-items: center;
    gap: 8px;
}
.name-text {
    font-weight: 500;
}

.key-id-cell {
    display: flex;
    align-items: center;
    gap: 4px;
}
.key-id-text {
    font-family: 'SF Mono', Monaco, 'Cascadia Code', monospace;
    font-size: 12px;
    color: var(--el-text-color-regular);
    user-select: all;
}

/* Secret Dialog */
.secret-display {
    margin-top: 16px;
}
.secret-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--el-text-color-secondary);
    margin-bottom: 6px;
}
.secret-row {
    display: flex;
    align-items: center;
    gap: 6px;
    background: var(--el-color-info-light-9);
    padding: 8px 12px;
    border-radius: 6px;
}
.secret-text {
    flex: 1;
    font-family: 'SF Mono', Monaco, 'Cascadia Code', monospace;
    font-size: 13px;
    word-break: break-all;
    user-select: all;
}
.secret-value {
    letter-spacing: 1px;
}
.secret-info {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    line-height: 1.8;
}

:deep(.el-card__body) { padding: 16px; }
</style>
