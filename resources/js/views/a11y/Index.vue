<template>
  <div class="a11y-page">
    <!-- 统计概览 -->
    <el-row :gutter="20" class="mb-4">
      <el-col :span="6" v-for="s in statItems" :key="s.label">
        <el-card shadow="hover" :body-style="{ padding: '16px' }">
          <div class="stat-value text-2xl font-bold" :class="s.color">{{ s.value }}</div>
          <div class="stat-label text-gray-500 text-sm">{{ s.label }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- Tabs -->
    <el-tabs v-model="activeTab" type="border-card">
      <!-- Tab: 符合性声明 -->
      <el-tab-pane :label="t('a11y_page.tabs.declaration')" name="declaration">
        <el-card shadow="never" class="mb-4">
          <template #header><span class="font-semibold">{{ t('a11y_page.declaration.title') }}</span></template>
          <p>
            {{ t('a11y_page.declaration.body_prefix') }}
            <strong>{{ t('a11y_page.declaration.wcag_standard') }}</strong>
            {{ t('a11y_page.declaration.body_suffix') }}
          </p>
        </el-card>

        <el-table :data="guidelines" stripe v-loading="loading" size="small">
          <el-table-column :label="t('a11y_page.columns.guideline_id')" prop="id" width="80" />
          <el-table-column :label="t('a11y_page.columns.name')" prop="name" width="160" />
          <el-table-column :label="t('a11y_page.columns.level')" width="70">
            <template #default="{ row }">
              <el-tag size="small" :type="row.level === 'A' ? 'warning' : 'success'" effect="plain">{{ row.level }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('a11y_page.columns.description')" prop="description" />
          <el-table-column :label="t('a11y_page.columns.status')" width="120">
            <template #default="{ row }">
              <el-tag size="small" :type="statusType(row.status)">
                {{ statusLabel(row.status) }}
              </el-tag>
            </template>
          </el-table-column>
        </el-table>

        <el-divider />
        <h3 class="text-lg font-medium mb-2">{{ t('a11y_page.limitations_title') }}</h3>
        <el-table :data="limitations" v-loading="loadingLim" size="small">
          <el-table-column :label="t('a11y_page.columns.area')" prop="area" width="160" />
          <el-table-column :label="t('a11y_page.columns.description')" prop="description" />
          <el-table-column :label="t('a11y_page.columns.severity')" width="100">
            <template #default="{ row }">
              <el-tag size="small" :type="sevType(row.severity)">{{ sevLabel(row.severity) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('a11y_page.columns.workaround')" prop="workaround" />
        </el-table>
      </el-tab-pane>

      <!-- Tab: 对比度检查 -->
      <el-tab-pane :label="t('a11y_page.tabs.contrast')" name="contrast">
        <el-card shadow="never">
          <template #header><span class="font-semibold">{{ t('a11y_page.contrast.title') }}</span></template>
          <el-form :inline="true">
            <el-form-item :label="t('a11y_page.contrast.foreground')">
              <el-color-picker v-model="contrastFg" @change="doContrastCheck" />
              <span class="ml-2 font-mono">{{ contrastFg }}</span>
            </el-form-item>
            <el-form-item :label="t('a11y_page.contrast.background')">
              <el-color-picker v-model="contrastBg" @change="doContrastCheck" />
              <span class="ml-2 font-mono">{{ contrastBg }}</span>
            </el-form-item>
          </el-form>

          <div v-if="contrastResult" class="contrast-result">
            <div class="sample-text" :style="{ color: contrastFg, background: contrastBg, padding: '12px', borderRadius: '4px', fontSize: '16px' }">
              <div class="font-bold text-lg">{{ t('a11y_page.contrast.sample_large') }}</div>
              <div>{{ t('a11y_page.contrast.sample_normal') }}</div>
              <div style="font-size:12px">{{ t('a11y_page.contrast.sample_small') }}</div>
            </div>
            <el-descriptions :column="2" border class="mt-4" size="small">
              <el-descriptions-item :label="t('a11y_page.contrast.ratio')">{{ contrastResult.ratio }}:1</el-descriptions-item>
              <el-descriptions-item :label="t('a11y_page.contrast.rating')">
                <el-tag :type="ratingType(contrastResult.rating)">{{ contrastResult.rating }}</el-tag>
              </el-descriptions-item>
              <el-descriptions-item :label="t('a11y_page.contrast.aa_normal')">
                <el-tag :type="contrastResult.passes_AA ? 'success' : 'danger'">{{ contrastResult.passes_AA ? t('a11y_page.contrast.pass') : t('a11y_page.contrast.fail') }}</el-tag>
              </el-descriptions-item>
              <el-descriptions-item :label="t('a11y_page.contrast.aa_large')">
                <el-tag :type="contrastResult.passes_AA_large ? 'success' : 'danger'">{{ contrastResult.passes_AA_large ? t('a11y_page.contrast.pass') : t('a11y_page.contrast.fail') }}</el-tag>
              </el-descriptions-item>
              <el-descriptions-item :label="t('a11y_page.contrast.aaa')">
                <el-tag :type="contrastResult.passes_AAA ? 'success' : 'warning'">{{ contrastResult.passes_AAA ? t('a11y_page.contrast.pass') : t('a11y_page.contrast.fail') }}</el-tag>
              </el-descriptions-item>
            </el-descriptions>
          </div>
        </el-card>
      </el-tab-pane>

      <!-- Tab: 色盲模拟 -->
      <el-tab-pane :label="t('a11y_page.tabs.colorblind')" name="colorblind">
        <el-card shadow="never">
          <template #header><span class="font-semibold">{{ t('a11y_page.colorblind.title') }}</span></template>
          <p class="text-gray-500 mb-4">{{ t('a11y_page.colorblind.desc') }}</p>

          <el-form inline>
            <el-form-item :label="t('a11y_page.colorblind.sim_type')">
              <el-select v-model="cbType" @change="applyCbFilter">
                <el-option v-for="opt in cbTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
              </el-select>
            </el-form-item>
          </el-form>

          <div class="cb-preview" :class="'cb-' + cbType">
            <el-card shadow="hover" class="mb-2">
              <template #header><span class="font-semibold">{{ t('a11y_page.colorblind.preview_status') }}</span></template>
              <div class="flex gap-3">
                <el-tag type="success">{{ t('a11y_page.colorblind.tags.active') }}</el-tag>
                <el-tag type="danger">{{ t('a11y_page.colorblind.tags.expired') }}</el-tag>
                <el-tag type="warning">{{ t('a11y_page.colorblind.tags.expiring') }}</el-tag>
                <el-tag type="info">{{ t('a11y_page.colorblind.tags.pending') }}</el-tag>
                <el-tag type="primary">{{ t('a11y_page.colorblind.tags.in_progress') }}</el-tag>
              </div>
            </el-card>

            <el-card shadow="hover" class="mb-2">
              <template #header><span class="font-semibold">{{ t('a11y_page.colorblind.preview_chart') }}</span></template>
              <div class="flex gap-3 items-center">
                <div v-for="c in chartColors" :key="c" class="cb-color-sample" :style="{ background: c }" />
              </div>
            </el-card>

            <el-card shadow="hover">
              <template #header><span class="font-semibold">{{ t('a11y_page.colorblind.preview_buttons') }}</span></template>
              <div class="flex gap-3">
                <el-button type="primary">{{ t('a11y_page.colorblind.buttons.primary') }}</el-button>
                <el-button type="success">{{ t('a11y_page.colorblind.buttons.success') }}</el-button>
                <el-button type="warning">{{ t('a11y_page.colorblind.buttons.warning') }}</el-button>
                <el-button type="danger">{{ t('a11y_page.colorblind.buttons.danger') }}</el-button>
                <el-button type="info">{{ t('a11y_page.colorblind.buttons.info') }}</el-button>
              </div>
            </el-card>
          </div>

          <el-alert type="warning" :closable="false" class="mt-4">
            <template #title>{{ t('a11y_page.colorblind.tip_title') }}</template>
            <template #default>{{ t('a11y_page.colorblind.tip_body') }}</template>
          </el-alert>
        </el-card>
      </el-tab-pane>

      <!-- Tab: 无障碍偏好 -->
      <el-tab-pane :label="t('a11y_page.tabs.preferences')" name="preferences">
        <el-card shadow="never">
          <template #header><span class="font-semibold">{{ t('a11y_page.preferences.title') }}</span></template>

          <el-form label-position="top" v-if="prefs" v-loading="savingPrefs">
            <el-form-item :label="t('a11y_page.preferences.reduced_motion')">
              <el-switch v-model="prefs.reduced_motion" @change="savePrefs" />
              <span class="ml-2 text-gray-500 text-sm">{{ t('a11y_page.preferences.reduced_motion_hint') }}</span>
            </el-form-item>

            <el-form-item :label="t('a11y_page.preferences.high_contrast')">
              <el-switch v-model="prefs.high_contrast" @change="savePrefs" />
              <span class="ml-2 text-gray-500 text-sm">{{ t('a11y_page.preferences.high_contrast_hint') }}</span>
            </el-form-item>

            <el-form-item :label="t('a11y_page.preferences.screen_reader')">
              <el-switch v-model="prefs.screen_reader_optimized" @change="savePrefs" />
              <span class="ml-2 text-gray-500 text-sm">{{ t('a11y_page.preferences.screen_reader_hint') }}</span>
            </el-form-item>

            <el-form-item :label="t('a11y_page.preferences.font_size')">
              <el-radio-group :model-value="prefs.font_size" @change="updateFontSize">
                <el-radio v-for="opt in fontSizeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</el-radio>
              </el-radio-group>
            </el-form-item>
          </el-form>
        </el-card>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import a11yApi from '@/api/a11y';

const { t } = useI18n();

const activeTab = ref('declaration');
const loading = ref(false);
const loadingLim = ref(false);

// ─── 统计 ───
const stats = ref(null);

const statItems = computed(() => {
  if (!stats.value) return [];
  const s = stats.value;
  return [
    { label: t('a11y_page.stats.total'), value: s.total, color: 'text-blue-500' },
    { label: t('a11y_page.stats.compliant'), value: s.compliant, color: 'text-green-500' },
    { label: t('a11y_page.stats.needs_work'), value: s.needsWork, color: 'text-yellow-500' },
    { label: t('a11y_page.stats.pass_rate'), value: s.passRate + '%', color: 'text-teal-500' },
  ];
});

async function loadStats() {
  try {
    const { data } = await a11yApi.stats();
    if (data?.data) {
      stats.value = data.data;
    }
  } catch (e) { /* ignore */ }
}

// ─── 准则列表 ───
const guidelines = ref([]);
async function loadGuidelines() {
  loading.value = true;
  try {
    const { data } = await a11yApi.guidelines();
    guidelines.value = data?.data || [];
  } finally {
    loading.value = false;
  }
}

// ─── 限制列表 ───
const limitations = ref([]);
async function loadLimitations() {
  loadingLim.value = true;
  try {
    const { data } = await a11yApi.limitations();
    limitations.value = data?.data || [];
  } finally {
    loadingLim.value = false;
  }
}

// ─── 对比度 ───
const contrastFg = ref('#0f172a');
const contrastBg = ref('#ffffff');
const contrastResult = ref(null);

async function doContrastCheck() {
  try {
    const { data } = await a11yApi.checkContrast(contrastFg.value, contrastBg.value);
    contrastResult.value = data?.data || null;
  } catch (e) {
    ElMessage.error(t('a11y_page.messages.contrast_check_failed'));
  }
}

// ─── 色盲模拟 ───
const cbType = ref('none');
const chartColors = ['#0f172a', '#67c23a', '#e6a23c', '#f56c6c', '#909399', '#b37feb', '#36cfc9'];

const cbTypeOptions = computed(() => [
  { value: 'none', label: t('a11y_page.colorblind.types.none') },
  { value: 'protanopia', label: t('a11y_page.colorblind.types.protanopia') },
  { value: 'deuteranopia', label: t('a11y_page.colorblind.types.deuteranopia') },
  { value: 'tritanopia', label: t('a11y_page.colorblind.types.tritanopia') },
  { value: 'achromatopsia', label: t('a11y_page.colorblind.types.achromatopsia') },
]);

const fontSizeOptions = computed(() => [
  { value: 'small', label: t('a11y_page.preferences.font_sizes.small') },
  { value: 'normal', label: t('a11y_page.preferences.font_sizes.normal') },
  { value: 'large', label: t('a11y_page.preferences.font_sizes.large') },
  { value: 'extra_large', label: t('a11y_page.preferences.font_sizes.extra_large') },
]);

function applyCbFilter() {
  // 通过 CSS filter 实现色盲模拟
  const previews = document.querySelectorAll('.cb-preview');
  previews.forEach(el => {
    el.style.filter = getCbFilter(cbType.value);
  });
}

function getCbFilter(type) {
  const filters = {
    protanopia: 'url("data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27%3E%3Cfilter id=%27cb%27%3E%3CfeColorMatrix type=%27matrix%27 values=%270.567,0.433,0,0,0 0.558,0.442,0,0,0 0,0.242,0.758,0,0 0,0,0,1,0%27/%3E%3C/filter%3E%3C/svg%3E#cb")',
    deuteranopia: 'url("data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27%3E%3Cfilter id=%27cb%27%3E%3CfeColorMatrix type=%27matrix%27 values=%270.625,0.375,0,0,0 0.7,0.3,0,0,0 0,0.3,0.7,0,0 0,0,0,1,0%27/%3E%3C/filter%3E%3C/svg%3E#cb")',
    tritanopia: 'url("data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27%3E%3Cfilter id=%27cb%27%3E%3CfeColorMatrix type=%27matrix%27 values=%270.95,0.05,0,0,0 0,0.433,0.567,0,0 0,0.475,0.525,0,0 0,0,0,1,0%27/%3E%3C/filter%3E%3C/svg%3E#cb")',
    achromatopsia: 'grayscale(100%)',
    none: 'none',
  };
  return filters[type] || 'none';
}

// ─── 偏好设置 ───
const prefs = ref(null);
const savingPrefs = ref(false);

async function loadPrefs() {
  try {
    const { data } = await a11yApi.getPreferences();
    prefs.value = data?.data || {};
  } catch (e) { /* ignore */ }
}

async function savePrefs() {
  savingPrefs.value = true;
  try {
    await a11yApi.savePreferences(prefs.value);
    ElMessage.success(t('messages.success'));
  } catch (e) {
    ElMessage.error(t('messages.failed'));
  } finally {
    savingPrefs.value = false;
  }
}

function updateFontSize(val) {
  prefs.value.font_size = val;
  const sizes = { small: '12px', normal: '14px', large: '16px', extra_large: '18px' };
  document.documentElement.style.fontSize = sizes[val] || '14px';
  savePrefs();
}

// ─── 工具 ───
function statusType(s) {
  const map = { compliant: 'success', needs_work: 'warning', not_applicable: 'info' };
  return map[s] || 'info';
}

function statusLabel(s) {
  const key = `a11y_page.status.${s}`;
  const translated = t(key);
  return translated !== key ? translated : s;
}

function sevType(s) {
  const map = { high: 'danger', medium: 'warning', low: 'info' };
  return map[s] || 'info';
}

function sevLabel(s) {
  const key = `a11y_page.severity.${s}`;
  const translated = t(key);
  return translated !== key ? translated : s;
}

function ratingType(r) {
  if (r === 'AAA') return 'success';
  if (r?.startsWith('AA')) return 'primary';
  return 'danger';
}

onMounted(() => {
  loadStats();
  loadGuidelines();
  loadLimitations();
  loadPrefs();
  doContrastCheck();
});

// 切换 tab 时也默认做对比度检查
</script>

<style scoped>
.a11y-page { max-width: 1200px; }
.mb-4 { margin-bottom: 16px; }
.mb-2 { margin-bottom: 8px; }
.cb-color-sample {
  width: 40px;
  height: 40px;
  border-radius: 6px;
  border: 1px solid #e4e7ed;
}
.contrast-result { max-width: 500px; }
</style>
