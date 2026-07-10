<template>
  <div class="ci-cd-page">
    <div class="page-header mb-6">
      <h1 class="text-2xl font-bold text-gray-800">🔑 CI/CD 自动授权</h1>
      <p class="text-sm text-gray-500 mt-1">开发者可在 GitHub Actions / GitLab CI / Jenkins 中通过令牌自动获取 License</p>
    </div>

    <div v-if="loading" class="text-center py-16"><el-skeleton :rows="5" animated /></div>

    <template v-else>
      <!-- 统计 -->
      <el-row :gutter="20" class="mb-6">
        <el-col :span="6" v-for="s in statCards" :key="s.label">
          <el-card shadow="never" class="text-center">
            <div class="text-2xl font-bold" :style="{ color: s.color }">{{ s.value }}</div>
            <div class="text-sm text-gray-500 mt-1">{{ s.label }}</div>
          </el-card>
        </el-col>
      </el-row>

      <!-- 操作栏 -->
      <el-card shadow="never" class="mb-6">
        <div class="flex justify-between items-center">
          <el-select v-model="filter.status" placeholder="状态" clearable size="small" style="width:120px" @change="fetchTokens">
            <el-option label="全部" value="" />
            <el-option label="活跃" value="active" />
            <el-option label="已吊销" value="revoked" />
            <el-option label="已过期" value="expired" />
          </el-select>
          <div class="flex gap-2">
            <el-button size="small" @click="showExamples = true">📖 集成示例</el-button>
            <el-button type="primary" size="small" @click="showCreate = true"><el-icon><Plus /></el-icon> 新建令牌</el-button>
          </div>
        </div>
      </el-card>

      <!-- 令牌列表 -->
      <el-card shadow="never">
        <el-table :data="tokens" v-loading="loading" stripe size="small">
          <el-table-column prop="name" label="名称" min-width="140" />
          <el-table-column label="令牌" min-width="220">
            <template #default="{ row }">
              <div class="flex items-center gap-2">
                <code class="text-xs bg-gray-100 px-2 py-1 rounded">{{ maskToken(row.token) }}</code>
                <el-button text size="small" @click="copyToken(row)" title="复制令牌">📋</el-button>
              </div>
            </template>
          </el-table-column>
          <el-table-column label="作用域" width="200">
            <template #default="{ row }">
              <el-tag v-for="s in (row.scopes || [])" :key="s" size="small" class="mr-1">{{ s }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="use_count" label="已用/上限" width="100">
            <template #default="{ row }">{{ row.use_count }}{{ row.max_uses ? '/' + row.max_uses : '' }}</template>
          </el-table-column>
          <el-table-column label="状态" width="90">
            <template #default="{ row }">
              <el-tag :type="row.status === 'active' ? 'success' : 'danger'" size="small">{{ row.status }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="last_used_at" label="最后使用" width="150" />
          <el-table-column prop="expires_at" label="过期时间" width="150">
            <template #default="{ row }">{{ row.expires_at || '永久' }}</template>
          </el-table-column>
          <el-table-column label="操作" width="200" fixed="right">
            <template #default="{ row }">
              <el-button text size="small" @click="viewLogs(row)">日志</el-button>
              <el-button text size="small" @click="editToken(row)">编辑</el-button>
              <el-popconfirm v-if="row.status === 'active'" title="吊销此令牌？" @confirm="revokeToken(row)">
                <template #reference><el-button text size="small" type="danger">吊销</el-button></template>
              </el-popconfirm>
              <el-popconfirm title="删除？" @confirm="deleteToken(row)">
                <template #reference><el-button text size="small" type="danger">删除</el-button></template>
              </el-popconfirm>
            </template>
          </el-table-column>
        </el-table>
        <el-empty v-if="tokens.length === 0" description="暂无 CI/CD 令牌" />
        <div class="mt-4 flex justify-center" v-if="pagination.total > pagination.per_page">
          <el-pagination v-model:current-page="pagination.current_page" :page-size="pagination.per_page" :total="pagination.total" layout="prev, pager, next" @current-change="fetchTokens" />
        </div>
      </el-card>
    </template>

    <!-- 创建对话框 -->
    <el-dialog v-model="showCreate" :title="editingId ? '编辑令牌' : '新建 CI/CD 令牌'" width="540px">
      <el-form :model="form" label-position="top" size="small">
        <el-form-item label="名称" required>
          <el-input v-model="form.name" placeholder="如: GitHub Actions - Production Build" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="form.description" type="textarea" :rows="2" />
        </el-form-item>
        <el-form-item label="作用域" required>
          <el-checkbox-group v-model="form.scopes">
            <el-checkbox label="license_read" value="license_read">读取 License</el-checkbox>
            <el-checkbox label="license_activate" value="license_activate">激活 License</el-checkbox>
            <el-checkbox label="all" value="all">完全访问</el-checkbox>
          </el-checkbox-group>
        </el-form-item>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="最大使用次数（选填）">
              <el-input-number v-model="form.max_uses" :min="0" placeholder="0=无限制" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="过期时间（选填）">
              <el-date-picker v-model="form.expires_at" type="date" placeholder="永久有效" style="width:100%" value-format="YYYY-MM-DD" />
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <template #footer>
        <el-button @click="showCreate = false">取消</el-button>
        <el-button type="primary" @click="saveToken" :loading="saving">{{ editingId ? '保存' : '创建' }}</el-button>
      </template>
    </el-dialog>

    <!-- 使用日志对话框 -->
    <el-dialog v-model="showLogs" :title="'使用日志 - ' + (logToken?.name || '')" width="700px">
      <el-table :data="logs" v-loading="logLoading" size="small" max-height="400">
        <el-table-column prop="action" label="操作" width="120" />
        <el-table-column prop="ci_provider" label="提供商" width="120" />
        <el-table-column prop="repository" label="仓库" min-width="160" />
        <el-table-column prop="workflow" label="Workflow" min-width="140" />
        <el-table-column prop="ip_address" label="IP" width="130" />
        <el-table-column prop="created_at" label="时间" width="160" />
      </el-table>
      <el-empty v-if="logs.length === 0" description="暂无使用记录" />
    </el-dialog>

    <!-- 集成示例对话框 -->
    <el-dialog v-model="showExamples" title="CI/CD 集成示例" width="700px" top="3vh">
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
        <el-tab-pane label="通用 curl" name="curl">
          <pre class="bg-gray-900 text-green-300 p-4 rounded-lg text-xs overflow-x-auto">{{ examples.curl }}</pre>
        </el-tab-pane>
      </el-tabs>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import {
  getCiTokens, createCiToken, updateCiToken, deleteCiToken,
  getCiTokenLogs, getCiStats, getCiExamples,
} from '@/api/ciCd';

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
  { label: '令牌总数', value: 0, color: '#409eff' },
  { label: '活跃令牌', value: 0, color: '#67c23a' },
  { label: '总调用次数', value: 0, color: '#e6a23c' },
  { label: '今日调用', value: 0, color: '#f56c6c' },
]);

