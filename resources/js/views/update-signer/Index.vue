<template>
  <div>
    <!-- 概览卡片 -->
    <el-card shadow="never" class="mb-4">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-medium">更新签名验证 &amp; 回滚 &amp; 灰度发布</h2>
      </div>
      <el-row :gutter="16">
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">签名验证通过率</div>
            <div class="text-2xl font-bold mt-1" :class="passRateColor">{{ dashboard.pass_rate || 100 }}%</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">待审批回滚</div>
            <div class="text-2xl font-bold mt-1 text-yellow-500">{{ dashboard.pending_rollbacks || 0 }}</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">活跃灰度发布</div>
            <div class="text-2xl font-bold mt-1 text-blue-500">{{ dashboard.active_gray_releases || 0 }}</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">总验证次数</div>
            <div class="text-2xl font-bold mt-1">{{ dashboard.total_verifications || 0 }}</div>
          </el-card>
        </el-col>
      </el-row>
    </el-card>

    <!-- Tabs -->
    <el-card shadow="never">
      <el-tabs v-model="activeTab">
        <!-- 签名验证 -->
        <el-tab-pane label="签名验证" name="verification">
          <div class="mb-3 flex gap-2">
            <el-select v-model="vFilters.verified" placeholder="结果" clearable size="small" style="width:120px">
              <el-option label="通过" value="true" />
              <el-option label="失败" value="false" />
            </el-select>
            <el-button size="small" @click="fetchVerifications">搜索</el-button>
          </div>
          <el-table :data="verifications" v-loading="loadingV" stripe style="width:100%">
            <el-table-column prop="update_package_id" label="包ID" width="80" />
            <el-table-column label="结果" width="80" align="center">
              <template #default="{ row }">
                <el-tag :type="row.verified ? 'success' : 'danger'" size="small">{{ row.verified ? '通过' : '失败' }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="algorithm" label="算法" width="100" />
            <el-table-column prop="sdk_instance_id" label="SDK实例" width="160" show-overflow-tooltip />
            <el-table-column prop="file_hash" label="文件哈希" width="140" show-overflow-tooltip />
            <el-table-column prop="error_message" label="错误信息" min-width="200" show-overflow-tooltip />
            <el-table-column prop="created_at" label="时间" width="180" />
          </el-table>
          <div class="mt-3 flex justify-between items-center">
            <span class="text-sm text-gray-500">共 {{ vPagination.total }} 条</span>
            <el-pagination v-model:current-page="vPagination.page" :page-size="vPagination.per_page" :total="vPagination.total" layout="prev, pager, next" small @current-change="fetchVerifications" />
          </div>
        </el-tab-pane>

        <!-- 回滚管理 -->
        <el-tab-pane label="回滚管理" name="rollback">
          <div class="mb-3 flex gap-2">
            <el-select v-model="rFilters.status" placeholder="状态" clearable size="small" style="width:140px">
              <el-option label="待审批" value="pending" />
              <el-option label="已审批" value="approved" />
              <el-option label="已拒绝" value="rejected" />
              <el-option label="已执行" value="executed" />
              <el-option label="失败" value="failed" />
            </el-select>
            <el-button size="small" @click="fetchRollbacks">搜索</el-button>
          </div>
          <el-table :data="rollbacks" v-loading="loadingR" stripe style="width:100%">
            <el-table-column prop="from_version" label="当前版本" width="120" />
            <el-table-column prop="to_version" label="目标版本" width="120" />
            <el-table-column prop="trigger_type" label="触发类型" width="120" />
            <el-table-column label="状态" width="100">
              <template #default="{ row }">
                <el-tag :type="rbStatusType(row.status)" size="small">{{ rbStatusLabel(row.status) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="reason" label="原因" min-width="220" show-overflow-tooltip />
            <el-table-column prop="affected_instances" label="影响实例" width="80" align="center" />
            <el-table-column prop="created_at" label="创建时间" width="180" />
            <el-table-column label="操作" width="160" fixed="right">
              <template #default="{ row }">
                <el-button v-if="row.status === 'pending'" size="small" type="success" @click="handleApprove(row)">审批</el-button>
                <el-button v-if="row.status === 'approved'" size="small" type="primary" @click="handleExecute(row)">执行</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="mt-3 flex justify-between items-center">
            <span class="text-sm text-gray-500">共 {{ rPagination.total }} 条</span>
            <el-pagination v-model:current-page="rPagination.page" :page-size="rPagination.per_page" :total="rPagination.total" layout="prev, pager, next" small @current-change="fetchRollbacks" />
          </div>
        </el-tab-pane>

        <!-- 灰度发布 -->
        <el-tab-pane label="灰度发布" name="gray">
          <div class="mb-3 flex gap-2">
            <el-select v-model="gFilters.status" placeholder="状态" clearable size="small" style="width:140px">
              <el-option label="待启动" value="pending" />
              <el-option label="运行中" value="running" />
              <el-option label="已暂停" value="paused" />
              <el-option label="已完成" value="completed" />
            </el-select>
            <el-button size="small" @click="fetchGrayReleases">搜索</el-button>
          </div>
          <el-table :data="grayReleases" v-loading="loadingG" stripe style="width:100%">
            <el-table-column prop="update_package_id" label="包ID" width="80" />
            <el-table-column prop="strategy" label="策略" width="100" />
            <el-table-column prop="current_stage" label="当前阶段" width="100" />
            <el-table-column prop="current_percentage" label="百分比" width="80" align="center">
              <template #default="{ row }">{{ row.current_percentage }}%</template>
            </el-table-column>
            <el-table-column label="状态" width="100">
              <template #default="{ row }">
                <el-tag :type="gsStatusType(row.status)" size="small">{{ gsStatusLabel(row.status) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="target_regions" label="目标区域" width="150" show-overflow-tooltip>
              <template #default="{ row }">{{ row.target_regions?.join(', ') || '-' }}</template>
            </el-table-column>
            <el-table-column prop="stage_started_at" label="阶段开始" width="180" />
            <el-table-column prop="stage_ends_at" label="阶段结束" width="180" />
            <el-table-column label="操作" width="180" fixed="right">
              <template #default="{ row }">
                <el-button v-if="row.status === 'pending'" size="small" type="primary" @click="handleStartGray(row)">启动</el-button>
                <el-button v-if="row.status === 'running'" size="small" type="success" @click="handleAdvanceGray(row)">推进</el-button>
                <el-button v-if="row.status === 'running'" size="small" type="warning" @click="handlePauseGray(row)">暂停</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="mt-3 flex justify-between items-center">
            <span class="text-sm text-gray-500">共 {{ gPagination.total }} 条</span>
            <el-pagination v-model:current-page="gPagination.page" :page-size="gPagination.per_page" :total="gPagination.total" layout="prev, pager, next" small @current-change="fetchGrayReleases" />
          </div>
        </el-tab-pane>
      </el-tabs>
    </el-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
  getSignerDashboard, getVerificationLogs, getRollbacks, getGrayReleases,
  approveRollback, executeRollback, startGrayRelease, advanceGrayRelease, pauseGrayRelease,
} from '../../api/updateSigner';

// ── 状态 ──
const loadingV = ref(false);
const loadingR = ref(false);
const loadingG = ref(false);
const activeTab = ref('verification');

const dashboard = ref({});
const verifications = ref([]);
const rollbacks = ref([]);
const grayReleases = ref([]);

const vPagination = ref({ page: 1, per_page: 20, total: 0 });
const rPagination = ref({ page: 1, per_page: 20, total: 0 });
const gPagination = ref({ page: 1, per_page: 20, total: 0 });

const vFilters = ref({ verified: '' });
const rFilters = ref({ status: '' });
const gFilters = ref({ status: '' });

// ── 计算属性 ──
const passRateColor = computed(() => {
  const rate = dashboard.value.pass_rate || 100;
  if (rate >= 95) return 'text-green-500';
  if (rate >= 80) return 'text-yellow-500';
  return 'text-red-500';
});

// ── 方法 ──
onMounted(async () => {
  await fetchDashboard();
  await fetchVerifications();
});

async function fetchDashboard() {
  try { const res = await getSignerDashboard(); dashboard.value = res.data.data || {}; } catch (e) {}
}

async function fetchVerifications() {
  loadingV.value = true;
  try {
    const params = { ...vFilters.value, page: vPagination.value.page, per_page: vPagination.value.per_page };
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
    const res = await getVerificationLogs(params);
    const data = res.data.data;
    verifications.value = data.data || [];
    vPagination.value = { page: data.current_page || 1, per_page: data.per_page || 20, total: data.total || 0 };
  } catch (e) {}
  loadingV.value = false;
}

async function fetchRollbacks() {
  loadingR.value = true;
  try {
    const params = { ...rFilters.value, page: rPagination.value.page, per_page: rPagination.value.per_page };
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
    const res = await getRollbacks(params);
    const data = res.data.data;
    rollbacks.value = data.data || [];
    rPagination.value = { page: data.current_page || 1, per_page: data.per_page || 20, total: data.total || 0 };
  } catch (e) {}
  loadingR.value = false;
}

async function fetchGrayReleases() {
  loadingG.value = true;
  try {
    const params = { ...gFilters.value, page: gPagination.value.page, per_page: gPagination.value.per_page };
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
    const res = await getGrayReleases(params);
    const data = res.data.data;
    grayReleases.value = data.data || [];
    gPagination.value = { page: data.current_page || 1, per_page: data.per_page || 20, total: data.total || 0 };
  } catch (e) {}
  loadingG.value = false;
}

async function handleApprove(row) {
  try {
    await ElMessageBox.confirm(`确认审批回滚 ${row.from_version} → ${row.to_version}？`, '确认');
    await approveRollback(row.id);
    ElMessage.success('已审批');
    await fetchRollbacks();
    await fetchDashboard();
  } catch (e) {}
}

async function handleExecute(row) {
  try {
    await ElMessageBox.confirm(`确认执行回滚 ${row.from_version} → ${row.to_version}？`, '确认', { type: 'warning' });
    await executeRollback(row.id);
    ElMessage.success('回滚已执行');
    await fetchRollbacks();
    await fetchDashboard();
  } catch (e) {}
}

async function handleStartGray(row) {
  try {
    await startGrayRelease(row.id);
    ElMessage.success('灰度发布已启动');
    await fetchGrayReleases();
    await fetchDashboard();
  } catch (e) {}
}

async function handleAdvanceGray(row) {
  try {
    const res = await advanceGrayRelease(row.id);
    ElMessage.success(res.data.message || '已推进到下一阶段');
    await fetchGrayReleases();
    await fetchDashboard();
  } catch (e) {}
}

async function handlePauseGray(row) {
  try {
    await pauseGrayRelease(row.id);
    ElMessage.success('已暂停');
    await fetchGrayReleases();
  } catch (e) {}
}

function rbStatusType(s) {
  return { pending: 'warning', approved: 'primary', rejected: 'danger', executed: 'success', failed: 'danger', rolled_forward: 'info' }[s] || 'info';
}
function rbStatusLabel(s) {
  return { pending: '待审批', approved: '已审批', rejected: '已拒绝', executed: '已执行', failed: '失败', rolled_forward: '已恢复' }[s] || s;
}

function gsStatusType(s) {
  return { pending: 'info', running: 'success', paused: 'warning', completed: 'primary', rolled_back: 'danger' }[s] || 'info';
}
function gsStatusLabel(s) {
  return { pending: '待启动', running: '运行中', paused: '已暂停', completed: '已完成', rolled_back: '已回滚' }[s] || s;
}
</script>
