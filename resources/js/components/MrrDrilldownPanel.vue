<template>
  <div class="mrr-drilldown">
    <div class="panel-header">
      <h4>
        <el-icon><FolderOpened /></el-icon>
        MRR 变化明细
        <span v-if="yearMonth" class="text-muted" style="font-weight:400;margin-left:6px;font-size:13px">({{ yearMonth }})</span>
      </h4>
      <div class="panel-actions">
        <el-select v-model="typeFilter" placeholder="变化类型" clearable size="small" style="width:130px" @change="loadData">
          <el-option label="所有" value="" />
          <el-option label="新增订阅" value="new_subscription" />
          <el-option label="升级" value="upgrade" />
          <el-option label="降级" value="downgrade" />
          <el-option label="取消" value="cancellation" />
          <el-option label="重新激活" value="reactivation" />
          <el-option label="价格变动" value="price_change" />
        </el-select>
      </div>
    </div>

    <el-table :data="details" v-loading="loading" stripe size="small" max-height="400">
      <el-table-column label="客户" min-width="140">
        <template #default="{ row }">
          <div>{{ row.customer?.name || '-' }}</div>
          <div class="text-muted" style="font-size:11px">{{ row.customer?.email || '' }}</div>
        </template>
      </el-table-column>
      <el-table-column label="变化类型" width="110">
        <template #default="{ row }">
          <el-tag :type="changeTypeColor(row.change_type)" effect="plain" size="small">
            {{ changeTypeLabel(row.change_type) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="方案" min-width="120">
        <template #default="{ row }">{{ row.plan?.name || row.subscription?.plan || '-' }}</template>
      </el-table-column>
      <el-table-column label="变更前MRR" width="120" align="right">
        <template #default="{ row }">¥{{ fmt(row.previous_mrr) }}</template>
      </el-table-column>
      <el-table-column label="变更后MRR" width="120" align="right">
        <template #default="{ row }">¥{{ fmt(row.new_mrr) }}</template>
      </el-table-column>
      <el-table-column label="影响" width="110" align="right">
        <template #default="{ row }">
          <span :class="row.mrr_impact >= 0 ? 'text-success' : 'text-danger'">
            {{ row.mrr_impact >= 0 ? '+' : '' }}¥{{ fmt(row.mrr_impact) }}
          </span>
        </template>
      </el-table-column>
      <el-table-column label="时间" width="150">
        <template #default="{ row }">{{ formatTime(row.occurred_at) }}</template>
      </el-table-column>
      <el-table-column label="原因" min-width="120">
        <template #default="{ row }">{{ row.reason || '-' }}</template>
      </el-table-column>
    </el-table>

    <div class="pagination-wrap" v-if="total > perPage">
      <el-pagination
        v-model:current-page="page"
        :page-size="perPage"
        :total="total"
        layout="prev, pager, next"
        background
        small
        @current-change="loadData"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import { getMrrDrilldown } from '../api/mrr.js';

const props = defineProps({
  yearMonth: { type: String, default: '' },
  changeType: { type: String, default: '' },
});

const details = ref([]);
const loading = ref(false);
const page = ref(1);
const perPage = ref(20);
const total = ref(0);
const typeFilter = ref(props.changeType || '');

async function loadData() {
  loading.value = true;
  try {
    const params = {
      year_month: props.yearMonth,
      change_type: typeFilter.value || undefined,
      page: page.value,
      per_page: perPage.value,
    };
    const res = await getMrrDrilldown(params);
    if (res.data.success) {
      const d = res.data.data;
      details.value = d.data || [];
      total.value = d.total || 0;
      page.value = d.current_page || 1;
    }
  } catch (e) { /* ignore */ }
  finally { loading.value = false; }
}

function fmt(v) {
  return Number(v || 0).toLocaleString('zh-CN', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

function formatTime(t) {
  if (!t) return '-';
  const d = new Date(t);
  return d.toLocaleDateString('zh-CN') + ' ' + d.toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit' });
}

function changeTypeColor(type) {
  return { new_subscription: 'success', upgrade: 'primary', downgrade: 'warning', cancellation: 'danger', reactivation: 'info', price_change: '' }[type] || '';
}

function changeTypeLabel(type) {
  return { new_subscription: '新增订阅', upgrade: '升级', downgrade: '降级', cancellation: '取消', reactivation: '重新激活', price_change: '价格变动' }[type] || type;
}

watch(() => props.yearMonth, () => { page.value = 1; loadData(); });
watch(() => props.changeType, (v) => { typeFilter.value = v || ''; page.value = 1; loadData(); });

onMounted(() => { if (props.yearMonth) loadData(); });
</script>

<style scoped>
.mrr-drilldown {
  margin-top: 16px;
}
.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}
.panel-header h4 {
  margin: 0;
  display: flex;
  align-items: center;
  gap: 6px;
}
.pagination-wrap {
  margin-top: 12px;
  text-align: center;
}
.text-muted { color: #909399; font-size: 12px; }
.text-success { color: #67c23a; }
.text-danger { color: #f56c6c; }
</style>
