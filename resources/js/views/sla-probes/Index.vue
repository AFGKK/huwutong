<template>
  <div class="sla-probes-page">
    <div class="page-header">
      <h2>SLA 可用性拨测</h2>
      <div>
        <el-button type="primary" @click="openDialog()"><el-icon><Plus /></el-icon> 新建拨测</el-button>
        <el-button @click="refreshAll" :loading="loading"><el-icon><Refresh /></el-icon> 刷新</el-button>
      </div>
    </div>

    <el-row :gutter="16" class="mb-4">
      <el-col :xs="12" :sm="6" v-for="item in statCards" :key="item.key">
        <el-card shadow="hover">
          <div class="stat-value">{{ dashboard[item.key] ?? 0 }}</div>
          <div class="stat-label">{{ item.label }}</div>
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="never">
      <el-table :data="probes" v-loading="loading" stripe>
        <el-table-column prop="name" label="名称" min-width="140" />
        <el-table-column prop="url" label="URL" min-width="220" show-overflow-tooltip />
        <el-table-column prop="method" label="方法" width="80" />
        <el-table-column prop="interval_minutes" label="间隔(分)" width="90" />
        <el-table-column label="状态" width="90">
          <template #default="{ row }">
            <el-tag :type="row.is_active ? 'success' : 'info'" size="small">{{ row.is_active ? '启用' : '停用' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="220" fixed="right">
          <template #default="{ row }">
            <el-button size="small" link @click="handleToggle(row)">{{ row.is_active ? '停用' : '启用' }}</el-button>
            <el-button size="small" type="primary" link @click="handleRun(row)">立即拨测</el-button>
            <el-button size="small" type="danger" link @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="dialog.visible" title="新建拨测" width="520px">
      <el-form :model="form" label-width="100px">
        <el-form-item label="名称"><el-input v-model="form.name" /></el-form-item>
        <el-form-item label="URL"><el-input v-model="form.url" placeholder="https://..." /></el-form-item>
        <el-form-item label="方法">
          <el-select v-model="form.method" style="width: 100%">
            <el-option label="GET" value="GET" />
            <el-option label="POST" value="POST" />
            <el-option label="HEAD" value="HEAD" />
          </el-select>
        </el-form-item>
        <el-form-item label="间隔(分钟)"><el-input-number v-model="form.interval_minutes" :min="1" :max="1440" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialog.visible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="handleSave">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Refresh } from '@element-plus/icons-vue';
import api from '@/api/slaProbe';

const loading = ref(false);
const saving = ref(false);
const probes = ref([]);
const dashboard = reactive({});
const dialog = reactive({ visible: false });
const form = reactive({ name: '', url: '', method: 'GET', interval_minutes: 5 });

const statCards = [
  { key: 'total_probes', label: '拨测总数' },
  { key: 'active_probes', label: '启用中' },
  { key: 'success_rate_24h', label: '24h 成功率(%)' },
  { key: 'failed_24h', label: '24h 失败次数' },
];

async function fetchDashboard() {
  const { data: res } = await api.dashboard();
  Object.assign(dashboard, res.data || {});
}

async function fetchList() {
  const { data: res } = await api.list();
  probes.value = res.data?.data || res.data || [];
}

async function refreshAll() {
  loading.value = true;
  try {
    await Promise.all([fetchDashboard(), fetchList()]);
  } catch {
    ElMessage.error('加载失败');
  } finally {
    loading.value = false;
  }
}

function openDialog() {
  form.name = '';
  form.url = '';
  form.method = 'GET';
  form.interval_minutes = 5;
  dialog.visible = true;
}

async function handleSave() {
  saving.value = true;
  try {
    await api.store({ ...form });
    ElMessage.success('创建成功');
    dialog.visible = false;
    await refreshAll();
  } catch (e) {
    ElMessage.error(e?.response?.data?.error?.message || '保存失败');
  } finally {
    saving.value = false;
  }
}

async function handleToggle(row) {
  await api.toggle(row.id);
  ElMessage.success('状态已更新');
  await refreshAll();
}

async function handleRun(row) {
  await api.run(row.id);
  ElMessage.success('拨测已触发');
}

async function handleDelete(row) {
  await ElMessageBox.confirm(`确定删除拨测「${row.name}」？`, '确认');
  await api.destroy(row.id);
  ElMessage.success('已删除');
  await refreshAll();
}

onMounted(refreshAll);
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.stat-value { font-size: 28px; font-weight: 700; }
.stat-label { color: #909399; margin-top: 4px; }
.mb-4 { margin-bottom: 16px; }
</style>
