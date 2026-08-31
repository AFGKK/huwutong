<template>
  <div>
    <el-card shadow="never" class="mb-4">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-medium">{{ t(`${P}.title`) }}</h2>
        <div class="flex gap-2">
          <el-button size="small" @click="seedDefaults" :loading="seeding">{{ t(`${P}.seed_defaults`) }}</el-button>
          <el-button size="small" type="warning" @click="handleProcessExpired" :loading="processing">{{ t(`${P}.process_expired`) }}</el-button>
        </div>
      </div>
      <el-row :gutter="16">
        <el-col :span="6" v-for="(lang, key) in dashboard" :key="key">
          <el-card shadow="hover" class="mb-2">
            <div class="text-sm text-gray-500">{{ lang.name }}</div>
            <div class="text-lg font-bold mt-1">{{ lang.current_version || '-' }}</div>
            <div class="text-xs text-gray-400 mt-1">
              <el-tag size="small" type="success" class="mr-1">{{ lang.stages.stable }} {{ t(`${P}.stage_short.stable`) }}</el-tag>
              <el-tag size="small" type="warning" class="mr-1">{{ lang.stages.deprecated }} {{ t(`${P}.stage_short.deprecated`) }}</el-tag>
              <el-tag size="small" type="danger">{{ lang.stages.sunset }} {{ t(`${P}.stage_short.sunset`) }}</el-tag>
            </div>
          </el-card>
        </el-col>
      </el-row>
    </el-card>

    <el-card shadow="never">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-base font-medium">{{ t(`${P}.list_title`) }}</h3>
        <el-button size="small" type="primary" @click="showCreate = true">{{ t(`${P}.register`) }}</el-button>
      </div>

      <el-tabs v-model="activeLang" @tab-change="fetchVersions">
        <el-tab-pane v-for="lang in languages" :key="lang" :label="langLabels[lang]" :name="lang" />
      </el-tabs>

      <el-table :data="versions" v-loading="loading" stripe style="width: 100%">
        <el-table-column prop="version" :label="t(`${P}.cols.version`)" width="120" />
        <el-table-column :label="t(`${P}.cols.status`)" width="100">
          <template #default="{ row }">
            <el-tag :type="stageType(row.stage)" size="small">{{ stageLabel(row.stage) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="t(`${P}.cols.current`)" width="100" align="center">
          <template #default="{ row }">
            <el-tag v-if="row.is_current" type="success" size="small">✓</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="allow_production" :label="t(`${P}.cols.production`)" width="100" align="center">
          <template #default="{ row }">
            <el-tag v-if="row.allow_production" type="success" size="small">{{ t(`${P}.yes`) }}</el-tag>
            <el-tag v-else type="danger" size="small">{{ t(`${P}.no`) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="min_api_version" :label="t(`${P}.cols.min_api`)" width="120" />
        <el-table-column prop="released_at" :label="t(`${P}.cols.released`)" width="180" />
        <el-table-column prop="deprecated_at" :label="t(`${P}.cols.deprecated`)" width="180" />
        <el-table-column prop="sunset_at" :label="t(`${P}.cols.sunset`)" width="180" />
        <el-table-column :label="t(`${P}.cols.actions`)" width="280" fixed="right">
          <template #default="{ row }">
            <el-button size="small" @click="showDetail(row)">{{ t(`${P}.detail`) }}</el-button>
            <el-button v-if="row.stage === 'stable'" size="small" type="warning" @click="handleDeprecate(row)">{{ t(`${P}.mark_deprecated`) }}</el-button>
            <el-button v-if="row.stage === 'deprecated'" size="small" type="danger" @click="handleSunset(row)">{{ t(`${P}.mark_sunset`) }}</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="showCreate" :title="t(`${P}.register`)" width="500px">
      <el-form :model="form" label-width="120px">
        <el-form-item :label="t(`${P}.cols.language`)" required>
          <el-select v-model="form.language" style="width:100%">
            <el-option v-for="l in languages" :key="l" :label="langLabels[l]" :value="l" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t(`${P}.cols.version`)" required>
          <el-input v-model="form.version" :placeholder="t(`${P}.version_ph`)" />
        </el-form-item>
        <el-form-item :label="t(`${P}.cols.stage`)">
          <el-select v-model="form.stage" style="width:100%">
            <el-option :label="t(`${P}.stages.preview`)" value="preview" />
            <el-option :label="t(`${P}.stages.stable`)" value="stable" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t(`${P}.set_current`)">
          <el-switch v-model="form.is_current" />
        </el-form-item>
        <el-form-item :label="t(`${P}.cols.min_api`)">
          <el-input v-model="form.min_api_version" placeholder="v1" />
        </el-form-item>
        <el-form-item :label="t(`${P}.changelog`)">
          <el-input v-model="form.changelog" type="textarea" :rows="3" />
        </el-form-item>
        <el-form-item :label="t(`${P}.upgrade_notes`)">
          <el-input v-model="form.upgrade_notes" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreate = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="handleCreate" :loading="creating">{{ t(`${P}.confirm_register`) }}</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="showDetailDialog" :title="t(`${P}.detail_title`, { version: detail.version })" width="600px">
      <el-descriptions :column="2" border>
        <el-descriptions-item :label="t(`${P}.cols.language`)">{{ detail.language }}</el-descriptions-item>
        <el-descriptions-item :label="t(`${P}.cols.version`)">{{ detail.version }}</el-descriptions-item>
        <el-descriptions-item :label="t(`${P}.cols.status`)">
          <el-tag :type="stageType(detail.stage)" size="small">{{ stageLabel(detail.stage) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item :label="t(`${P}.cols.current`)">
          <el-tag v-if="detail.is_current" type="success">{{ t(`${P}.yes`) }}</el-tag>
          <el-tag v-else type="info">{{ t(`${P}.no`) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item :label="t(`${P}.cols.production`)">
          <el-tag v-if="detail.allow_production" type="success">{{ t(`${P}.yes`) }}</el-tag>
          <el-tag v-else type="danger">{{ t(`${P}.no`) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item :label="t(`${P}.cols.min_api`)">{{ detail.min_api_version }}</el-descriptions-item>
        <el-descriptions-item :label="t(`${P}.cols.released`)">{{ detail.released_at }}</el-descriptions-item>
        <el-descriptions-item :label="t(`${P}.cols.deprecated`)">{{ detail.deprecated_at || '-' }}</el-descriptions-item>
        <el-descriptions-item :label="t(`${P}.cols.sunset`)">{{ detail.sunset_at || '-' }}</el-descriptions-item>
      </el-descriptions>
      <div v-if="detail.changelog" class="mt-4">
        <h4 class="text-sm font-medium mb-2">{{ t(`${P}.changelog`) }}</h4>
        <p class="text-sm text-gray-600 whitespace-pre-wrap">{{ detail.changelog }}</p>
      </div>
      <div v-if="detail.upgrade_notes" class="mt-4">
        <h4 class="text-sm font-medium mb-2">{{ t(`${P}.upgrade_notes`) }}</h4>
        <p class="text-sm text-gray-600 whitespace-pre-wrap">{{ detail.upgrade_notes }}</p>
      </div>

      <el-divider />
      <div class="mt-2">
        <h4 class="text-sm font-medium mb-2">{{ t(`${P}.upgrade_check`) }}</h4>
        <el-button size="small" @click="checkVersionUpgrade" :loading="checkingUpgrade">{{ t(`${P}.check_upgrade`) }}</el-button>
        <div v-if="upgradeResult" class="mt-2">
          <el-alert v-if="upgradeResult.needs_upgrade" :title="t(`${P}.needs_upgrade`, { reason: upgradeResult.reason })" type="warning" show-icon />
          <el-alert v-else :title="t(`${P}.no_upgrade`)" type="success" show-icon />
          <div v-if="upgradeResult.upgrade_to" class="mt-2">
            <span class="text-sm">{{ t(`${P}.recommend_upgrade`) }}</span>
            <el-tag type="primary">{{ upgradeResult.upgrade_to }}</el-tag>
          </div>
        </div>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
  getSdkDashboard, getLanguageVersions, getSdkVersion,
  createSdkVersion, markDeprecated, markSunset,
  seedDefaultVersions, processExpired as processExpiredApi, checkUpgrade,
} from '../../api/sdkVersion';

const { t } = useI18n();
const P = 'sdk_version_page';

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
    ElMessage.success(t(`${P}.messages.registered`));
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
    await ElMessageBox.confirm(t(`${P}.confirm_deprecate`, { version: row.version }), t('actions.confirm'), { type: 'warning' });
    await markDeprecated(row.id);
    ElMessage.success(t(`${P}.messages.deprecated`));
    await fetchVersions();
    await fetchDashboard();
  } catch (e) { /* ignore */ }
}

async function handleSunset(row) {
  try {
    await ElMessageBox.confirm(t(`${P}.confirm_sunset`, { version: row.version }), t('actions.confirm'), { type: 'danger' });
    await markSunset(row.id);
    ElMessage.success(t(`${P}.messages.sunset`));
    await fetchVersions();
    await fetchDashboard();
  } catch (e) { /* ignore */ }
}

async function seedDefaults() {
  seeding.value = true;
  try {
    const res = await seedDefaultVersions();
    ElMessage.success(res.data.message || t(`${P}.messages.seeded`));
    await fetchVersions();
    await fetchDashboard();
  } catch (e) { /* ignore */ }
  seeding.value = false;
}

async function handleProcessExpired() {
  processing.value = true;
  try {
    const res = await processExpiredApi();
    ElMessage.success(res.data.message || t(`${P}.messages.process_done`));
    await fetchVersions();
    await fetchDashboard();
  } catch (e) { /* ignore */ }
  processing.value = false;
}

function stageType(stage) {
  return { preview: 'info', stable: 'success', deprecated: 'warning', sunset: 'danger' }[stage] || 'info';
}
function stageLabel(stage) {
  const key = `${P}.stages.${stage}`;
  const translated = t(key);
  return translated === key ? stage : translated;
}
</script>
