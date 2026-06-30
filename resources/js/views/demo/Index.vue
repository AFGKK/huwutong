<template>
  <div class="demo-overlay" v-if="!demoEnded">
    <!-- 导航栏 -->
    <header class="demo-header">
      <div class="demo-brand">
        <span class="demo-logo">◆</span>
        <span class="demo-title">HWT License 产品演示</span>
      </div>

      <div class="demo-header-center">
        <div class="step-indicators">
          <div
            v-for="(s, i) in steps"
            :key="i"
            class="step-dot"
            :class="{ active: currentStep === i, completed: currentStep > i }"
            @click="goToStep(i)"
          >
            <div class="step-dot-inner">{{ currentStep > i ? '✓' : i + 1 }}</div>
            <span class="step-label">{{ s.title }}</span>
          </div>
        </div>
      </div>

      <div class="demo-header-right">
        <span class="timer" :class="{ warning: remainingSec <= 300 }">
          <el-icon><Timer /></el-icon>
          {{ formatTime(remainingSec) }}
        </span>
        <el-button type="primary" size="small" @click="showRegister = true">立即注册 →</el-button>
      </div>
    </header>

    <!-- 主内容区 -->
    <div class="demo-content">
      <div v-if="loading" class="loading-state">
        <el-skeleton :rows="6" animated />
      </div>

      <!-- 步骤 0: 欢迎页 -->
      <div v-else-if="currentStep === 0" class="step-welcome">
        <el-result icon="success" title="欢迎体验 HWT License" sub-title="这是一个交互式产品演示，您将了解 HWT License 的核心功能。">
          <template #extra>
            <div class="welcome-features">
              <div v-for="f in features" :key="f.title" class="feature-card" @click="startTour">
                <el-icon :size="32" :color="f.color"><component :is="f.icon" /></el-icon>
                <h4>{{ f.title }}</h4>
                <p>{{ f.desc }}</p>
              </div>
            </div>
            <el-button type="primary" size="large" @click="startTour" class="mt-4">
              开始体验 →
            </el-button>
          </template>
        </el-result>
      </div>

      <!-- 步骤 1: 仪表盘 -->
      <div v-else-if="currentStep === 1" class="step-dashboard">
        <el-alert title="这是管理者仪表盘，显示关键业务指标的总览" type="info" :closable="false" show-icon class="mb-4" />

        <el-row :gutter="20" class="mb-4">
          <el-col :span="6" v-for="s in demoStats" :key="s.label">
            <el-card shadow="hover" :body-style="{ padding: '16px' }">
              <div class="stat-value text-2xl font-bold" :class="s.color">{{ s.value }}</div>
              <div class="stat-label text-gray-500 text-sm">{{ s.label }}</div>
            </el-card>
          </el-col>
        </el-row>

        <el-row :gutter="20">
          <el-col :span="16">
            <el-card shadow="never">
              <template #header><span class="font-semibold">收入趋势</span></template>
              <div class="chart-placeholder">
                <el-space wrap>
                  <div v-for="m in revenueTrend" :key="m.month" class="bar-wrapper">
                    <div class="bar" :style="{ height: barHeight(m.new) + 'px' }" style="background:#409eff" />
                    <div class="bar" :style="{ height: barHeight(m.renewal) + 'px' }" style="background:#67c23a" />
                    <div class="bar-label text-xs">{{ m.month.slice(-2) }}月</div>
                  </div>
                </el-space>
              </div>
            </el-card>
          </el-col>
          <el-col :span="8">
            <el-card shadow="never">
              <template #header><span class="font-semibold">最近活动</span></template>
              <el-timeline>
                <el-timeline-item v-for="a in activities" :key="a.action" :timestamp="a.time" size="small">
                  <div class="font-medium text-sm">{{ a.action }}</div>
                  <div class="text-xs text-gray-500">{{ a.detail }}</div>
                </el-timeline-item>
              </el-timeline>
            </el-card>
          </el-col>
        </el-row>

        <div class="text-center mt-4">
          <el-button type="primary" @click="nextStep">继续 →</el-button>
        </div>
      </div>

      <!-- 步骤 2: 产品浏览 -->
      <div v-else-if="currentStep === 2" class="step-products">
        <el-alert title="管理您的产品线，每个产品可配置多种 License 类型" type="info" :closable="false" show-icon class="mb-4" />

        <el-table :data="products" stripe v-loading="loading">
          <el-table-column prop="name" label="产品名称" />
          <el-table-column prop="slug" label="标识" />
          <el-table-column prop="version" label="版本" width="100" />
          <el-table-column label="License 数" width="120">
            <template #default="{ row }">{{ row.licenses }}</template>
          </el-table-column>
          <el-table-column label="月收入" width="120">
            <template #default="{ row }">¥{{ row.revenue.toLocaleString() }}</template>
          </el-table-column>
          <el-table-column label="标签" width="80">
            <template #default="{ row }">
              <el-tag :color="row.color" size="small" style="color:#fff">{{ row.version }}</el-tag>
            </template>
          </el-table-column>
        </el-table>

        <div class="text-center mt-4">
          <el-button @click="prevStep">上一步</el-button>
          <el-button type="primary" @click="nextStep">继续 →</el-button>
        </div>
      </div>

      <!-- 步骤 3: 创建 License -->
      <div v-else-if="currentStep === 3" class="step-create-license">
        <el-alert title="创建一个新的 License Key，支持多种类型和高级配置" type="info" :closable="false" show-icon class="mb-4" />

        <el-card shadow="never" class="demo-card">
          <el-form :model="licenseForm" label-width="120px" v-if="!licenseCreated">
            <el-form-item label="产品">
              <el-select v-model="licenseForm.product" style="width:100%">
                <el-option label="HWT License Core" value="hwt-core" />
                <el-option label="HWT Enterprise" value="hwt-enterprise" />
                <el-option label="HWT Security Suite" value="hwt-security" />
              </el-select>
            </el-form-item>
            <el-form-item label="License 类型">
              <el-radio-group v-model="licenseForm.type">
                <el-radio value="enterprise">Enterprise</el-radio>
                <el-radio value="professional">Professional</el-radio>
                <el-radio value="standard">Standard</el-radio>
              </el-radio-group>
            </el-form-item>
            <el-form-item label="客户">
              <el-select v-model="licenseForm.customer" style="width:100%">
                <el-option v-for="c in customers" :key="c.name" :label="c.name" :value="c.name" />
              </el-select>
            </el-form-item>
            <el-row :gutter="20">
              <el-col :span="12">
                <el-form-item label="Seats">
                  <el-input-number v-model="licenseForm.seats" :min="1" :max="1000" />
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="过期时间">
                  <el-date-picker v-model="licenseForm.expires" type="date" placeholder="选择" style="width:100%" />
                </el-form-item>
              </el-col>
            </el-row>
            <el-form-item>
              <el-button type="primary" @click="createLicenseDemo">创建 License</el-button>
            </el-form-item>
          </el-form>

          <div v-else class="text-center">
            <el-result icon="success" title="License 创建成功" :sub-title="`DEMO-${licenseForm.type.toUpperCase()}-${randomKey}`">
              <template #extra>
                <el-button @click="licenseCreated = false">再试一次</el-button>
              </template>
            </el-result>
          </div>
        </el-card>

        <div class="text-center mt-4">
          <el-button @click="prevStep">上一步</el-button>
          <el-button type="primary" @click="nextStep">继续 →</el-button>
        </div>
      </div>

      <!-- 步骤 4: 客户管理 -->
      <div v-else-if="currentStep === 4" class="step-customers">
        <el-alert title="管理客户信息，查看 License 使用情况和订阅状态" type="info" :closable="false" show-icon class="mb-4" />

        <el-table :data="customers" stripe>
          <el-table-column prop="name" label="客户名称" />
          <el-table-column prop="industry" label="行业" width="100" />
          <el-table-column prop="plan" label="套餐" width="120" />
          <el-table-column label="License 数" width="100">
            <template #default="{ row }">{{ row.licenses }}</template>
          </el-table-column>
          <el-table-column label="状态" width="100">
            <template #default="{ row }">
              <el-tag :type="row.status === 'active' ? 'success' : 'danger'" size="small">
                {{ row.status === 'active' ? '活跃' : '已过期' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="120">
            <template #default>
              <el-button size="small" text>查看详情</el-button>
            </template>
          </el-table-column>
        </el-table>

        <div class="text-center mt-4">
          <el-button @click="prevStep">上一步</el-button>
          <el-button type="primary" @click="nextStep">继续 →</el-button>
        </div>
      </div>

      <!-- 步骤 5: 报告 -->
      <div v-else-if="currentStep === 5" class="step-reports">
        <el-alert title="生成业务报告，支持多种维度的数据分析" type="info" :closable="false" show-icon class="mb-4" />

        <el-row :gutter="20">
          <el-col :span="12">
            <el-card shadow="never">
              <template #header><span class="font-semibold">平台分布</span></template>
              <div class="platform-chart">
                <div v-for="(pct, name) in chartData.device_platform" :key="name" class="platform-row">
                  <span class="platform-name text-sm">{{ name }}</span>
                  <el-progress :percentage="pct" :stroke-width="16" :color="platformColor(name)" />
                </div>
              </div>
            </el-card>
          </el-col>
          <el-col :span="12">
            <el-card shadow="never">
              <template #header><span class="font-semibold">License 激活趋势</span></template>
              <div class="trend-chart-placeholder">
                <el-space wrap>
                  <div v-for="(v, i) in chartData.activation_trend.slice(-6)" :key="i" class="trend-bar-wrapper">
                    <div class="trend-bar" :style="{ height: (v / 130 * 120) + 'px' }" />
                    <div class="trend-label text-xs">{{ (i + 1) + '月' }}</div>
                  </div>
                </el-space>
              </div>
            </el-card>
          </el-col>
        </el-row>

        <div class="text-center mt-4">
          <el-button @click="prevStep">上一步</el-button>
          <el-button type="primary" @click="nextStep">继续 →</el-button>
        </div>
      </div>

      <!-- 步骤 6: 下一步/CTA -->
      <div v-else-if="currentStep === 6" class="step-cta">
        <el-result icon="success" title="演示完成！" sub-title="您已体验了 HWT License 的核心功能">
          <template #extra>
            <div class="cta-cards">
              <el-card shadow="hover" class="cta-card" @click="showRegister = true">
                <el-icon :size="36" color="#409eff"><User /></el-icon>
                <h3>免费注册</h3>
                <p>创建您的账户，开始管理 License</p>
                <el-button type="primary" class="mt-2">立即注册 →</el-button>
              </el-card>
              <el-card shadow="hover" class="cta-card">
                <el-icon :size="36" color="#67c23a"><Document /></el-icon>
                <h3>查看文档</h3>
                <p>深入了解产品功能和技术细节</p>
                <el-button class="mt-2">查看文档</el-button>
              </el-card>
              <el-card shadow="hover" class="cta-card">
                <el-icon :size="36" color="#e6a23c"><ChatDotSquare /></el-icon>
                <h3>联系销售</h3>
                <p>与我们的团队讨论您的需求</p>
                <el-button class="mt-2">联系我们</el-button>
              </el-card>
            </div>

            <el-button text @click="extendDemo">再给我 15 分钟</el-button>
          </template>
        </el-result>
      </div>
    </div>

    <!-- 注册对话框 -->
    <el-dialog v-model="showRegister" title="免费注册" width="420px" :close-on-click-modal="false">
      <el-form label-position="top">
        <el-form-item label="姓名">
          <el-input v-model="registerForm.name" placeholder="您的姓名" />
        </el-form-item>
        <el-form-item label="邮箱">
          <el-input v-model="registerForm.email" placeholder="your@email.com" />
        </el-form-item>
        <el-form-item label="公司">
          <el-input v-model="registerForm.company" placeholder="（选填）" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showRegister = false">取消</el-button>
        <el-button type="primary" @click="handleRegister" :loading="registering">提交注册</el-button>
      </template>
    </el-dialog>
  </div>

  <!-- 过期提示 -->
  <div v-else class="expired-overlay">
    <el-result icon="warning" title="演示已结束" sub-title="30 分钟体验时间已到">
      <template #extra>
        <p class="text-gray-500 mb-4">感谢您的体验！如需继续，请免费注册。</p>
        <el-button type="primary" @click="showRegister = true">免费注册 →</el-button>
        <el-button @click="restartDemo">重新体验</el-button>
      </template>
    </el-result>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch } from 'vue';
import { ElMessage } from 'element-plus';
import { Timer, User, Document, ChatDotSquare, DataLine, Key, Setting, TrendCharts } from '@element-plus/icons-vue';
import demoApi from '@/api/demo';

// ─── 状态 ───
const loading = ref(true);
const demoEnded = ref(false);
const showRegister = ref(false);
const registering = ref(false);
const remainingSec = ref(1800);
const currentStep = ref(0);
const token = ref('');
const sessionId = ref('');
const licenseCreated = ref(false);
const randomKey = ref('');

const registerForm = reactive({ name: '', email: '', company: '' });
const licenseForm = reactive({ product: 'hwt-enterprise', type: 'enterprise', customer: 'Acme Corp', seats: 10, expires: null });

const demoStats = ref([]);
const products = ref([]);
const customers = ref([]);
const activities = ref([]);
const revenueTrend = ref([]);
const chartData = ref({ activation_trend: [], device_platform: {} });

const features = [
  { icon: DataLine, title: '仪表盘', desc: '关键业务指标总览', color: '#409eff' },
  { icon: Key, title: 'License 管理', desc: '创建和管理 License Key', color: '#67c23a' },
  { icon: Setting, title: '产品配置', desc: '多产品线管理', color: '#e6a23c' },
  { icon: TrendCharts, title: '数据分析', desc: '报告和趋势分析', color: '#f56c6c' },
];

const steps = [
  { title: '欢迎' },
  { title: '仪表盘' },
  { title: '产品' },
  { title: '创建 License' },
  { title: '客户' },
  { title: '报告' },
  { title: '下一步' },
];

// ─── 初始化 ───
onMounted(async () => {
  try {
    const stored = sessionStorage.getItem('demo_token');
    if (stored) {
      token.value = stored;
      sessionId.value = sessionStorage.getItem('demo_session') || '';
      await loadData();
    } else {
      const { data: startData } = await demoApi.start(uniqId());
      const d = startData?.data;
      if (d) {
        token.value = d.token;
        sessionId.value = d.session_id;
        sessionStorage.setItem('demo_token', d.token);
        sessionStorage.setItem('demo_session', d.session_id);
        currentStep.value = 0;
        loading.value = false;
      }
    }
  } catch (e) {
    ElMessage.error('初始化演示失败');
    loading.value = false;
  }

  // 心搏
  heartInterval = setInterval(doHeartbeat, 15000);
  timerInterval = setInterval(updateTimer, 1000);
});

let heartInterval;
let timerInterval;

onUnmounted(() => {
  clearInterval(heartInterval);
  clearInterval(timerInterval);
});

function uniqId() {
  return 'demo-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8);
}

// ─── 加载数据 ───
async function loadData() {
  loading.value = true;
  try {
    const { data: d } = await demoApi.getData('all', token.value);
    if (d?.data) {
      const dd = d.data;
      demoStats.value = [
        { label: '总 License', value: dd.stats?.total_licenses, color: 'text-blue-500' },
        { label: '活跃 License', value: dd.stats?.active_licenses, color: 'text-green-500' },
        { label: '客户数', value: dd.stats?.total_customers, color: 'text-purple-500' },
        { label: '月收入', value: '¥' + (dd.stats?.monthly_revenue?.toLocaleString() || '0'), color: 'text-yellow-600' },
      ];
      products.value = dd.products || [];
      customers.value = dd.customers || [];
      activities.value = dd.activities || [];
      revenueTrend.value = dd.revenue_trend || [];
      chartData.value = dd['chart-data'] || { activation_trend: [], device_platform: {} };
    }
    await doHeartbeat();
  } catch (e) {
    if (e.response?.status === 401) {
      demoEnded.value = true;
    }
  } finally {
    loading.value = false;
  }
}

// ─── 引导导航 ───
function startTour() {
  currentStep.value = 1;
  demoApi.advanceStep(1, token.value).catch(() => {});
}

function nextStep() {
  if (currentStep.value < steps.length - 1) {
    const step = currentStep.value + 1;
    currentStep.value = step;
    demoApi.advanceStep(step, token.value).catch(() => {});
  }
}

function prevStep() {
  if (currentStep.value > 0) {
    currentStep.value--;
  }
}

function goToStep(step) {
  if (step <= currentStep.value + 1) {
    currentStep.value = step;
    demoApi.advanceStep(step, token.value).catch(() => {});
  }
}

// ─── 演示操作 ───
function createLicenseDemo() {
  randomKey.value = Math.random().toString(36).substring(2, 10).toUpperCase();
  licenseCreated.value = true;
  demoApi.recordAction('create_license', token.value).catch(() => {});
}

// ─── 心搏和定时 ───
async function doHeartbeat() {
  try {
    const { data } = await demoApi.heartbeat(token.value);
    if (data?.data) {
      remainingSec.value = data.data.remaining_seconds || 0;
      if (data.data.status === 'expired') {
        demoEnded.value = true;
      }
    }
  } catch (e) {
    if (e.response?.status === 401) {
      demoEnded.value = true;
    }
  }
}

function updateTimer() {
  if (remainingSec.value > 0) {
    remainingSec.value--;
  }
}

async function extendDemo() {
  try {
    await demoApi.extend(15, token.value);
    remainingSec.value += 900;
    ElMessage.success('演示已延长 15 分钟');
  } catch (e) {
    ElMessage.error('延长失败');
  }
}

async function handleRegister() {
  if (!registerForm.name || !registerForm.email) {
    ElMessage.warning('请填写姓名和邮箱');
    return;
  }
  registering.value = true;
  try {
    const { data: res } = await demoApi.register({
      name: registerForm.name,
      email: registerForm.email,
      company: registerForm.company,
    }, token.value);
    const d = res?.data;
    if (d?.new_user) {
      ElMessage.success(`注册成功！您的初始密码为: ${d.password}，请妥善保管`);
    } else {
      ElMessage.success('欢迎回来！');
    }
    await demoApi.complete(token.value);
    showRegister.value = false;
    demoEnded.value = true;
    sessionStorage.removeItem('demo_token');
    sessionStorage.removeItem('demo_session');
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '注册失败，请稍后重试');
  } finally {
    registering.value = false;
  }
}

