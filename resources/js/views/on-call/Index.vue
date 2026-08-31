<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold">{{ t(`${P}.title`) }}</h1>
        <p class="text-gray-500 text-sm mt-1">{{ t(`${P}.subtitle`) }}</p>
      </div>
      <el-button type="primary" @click="showCreate = true">
        <el-icon><Plus /></el-icon> {{ t(`${P}.create`) }}
      </el-button>
    </div>

    <el-card shadow="never" class="mb-6">
      <template #header><span>{{ t(`${P}.current`) }}</span></template>
      <el-row :gutter="16" v-if="currentOnCall.length">
        <el-col v-for="entry in currentOnCall" :key="entry.id" :span="8">
          <el-card shadow="hover" class="oncall-card">
            <div class="text-sm font-bold">{{ entry.schedule?.name }}</div>
            <div class="flex items-center gap-2 mt-2">
              <el-avatar :size="32">{{ (entry.replacement_user?.name || entry.user?.name)?.[0] }}</el-avatar>
              <div>
                <div class="font-medium">{{ entry.replacement_user?.name || entry.user?.name }}</div>
                <el-tag :type="roleTag(entry.role)" size="small">{{ roleLabel(entry.role) }}</el-tag>
                <span v-if="entry.overridden" class="text-warning text-xs ml-1">{{ t(`${P}.replaced`) }}</span>
              </div>
            </div>
            <div class="text-xs text-gray-400 mt-2">
              {{ formatTime(entry.starts_at) }} ~ {{ formatTime(entry.ends_at) }}
            </div>
          </el-card>
        </el-col>
      </el-row>
      <el-empty v-else :description="t(`${P}.empty`)" :image-size="60" />
    </el-card>

    <el-row :gutter="16" class="mb-6">
      <el-col :span="6">
        <el-card shadow="never" class="stat-card"><div class="stat-value">{{ dashboard.total_schedules }}</div><div class="stat-label">{{ t(`${P}.stats.schedules`) }}</div></el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card"><div class="stat-value">{{ dashboard.total_members }}</div><div class="stat-label">{{ t(`${P}.stats.members`) }}</div></el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card"><div class="stat-value">{{ dashboard.active_entries }}</div><div class="stat-label">{{ t(`${P}.stats.active`) }}</div></el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card"><div class="stat-value">{{ upcomingShifts.length }}</div><div class="stat-label">{{ t(`${P}.stats.upcoming`) }}</div></el-card>
      </el-col>
    </el-row>

    <el-card shadow="never" class="mb-6">
      <template #header><span>{{ t(`${P}.list_title`) }}</span></template>
      <el-table :data="schedules" v-loading="loading" stripe>
        <el-table-column prop="name" :label="t(`${P}.cols.name`)" min-width="180" />
        <el-table-column prop="rotation_type" :label="t(`${P}.cols.rotation`)" width="100">
          <template #default="{ row }">{{ rotationLabel(row.rotation_type) }}</template>
        </el-table-column>
        <el-table-column :label="t(`${P}.cols.members`)" min-width="200">
          <template #default="{ row }">
            <el-tag v-for="m in (row.members || []).filter(m => m.is_active)" :key="m.id" size="small" class="mr-1 mb-1">
              {{ m.user?.name || t(`${P}.unknown`) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="status" :label="t(`${P}.cols.status`)" width="80">
          <template #default="{ row }">
            <el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">{{ row.status === 'active' ? t('actions.enable') : t('actions.disable') }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="t(`${P}.cols.actions`)" width="200" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="viewDetail(row)">{{ t(`${P}.detail`) }}</el-button>
            <el-button link type="primary" size="small" @click="handleGenerate(row)">{{ t(`${P}.generate`) }}</el-button>
            <el-popconfirm :title="t(`${P}.confirm_delete`)" @confirm="handleDelete(row)">
              <template #reference><el-button link type="danger" size="small">{{ t('actions.delete') }}</el-button></template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-card shadow="never" v-if="upcomingShifts.length">
      <template #header><span>{{ t(`${P}.upcoming_title`) }}</span></template>
      <div v-for="s in upcomingShifts" :key="s.id" class="flex items-center justify-between py-2 border-b last:border-0">
        <div>
          <span class="font-medium">{{ s.user?.name }}</span>
          <el-tag :type="roleTag(s.role)" size="small" class="ml-2">{{ roleLabel(s.role) }}</el-tag>
          <span class="text-xs text-gray-400 ml-2">{{ s.schedule?.name }}</span>
        </div>
        <div class="text-sm text-gray-500">{{ formatTime(s.starts_at) }} → {{ formatTime(s.ends_at) }}</div>
      </div>
    </el-card>

    <el-dialog v-model="showCreate" :title="t(`${P}.create`)" width="500px">
      <el-form :model="form" label-position="top">
        <el-form-item :label="t(`${P}.cols.name`)" required><el-input v-model="form.name" :placeholder="t(`${P}.name_ph`)" /></el-form-item>
        <el-form-item :label="t(`${P}.cols.rotation`)">
          <el-select v-model="form.rotation_type" style="width:100%">
            <el-option :label="t(`${P}.rotation.daily`)" value="daily" />
            <el-option :label="t(`${P}.rotation.weekly`)" value="weekly" />
            <el-option :label="t(`${P}.rotation.biweekly`)" value="biweekly" />
            <el-option :label="t(`${P}.rotation.monthly`)" value="monthly" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t(`${P}.channels_label`)">
          <el-checkbox-group v-model="form.channels">
            <el-checkbox label="database">{{ t(`${P}.channels.database`) }}</el-checkbox>
            <el-checkbox label="email">{{ t(`${P}.channels.email`) }}</el-checkbox>
            <el-checkbox label="sms">{{ t(`${P}.channels.sms`) }}</el-checkbox>
            <el-checkbox label="slack">Slack</el-checkbox>
          </el-checkbox-group>
        </el-form-item>
        <el-form-item :label="t(`${P}.color`)">
          <el-color-picker v-model="form.color" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreate = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="saving" @click="handleCreate">{{ t('actions.create') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import onCallApi from '@/api/onCall';

const { t, locale } = useI18n();
const P = 'on_call_page';
const dateLocale = computed(() => (locale.value?.startsWith('zh') ? 'zh-CN' : 'en-US'));

const loading = ref(false);
const saving = ref(false);
const schedules = ref([]);
const dashboard = ref({ total_schedules: 0, total_members: 0, active_entries: 0, current_on_call: [], upcoming_shifts: [], schedules: [] });
const showCreate = ref(false);
const form = reactive({ name: '', rotation_type: 'weekly', channels: ['database', 'email'], color: '#0f172a' });

const currentOnCall = computed(() => dashboard.value.current_on_call || []);
const upcomingShifts = computed(() => dashboard.value.upcoming_shifts || []);

function rotationLabel(type) {
  const key = `${P}.rotation_short.${type}`;
  const translated = t(key);
  return translated === key ? type : translated;
}
function roleLabel(r) {
  const key = `${P}.roles.${r}`;
  const translated = t(key);
  return translated === key ? r : translated;
}
function roleTag(r) { return { primary:'danger', backup:'warning', escalation:'info' }[r] || ''; }
function formatTime(time) { return time ? new Date(time).toLocaleString(dateLocale.value) : ''; }

async function loadData() {
  loading.value = true;
  try {
    const [dashRes, listRes] = await Promise.all([onCallApi.dashboard(), onCallApi.list()]);
    dashboard.value = dashRes.data?.data || dashboard.value;
    schedules.value = listRes.data?.data || [];
  } catch { ElMessage.error(t('messages.load_failed')); }
  finally { loading.value = false; }
}

async function handleCreate() {
  if (!form.name.trim()) return ElMessage.warning(t(`${P}.name_required`));
  saving.value = true;
  try {
    await onCallApi.create({ ...form });
    ElMessage.success(t(`${P}.messages.created`));
    showCreate.value = false;
    form.name = '';
    await loadData();
  } catch { ElMessage.error(t(`${P}.messages.create_failed`)); }
  finally { saving.value = false; }
}

async function handleGenerate(schedule) {
  try {
    await ElMessageBox.confirm(t(`${P}.confirm_generate`, { name: schedule.name }), t(`${P}.generate`));
    const res = await onCallApi.generate(schedule.id);
    ElMessage.success(res.data?.message || t(`${P}.messages.generated`));
    await loadData();
  } catch { /* cancelled */ }
}

async function handleDelete(schedule) {
  try { await onCallApi.remove(schedule.id); ElMessage.success(t(`${P}.messages.deleted`)); await loadData(); }
  catch { ElMessage.error(t(`${P}.messages.delete_failed`)); }
}

function viewDetail(schedule) {
  const members = (schedule.members || []).map(m => m.user?.name).join(', ') || t(`${P}.none`);
  ElMessageBox.alert(
    t(`${P}.detail_body`, { name: schedule.name, type: rotationLabel(schedule.rotation_type), members }),
    t(`${P}.detail_title`),
  );
}

onMounted(loadData);
</script>

<style scoped>
.stat-card { text-align: center; }
.stat-value { font-size: 28px; font-weight: 700; color: #0f172a; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.oncall-card { cursor: default; }
.text-warning { color: #e6a23c; }
</style>
