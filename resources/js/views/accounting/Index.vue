<template>
  <div class="accounting-page">
    <div class="page-header mb-6">
      <h1 class="text-2xl font-bold text-gray-800">📊 会计系统集成</h1>
      <p class="text-sm text-gray-500 mt-1">对接 QuickBooks / Xero / 用友 / 金蝶，自动同步发票和收款</p>
    </div>

    <div v-if="loading" class="text-center py-16">
      <el-skeleton :rows="5" animated />
    </div>

    <template v-else>
      <!-- 已配置的集成列表 -->
      <el-card shadow="never" class="mb-6">
        <template #header>
          <div class="flex justify-between items-center">
            <span>已配置的会计系统</span>
            <el-button type="primary" size="small" @click="showAddDialog = true">
              <el-icon><Plus /></el-icon> 新增集成
            </el-button>
          </div>
        </template>

        <el-table :data="integrations" v-loading="loading" stripe size="small">
          <el-table-column label="系统" width="200">
            <template #default="{ row }">
              <div class="flex items-center gap-2">
                <span class="text-lg">{{ providerIcon(row.provider) }}</span>
                <div>
                  <div class="font-medium">{{ row.name || providerName(row.provider) }}</div>
                  <div class="text-xs text-gray-400">{{ row.provider }}</div>
                </div>
              </div>
            </template>
          </el-table-column>
          <el-table-column prop="environment" label="环境" width="100">
            <template #default="{ row }">
              <el-tag :type="row.environment === 'production' ? 'success' : 'warning'" size="small">
                {{ row.environment === 'production' ? '生产' : '沙箱' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="连接状态" width="120">
            <template #default="{ row }">
              <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">
                {{ row.is_active ? '已连接' : '未连接' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="sync_mappings_count" label="已同步" width="80" />
          <el-table-column prop="last_sync_at" label="最后同步" min-width="160" />
          <el-table-column label="操作" width="300" fixed="right">
            <template #default="{ row }">
              <el-button text size="small" @click="testConn(row)">测试</el-button>
              <el-button text size="small" :disabled="!row.is_active" @click="syncNow(row)">同步</el-button>
              <el-button text size="small" @click="viewLogs(row)">日志</el-button>
              <el-button text size="small" @click="editIntegration(row)">编辑</el-button>
              <el-popconfirm title="删除此集成？同步映射也将清除" @confirm="deleteIntegration(row)">
                <template #reference>
                  <el-button text size="small" type="danger">删除</el-button>
                </template>
              </el-popconfirm>
            </template>
          </el-table-column>
        </el-table>

        <el-empty v-if="integrations.length === 0" description="尚未配置会计系统集成" />
      </el-card>

      <!-- 可用提供商 -->
      <el-card shadow="never">
        <template #header><span>支持的会计系统</span></template>
        <el-row :gutter="20">
          <el-col :span="6" v-for="p in providers" :key="p.key">
            <el-card shadow="hover" class="provider-card" :class="{ 'opacity-50': isConfigured(p.key) }">
              <div class="text-center p-4">
                <div class="text-4xl mb-2">{{ p.icon }}</div>
                <div class="font-medium text-sm">{{ p.name }}</div>
                <div class="text-xs text-gray-400 mt-1">
                  {{ p.type === 'international' ? '🌍 国际' : '🇨🇳 国内' }}
                </div>
                <div class="mt-3">
                  <el-tag v-if="isConfigured(p.key)" type="success" size="small">已配置</el-tag>
                  <el-tag v-else type="info" size="small">未配置</el-tag>
                </div>
              </div>
            </el-card>
          </el-col>
        </el-row>
      </el-card>
    </template>

    <!-- 新增/编辑对话框 -->
    <el-dialog v-model="showAddDialog" :title="editingId ? '编辑集成' : '新增会计系统集成'" width="560px">
      <el-form :model="form" label-position="top" size="small">
        <el-form-item label="会计系统" required>
          <el-select v-model="form.provider" :disabled="!!editingId" style="width:100%">
            <el-option label="QuickBooks Online" value="quickbooks" />
            <el-option label="Xero" value="xero" />
            <el-option label="用友 (U8+/畅捷通)" value="yonyou" />
            <el-option label="金蝶 (K/3+云星空)" value="kingdee" />
          </el-select>
        </el-form-item>
        <el-form-item label="名称">
          <el-input v-model="form.name" :placeholder="providerName(form.provider)" />
        </el-form-item>
        <el-form-item label="环境">
          <el-radio-group v-model="form.environment">
            <el-radio value="sandbox">沙箱 (Sandbox)</el-radio>
            <el-radio value="production">生产 (Production)</el-radio>
          </el-radio-group>
        </el-form-item>

        <!-- OAuth 类型 -->
        <template v-if="form.provider === 'quickbooks' || form.provider === 'xero'">
          <el-form-item label="Client ID">
            <el-input v-model="form.client_id" />
          </el-form-item>
          <el-form-item label="Client Secret">
            <el-input v-model="form.client_secret" type="password" show-password />
          </el-form-item>
        </template>

        <!-- API Key 类型 -->
        <template v-if="form.provider === 'yonyou' || form.provider === 'kingdee'">
          <el-form-item label="API 端点">
            <el-input v-model="form.api_endpoint" placeholder="https://your-server.com" />
          </el-form-item>
          <el-form-item label="账套/公司ID">
            <el-input v-model="form.company_id" />
          </el-form-item>
          <el-form-item label="用户名">
            <el-input v-model="form.username" />
          </el-form-item>
          <el-form-item label="密码">
            <el-input v-model="form.password" type="password" show-password />
          </el-form-item>
        </template>
      </el-form>
      <template #footer>
        <el-button @click="showAddDialog = false">取消</el-button>
        <el-button type="primary" @click="saveIntegration" :loading="saving">保存</el-button>
      </template>
    </el-dialog>

    <!-- 同步日志对话框 -->
    <el-dialog v-model="showLogDialog" title="同步日志" width="700px">
      <el-table :data="syncLogs" v-loading="logLoading" size="small">
        <el-table-column prop="sync_type" label="类型" width="80" />
        <el-table-column prop="direction" label="方向" width="80" />
        <el-table-column prop="entity_type" label="实体" width="120" />
        <el-table-column prop="total_count" label="总数" width="60" />
        <el-table-column prop="success_count" label="成功" width="60" />
        <el-table-column prop="fail_count" label="失败" width="60" />
        <el-table-column prop="created_at" label="时间" width="160" />
        <el-table-column prop="error_message" label="错误" min-width="150" show-overflow-tooltip />
      </el-table>
      <el-empty v-if="syncLogs.length === 0" description="暂无同步日志" />
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import {
  getAccountingIntegrations,
  createAccountingIntegration,
  updateAccountingIntegration,
  deleteAccountingIntegration,
  testAccountingConnection,
  syncPendingAccounting,
  getAccountingLogs,
} from '@/api/accounting';

const loading = ref(true);
const saving = ref(false);
const showAddDialog = ref(false);
const editingId = ref(null);
const integrations = ref([]);
const providers = ref([]);

// 表单
const form = reactive({
  provider: 'quickbooks', name: '', environment: 'sandbox',
  client_id: '', client_secret: '',
  api_endpoint: '', company_id: '', username: '', password: '',
});

// 日志
const showLogDialog = ref(false);
const logLoading = ref(false);
const syncLogs = ref([]);
const currentIntegrationId = ref(null);

const providerIcon = (key) => ({ quickbooks: '📘', xero: '📗', yonyou: '🇨🇳', kingdee: '🇨🇳' }[key] || '📄');

function providerName(key) {
  const map = { quickbooks: 'QuickBooks Online', xero: 'Xero', yonyou: '用友', kingdee: '金蝶' };
  return map[key] || key;
}

function isConfigured(key) {
  return integrations.value.some(i => i.provider === key);
}

async function loadData() {
  loading.value = true;
  try {
    const res = await getAccountingIntegrations();
    if (res.data?.success) {
      integrations.value = res.data.data.integrations || [];
      providers.value = res.data.data.providers || [];
    }
  } catch (e) {
    ElMessage.error('加载失败');
  } finally {
    loading.value = false;
  }
}

function resetForm() {
  form.provider = 'quickbooks';
  form.name = '';
  form.environment = 'sandbox';
  form.client_id = '';
  form.client_secret = '';
  form.api_endpoint = '';
  form.company_id = '';
  form.username = '';
  form.password = '';
  editingId.value = null;
}

function editIntegration(row) {
  editingId.value = row.id;
  Object.assign(form, {
    provider: row.provider,
    name: row.name,
    environment: row.environment,
    client_id: row.client_id || '',
    client_secret: '',
    api_endpoint: row.api_endpoint || '',
    company_id: row.company_id || '',
    username: row.username || '',
    password: '',
  });
  showAddDialog.value = true;
}

async function saveIntegration() {
  saving.value = true;
  try {
    if (editingId.value) {
      await updateAccountingIntegration(editingId.value, form);
      ElMessage.success('更新成功');
    } else {
      await createAccountingIntegration(form);
      ElMessage.success('创建成功');
    }
    showAddDialog.value = false;
    resetForm();
    await loadData();
  } catch (e) {
    ElMessage.error('操作失败');
  } finally {
    saving.value = false;
  }
}

async function deleteIntegration(row) {
  try {
    await deleteAccountingIntegration(row.id);
    ElMessage.success('删除成功');
    await loadData();
  } catch (e) {
    ElMessage.error('删除失败');
  }
}

async function testConn(row) {
  try {
    const res = await testAccountingConnection(row.id);
    if (res.data?.success) {
      const d = res.data.data;
      if (d.connected) {
        ElMessage.success(`连接成功: ${d.company_name || d.message || ''}`);
      } else {
        ElMessage.error(`连接失败: ${d.error || ''}`);
      }
    }
    await loadData();
  } catch (e) {
    ElMessage.error('测试失败');
  }
}

async function syncNow(row) {
  try {
    const res = await syncPendingAccounting(row.id);
    if (res.data?.success) {
      const d = res.data.data;
      ElMessage.success(`同步完成: 共 ${d.total} 条, 成功 ${d.success}, 失败 ${d.failed}`);
    }
  } catch (e) {
    ElMessage.error('同步失败');
  }
}

async function viewLogs(row) {
  currentIntegrationId.value = row.id;
  showLogDialog.value = true;
  logLoading.value = true;
  try {
    const res = await getAccountingLogs(row.id);
    if (res.data?.success) {
      syncLogs.value = res.data.data.data || [];
    }
  } finally {
    logLoading.value = false;
  }
}

onMounted(loadData);
</script>

<style scoped>
.accounting-page { padding: 24px; }
.provider-card { transition: all 0.2s; cursor: default; }
.provider-card:hover { transform: translateY(-2px); }
</style>