function restartDemo() {
  sessionStorage.removeItem('demo_token');
  sessionStorage.removeItem('demo_session');
  window.location.reload();
}

// ─── 工具 ───
function formatTime(sec) {
  const m = Math.floor(sec / 60);
  const s = sec % 60;
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

function barHeight(val) {
  return Math.max(10, (val / 30000) * 160);
}

function platformColor(name) {
  const colors = { Windows: '#409eff', macOS: '#67c23a', Linux: '#e6a23c', iOS: '#f56c6c', Android: '#909399' };
  return colors[name] || '#409eff';
}
</script>

<style scoped>
.demo-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: #f5f7fa;
  display: flex;
  flex-direction: column;
}

/* ─── Header ─── */
.demo-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 24px;
  background: #fff;
  border-bottom: 1px solid #e4e7ed;
  box-shadow: 0 1px 4px rgba(0,0,0,0.06);
  z-index: 10;
}
.demo-brand {
  display: flex;
  align-items: center;
  gap: 8px;
}
.demo-logo {
  font-size: 24px;
  color: #409eff;
}
.demo-title {
  font-size: 16px;
  font-weight: 600;
}
.demo-header-center {
  flex: 1;
  display: flex;
  justify-content: center;
}
.step-indicators {
  display: flex;
  gap: 4px;
}
.step-dot {
  display: flex;
  flex-direction: column;
  align-items: center;
  cursor: pointer;
  padding: 4px 8px;
  border-radius: 4px;
  transition: all 0.2s;
}
.step-dot:hover { background: #f0f5ff; }
.step-dot-inner {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 600;
  background: #e4e7ed;
  color: #909399;
  transition: all 0.3s;
}
.step-dot.active .step-dot-inner {
  background: #409eff;
  color: #fff;
  box-shadow: 0 0 0 3px rgba(64,158,255,0.2);
}
.step-dot.completed .step-dot-inner {
  background: #67c23a;
  color: #fff;
}
.step-label {
  font-size: 10px;
  margin-top: 2px;
  color: #909399;
  white-space: nowrap;
}
.step-dot.active .step-label { color: #409eff; }
.demo-header-right {
  display: flex;
  align-items: center;
  gap: 12px;
}
.timer {
  font-size: 14px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 4px;
  color: #606266;
}
.timer.warning { color: #f56c6c; animation: pulse 1s infinite; }
@keyframes pulse { 0%,100% { opacity:1 } 50% { opacity:0.6 } }

/* ─── Content ─── */
.demo-content {
  flex: 1;
  overflow-y: auto;
  padding: 24px;
  max-width: 1200px;
  margin: 0 auto;
  width: 100%;
}
.loading-state { padding: 60px; }
.mb-4 { margin-bottom: 16px; }
.mt-2 { margin-top: 8px; }
.mt-4 { margin-top: 16px; }

/* ─── Welcome ─── */
.welcome-features {
  display: flex;
  gap: 20px;
  justify-content: center;
  margin: 24px 0;
}
.feature-card {
  width: 180px;
  padding: 24px 16px;
  border: 1px solid #e4e7ed;
  border-radius: 8px;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s;
}
.feature-card:hover {
  border-color: #409eff;
  box-shadow: 0 4px 12px rgba(64,158,255,0.15);
  transform: translateY(-2px);
}
.feature-card h4 { margin: 12px 0 4px; font-size: 15px; }
.feature-card p { font-size: 12px; color: #909399; }

/* ─── Charts ─── */
.chart-placeholder {
  display: flex;
  align-items: flex-end;
  height: 180px;
  padding: 16px 0;
  gap: 12px;
}
.bar-wrapper {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
}
.bar {
  width: 20px;
  border-radius: 3px 3px 0 0;
  transition: height 0.5s;
  min-height: 4px;
}
.bar-label { margin-top: 4px; }
.trend-chart-placeholder {
  display: flex;
  align-items: flex-end;
  height: 140px;
  gap: 12px;
  padding: 16px 0;
}
.trend-bar {
  width: 32px;
  background: linear-gradient(to top, #409eff, #79bbff);
  border-radius: 4px 4px 0 0;
  transition: height 0.5s;
}
.trend-label { text-align: center; margin-top: 4px; }

/* ─── Platform Chart ─── */
.platform-chart { padding: 8px 0; }
.platform-row {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
}
.platform-name { min-width: 70px; }

/* ─── CTA ─── */
.cta-cards {
  display: flex;
  gap: 20px;
  justify-content: center;
  margin: 20px 0;
}
.cta-card {
  width: 240px;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s;
}
.cta-card:hover {
  border-color: #409eff;
  box-shadow: 0 4px 12px rgba(64,158,255,0.12);
}
.cta-card h3 { margin: 12px 0 4px; }
.cta-card p { font-size: 13px; color: #909399; }

/* ─── Expired ─── */
.expired-overlay {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  background: #f5f7fa;
}

/* ─── Demo Card ─── */
.demo-card {
  max-width: 600px;
  margin: 0 auto;
}
</style>
