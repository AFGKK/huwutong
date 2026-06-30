<template>
  <div>
    <!-- 概览卡片 -->
    <el-card shadow="never" class="mb-4">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-medium">SDK 版本兼容策略</h2>
        <div class="flex gap-2">
          <el-button size="small" @click="seedDefaults" :loading="seeding">导入默认版本</el-button>
          <el-button size="small" type="warning" @click="handleProcessExpired" :loading="processing">处理过期版本</el-button>
        </div>
      </div>
      <el-row :gutter="16">
        <el-col :span="6" v-for="(lang, key) in dashboard" :key="key">
          <el-card shadow="hover" class="mb-2">
            <div class="text-sm text-gray-500">{{ lang.name }}</div>
            <div class="text-lg font-bold mt-1">{{ lang.current_version || '-' }}</div>
            <div class="text-xs text-gray-400 mt-1">
              <el-tag size="small" type="success" class="mr-1">{{ lang.stages.stable }} 稳定</el-tag>
              <el-tag size="small" type="warning" class="mr-1">{{ lang.stages.deprecated }} 废弃</el-tag>
              <el-tag size="small" type="danger">{{ lang.stages.sunset }} 停服</el-tag>
            </div>
          </el-card>
        </el-col>
      </el-row>
    </el-card>

    <!-- 版本列表 -->
    <el-card shadow="never">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-base font-medium">版本列表</h3>
        <el-button size="small" type="primary" @click="showCreate = true">注册新版本</el-button>
      </div>

      <!-- 语言标签 -->
      <el-tabs v-model="activeLang" @tab-change="fetchVersions">
        <el-tab-pane v-for="lang in languages" :key="lang" :label="langLabels[lang]" :name="lang" />
      </el-tabs>

      <el-table :data="versions" v-loading="loading" stripe style="width: 100%">
        <el-table-column prop="version" label="版本号" width="120" />
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="stageType(row.stage)" size="small">{{ stageLabel(row.stage) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="当前推荐" width="100" align="center">
          <template #default="{ row }">
            <el-tag v-if="row.is_current" type="success" size="small">✓</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="allow_production" label="生产可用" width="100" align="center">
          <template #default="{ row }">
            <el-tag v-if="row.allow_production" type="success" size="small">是</el-tag>
            <el-tag v-else type="danger" size="small">否</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="min_api_version" label="最小API版本" width="120" />
        <el-table-column prop="released_at" label="发布时间" width="180" />
        <el-table-column prop="deprecated_at" label="废弃时间" width="180" />
        <el-table-column prop="sunset_at" label="停服时间" width="180" />
        <el-table-column label="操作" width="280" fixed="right">
          <template #default="{ row }">
            <el-button size="small" @click="showDetail(row)">详情</el-button>
            <el-button v-if="row.stage === 'stable'" size="small" type="warning" @click="handleDeprecate(row)">标记废弃</el-button>
            <el-button v-if="row.stage === 'deprecated'" size="small" type="danger" @click="handleSunset(row)">停服</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- 新建版本对话框 -->
    <el-dialog v-model="showCreate" title="注册新版本" width="500px">
      <el-form :model="form" label-width="120px">
        <el-form-item label="语言" required>
          <el-select v-model="form.language" style="width:100%">
            <el-option v-for="l in languages" :key="l" :label="langLabels[l]" :value="l" />
          </el-select>
        </el-form-item>
        <el-form-item label="版本号" required>
          <el-input v-model="form.version" placeholder="如 1.1.0" />
        </el-form-item>
        <el-form-item label="阶段">
          <el-select v-model="form.stage" style="width:100%">
            <el-option label="预览版" value="preview" />
            <el-option label="稳定版" value="stable" />
          </el-select>
        </el-form-item>
        <el-form-item label="设为推荐">
          <el-switch v-model="form.is_current" />
        </el-form-item>
        <el-form-item label="最小API版本">
          <el-input v-model="form.min_api_version" placeholder="v1" />
        </el-form-item>
        <el-form-item label="更新日志">
          <el-input v-model="form.changelog" type="textarea" :rows="3" />
        </el-form-item>
        <el-form-item label="升级说明">
          <el-input v-model="form.upgrade_notes" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreate = false">取消</el-button>
        <el-button type="primary" @click="handleCreate" :loading="creating">确认注册</el-button>
      </template>
    </el-dialog>

    <!-- 详情对话框 -->
    <el-dialog v-model="showDetailDialog" :title="'版本详情 - ' + detail.version" width="600px">
      <el-descriptions :column="2" border>
        <el-descriptions-item label="语言">{{ detail.language }}</el-descriptions-item>
        <el-descriptions-item label="版本号">{{ detail.version }}</el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="stageType(detail.stage)" size="small">{{ stageLabel(detail.stage) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="当前推荐">
          <el-tag v-if="detail.is_current" type="success">是</el-tag>
          <el-tag v-else type="info">否</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="生产可用">
          <el-tag v-if="detail.allow_production" type="success">是</el-tag>
          <el-tag v-else type="danger">否</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="最小API版本">{{ detail.min_api_version }}</el-descriptions-item>
        <el-descriptions-item label="发布时间">{{ detail.released_at }}</el-descriptions-item>
        <el-descriptions-item label="废弃时间">{{ detail.deprecated_at || '-' }}</el-descriptions-item>
        <el-descriptions-item label="停服时间">{{ detail.sunset_at || '-' }}</el-descriptions-item>
      </el-descriptions>
      <div v-if="detail.changelog" class="mt-4">
        <h4 class="text-sm font-medium mb-2">更新日志</h4>
        <p class="text-sm text-gray-600 whitespace-pre-wrap">{{ detail.changelog }}</p>
      </div>
      <div v-if="detail.upgrade_notes" class="mt-4">
        <h4 class="text-sm font-medium mb-2">升级说明</h4>
        <p class="text-sm text-gray-600 whitespace-pre-wrap">{{ detail.upgrade_notes }}</p>
      </div>

      <!-- 升级检查 -->
      <el-divider />
      <div class="mt-2">
        <h4 class="text-sm font-medium mb-2">升级检查</h4>
        <el-button size="small" @click="checkVersionUpgrade" :loading="checkingUpgrade">检查升级需求</el-button>
        <div v-if="upgradeResult" class="mt-2">
          <el-alert v-if="upgradeResult.needs_upgrade" :title="'需要升级：' + upgradeResult.reason" type="warning" show-icon />
          <el-alert v-else title="当前版本无需升级" type="success" show-icon />
          <div v-if="upgradeResult.upgrade_to" class="mt-2">
            <span class="text-sm">推荐升级版本：</span>
            <el-tag type="primary">{{ upgradeResult.upgrade_to }}</el-tag>
          </div>
        </div>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
  getSdkDashboard, getSdkVersions, getLanguageVersions, getSdkVersion,
  createSdkVersion, markDeprecated, markSunset,
  seedDefaultVersions, processExpired as processExpiredApi, checkUpgrade,
} from '../../api/sdkVersion';

const loading = ref(false);
const seeding = ref(false);
const processing = ref(false);
const creating = ref(false);
const checkingUpgrade = ref(false);

const dashboard = ref({});
const versions = ref([]);
const activeLang = ref('php');
const languages = ['php', 'node', 'python', 'go', 'java'];
const langLabels = { php: 'PHP', node: 'Node.js', python: 'Python', go: 'Go', java: 'Java' };

const showCreate = ref(false);
const form = ref({
  language: 'php', version: '', stage: 'stable', is_current: false,
  min_api_version: 'v1', changelog: '', upgrade_notes: '',
});

const showDetailDialog = ref(false);
const detail = ref({});
const upgradeResult = ref(null);

onMounted(async () => {
  await fetchDashboard();
  await fetchVersions();
});

async function fetchDashboard() {
  try {
    const res = await getSdkDashboard();
    dashboard.value = res.data.data || {};
  } catch (e) { /* ignore */ }
}

async function fetchVersions() {
  loading.value = true;
  try {
    const res = await getLanguageVersions(activeLang.value);
    versions.value = res.data.data || [];
  } catch (e) { /* ignore */ }
  loading.value = false;
}

async function handleCreate() {
  creating.value = true;
  try {
    await createSdkVersion(form.value);
    ElMessage.success('版本注册成功');
    showCreate.value = false;
    form.value = { language: 'php', version: '', stage: 'stable', is_current: false, min_api_version: 'v1', changelog: '', upgrade_notes: '' };
    await fetchVersions();
    await fetchDashboard();
  } catch (e) { /* ignore */ }
  creating.value = false;
}

async function showDetail(row) {
  try {
    const res = await getSdkVersion(row.id);
    detail.value = res.data.data || row;
    upgradeResult.value = null;
    showDetailDialog.value = true;
  } catch (e) { /* ignore */ }
}

async function checkVersionUpgrade() {
  checkingUpgrade.value = true;
  try {
    const res = await checkUpgrade(detail.value.language, detail.value.version);
    upgradeResult.value = res.data.data;
  } catch (e) { /* ignore */ }
  checkingUpgrade.value = false;
}

async function handleDeprecate(row) {
  try {
    await ElMessageBox.confirm(`确认将 ${row.version} 标记为废弃？`, '确认', { type: 'warning' });
    await markDeprecated(row.id);
    ElMessage.success('已标记为废弃');
    await fetchVersions();
    await fetchDashboard();
  } catch (e) { /* ignore */ }
}

async function handleSunset(row) {
  try {
    await ElMessageBox.confirm(`确认将 ${row.version} 停服？此操作不可撤销！`, '确认', { type: 'danger' });
    await markSunset(row.id);
    ElMessage.success('已停服');
    await fetchVersions();
    await fetchDashboard();
  } catch (e) { /* ignore */ }
}

async function seedDefaults() {
  seeding.value = true;
  try {
    const res = await seedDefaultVersions();
    ElMessage.success(res.data.message || '导入成功');
    await fetchVersions();
    await fetchDashboard();
  } catch (e) { /* ignore */ }
  seeding.value = false;
}

async function handleProcessExpired() {
  processing.value = true;
  try {
    const res = await processExpiredApi();
    ElMessage.success(res.data.message || '处理完成');
    await fetchVersions();
    await fetchDashboard();
  } catch (e) { /* ignore */ }
  processing.value = false;
}

function stageType(stage) {
  return { preview: 'info', stable: 'success', deprecated: 'warning', sunset: 'danger' }[stage] || 'info';
}
function stageLabel(stage) {
  return { preview: '预览版', stable: '稳定版', deprecated: '已废弃', sunset: '已停服' }[stage] || stage;
}
</script>