function maskToken(token) {
  if (!token) return '';
  if (token.length <= 12) return token;
  return token.substring(0, 8) + '...' + token.slice(-4);
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
        { label: '令牌总数', value: s.data.data.total_tokens, color: '#409eff' },
        { label: '活跃令牌', value: s.data.data.active_tokens, color: '#67c23a' },
        { label: '总调用次数', value: s.data.data.total_calls, color: '#e6a23c' },
        { label: '今日调用', value: s.data.data.today_calls, color: '#f56c6c' },
      ];
    }
    if (e.data?.success) {
      examples.value = e.data.data;
    }
  } catch { ElMessage.error('加载失败') }
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
  navigator.clipboard?.writeText(row.token).then(() => ElMessage.success('已复制'));
}

async function saveToken() {
  saving.value = true;
  try {
    if (editingId.value) {
      await updateCiToken(editingId.value, form);
      ElMessage.success('更新成功');
    } else {
      const res = await createCiToken(form);
      ElMessage.success(`创建成功！令牌: ${res.data.data.token}`);
    }
    showCreate.value = false;
    resetForm();
    await loadData();
  } catch { ElMessage.error('操作失败') } finally { saving.value = false }
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
    await updateCiToken(row.id, { status: 'revoked', revoked_reason: '手动吊销' });
    ElMessage.success('已吊销');
    await loadData();
  } catch { ElMessage.error('操作失败') }
}

async function deleteToken(row) {
  try {
    await deleteCiToken(row.id);
    ElMessage.success('已删除');
    await loadData();
  } catch { ElMessage.error('删除失败') }
}

onMounted(loadData);
</script>

<style scoped>
.ci-cd-page { padding: 24px; }
</style>
