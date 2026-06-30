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
      <el-tab-pane label="符合性声明" name="declaration">
        <el-card shadow="never" class="mb-4">
          <template #header><span class="font-semibold">WCAG 2.1 AA 符合性声明</span></template>
          <p>本系统致力于为所有用户提供无障碍的使用体验，包括残障人士。我们遵循 <strong>Web内容无障碍指南 (WCAG) 2.1 AA 级别</strong> 标准，持续优化产品的可访问性。</p>
        </el-card>

        <el-table :data="guidelines" stripe v-loading="loading" size="small">
          <el-table-column label="准则" prop="id" width="80" />
          <el-table-column label="名称" prop="name" width="160" />
          <el-table-column label="级别" width="70">
            <template #default="{ row }">
              <el-tag size="small" :type="row.level === 'A' ? 'warning' : 'success'" effect="plain">{{ row.level }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="说明" prop="description" />
          <el-table-column label="状态" width="120">
            <template #default="{ row }">
              <el-tag size="small" :type="statusType(row.status)">
                {{ statusLabel(row.status) }}
              </el-tag>
            </template>
          </el-table-column>
        </el-table>

        <el-divider />
        <h3 class="text-lg font-medium mb-2">已知限制</h3>
        <el-table :data="limitations" v-loading="loadingLim" size="small">
          <el-table-column label="领域" prop="area" width="160" />
          <el-table-column label="描述" prop="description" />
          <el-table-column label="严重程度" width="100">
            <template #default="{ row }">
              <el-tag size="small" :type="sevType(row.severity)">{{ sevLabel(row.severity) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="替代方案" prop="workaround" />
        </el-table>
      </el-tab-pane>

      <!-- Tab: 对比度检查 -->
      <el-tab-pane label="对比度检查" name="contrast">
        <el-card shadow="never">
          <template #header><span class="font-semibold">WCAG 对比度检查工具</span></template>
          <el-form :inline="true">
            <el-form-item label="前景色">
              <el-color-picker v-model="contrastFg" @change="doContrastCheck" />
              <span class="ml-2 font-mono">{{ contrastFg }}</span>
            </el-form-item>
            <el-form-item label="背景色">
              <el-color-picker v-model="contrastBg" @change="doContrastCheck" />
              <span class="ml-2 font-mono">{{ contrastBg }}</span>
            </el-form-item>
          </el-form>

          <div v-if="contrastResult" class="contrast-result">
            <div class="sample-text" :style="{ color: contrastFg, background: contrastBg, padding: '12px', borderRadius: '4px', fontSize: '16px' }">
              <div class="font-bold text-lg">示例文本（大文本 18pt+ 或 14pt 粗体）</div>
              <div>示例文本（普通文本）</div>
              <div style="font-size:12px">示例文本（小文本 12px）</div>
            </div>
            <el-descriptions :column="2" border class="mt-4" size="small">
              <el-descriptions-item label="对比度">{{ contrastResult.ratio }}:1</el-descriptions-item>
              <el-descriptions-item label="评级">
                <el-tag :type="ratingType(contrastResult.rating)">{{ contrastResult.rating }}</el-tag>
              </el-descriptions-item>
              <el-descriptions-item label="AA 普通文本（4.5:1）">
                <el-tag :type="contrastResult.passes_AA ? 'success' : 'danger'">{{ contrastResult.passes_AA ? '通过' : '未通过' }}</el-tag>
              </el-descriptions-item>
              <el-descriptions-item label="AA 大文本（3:1）">
                <el-tag :type="contrastResult.passes_AA_large ? 'success' : 'danger'">{{ contrastResult.passes_AA_large ? '通过' : '未通过' }}</el-tag>
              </el-descriptions-item>
              <el-descriptions-item label="AAA 增强（7:1）">
                <el-tag :type="contrastResult.passes_AAA ? 'success' : 'warning'">{{ contrastResult.passes_AAA ? '通过' : '未通过' }}</el-tag>
              </el-descriptions-item>
            </el-descriptions>
          </div>
        </el-card>
      </el-tab-pane>

      <!-- Tab: 色盲模拟 -->
      <el-tab-pane label="色盲模拟" name="colorblind">
        <el-card shadow="never">
          <template #header><span class="font-semibold">色盲模拟器</span></template>
          <p class="text-gray-500 mb-4">选择色盲类型，查看界面在不同色觉缺陷下的表现。</p>

          <el-form inline>
            <el-form-item label="模拟类型">
              <el-select v-model="cbType" @change="applyCbFilter">
                <el-option label="正常视觉" value="none" />
                <el-option label="红色盲 (Protanopia)" value="protanopia" />
                <el-option label="绿色盲 (Deuteranopia)" value="deuteranopia" />
                <el-option label="蓝色盲 (Tritanopia)" value="tritanopia" />
                <el-option label="全色盲 (Achromatopsia)" value="achromatopsia" />
              </el-select>
            </el-form-item>
          </el-form>

          <div class="cb-preview" :class="'cb-' + cbType">
            <el-card shadow="hover" class="mb-2">
              <template #header><span class="font-semibold">功能状态示例</span></template>
              <div class="flex gap-3">
                <el-tag type="success">已激活</el-tag>
                <el-tag type="danger">已过期</el-tag>
                <el-tag type="warning">即将到期</el-tag>
                <el-tag type="info">待审核</el-tag>
                <el-tag type="primary">进行中</el-tag>
              </div>
            </el-card>

            <el-card shadow="hover" class="mb-2">
              <template #header><span class="font-semibold">图表颜色示例</span></template>
              <div class="flex gap-3 items-center">
                <div v-for="c in chartColors" :key="c" class="cb-color-sample" :style="{ background: c }" />
              </div>
            </el-card>

            <el-card shadow="hover">
              <template #header><span class="font-semibold">按钮状态示例</span></template>
              <div class="flex gap-3">
                <el-button type="primary">主要按钮</el-button>
                <el-button type="success">成功</el-button>
                <el-button type="warning">警告</el-button>
                <el-button type="danger">危险</el-button>
                <el-button type="info">信息</el-button>
              </div>
            </el-card>
          </div>

          <el-alert type="warning" :closable="false" class="mt-4">
            <template #title>提示</template>
            <template #default>色盲模拟仅在前端展示效果，不会影响实际数据。建议避免仅依赖颜色传达信息，配合图标和文字标识使用。</template>
          </el-alert>
        </el-card>
      </el-tab-pane>

      <!-- Tab: 无障碍偏好 -->
      <el-tab-pane label="无障碍偏好" name="preferences">
        <el-card shadow="never">
          <template #header><span class="font-semibold">无障碍偏好设置</span></template>

          <el-form label-position="top" v-if="prefs" v-loading="savingPrefs">
            <el-form-item label="减少动画">
              <el-switch v-model="prefs.reduced_motion" @change="savePrefs" />
              <span class="ml-2 text-gray-500 text-sm">减少页面动画和过渡效果，适合前庭障碍用户</span>
            </el-form-item>

            <el-form-item label="高对比度模式">
              <el-switch v-model="prefs.high_contrast" @change="savePrefs" />
              <span class="ml-2 text-gray-500 text-sm">增强所有界面元素的颜色对比度</span>
            </el-form-item>

            <el-form-item label="屏幕阅读器优化">
              <el-switch v-model="prefs.screen_reader_optimized" @change="savePrefs" />
              <span class="ml-2 text-gray-500 text-sm">为屏幕阅读器用户优化 ARIA 标签和信息播报</span>
            </el-form-item>

            <el-form-item label="字体大小">
              <el-radio-group :model-value="prefs.font_size" @change="updateFontSize">
                <el-radio value="small">小</el-radio>
                <el-radio value="normal">默认</el-radio>
                <el-radio value="large">大</el-radio>
                <el-radio value="extra_large">超大</el-radio>
              </el-radio-group>
            </el-form-item>
          </el-form>
        </el-card>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import a11yApi from '@/api/a11y';

const activeTab = ref('declaration');
const loading = ref(false);
const loadingLim = ref(false);

// ─── 统计 ───
const statItems = ref([]);

async function loadStats() {
  try {
    const { data } = await a11yApi.stats();
    if (data?.data) {
      const s = data.data;
      statItems.value = [
        { label: '总准则', value: s.total, color: 'text-blue-500' },
        { label: '已符合', value: s.compliant, color: 'text-green-500' },
        { label: '待改进', value: s.needsWork, color: 'text-yellow-500' },
        { label: '通过率', value: s.passRate + '%', color: 'text-teal-500' },
      ];
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
const contrastFg = ref('#409eff');
const contrastBg = ref('#ffffff');
const contrastResult = ref(null);

async function doContrastCheck() {
  try {
    const { data } = await a11yApi.checkContrast(contrastFg.value, contrastBg.value);
    contrastResult.value = data?.data || null;
  } catch (e) {
    ElMessage.error('对比度检查失败');
  }
}

// ─── 色盲模拟 ───
const cbType = ref('none');
const chartColors = ['#409eff', '#67c23a', '#e6a23c', '#f56c6c', '#909399', '#b37feb', '#36cfc9'];

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
    ElMessage.success('偏好已保存');
  } catch (e) {
    ElMessage.error('保存失败');
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
  const map = { compliant: '符合', needs_work: '待改进', not_applicable: '不适用' };
  return map[s] || s;
}

function sevType(s) {
  const map = { high: 'danger', medium: 'warning', low: 'info' };
  return map[s] || 'info';
}

function sevLabel(s) {
  const map = { high: '严重', medium: '中等', low: '轻微' };
  return map[s] || s;
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
