<template>
  <div class="funnel-page">
    <div class="page-header">
      <h2>
        <el-icon style="vertical-align:middle;margin-right:8px"><DataAnalysis /></el-icon>
        Trial → 付费转化漏斗
      </h2>
      <div class="header-actions">
        <el-select v-model="period" style="width:140px;margin-right:8px" @change="refreshAll">
          <el-option label="近7天" value="7" />
          <el-option label="近30天" value="30" />
          <el-option label="近90天" value="90" />
        </el-select>
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon><Refresh /></el-icon> 刷新
        </el-button>
      </div>
    </div>

    <!-- 指标卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-primary">{{ stats.funnel?.total_started || 0 }}</div>
          <div class="stat-label">注册 Trial</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-success">{{ stats.funnel?.total_converted || 0 }}</div>
          <div class="stat-label">转化为付费</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value" :class="stats.funnel?.overall_rate < 10 ? 'stat-danger' : 'stat-success'">
            {{ stats.funnel?.overall_rate || 0 }}%
          </div>
          <div class="stat-label">整体转化率</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.today_registered || 0 }}</div>
          <div class="stat-label">今日注册</div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="16" class="mb-4">
      <el-col :span="16">
        <!-- 漏斗图 -->
        <el-card shadow="hover">
          <template #header><span>转化漏斗</span></template>
          <div v-if="funnelStages.length">
            <div v-for="(stage, idx) in funnelStages" :key="stage.stage" class="funnel-row">
              <div class="funnel-label">
                <span class="stage-name">{{ stage.label }}</span>
                <span class="stage-count">{{ stage.count }} 个</span>
              </div>
              <div class="funnel-bar-wrap">
                <div
                  class="funnel-bar"
                  :style="{ width: stage.conversion_from_first + '%', background: stageColor(idx) }"
                >
                  <span class="bar-text">{{ stage.conversion_from_first }}%</span>
                </div>
              </div>
              <div class="funnel-drop">
                <span v-if="stage.drop_off > 0" class="drop-text">-{{ stage.drop_rate }}%</span>
              </div>
            </div>
          </div>
          <el-empty v-else description="暂无数据" :image-size="60" />
        </el-card>
      </el-col>
      <el-col :span="8">
        <!-- 流失分析 -->
        <el-card shadow="hover">
          <template #header><span>流失分析</span></template>
          <div v-if="funnelStages.length">
            <el-table :data="funnelStages" size="small" stripe>
              <el-table-column label="阶段" prop="label" min-width="100" />
              <el-table-column label="数量" prop="count" width="60" align="center" />
              <el-table-column label="流失率" width="80" align="center">
                <template #default="{ row }">
                  <span :class="row.drop_rate > 20 ? 'text-danger' : ''">{{ row.drop_rate }}%</span>
                </template>
              </el-table-column>
            </el-table>
            <el-alert
              v-if="stats.worst_stage"
              :title="'最大流失点: ' + stats.worst_stage.label + ' (' + stats.worst_stage.drop_rate + '%)'"
              type="warning"
              :closable="false"
              style="margin-top:12px"
            />
          </div>
          <el-empty v-else description="暂无数据" :image-size="60" />
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="16">
      <!-- 渠道分析 -->
      <el-col :span="12">
        <el-card shadow="hover">
          <template #header><span>渠道来源分析</span></template>
          <el-table :data="sourceData" stripe v-loading="sourceLoading" size="small">
            <el-table-column label="渠道" prop="source" min-width="100" />
            <el-table-column label="总数" prop="total" width="80" align="center" />
            <el-table-column label="转化数" prop="converted" width="80" align="center" />
            <el-table-column label="转化率" width="100" align="center">
              <template #default="{ row }">
                <el-tag :type="row.rate > 10 ? 'success' : 'warning'" size="small">{{ row.rate }}%</el-tag>
              </template>
            </el-table-column>
          </el-table>
          <el-empty v-if="!sourceData.length" description="暂无渠道数据" :image-size="50" />
        </el-card>
      </el-col>

      <!-- 趋势 -->
      <el-col :span="12">
        <el-card shadow="hover">
          <template #header><span>转化率趋势</span></template>
          <div ref="trendChartRef" style="height:240px"></div>
          <el-empty v-if="!trendData.length" description="暂无趋势数据" :image-size="50" />
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick, watch } from 'vue';
import { DataAnalysis, Refresh } from '@element-plus/icons-vue';
import funnelApi from '@/api/conversionFunnel';
import * as echarts from 'echarts';

