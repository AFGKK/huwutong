<template>
  <div class="portal-api-keys">
    <div class="page-header">
      <div>
        <h2>我的 API Keys</h2>
        <p class="text-muted">API Key 用于程序化访问互物通 API，请妥善保管，不要分享给他人。</p>
      </div>
      <el-button type="primary" @click="showCreate">
        <el-icon><Plus /></el-icon> 新建 API Key
      </el-button>
    </div>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value text-primary">{{ stats.total || 0 }}</div>
          <div class="stat-label">总 Key 数</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value text-success">{{ stats.active || 0 }}</div>
          <div class="stat-label">活跃</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value text-warning">{{ stats.expired || 0 }}</div>
          <div class="stat-label">即将过期 / 已过期</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value text-info">{{ stats.usage_count || 0 }}</div>
          <div class="stat-label">本月调用次数</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- API Key 列表 -->
    <el-card shadow="hover">
      <el-table :data="keys" stripe v-loading="loading">
        <el-table-column label="名称" prop="name" min-width="130" />
        <el-table-column label="Key" width="210">
          <template #default="{ row }">
            <span class="font-mono">{{ maskKey(row.key) }}</span>
            <el-button size="small" text @click="copyKey(row.key)">
              <el-icon><CopyDocument /></el-icon>
            </el-button>
          </template>
        </el-table-column>
        <el-table-column label="权限" min-width="140">
          <template #default="{ row }">
            <el-tag v-for="ab in (row.abilities || row.permissions ? [row.permissions] : [])" :key="ab" size="small" style="margin-right:4px">{{ abLabel(ab) }}</el-tag>
            <span v-if="!row.abilities && !row.permissions" class="text-muted">全部</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="70">
          <template #default="{ row }">
            <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? '启用' : '已禁用' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="用量" width="130">
          <template #default="{ row }">
            <el-progress
              v-if="row.daily_quota"
              :percentage="Math.min(100, ((row.daily_usage || 0) / row.daily_quota) * 100)"
              :status="((row.daily_usage || 0) / row.daily_quota) >= 0.8 ? 'exception' : 'success'"
              :stroke-width="12"
              :text-inside="true"
              style="width:110px"
            >
              {{ row.daily_usage || 0 }}/{{ row.daily_quota }}
            </el-progress>
            <span v-else class="text-muted">{{ row.usage_count || 0 }} 次</span>
          </template>
        </el-table-column>
        <el-table-column label="到期时间" width="120">
          <template #default="{ row }">
            <span v-if="row.expires_at" :class="isExpiring(row.expires_at) ? 'expiring-text' : ''">
              {{ formatTime(row.expires_at) }}
            </span>
            <span v-else class="text-muted">永久</span>
          </template>
        </el-table-column>
        <el-table-column label="最后使用" width="140">
          <template #default="{ row }">{{ row.last_used_at ? formatTime(row.last_used_at) : '从未' }}</template>
        </el-table-column>
        <el-table-column label="操作" width="210" fixed="right">
          <template #default="{ row }">
            <el-button size="small" @click="showEdit(row)">编辑</el-button>
            <el-button size="small" :type="row.is_active ? 'warning' : 'success'" @click="toggleStatus(row)">
              {{ row.is_active ? '禁用' : '启用' }}
            </el-button>
            <el-popconfirm title="确定删除？关联服务将立即无法使用。" @confirm="handleDelete(row)">
              <template #reference>
                <el-button size="small" type="danger">删除</el-button>
              </template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
      <el-empty v-if="!keys.length && !loading" description="暂无 API Key，点击右上角创建" :image-size="60" />
    </el-card>

    <!-- 创建对话框 -->
    <el-dialog v-model="createVisible" title="新建 API Key" width="520px" :close-on-click-modal="false" destroy-on-close>
      <el-form :model="createForm" :rules="formRules" ref="createFormRef" label-position="top">
        <el-form-item label="名称" prop="name">
          <el-input v-model="createForm.name" placeholder="例如: 生产环境集成" maxlength="100" />
        </el-form-item>
        <el-form-item label="权限范围" prop="abilities">
          <el-checkbox-group v-model="createForm.abilities">
            <el-checkbox v-for="(label, key) in abilityOptions" :key="key" :label="key">{{ label }}</el-checkbox>
          </el-checkbox-group>
          <div class="text-muted" style="font-size:12px;margin-top:4px">不选择则默认为全部权限</div>
        </el-form-item>
        <el-form-item label="IP 白名单 (可选)">
          <el-input v-model="createForm.ip_whitelist" placeholder="逗号分隔，如 192.168.1.1,10.0.0.0/8" />
        </el-form-item>
        <el-form-item label="过期时间 (可选)">
          <el-date-picker v-model="createForm.expires_at" type="datetime" placeholder="永不过期" style="width:100%" value-format="YYYY-MM-DD HH:mm:ss" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createVisible = false">取消</el-button>
        <el-button type="primary" @click="handleCreate" :loading="submitting">创建</el-button>
      </template>
    </el-dialog>

    <!-- 创建成功：展示 Key -->
    <el-dialog v-model="showKeyResult" title="API Key 创建成功" width="520px" :close-on-click-modal="false" @close="onKeyResultClose">
      <el-alert type="warning" :closable="false" show-icon>
        <template #title><strong>请立即保存此密钥！</strong>关闭后将不再显示。</template>
      </el-alert>
      <div class="key-display-box">
        <div class="key-label">API Key</div>
        <div class="key-value-wrap">
          <code class="key-value">{{ lastCreatedKey }}</code>
          <el-button type="primary" size="small" @click="copyKey(lastCreatedKey)">
            <el-icon><CopyDocument /></el-icon> 复制
          </el-button>
        </div>
      </div>
      <template #footer>
        <el-button type="primary" @click="showKeyResult = false">我已保存</el-button>
      </template>
    </el-dialog>

    <!-- 编辑对话框 -->
    <el-dialog v-model="editVisible" title="编辑 API Key" width="520px" destroy-on-close>
      <el-form :model="editForm" :rules="formRules" ref="editFormRef" label-position="top">
        <el-form-item label="名称" prop="name">
          <el-input v-model="editForm.name" maxlength="100" />
        </el-form-item>
        <el-form-item label="权限范围">
          <el-checkbox-group v-model="editForm.abilities">
            <el-checkbox v-for="(label, key) in abilityOptions" :key="key" :label="key">{{ label }}</el-checkbox>
          </el-checkbox-group>
        </el-form-item>
        <el-form-item label="IP 白名单">
          <el-input v-model="editForm.ip_whitelist" placeholder="逗号分隔的 IP 地址或 CIDR" />
        </el-form-item>
        <el-form-item label="过期时间">
          <el-date-picker v-model="editForm.expires_at" type="datetime" placeholder="永不过期" style="width:100%" value-format="YYYY-MM-DD HH:mm:ss" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="editVisible = false">取消</el-button>
        <el-button type="primary" @click="handleUpdate" :loading="submitting">保存</el-button>
      </template>
    </el-dialog>

    <!-- 重新生成对话框 -->
    <el-dialog v-model="regenerateVisible" title="重新生成 API Key" width="400px">
      <p>重新生成后，旧 Key 将立即失效。确认继续？</p>
      <template #footer>
        <el-button @click="regenerateVisible = false">取消</el-button>
        <el-button type="warning" @click="handleRegenerate" :loading="submitting">确认重新生成</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, CopyDocument } from '@element-plus/icons-vue';
