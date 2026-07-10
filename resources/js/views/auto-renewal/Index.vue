<template>
  <div class="auto-renewal-page">
    <div class="page-header">
      <h2>自动续费管理</h2>
      <el-button @click="refreshAll" :loading="loading"><el-icon><Refresh /></el-icon> 刷新</el-button>
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
      <el-tabs v-model="activeTab">
        <el-tab-pane label="续费计划" name="plans">
          <el-table :data="plans" v-loading="loading" stripe>
            <el-table-column prop="name" label="计划名称" min-width="160" />
            <el-table-column prop="product?.name" label="产品" min-width="120" />
            <el-table-column prop="billing_period" label="周期" width="100" />
            <el-table-column prop="price" label="价格" width="100" />
            <el-table-column label="状态" width="90">
              <template #default="{ row }">
                <el-tag :type="row.is_active ? 'success' : 'info'" size="small">{{ row.is_active ? '启用' : '停用' }}</el-tag>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <el-tab-pane label="订阅实例" name="subscriptions">
          <el-table :data="subscriptions" v-loading="loading" stripe>
            <el-table-column prop="customer?.name" label="客户" min-width="120" />
            <el-table-column prop="plan?.name" label="计划" min-width="140" />
            <el-table-column prop="status" label="状态" width="100" />
            <el-table-column prop="next_renew_at" label="下次续费" width="170" />
            <el-table-column label="操作" width="220" fixed="right">
              <template #default="{ row }">
                <el-button size="small" type="primary" link @click="handleAction('renew', row)">续费</el-button>
                <el-button size="small" link @click="handleAction(row.status === 'paused' ? 'resume' : 'pause', row)">
                  {{ row.status === 'paused' ? '恢复' : '暂停' }}
                </el-button>
                <el-button size="small" type="danger" link @click="handleAction('cancel', row)">取消</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Refresh } from '@element-plus/icons-vue';
import api from '@/api/autoRenewal';

const loading = ref(false);
const activeTab = ref('plans');
const plans = ref([]);
const subscriptions = ref([]);
const dashboard = reactive({});

const statCards = [
  { key: 'active_plans', label: '活跃计划' },
  { key: 'active_subscriptions', label: '活跃订阅' },
  { key: 'renewals_30d', label: '近30日续费' },
  { key: 'failed_30d', label: '近30日失败' },
];

async function fetchDashboard() {
  const { data: res } = await api.dashboard();
  Object.assign(dashboard, res.data || {});
}

async function fetchPlans() {
  const { data: res } = await api.plans();
  plans.value = res.data?.data || res.data || [];
}

async function fetchSubscriptions() {
  const { data: res } = await api.subscriptions();
  subscriptions.value = res.data?.data || res.data || [];
}

async function refreshAll() {
  loading.value = true;
  try {
    await Promise.all([fetchDashboard(), fetchPlans(), fetchSubscriptions()]);
  } catch {
    ElMessage.error('加载失败');
  } finally {
    loading.value = false;
  }
}

async function handleAction(action, row) {
  try {
    await api[action](row.id);
    ElMessage.success('操作成功');
    await refreshAll();
  } catch (e) {
    ElMessage.error(e?.response?.data?.error?.message || '操作失败');
  }
}

onMounted(refreshAll);
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.stat-value { font-size: 28px; font-weight: 700; }
.stat-label { color: #909399; margin-top: 4px; }
.mb-4 { margin-bottom: 16px; }
</style>
