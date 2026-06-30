<template>
  <div class="demo-admin-page">
    <div class="page-header">
      <h2>
        <el-icon style="vertical-align:middle;margin-right:8px"><Monitor /></el-icon>
        交互式产品演示
      </h2>
      <div class="header-actions">
        <el-tag v-if="config.enabled" type="success" effect="dark" size="small">已启用</el-tag>
        <el-tag v-else type="info" size="small">已禁用</el-tag>
        <el-button @click="refreshAll" :loading="loading" size="small">
          <el-icon><Refresh /></el-icon> 刷新
        </el-button>
      </div>
    </div>

    <el-alert
      title="无需注册即可在线体验 — 预置数据+限时30分钟+引导式操作+CTA注册引导，销售转化利器"
      type="success" show-icon :closable="false" class="mb-4"
    />

    <!-- 指标卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ analytics.total }}</div>
          <div class="stat-label">总体验次数</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value text-green">{{ analytics.active }}</div>
          <div class="stat-label">当前活跃</div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value text-blue">{{ analytics.completed }}</div>
          <div class="stat-label">已完成</div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value text-yellow">{{ analytics.registrations }}</div>
          <div class="stat-label">注册转化</div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value text-red">{{ analytics.conversion_rate }}%</div>
          <div class="stat-label">转化率</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 主 Tabs -->
    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <el-tab-pane label="数据看板" name="dashboard">
          <el-row :gutter="16">
            <el-col :span="12">
              <el-card shadow="never">
                <template #header><span>每日趋势 (近7天)</span></template>
                <div class="trend-chart" v-if="analytics.daily_trend?.length">
                  <div class="trend-row" v-for="d in analytics.daily_trend" :key="d.date">
                    <span class="trend-date">{{ d.date.slice(5) }}</span>
                    <div class="trend-bar-container">
                      <div class="trend-bar starts" :style="{ width: barWidth(d.starts, 'starts') + '%' }">
                        {{ d.starts }}
                      </div>
                      <div class="trend-bar completions" :style="{ width: barWidth(d.completions, 'completions') + '%', left: barWidth(d.starts, 'starts') + '%' }">
                        {{ d.completions }}
                      </div>
                    </div>
                  </div>
                </div>
                <div v-else class="text-gray text-center p-4">暂无数据</div>
              </el-card>
            </el-col>
            <el-col :span="12">
              <el-card shadow="never">
                <template #header><span>浏览器分布</span></template>
                <div v-if="analytics.browsers?.length">
                  <div v-for="b in analytics.browsers" :key="b.browser" class="browser-row">
                    <span class="browser-name">{{ b.browser || 'Unknown' }}</span>
                    <el-progress :percentage="browserPct(b.count)" :stroke-width="20" />
                    <span class="browser-count">{{ b.count }}</span>
                  </div>
                </div>
                <div v-else class="text-gray text-center p-4">暂无数据</div>
              </el-card>
              <el-card shadow="never" class="mt-3">
                <template #header><span>关键指标</span></template>
                <el-descriptions :column="2" border size="small">
                  <el-descriptions-item label="今日">{{ analytics.today }}</el-descriptions-item>
                  <el-descriptions-item label="本周">{{ analytics.this_week }}</el-descriptions-item>
                  <el-descriptions-item label="本月">{{ analytics.this_month }}</el-descriptions-item>
                  <el-descriptions-item label="平均完成步骤">{{ analytics.avg_steps_completed }}</el-descriptions-item>
                </el-descriptions>
              </el-card>
            </el-col>
          </el-row>
        </el-tab-pane>

        <el-tab-pane label="演示配置" name="config">
          <el-form :model="configForm" label-width="180px" @submit.prevent="saveConfig">
            <el-form-item label="启用演示">
              <el-switch v-model="configForm.enabled" />
            </el-form-item>
            <el-form-item label="会话时长 (分钟)">
              <el-input-number v-model="configForm.session_duration_minutes" :min="5" :max="120" :step="5" />
            </el-form-item>
            <el-form-item label="延长时长 (分钟)">
              <el-input-number v-model="configForm.extend_minutes" :min="5" :max="60" :step="5" />
            </el-form-item>
            <el-form-item label="CTA 标题">
              <el-input v-model="configForm.cta_title" maxlength="100" />
            </el-form-item>
            <el-form-item label="CTA 描述">
              <el-input v-model="configForm.cta_description" type="textarea" :rows="2" maxlength="500" />
            </el-form-item>
            <el-form-item>
              <el-button type="primary" native-type="submit" :loading="saving">保存配置</el-button>
            </el-form-item>
          </el-form>
        </el-tab-pane>

        <el-tab-pane label="嵌入代码" name="embed">
          <el-alert
            title="将以下代码复制到官网 HTML 中，即可嵌入交互式产品演示"
            type="info" show-icon :closable="false" class="mb-3"
          />
          <el-card shadow="never">
            <pre class="embed-code">{{ embedCode || '加载中...' }}</pre>
            <el-button size="small" type="primary" class="mt-2" @click="copyEmbedCode">
              复制嵌入代码
            </el-button>
          </el-card>
          <el-card shadow="never" class="mt-3">
            <template #header><span>嵌入配置选项</span></template>
            <el-descriptions :column="1" border size="small">
              <el-descriptions-item label="嵌入 JS URL">{{ embedJsUrl }}</el-descriptions-item>
              <el-descriptions-item label="Demo URL">https://demo.huwutong.com</el-descriptions-item>
              <el-descriptions-item label="支持模式">floating / inline / modal</el-descriptions-item>
            </el-descriptions>
            <pre class="config-example mt-2">{{ embedConfigExample }}</pre>
          </el-card>
        </el-tab-pane>

        <el-tab-pane label="会话记录" name="sessions">
          <div class="section-toolbar">
            <el-select v-model="sessionFilter.status" placeholder="状态筛选" clearable style="width:150px" @change="loadSessions">
              <el-option label="全部" value="" />
              <el-option label="活跃" value="active" />
              <el-option label="已完成" value="completed" />
              <el-option label="已过期" value="expired" />
            </el-select>
          </div>
          <el-table :data="sessions" stripe v-loading="sessionsLoading">
            <el-table-column prop="id" label="ID" width="60" />
            <el-table-column label="状态" width="80">
              <template #default="{ row }">
                <el-tag :type="row.status === 'active' ? 'success' : row.status === 'completed' ? 'primary' : 'info'" size="small">
                  {{ row.status }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="ip_address" label="IP" width="140" />
            <el-table-column prop="step" label="步骤" width="60" />
            <el-table-column prop="created_at" label="开始时间" width="170" />
            <el-table-column prop="expires_at" label="过期时间" width="170" />
            <el-table-column prop="last_activity_at" label="最后活动" width="170" />
          </el-table>
          <div class="pagination-wrap" v-if="sessions.length">
            <el-pagination background layout="prev,pager,next" :total="sessionsTotal" :current="sessionsPage" @current-change="loadSessions" />
          </div>
        </el-tab-pane>
      </el-tabs>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { ElMessage } from 'element-plus';
import { Monitor, Refresh } from '@element-plus/icons-vue';
import demoAdminApi from '@/api/demoAdmin';

const loading = ref(false);
const activeTab = ref('dashboard');
const saving = ref(false);
const sessionsLoading = ref(false);
const sessions = ref([]);
const sessionsTotal = ref(0);
const sessionsPage = ref(1);
const embedCode = ref('');
const embedJsUrl = ref('');

const analytics = reactive({
  total: 0, active: 0, completed: 0, expired: 0,
  today: 0, this_week: 0, this_month: 0,
  avg_steps_completed: 0, conversion_rate: 0, registrations: 0,
  daily_trend: [], browsers: [],
});

const config = reactive({
  enabled: true, session_duration_minutes: 30, extend_minutes: 15,
  cta_title: '免费注册', cta_description: '',
});

const configForm = reactive({
  enabled: true, session_duration_minutes: 30, extend_minutes: 15,
  cta_title: '', cta_description: '',
});

const sessionFilter = reactive({ status: '' });

const embedConfigExample = `// 可选配置
window.HWT_DEMO_CONFIG = {
  mode: 'floating',      // floating | inline | modal
  position: 'bottom-right',
  buttonText: '在线体验',
  themeColor: '#409eff',
  autoOpen: false,       // 自动打开
  autoOpenDelay: 3000,
  onRegister: function(data) {
    console.log('用户注册:', data);
  },
};`;

function barWidth(val, type) {
  const max = type === 'starts'
    ? Math.max(...analytics.daily_trend.map(d => d.starts), 1)
    : Math.max(...analytics.daily_trend.map(d => d.completions), 1);
  return (val / max) * 100;
}

function browserPct(count) {
  const total = analytics.browsers.reduce((s, b) => s + b.count, 0);
  return total > 0 ? Math.round((count / total) * 100) : 0;
}

async function refreshAll() {
  loading.value = true;
  try {
    const [anaRes, cfgRes, embedRes] = await Promise.all([
      demoAdminApi.getAnalytics(),
      demoAdminApi.getConfig(),
      demoAdminApi.getEmbedCode(),
    ]);

    if (anaRes?.data) Object.assign(analytics, anaRes.data);
    if (cfgRes?.data) {
      Object.assign(config, cfgRes.data);
      Object.assign(configForm, cfgRes.data);
    }
    if (embedRes?.data) {
      embedCode.value = embedRes.data.embed_code;
      embedJsUrl.value = embedRes.data.embed_js_url;
    }
  } catch {
    ElMessage.error('加载失败');
  } finally {
    loading.value = false;
  }
}

async function saveConfig() {
  saving.value = true;
  try {
    const { data } = await demoAdminApi.updateConfig(configForm);
    if (data?.data) Object.assign(config, data.data);
    ElMessage.success('配置已保存');
  } catch {
    ElMessage.error('保存失败');
  } finally {
    saving.value = false;
  }
}

async function loadSessions(page) {
  if (page) sessionsPage.value = page;
  sessionsLoading.value = true;
  try {
    const { data } = await demoAdminApi.getSessions({
      page: sessionsPage.value,
      per_page: 15,
      status: sessionFilter.status || undefined,
    });
    if (data?.data) {
      sessions.value = data.data.data || data.data || [];
      sessionsTotal.value = data.data.total || 0;
    }
  } finally {
    sessionsLoading.value = false;
  }
}

async function copyEmbedCode() {
  try {
    await navigator.clipboard.writeText(embedCode.value);
    ElMessage.success('嵌入代码已复制');
  } catch {
    ElMessage.warning('复制失败，请手动复制');
  }
}

onMounted(async () => {
  await refreshAll();
  if (activeTab.value === 'sessions') await loadSessions();
});
</script>

<style scoped>
.demo-admin-page { padding: 0; }
.page-header {
  display: flex; justify-content: space-between;
  align-items: center; margin-bottom: 16px;
}
.page-header h2 { margin: 0; }
.header-actions { display: flex; gap: 8px; align-items: center; }
.mb-4 { margin-bottom: 16px; }
.mb-3 { margin-bottom: 12px; }
.mt-2 { margin-top: 8px; }
.mt-3 { margin-top: 12px; }
.mt-4 { margin-top: 16px; }
.p-4 { padding: 16px; }
.text-gray { color: #909399; }
.text-center { text-align: center; }
.text-green { color: #67c23a; }
.text-blue { color: #409eff; }
.text-yellow { color: #e6a23c; }
.text-red { color: #f56c6c; }
.stat-card { text-align: center; }
.stat-card .stat-value { font-size: 1.8em; font-weight: 700; color: #409eff; }
.stat-card .stat-label { font-size: 0.85em; color: #909399; margin-top: 4px; }
.section-toolbar { margin-bottom: 12px; }
.trend-row {
  display: flex; align-items: center; gap: 8px;
  margin-bottom: 8px; font-size: 13px;
}
.trend-date { width: 50px; flex-shrink: 0; }
.trend-bar-container {
  flex: 1; height: 24px; background: #f0f0f0;
  border-radius: 4px; position: relative; overflow: hidden;
}
.trend-bar {
  position: absolute; height: 100%; border-radius: 4px;
  display: flex; align-items: center; padding-left: 6px;
  font-size: 11px; color: #fff;
  transition: width 0.3s;
}
.trend-bar.starts { background: #409eff; z-index: 1; }
.trend-bar.completions { background: #67c23a; z-index: 2; }
.browser-row {
  display: flex; align-items: center; gap: 8px;
  margin-bottom: 8px; font-size: 13px;
}
.browser-name { width: 100px; flex-shrink: 0; }
.browser-count { width: 40px; text-align: right; color: #909399; }
.embed-code {
  background: #1d1e1f; color: #e6e6e6;
  padding: 16px; border-radius: 6px;
  font-size: 13px; line-height: 1.6;
  overflow-x: auto; white-space: pre-wrap;
  max-height: 300px;
}
.config-example {
  background: #f5f7fa; padding: 12px;
  border-radius: 4px; font-size: 12px;
  line-height: 1.5; color: #606266;
}
.pagination-wrap { margin-top: 16px; text-align: center; }
</style>