import apiKeyApi from '@/api/apiKey';

const loading = ref(false);
const submitting = ref(false);
const keys = ref([]);
const createVisible = ref(false);
const showKeyResult = ref(false);
const lastCreatedKey = ref('');
const regenerateVisible = ref(false);
const editVisible = ref(false);
const createFormRef = ref(null);
const editFormRef = ref(null);
const selectedKey = ref(null);
const editingId = ref(null);
const abilityOptions = ref({});

const stats = reactive({ total: 0, active: 0, expired: 0, usage_count: 0 });

const createForm = reactive({
    name: '',
    abilities: [],
    ip_whitelist: '',
    expires_at: null,
});

const editForm = reactive({
    name: '',
    abilities: [],
    ip_whitelist: '',
    expires_at: null,
});

const formRules = {
    name: [{ required: true, message: '请输入名称', trigger: 'blur' }],
};

onMounted(() => {
    loadAbilities();
    loadStats();
    loadKeys();
});

async function loadAbilities() {
    try {
        const res = await apiKeyApi.getTierConfig();
        const perms = res.data?.permissions || [];
        const map = {};
        perms.forEach(p => { map[p.value] = p.label; });
        abilityOptions.value = map;
    } catch { /* fallback */ }
}

async function loadStats() {
    try {
        const res = await apiKeyApi.myOverview();
        const d = res.data || {};
        stats.total = d.total_keys || 0;
        stats.active = d.active_keys || 0;
        stats.expired = d.keys_expired || 0;
        stats.usage_count = d.total_usage_count || 0;
    } catch { /* ignore */ }
}

async function loadKeys() {
    loading.value = true;
    try {
        const res = await apiKeyApi.list({ per_page: 50 });
        keys.value = res.data?.data || res.data?.keys || [];
    } finally { loading.value = false; }
}

function abLabel(key) {
    const map = abilityOptions.value;
    return map[key] || key;
}

