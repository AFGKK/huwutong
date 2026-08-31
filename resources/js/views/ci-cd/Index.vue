<template>
  <div class="ci-cd-page">
    <div class="page-header mb-6">
      <h1 class="text-2xl font-bold text-gray-800">{{ t(`${P}.title`) }}</h1>
      <p class="text-sm text-gray-500 mt-1">{{ t(`${P}.subtitle`) }}</p>
    </div>

    <div v-if="loading" class="text-center py-16"><el-skeleton :rows="5" animated /></div>

    <template v-else>
      <el-row :gutter="20" class="mb-6">
        <el-col :span="6" v-for="s in statCards" :key="s.key">
          <el-card shadow="never" class="text-center">
            <div class="text-2xl font-bold" :style="{ color: s.color }">{{ s.value }}</div>
            <div class="text-sm text-gray-500 mt-1">{{ t(`${P}.stats.${s.key}`) }}</div>
          </el-card>
        </el-col>
      </el-row>

      <el-card shadow="never" class="mb-6">
        <div class="flex justify-between items-center">
          <el-select v-model="filter.status" :placeholder="t(`${P}.status_label`)" clearable size="small" style="width:120px" @change="fetchTokens">
            <el-option :label="t(`${P}.status_all`)" value="" />
            <el-option :label="t(`${P}.status.active`)" value="active" />
            <el-option :label="t(`${P}.status.revoked`)" value="revoked" />
            <el-option :label="t(`${P}.status.expired`)" value="expired" />
          </el-select>
          <div class="flex gap-2">
            <el-button size="small" @click="showExamples = true">{{ t(`${P}.examples_btn`) }}</el-button>
            <el-button type="primary" size="small" @click="openCreate"><el-icon><Plus /></el-icon> {{ t(`${P}.create_token`) }}</el-button>
          </div>
        </div>
      </el-card>

      <el-card shadow="never">
        <el-table :data="tokens" v-loading="loading" stripe size="small">
          <el-table-column prop="name" :label="t(`${P}.cols.name`)" min-width="140" />
          <el-table-column :label="t(`${P}.cols.token`)" min-width="220">
            <template #default="{ row }">
              <div class="flex items-center gap-2">
                <code class="text-xs bg-gray-100 px-2 py-1 rounded">{{ maskToken(row.token) }}</code>
                <el-button text size="small" @click="copyToken(row)" :title="t(`${P}.copy_token`)">{{ t(`${P}.copy`) }}</el-button>
              </div>
            </template>
          </el-table-column>
          <el-table-column :label="t(`${P}.cols.scopes`)" width="200">
            <template #default="{ row }">
              <el-tag v-for="s in (row.scopes || [])" :key="s" size="small" class="mr-1">{{ s }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="use_count" :label="t(`${P}.cols.uses`)" width="100">
            <template #default="{ row }">{{ row.use_count }}{{ row.max_uses ? '/' + row.max_uses : '' }}</template>
          </el-table-column>
          <el-table-column :label="t(`${P}.cols.status`)" width="90">
            <template #default="{ row }">
              <el-tag :type="row.status === 'active' ? 'success' : 'danger'" size="small">{{ statusLabel(row.status) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="last_used_at" :label="t(`${P}.cols.last_used`)" width="150" />
          <el-table-column prop="expires_at" :label="t(`${P}.cols.expires`)" width="150">
            <template #default="{ row }">{{ row.expires_at || t(`${P}.permanent`) }}</template>
          </el-table-column>
          <el-table-column :label="t(`${P}.cols.actions`)" width="200" fixed="right">
            <template #default="{ row }">
              <el-button text size="small" @click="viewLogs(row)">{{ t(`${P}.logs`) }}</el-button>
              <el-button text size="small" @click="editToken(row)">{{ t('actions.edit') }}</el-button>
              <el-popconfirm v-if="row.status === 'active'" :title="t(`${P}.confirm_revoke`)" @confirm="revokeToken(row)">
                <template #reference><el-button text size="small" type="danger">{{ t(`${P}.revoke`) }}</el-button></template>
              </el-popconfirm>
              <el-popconfirm :title="t(`${P}.confirm_delete`)" @confirm="deleteToken(row)">
                <template #reference><el-button text size="small" type="danger">{{ t('actions.delete') }}</el-button></template>
              </el-popconfirm>
            </template>
          </el-table-column>
        </el-table>
        <el-empty v-if="tokens.length === 0" :description="t(`${P}.empty`)" />
        <div class="mt-4 flex justify-center" v-if="pagination.total > pagination.per_page">
          <el-pagination v-model:current-page="pagination.current_page" :page-size="pagination.per_page" :total="pagination.total" layout="prev, pager, next" @current-change="fetchTokens" />
        </div>
      </el-card>
    </template>

    <el-dialog v-model="showCreate" :title="editingId ? t(`${P}.edit_title`) : t(`${P}.create_title`)" width="540px">
      <el-form :model="form" label-position="top" size="small">
        <el-form-item :label="t(`${P}.cols.name`)" required>
          <el-input v-model="form.name" :placeholder="t(`${P}.name_ph`)" />
        </el-form-item>
        <el-form-item :label="t(`${P}.description`)">
          <el-input v-model="form.description" type="textarea" :rows="2" />
        </el-form-item>
        <el-form-item :label="t(`${P}.cols.scopes`)" required>
          <el-checkbox-group v-model="form.scopes">
            <el-checkbox label="license_read" value="license_read">{{ t(`${P}.scopes.license_read`) }}</el-checkbox>
            <el-checkbox label="license_activate" value="license_activate">{{ t(`${P}.scopes.license_activate`) }}</el-checkbox>
            <el-checkbox label="all" value="all">{{ t(`${P}.scopes.all`) }}</el-checkbox>
          </el-checkbox-group>
        </el-form-item>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item :label="t(`${P}.max_uses`)">
              <el-input-number v-model="form.max_uses" :min="0" :placeholder="t(`${P}.max_uses_ph`)" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item :label="t(`${P}.expires_optional`)">
              <el-date-picker v-model="form.expires_at" type="date" :placeholder="t(`${P}.permanent_valid`)" style="width:100%" value-format="YYYY-MM-DD" />
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <template #footer>
        <el-button @click="showCreate = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="saveToken" :loading="saving">{{ editingId ? t('actions.save') : t('actions.create') }}</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="showLogs" :title="t(`${P}.logs_title`, { name: logToken?.name || '' })" width="700px">
      <el-table :data="logs" v-loading="logLoading" size="small" max-height="400">
        <el-table-column prop="action" :label="t(`${P}.cols.action`)" width="120" />
        <el-table-column prop="ci_provider" :label="t(`${P}.cols.provider`)" width="120" />
        <el-table-column prop="repository" :label="t(`${P}.cols.repository`)" min-width="160" />
        <el-table-column prop="workflow" :label="t(`${P}.cols.workflow`)" min-width="140" />
        <el-table-column prop="ip_address" :label="t(`${P}.cols.ip`)" width="130" />
        <el-table-column prop="created_at" :label="t(`${P}.cols.time`)" width="160" />
      </el-table>
      <el-empty v-if="logs.length === 0" :description="t(`${P}.empty_logs`)" />
    </el-dialog>

    <el-dialog v-model="showExamples" :title="t(`${P}.examples_title`)" width="700px" top="3vh">
      <el-tabs v-model="exampleTab">
        <el-tab-pane label="GitHub Actions" name="github_actions">
          <pre class="bg-gray-900 text-green-300 p-4 rounded-lg text-xs overflow-x-auto">{{ examples.github_actions }}</pre>
        </el-tab-pane>
        <el-tab-pane label="GitLab CI" name="gitlab_ci">
          <pre class="bg-gray-900 text-green-300 p-4 rounded-lg text-xs overflow-x-auto">{{ examples.gitlab_ci }}</pre>
        </el-tab-pane>
        <el-tab-pane label="Jenkins" name="jenkins">
          <pre class="bg-gray-900 text-green-300 p-4 rounded-lg text-xs overflow-x-auto">{{ examples.jenkins }}</pre>
        </el-tab-pane>
        <el-tab-pane :label="t(`${P}.curl_tab`)" name="curl">
          <pre class="bg-gray-900 text-green-300 p-4 rounded-lg text-xs overflow-x-auto">{{ examples.curl }}</pre>
        </el-tab-pane>
      </el-tabs>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import {
  getCiTokens, createCiToken, updateCiToken, deleteCiToken,
  getCiTokenLogs, getCiStats, getCiExamples,
} from '@/api/ciCd';

const { t } = useI18n();
const P = 'ci_cd_page';

const loading = ref(true);
const saving = ref(false);
const showCreate = ref(false);
const showLogs = ref(false);
const showExamples = ref(false);
const editingId = ref(null);
const tokens = ref([]);
const logs = ref([]);
const logToken = ref(null);
const logLoading = ref(false);
const exampleTab = ref('github_actions');
const examples = ref({});
const filter = reactive({ status: '' });
const pagination = reactive({ current_page: 1, per_page: 20, total: 0 });

const form = reactive({
  name: '', description: '', scopes: ['license_read'],
  max_uses: null, expires_at: null,
});

const statCards = ref([
  { key: 'total', value: 0, color: '#0f172a' },
  { key: 'active', value: 0, color: '#67c23a' },
  { key: 'calls', value: 0, color: '#e6a23c' },
  { key: 'today', value: 0, color: '#f56c6c' },
]);

function maskToken(token) {
  if (!token) return '';
  if (token.length <= 12) return token;
  return token.substring(0, 8) + '...' + token.slice(-4);
}

function statusLabel(s) {
  const key = `${P}.status.${s}`;
  const translated = t(key);
  return translated === key ? s : translated;
}

function openCreate() {
  resetForm();
  showCreate.value = true;
}

async function loadData() {
  loading.value = true;
  try {
    const [r, s, e] = await Promise.all([
      getCiTokens(), getCiStats(), getCiExamples(),
    ]);
    if (r.data?.success) {
      tokens.value = r.data.data.data || [];
      pagination.current_page = r.data.data.current_page;
      pagination.total = r.data.data.total;
    }
    if (s.data?.success) {
      statCards.value = [
        { key: 'total', value: s.data.data.total_tokens, color: '#0f172a' },
        { key: 'active', value: s.data.data.active_tokens, color: '#67c23a' },
        { key: 'calls', value: s.data.data.total_calls, color: '#e6a23c' },
        { key: 'today', value: s.data.data.today_calls, color: '#f56c6c' },
      ];
    }
    if (e.data?.success) {
      examples.value = e.data.data;
    }
  } catch { ElMessage.error(t('messages.load_failed')) }
  finally { loading.value = false }
}

async function fetchTokens(page) {
  loading.value = true;
  try {
    const res = await getCiTokens({ ...filter, page: page || pagination.current_page });
    if (res.data?.success) {
      tokens.value = res.data.data.data || [];
      pagination.current_page = res.data.data.current_page;
      pagination.total = res.data.data.total;
    }
  } finally { loading.value = false }
}

function copyToken(row) {
  navigator.clipboard?.writeText(row.token).then(() => ElMessage.success(t(`${P}.messages.copied`)));
}

async function saveToken() {
  saving.value = true;
  try {
    if (editingId.value) {
      await updateCiToken(editingId.value, form);
      ElMessage.success(t(`${P}.messages.updated`));
    } else {
      const res = await createCiToken(form);
      ElMessage.success(t(`${P}.messages.created`, { token: res.data.data.token }));
    }
    showCreate.value = false;
    resetForm();
    await loadData();
  } catch { ElMessage.error(t(`${P}.messages.action_failed`)) } finally { saving.value = false }
}

function editToken(row) {
  editingId.value = row.id;
  Object.assign(form, {
    name: row.name, description: row.description,
    scopes: row.scopes || ['license_read'],
    max_uses: row.max_uses, expires_at: row.expires_at,
  });
  showCreate.value = true;
}

function resetForm() {
  editingId.value = null;
  form.name = ''; form.description = '';
  form.scopes = ['license_read'];
  form.max_uses = null; form.expires_at = null;
}

async function viewLogs(row) {
  logToken.value = row;
  showLogs.value = true;
  logLoading.value = true;
  try {
    const res = await getCiTokenLogs(row.id);
    if (res.data?.success) logs.value = res.data.data.data || [];
  } finally { logLoading.value = false }
}

async function revokeToken(row) {
  try {
    await updateCiToken(row.id, { status: 'revoked', revoked_reason: t(`${P}.manual_revoke`) });
    ElMessage.success(t(`${P}.messages.revoked`));
    await loadData();
  } catch { ElMessage.error(t(`${P}.messages.action_failed`)) }
}

async function deleteToken(row) {
  try {
    await deleteCiToken(row.id);
    ElMessage.success(t(`${P}.messages.deleted`));
    await loadData();
  } catch { ElMessage.error(t(`${P}.messages.delete_failed`)) }
}

onMounted(loadData);
</script>

<style scoped>
.ci-cd-page { padding: 24px; }
</style>
