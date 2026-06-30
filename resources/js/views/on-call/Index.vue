<template>
  <div class="p-6">
    <!-- 头部 -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold">📟 值班轮换 On-Call</h1>
        <p class="text-gray-500 text-sm mt-1">管理值班排班，告警自动路由到当前值班人</p>
      </div>
      <el-button type="primary" @click="showCreate = true">
        <el-icon><Plus /></el-icon> 新建排班
      </el-button>
    </div>

    <!-- 当前值班 -->
    <el-card shadow="never" class="mb-6">
      <template #header><span>🟢 当前值班</span></template>
      <el-row :gutter="16" v-if="currentOnCall.length">
        <el-col v-for="entry in currentOnCall" :key="entry.id" :span="8">
          <el-card shadow="hover" class="oncall-card">
            <div class="text-sm font-bold">{{ entry.schedule?.name }}</div>
            <div class="flex items-center gap-2 mt-2">
              <el-avatar :size="32">{{ (entry.replacement_user?.name || entry.user?.name)?.[0] }}</el-avatar>
              <div>
                <div class="font-medium">{{ entry.replacement_user?.name || entry.user?.name }}</div>
                <el-tag :type="roleTag(entry.role)" size="small">{{ roleLabel(entry.role) }}</el-tag>
                <span v-if="entry.overridden" class="text-warning text-xs ml-1">(已替换)</span>
              </div>
            </div>
            <div class="text-xs text-gray-400 mt-2">
              {{ formatTime(entry.starts_at) }} ~ {{ formatTime(entry.ends_at) }}
            </div>
          </el-card>
        </el-col>
      </el-row>
      <el-empty v-else description="暂无值班安排" :image-size="60" />
    </el-card>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="mb-6">
      <el-col :span="6">
        <el-card shadow="never" class="stat-card"><div class="stat-value">{{ dashboard.total_schedules }}</div><div class="stat-label">排班数</div></el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card"><div class="stat-value">{{ dashboard.total_members }}</div><div class="stat-label">值班成员</div></el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card"><div class="stat-value">{{ dashboard.active_entries }}</div><div class="stat-label">当前值班</div></el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card"><div class="stat-value">{{ upcomingShifts.length }}</div><div class="stat-label">即将值班</div></el-card>
      </el-col>
    </el-row>

    <!-- 排班列表 -->
    <el-card shadow="never" class="mb-6">
      <template #header><span>📋 排班列表</span></template>
      <el-table :data="schedules" v-loading="loading" stripe>
        <el-table-column prop="name" label="名称" min-width="180" />
        <el-table-column prop="rotation_type" label="轮换类型" width="100">
          <template #default="{ row }">{{ rotationLabel(row.rotation_type) }}</template>
        </el-table-column>
        <el-table-column label="成员" min-width="200">
          <template #default="{ row }">
            <el-tag v-for="m in (row.members || []).filter(m => m.is_active)" :key="m.id" size="small" class="mr-1 mb-1">
              {{ m.user?.name || '未知' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="80">
          <template #default="{ row }">
            <el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">{{ row.status === 'active' ? '启用' : '停用' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="viewDetail(row)">详情</el-button>
            <el-button link type="primary" size="small" @click="handleGenerate(row)">生成排班</el-button>
            <el-popconfirm title="确定删除？" @confirm="handleDelete(row)">
              <template #reference><el-button link type="danger" size="small">删除</el-button></template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- 即将到来的值班 -->
    <el-card shadow="never" v-if="upcomingShifts.length">
      <template #header><span>📅 即将值班</span></template>
      <div v-for="s in upcomingShifts" :key="s.id" class="flex items-center justify-between py-2 border-b last:border-0">
        <div>
          <span class="font-medium">{{ s.user?.name }}</span>
          <el-tag :type="roleTag(s.role)" size="small" class="ml-2">{{ roleLabel(s.role) }}</el-tag>
          <span class="text-xs text-gray-400 ml-2">{{ s.schedule?.name }}</span>
        </div>
        <div class="text-sm text-gray-500">{{ formatTime(s.starts_at) }} → {{ formatTime(s.ends_at) }}</div>
      </div>
    </el-card>

    <!-- 新建排班对话框 -->
    <el-dialog v-model="showCreate" title="新建排班" width="500px">
      <el-form :model="form" label-position="top">
        <el-form-item label="名称" required><el-input v-model="form.name" placeholder="如：一线值班" /></el-form-item>
        <el-form-item label="轮换类型">
          <el-select v-model="form.rotation_type" style="width:100%">
            <el-option label="每日轮换" value="daily" />
            <el-option label="每周轮换" value="weekly" />
            <el-option label="双周轮换" value="biweekly" />
            <el-option label="每月轮换" value="monthly" />
          </el-select>
        </el-form-item>
        <el-form-item label="通知渠道">
          <el-checkbox-group v-model="form.channels">
            <el-checkbox label="database">站内信</el-checkbox>
            <el-checkbox label="email">邮件</el-checkbox>
            <el-checkbox label="sms">短信</el-checkbox>
            <el-checkbox label="slack">Slack</el-checkbox>
          </el-checkbox-group>
        </el-form-item>
        <el-form-item label="颜色">
          <el-color-picker v-model="form.color" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreate = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="handleCreate">创建</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import onCallApi from '@/api/onCall';

const loading = ref(false);
const saving = ref(false);
const schedules = ref([]);
const dashboard = ref({ total_schedules: 0, total_members: 0, active_entries: 0, current_on_call: [], upcoming_shifts: [], schedules: [] });
const showCreate = ref(false);
const form = reactive({ name: '', rotation_type: 'weekly', channels: ['database', 'email'], color: '#409eff' });

const currentOnCall = computed(() => dashboard.value.current_on_call || []);
const upcomingShifts = computed(() => dashboard.value.upcoming_shifts || []);

function rotationLabel(t) { return { daily:'每日', weekly:'每周', biweekly:'双周', monthly:'每月' }[t] || t; }
function roleLabel(r) { return { primary:'一线', backup:'二线', escalation:'三线' }[r] || r; }
function roleTag(r) { return { primary:'danger', backup:'warning', escalation:'info' }[r] || ''; }
function formatTime(t) { return t ? new Date(t).toLocaleString('zh-CN') : ''; }

async function loadData() {
  loading.value = true;
  try {
    const [dashRes, listRes] = await Promise.all([onCallApi.dashboard(), onCallApi.list()]);
    dashboard.value = dashRes.data?.data || dashboard.value;
    schedules.value = listRes.data?.data || [];
  } catch { ElMessage.error('加载失败'); }
  finally { loading.value = false; }
}

async function handleCreate() {
  if (!form.name.trim()) return ElMessage.warning('请输入名称');
  saving.value = true;
  try {
    await onCallApi.create({ ...form });
    ElMessage.success('排班已创建');
    showCreate.value = false;
    form.name = '';
    await loadData();
  } catch { ElMessage.error('创建失败'); }
  finally { saving.value = false; }
}

async function handleGenerate(schedule) {
  try {
    await ElMessageBox.confirm(`将为「${schedule.name}」生成未来90天排班，确定？`, '生成排班');
    const res = await onCallApi.generate(schedule.id);
    ElMessage.success(res.data?.message || '排班已生成');
    await loadData();
  } catch { /* cancelled */ }
}

async function handleDelete(schedule) {
  try { await onCallApi.remove(schedule.id); ElMessage.success('已删除'); await loadData(); }
  catch { ElMessage.error('删除失败'); }
}

function viewDetail(schedule) {
  ElMessageBox.alert(`排班: ${schedule.name}\n类型: ${rotationLabel(schedule.rotation_type)}\n成员: ${(schedule.members || []).map(m => m.user?.name).join(', ') || '无'}`, '排班详情');
}

onMounted(loadData);
</script>

<style scoped>
.stat-card { text-align: center; }
.stat-value { font-size: 28px; font-weight: 700; color: #409eff; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.oncall-card { cursor: default; }
</style>