function maskKey(key) {
    if (!key) return '—';
    if (key.length > 12) return key.substring(0, 8) + '••••' + key.substring(key.length - 4);
    return key.substring(0, 4) + '••••';
}

function copyKey(key) {
    if (!key) return;
    navigator.clipboard.writeText(key).then(() => ElMessage.success('已复制'));
}

function showCreate() {
    createForm.name = '';
    createForm.abilities = [];
    createForm.ip_whitelist = '';
    createForm.expires_at = null;
    createVisible.value = true;
}

async function handleCreate() {
    const valid = await createFormRef.value.validate().catch(() => false);
    if (!valid) return;
    submitting.value = true;
    try {
        const data = {
            name: createForm.name,
            abilities: createForm.abilities.length > 0 ? createForm.abilities : ['*'],
            ip_whitelist: createForm.ip_whitelist || undefined,
            expires_at: createForm.expires_at || undefined,
        };
        const res = await apiKeyApi.create(data);
        lastCreatedKey.value = res.data?.plain_text_key || res.data?.key || res.key || '';
        createVisible.value = false;
        showKeyResult.value = true;
        loadKeys();
        loadStats();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || '创建失败');
    } finally { submitting.value = false; }
}

function onKeyResultClose() {
    lastCreatedKey.value = '';
}

function showEdit(row) {
    editingId.value = row.id;
    editForm.name = row.name || '';
    editForm.abilities = row.abilities || [];
    editForm.ip_whitelist = row.ip_whitelist || '';
    editForm.expires_at = row.expires_at || null;
    editVisible.value = true;
}

async function handleUpdate() {
    const valid = await editFormRef.value.validate().catch(() => false);
    if (!valid) return;
    submitting.value = true;
    try {
        await apiKeyApi.update(editingId.value, {
            name: editForm.name,
            abilities: editForm.abilities,
            ip_whitelist: editForm.ip_whitelist || undefined,
            expires_at: editForm.expires_at || undefined,
        });
        ElMessage.success('更新成功');
        editVisible.value = false;
        loadKeys();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || '更新失败');
    } finally { submitting.value = false; }
}

function showRegenerate(row) {
    selectedKey.value = row;
    regenerateVisible.value = true;
}

async function handleRegenerate() {
    submitting.value = true;
    try {
        const res = await apiKeyApi.regenerate(selectedKey.value.id);
        lastCreatedKey.value = res.data?.plain_text_key || res.data?.key || res.key || '';
        regenerateVisible.value = false;
        showKeyResult.value = true;
        loadKeys();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || '重新生成失败');
    } finally { submitting.value = false; }
}

async function toggleStatus(row) {
    try {
        await apiKeyApi.toggleActive(row.id);
        ElMessage.success(row.is_active ? '已禁用' : '已启用');
        loadKeys();
        loadStats();
    } catch (err) {
        ElMessage.error('操作失败');
    }
}

async function handleDelete(row) {
    try {
        await ElMessageBox.confirm(`确定删除 API Key "${row.name}"？此操作不可撤销。`, '确认删除');
        await apiKeyApi.delete(row.id);
        ElMessage.success('已删除');
        loadKeys();
        loadStats();
    } catch (e) { if (e !== 'cancel') ElMessage.error('删除失败'); }
}

function formatTime(t) {
    if (!t) return '—';
    return new Date(t).toLocaleString('zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

function isExpiring(t) {
    if (!t) return false;
    const days = (new Date(t) - new Date()) / (1000 * 60 * 60 * 24);
    return days <= 14 && days > 0;
}
</script>

<style scoped>
.portal-api-keys { padding: 0 4px; }
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}
.page-header h2 { margin: 0 0 4px; font-size: 22px; }
.text-muted { color: #909399; font-size: 13px; }
.mb-4 { margin-bottom: 16px; }
.stat-card { cursor: default; }
.stat-card .stat-value { font-size: 28px; font-weight: 700; }
.stat-card .stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.text-primary { color: #409eff; }
.text-success { color: #67c23a; }
.text-warning { color: #e6a23c; }
.text-info { color: #909399; }
.font-mono { font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', monospace; font-size: 13px; }
.expiring-text { color: #e6a23c; font-weight: 600; }
.key-display-box {
    margin: 16px 0;
    padding: 16px;
    background: #f5f7fa;
    border-radius: 6px;
    border: 1px solid #e4e7ed;
}
.key-label { font-size: 13px; color: #909399; margin-bottom: 8px; }
.key-value-wrap {
    display: flex;
    align-items: center;
    gap: 8px;
}
.key-value {
    flex: 1;
    padding: 8px 12px;
    background: #fff;
    border: 1px solid #dcdfe6;
    border-radius: 4px;
    font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', monospace;
    font-size: 14px;
    word-break: break-all;
    user-select: all;
}
</style>
