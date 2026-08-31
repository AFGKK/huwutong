<template>
  <div class="portal-api-keys">
    <div class="page-header">
      <div>
        <h2>{{ $t('portal.apikeys_title') }}</h2>
        <p class="text-muted">{{ $t('portal.apikeys_subtitle') }}</p>
      </div>
      <el-button type="primary" @click="showCreate">
        <el-icon><Plus /></el-icon> {{ $t('portal.new_apikey') }}
      </el-button>
    </div>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value text-primary">{{ stats.total || 0 }}</div>
          <div class="stat-label">{{ $t('portal.total_keys') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value text-success">{{ stats.active || 0 }}</div>
          <div class="stat-label">{{ $t('portal.st_active') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value text-warning">{{ stats.expired || 0 }}</div>
          <div class="stat-label">{{ $t('portal.keys_expiring') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value text-info">{{ stats.usage_count || 0 }}</div>
          <div class="stat-label">{{ $t('portal.month_calls') }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- API Key 列表 -->
    <el-card shadow="hover">
      <el-table :data="keys" stripe v-loading="loading">
        <el-table-column :label="$t('portal.name')" prop="name" min-width="130" />
        <el-table-column label="Key" width="210">
          <template #default="{ row }">
            <span class="font-mono">{{ maskKey(row.key) }}</span>
            <el-button size="small" text @click="copyKey(row.key)">
              <el-icon><CopyDocument /></el-icon>
            </el-button>
          </template>
        </el-table-column>
        <el-table-column :label="$t('portal.permissions')" min-width="140">
          <template #default="{ row }">
            <el-tag v-for="ab in (row.abilities || row.permissions ? [row.permissions] : [])" :key="ab" size="small" style="margin-right:4px">{{ abLabel(ab) }}</el-tag>
            <span v-if="!row.abilities && !row.permissions" class="text-muted">{{ $t('portal.all_perms') }}</span>
          </template>
        </el-table-column>
        <el-table-column :label="$t('portal.status')" width="70">
          <template #default="{ row }">
            <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? $t('portal.enabled_status') : $t('portal.disabled_status') }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="$t('portal.usage')" width="130">
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
            <span v-else class="text-muted">{{ $t('portal.times_n', { n: row.usage_count || 0 }) }}</span>
          </template>
        </el-table-column>
        <el-table-column :label="$t('portal.expires_at')" width="120">
          <template #default="{ row }">
            <span v-if="row.expires_at" :class="isExpiring(row.expires_at) ? 'expiring-text' : ''">
              {{ formatTime(row.expires_at) }}
            </span>
            <span v-else class="text-muted">{{ $t('portal.lifetime') }}</span>
          </template>
        </el-table-column>
        <el-table-column :label="$t('portal.last_used')" width="140">
          <template #default="{ row }">{{ row.last_used_at ? formatTime(row.last_used_at) : $t('portal.never') }}</template>
        </el-table-column>
        <el-table-column :label="$t('portal.actions')" width="210" fixed="right">
          <template #default="{ row }">
            <el-button size="small" @click="showEdit(row)">{{ $t('portal.edit') }}</el-button>
            <el-button size="small" :type="row.is_active ? 'warning' : 'success'" @click="toggleStatus(row)">
              {{ row.is_active ? $t('actions.disable') : $t('actions.enable') }}
            </el-button>
            <el-popconfirm :title="$t('portal.delete_key_hint')" @confirm="handleDelete(row)">
              <template #reference>
                <el-button size="small" type="danger">{{ $t('actions.delete') }}</el-button>
              </template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
      <el-empty v-if="!keys.length && !loading" :description="$t('portal.no_apikeys')" :image-size="60" />
    </el-card>

    <!-- 创建对话框 -->
    <el-dialog v-model="createVisible" :title="$t('portal.new_apikey')" width="520px" :close-on-click-modal="false" destroy-on-close>
      <el-form :model="createForm" :rules="formRules" ref="createFormRef" label-position="top">
        <el-form-item :label="$t('portal.name')" prop="name">
          <el-input v-model="createForm.name" :placeholder="$t('portal.key_name_ph')" maxlength="100" />
        </el-form-item>
        <el-form-item :label="$t('portal.ability_scope')" prop="abilities">
          <el-checkbox-group v-model="createForm.abilities">
            <el-checkbox v-for="(label, key) in abilityOptions" :key="key" :label="key">{{ label }}</el-checkbox>
          </el-checkbox-group>
          <div class="text-muted" style="font-size:12px;margin-top:4px">{{ $t('portal.ability_all_hint') }}</div>
        </el-form-item>
        <el-form-item :label="$t('portal.ip_whitelist_opt')">
          <el-input v-model="createForm.ip_whitelist" :placeholder="$t('portal.ip_whitelist_ph')" />
        </el-form-item>
        <el-form-item :label="$t('portal.expires_opt')">
          <el-date-picker v-model="createForm.expires_at" type="datetime" :placeholder="$t('portal.never_expires')" style="width:100%" value-format="YYYY-MM-DD HH:mm:ss" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createVisible = false">{{ $t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="handleCreate" :loading="submitting">{{ $t('portal.create') }}</el-button>
      </template>
    </el-dialog>

    <!-- 创建成功：展示 Key -->
    <el-dialog v-model="showKeyResult" :title="$t('portal.key_created_title')" width="520px" :close-on-click-modal="false" @close="onKeyResultClose">
      <el-alert type="warning" :closable="false" show-icon>
        <template #title><strong>{{ $t('portal.key_save_now') }}</strong></template>
      </el-alert>
      <div class="key-display-box">
        <div class="key-label">API Key</div>
        <div class="key-value-wrap">
          <code class="key-value">{{ lastCreatedKey }}</code>
          <el-button type="primary" size="small" @click="copyKey(lastCreatedKey)">
            <el-icon><CopyDocument /></el-icon> {{ $t('portal.copy') }}
          </el-button>
        </div>
      </div>
      <template #footer>
        <el-button type="primary" @click="showKeyResult = false">{{ $t('portal.saved_key') }}</el-button>
      </template>
    </el-dialog>

    <!-- 编辑对话框 -->
    <el-dialog v-model="editVisible" :title="$t('portal.edit_apikey')" width="520px" destroy-on-close>
      <el-form :model="editForm" :rules="formRules" ref="editFormRef" label-position="top">
        <el-form-item :label="$t('portal.name')" prop="name">
          <el-input v-model="editForm.name" maxlength="100" />
        </el-form-item>
        <el-form-item :label="$t('portal.ability_scope')">
          <el-checkbox-group v-model="editForm.abilities">
            <el-checkbox v-for="(label, key) in abilityOptions" :key="key" :label="key">{{ label }}</el-checkbox>
          </el-checkbox-group>
        </el-form-item>
        <el-form-item :label="$t('portal.ip_whitelist')">
          <el-input v-model="editForm.ip_whitelist" :placeholder="$t('portal.ip_whitelist_edit_ph')" />
        </el-form-item>
        <el-form-item :label="$t('portal.expires_at')">
          <el-date-picker v-model="editForm.expires_at" type="datetime" :placeholder="$t('portal.never_expires')" style="width:100%" value-format="YYYY-MM-DD HH:mm:ss" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="editVisible = false">{{ $t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="handleUpdate" :loading="submitting">{{ $t('actions.save') }}</el-button>
      </template>
    </el-dialog>

    <!-- 重新生成对话框 -->
    <el-dialog v-model="regenerateVisible" :title="$t('portal.regenerate_title')" width="400px">
      <p>{{ $t('portal.regenerate_confirm') }}</p>
      <template #footer>
        <el-button @click="regenerateVisible = false">{{ $t('actions.cancel') }}</el-button>
        <el-button type="warning" @click="handleRegenerate" :loading="submitting">{{ $t('portal.confirm_regenerate') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, CopyDocument } from '@element-plus/icons-vue';
import apiKeyApi from '@/api/apiKey';

const { t, locale } = useI18n();

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

const formRules = computed(() => ({
    name: [{ required: true, message: t('portal.name_required'), trigger: 'blur' }],
}));

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
    navigator.clipboard.writeText(key).then(() => ElMessage.success(t('portal.copied')));
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
        ElMessage.error(err.response?.data?.message || t('portal.create_failed'));
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
        ElMessage.success(t('portal.update_ok'));
        editVisible.value = false;
        loadKeys();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('portal.update_failed'));
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
        ElMessage.error(err.response?.data?.message || t('portal.regenerate_failed'));
    } finally { submitting.value = false; }
}

async function toggleStatus(row) {
    try {
        await apiKeyApi.toggleActive(row.id);
        ElMessage.success(row.is_active ? t('portal.disabled_ok') : t('portal.enabled_ok'));
        loadKeys();
        loadStats();
    } catch (err) {
        ElMessage.error(t('messages.failed'));
    }
}

async function handleDelete(row) {
    try {
        await ElMessageBox.confirm(
            t('portal.delete_key_confirm', { name: row.name }),
            t('portal.confirm_delete'),
        );
        await apiKeyApi.delete(row.id);
        ElMessage.success(t('portal.deleted_ok'));
        loadKeys();
        loadStats();
    } catch (e) { if (e !== 'cancel') ElMessage.error(t('portal.delete_failed_msg')); }
}

function formatTime(time) {
    if (!time) return '—';
    const dateLocale = locale.value === 'en' ? 'en-US' : 'zh-CN';
    return new Date(time).toLocaleString(dateLocale, {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

function isExpiring(time) {
    if (!time) return false;
    const days = (new Date(time) - new Date()) / (1000 * 60 * 60 * 24);
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
.text-primary { color: #0f172a; }
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