const loading = ref(false);
const sourceLoading = ref(false);
const period = ref('30');

const stats = ref({ funnel: { stages: [], total_started: 0, total_converted: 0, overall_rate: 0 }, today_registered: 0, worst_stage: null });
const sourceData = ref([]);
const trendData = ref([]);
const trendChartRef = ref(null);

const funnelStages = computed(() => stats.value.funnel?.stages || []);

onMounted(() => { refreshAll(); });

watch(trendData, () => { nextTick(renderTrendChart); });

async function refreshAll() {
  loading.value = true;
  sourceLoading.value = true;
  try {
    const days = parseInt(period.value);
    const endDate = new Date().toISOString().split('T')[0];
    const startDate = new Date(Date.now() - days * 86400000).toISOString().split('T')[0];

    const [dashRes, funnelRes, sourceRes, trendRes] = await Promise.all([
      funnelApi.dashboard(),
      funnelApi.getFunnelData({ start_date: startDate, end_date: endDate }),
      funnelApi.getBySource({ start_date: startDate, end_date: endDate }),
      funnelApi.getTrend({ days }),
    ]);

    stats.value = dashRes.data;
    // 合并漏斗数据
    if (funnelRes.data?.stages) {
      stats.value.funnel = funnelRes.data;
    }
    sourceData.value = sourceRes.data || [];
    trendData.value = trendRes.data || [];
  } finally { loading.value = false; sourceLoading.value = false; }
}

function stageColor(idx) {
  const colors = ['#409EFF', '#67C23A', '#E6A23C', '#F56C6C', '#909399', '#B37FEB'];
  return colors[idx % colors.length];
}

function renderTrendChart() {
  if (!trendChartRef.value || !trendData.value.length) return;
  const chart = echarts.init(trendChartRef.value);
  chart.setOption({
    tooltip: { trigger: 'axis' },
    xAxis: { type: 'category', data: trendData.value.map(d => d.date), axisLabel: { rotate: 45 } },
    yAxis: { type: 'value', axisLabel: { formatter: '{value}%' } },
    series: [{
      type: 'line', data: trendData.value.map(d => d.conversion_rate),
      smooth: true, areaStyle: { opacity: 0.15 },
      itemStyle: { color: '#409EFF' },
    }],
  });
}
</script>

<style scoped>
.funnel-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; font-size: 20px; }
.mb-4 { margin-bottom: 16px; }
.stat-card { text-align: center; cursor: default; }
.stat-value { font-size: 28px; font-weight: 700; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.stat-success { color: #67C23A; }
.stat-danger { color: #F56C6C; }
.stat-primary { color: #409EFF; }
.funnel-row { display: flex; align-items: center; margin-bottom: 12px; gap: 12px; }
.funnel-label { width: 140px; flex-shrink: 0; text-align: right; }
.stage-name { font-weight: 600; font-size: 14px; display: block; }
.stage-count { font-size: 12px; color: #909399; }
.funnel-bar-wrap { flex: 1; height: 36px; background: #f0f2f5; border-radius: 6px; overflow: hidden; }
.funnel-bar { height: 100%; border-radius: 6px; display: flex; align-items: center; justify-content: center; transition: width .5s; min-width: 40px; }
.bar-text { color: #fff; font-weight: 600; font-size: 13px; }
.funnel-drop { width: 60px; flex-shrink: 0; text-align: left; }
.drop-text { color: #F56C6C; font-size: 12px; }
.text-danger { color: #F56C6C; font-weight: 600; }
</style>
