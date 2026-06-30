<template>
    <div class="ai-ops-page">
        <div class="page-header">
            <h2><el-icon><MagicStick /></el-icon> AI 运营分析</h2>
            <p class="text-muted">用自然语言查询运营数据，AI 自动生成图表</p>
        </div>

        <!-- 看板概览 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="4" v-for="(val, key) in metrics" :key="key">
                <el-card shadow="never" @click="quickQuestion(key)" style="cursor:pointer">
                    <div class="metric-card">
                        <div class="metric-value">{{ val }}</div>
                        <div class="metric-label">{{ metricLabels[key] || key }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 聊天式分析区 -->
        <el-row :gutter="16">
            <el-col :span="6">
                <!-- 预置模板 -->
                <el-card shadow="never">
                    <template #header><span>📊 分析模板</span></template>
                    <el-menu :default-active="activeTemplate" @select="onSelectTemplate">
                        <el-menu-item-group title="License 分析">
                            <el-menu-item index="activation_trend">📈 激活趋势</el-menu-item>
                            <el-menu-item index="activation_by_product">📊 按产品统计激活</el-menu-item>
                            <el-menu-item index="license_status_dist">🥧 License 状态分布</el-menu-item>
                            <el-menu-item index="expiring_soon">⏰ 即将过期</el-menu-item>
                        </el-menu-item-group>
                        <el-menu-item-group title="客户分析">
                            <el-menu-item index="top_customers">🏆 客户排行</el-menu-item>
                            <el-menu-item index="customer_growth">📈 客户增长</el-menu-item>
                        </el-menu-item-group>
                        <el-menu-item-group title="设备分析">
                            <el-menu-item index="device_by_platform">💻 设备平台分布</el-menu-item>
                            <el-menu-item index="active_devices">📱 活跃设备趋势</el-menu-item>
                        </el-menu-item-group>
                        <el-menu-item-group title="订阅分析">
                            <el-menu-item index="subscription_by_plan">📋 订阅方案分布</el-menu-item>
                            <el-menu-item index="churn_rate">📉 流失率</el-menu-item>
                        </el-menu-item-group>
                    </el-menu>
                </el-card>
            </el-col>

            <el-col :span="18">
                <!-- 提问区 -->
                <el-card shadow="never" class="mb-4">
                    <el-input
                        v-model="question"
                        type="textarea"
                        :rows="2"
                        placeholder="输入运营分析问题，例如：上周哪个产品激活最多？"
                        @keyup.enter="submitQuestion"
                    />
                    <div class="ask-actions">
                        <div class="suggestions">
                            <el-tag
                                v-for="s in suggestions"
                                :key="s"
                                size="small"
                                class="suggestion-tag"
                                @click="question = s; submitQuestion()"
                            >{{ s }}</el-tag>
                        </div>
                        <el-button type="primary" :loading="loading" @click="submitQuestion" icon="Search">分析</el-button>
                    </div>
                </el-card>

                <!-- 结果区 -->
                <el-card shadow="never" v-if="result">
                    <template #header>
                        <div class="result-header">
                            <span>{{ result.explanation || '分析结果' }}</span>
                            <div class="result-meta">
                                <el-tag size="small" type="info">{{ chartTypeLabel }}</el-tag>
                                <el-tag size="small" type="success">{{ result.count }} 条</el-tag>
                                <el-tag size="small" type="warning">{{ result.elapsed_ms }}ms</el-tag>
                            </div>
                        </div>
                    </template>

                    <!-- 数值卡片 -->
                    <div v-if="result.chart_type === 'number' && result.data?.length" class="number-display">
                        <div v-for="(val, key) in result.data[0]" :key="key" class="number-item">
                            <div class="number-label">{{ key }}</div>
                            <div class="number-value">{{ val }}</div>
                        </div>
                    </div>

                    <!-- ECharts 图表 -->
                    <div v-if="showChart" ref="chartRef" style="width:100%;height:400px"></div>

                    <!-- 数据表格 -->
                    <el-table v-if="result.chart_type === 'table' || result.data?.length > 0" :data="result.data || []" stripe size="small" max-height="400" class="mt-3">
                        <el-table-column v-for="col in tableColumns" :key="col" :prop="col" :label="col" min-width="120" show-overflow-tooltip />
                    </el-table>

                    <!-- SQL 详情 -->
                    <div class="sql-detail" v-if="result.sql">
                        <el-divider />
                        <div class="sql-header">
                            <span class="text-muted">生成的 SQL</span>
                            <el-button text size="small" @click="copied = true; copySql(result.sql)">{{ copied ? '已复制' : '复制' }}</el-button>
                        </div>
                        <pre class="sql-code">{{ result.sql }}</pre>
                    </div>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick, watch } from 'vue';
import { ElMessage } from 'element-plus';
import { getAIOpsDashboard, getAIOpsTemplates, runAIOpsTemplate, askAIOpsQuestion } from '@/api/aiOps';
import * as echarts from 'echarts';

const loading = ref(false);
const question = ref('');
const result = ref(null);
const activeTemplate = ref('');
const metrics = ref({});
const chartRef = ref(null);
let chartInstance = null;
const copied = ref(false);

const metricLabels = {
    total_licenses: 'License 总数',
    active_licenses: '活跃 License',
    total_customers: '客户总数',
    today_activations: '今日激活',
    expiring_soon: '即将过期',
};

const suggestions = [
    '上周哪个产品激活最多？',
    '本月新增多少客户？',
    'License 各状态分布情况',
    '最近 30 天激活趋势',
    '未来 7 天哪些 License 即将过期',
];

const chartTypeLabel = computed(() => {
    const labels = { line: '📈 折线图', bar: '📊 柱状图', pie: '🥧 饼图', table: '📋 表格', number: '🔢 数值' };
    return labels[result.value?.chart_type] || result.value?.chart_type;
});

const showChart = computed(() => {
    return result.value?.chart_type && ['line', 'bar', 'pie', 'trend'].includes(result.value?.chart_type);
});

const tableColumns = computed(() => {
    if (!result.value?.data?.length) return [];
    return Object.keys(result.value.data[0]);
});

const loadDashboard = async () => {
    try {
        const res = await getAIOpsDashboard();
        if (res.data.success) metrics.value = res.data.data;
    } catch (e) { /* ignore */ }
};

const submitQuestion = async () => {
    if (!question.value.trim()) { ElMessage.warning('请输入分析问题'); return; }
    loading.value = true;
    result.value = null;
    activeTemplate.value = '';
    try {
        const res = await askAIOpsQuestion(question.value);
        if (res.data.success) {
            result.value = res.data.data;
            await nextTick();
            renderChart();
        } else {
            ElMessage.error(res.data.message || '分析失败');
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '请求失败');
    } finally { loading.value = false; }
};

const onSelectTemplate = async (key) => {
    activeTemplate.value = key;
    loading.value = true;
    result.value = null;
    try {
        const res = await runAIOpsTemplate(key, { days: 30 });
        if (res.data.success) {
            result.value = res.data.data;
            await nextTick();
            renderChart();
        } else {
            ElMessage.error(res.data.message || '模板分析失败');
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '请求失败');
    } finally { loading.value = false; }
};

const quickQuestion = (key) => {
    const promptMap = {
        total_licenses: '当前共有多少 License？',
        active_licenses: '活跃 License 有多少？',
        total_customers: '总共有多少客户？',
        today_activations: '今天的激活数量是多少？',
        expiring_soon: '未来 7 天哪些 License 即将过期？',
    };
    question.value = promptMap[key] || '';
    if (question.value) submitQuestion();
};

const renderChart = () => {
    if (!chartRef.value || !result.value?.data?.length) return;
    if (chartInstance) chartInstance.dispose();

    chartInstance = echarts.init(chartRef.value);
    const data = result.value.data;
    const chartType = result.value.chart_type;
    const columns = Object.keys(data[0] || {});
    if (columns.length < 2) return;

    const xCol = columns[0];
    const yCol = columns[1];

    let option = {};

    if (chartType === 'line') {
        option = {
            tooltip: { trigger: 'axis' },
            grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
            xAxis: { type: 'category', data: data.map(d => d[xCol]), axisLabel: { rotate: 45 } },
            yAxis: { type: 'value' },
            series: [{ data: data.map(d => Number(d[yCol]) || 0), type: 'line', smooth: true, areaStyle: { opacity: 0.15 } }],
        };
    } else if (chartType === 'bar') {
        option = {
            tooltip: { trigger: 'axis' },
            grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
            xAxis: { type: 'category', data: data.map(d => d[xCol]), axisLabel: { rotate: data.length > 8 ? 45 : 0 } },
            yAxis: { type: 'value' },
            series: [{ data: data.map(d => Number(d[yCol]) || 0), type: 'bar', itemStyle: { color: '#409eff' } }],
        };
    } else if (chartType === 'pie') {
        option = {
            tooltip: { trigger: 'item' },
            series: [{
                type: 'pie',
                radius: ['30%', '60%'],
                data: data.map(d => ({ name: d[xCol], value: Number(d[yCol]) || 0 })),
                emphasis: { itemStyle: { shadowBlur: 10, shadowOffsetX: 0, shadowColor: 'rgba(0,0,0,0.5)' } },
            }],
        };
    }

    if (Object.keys(option).length) {
        chartInstance.setOption(option);
        window.addEventListener('resize', () => chartInstance?.resize());
    }
};

const copySql = (sql) => {
    navigator.clipboard.writeText(sql).then(() => {
        setTimeout(() => { copied.value = false; }, 2000);
    });
};

onMounted(() => {
    loadDashboard();
});
</script>

<style scoped>
.page-header { display: flex; align-items: center; gap: 8px; margin-bottom: 16px; }
.page-header h2 { margin: 0; display: flex; align-items: center; gap: 6px; }
.text-muted { color: #909399; font-size: 13px; margin: 0; }
.metric-card { text-align: center; padding: 8px 0; cursor: pointer; }
.metric-value { font-size: 24px; font-weight: 700; color: #409eff; }
.metric-label { font-size: 12px; color: #909399; margin-top: 2px; }
.ask-actions { display: flex; justify-content: space-between; align-items: flex-start; margin-top: 8px; }
.suggestions { display: flex; flex-wrap: wrap; gap: 4px; flex: 1; }
.suggestion-tag { cursor: pointer; }
.suggestion-tag:hover { opacity: 0.8; }
.result-header { display: flex; justify-content: space-between; align-items: center; }
.result-meta { display: flex; gap: 4px; }
.number-display { display: flex; gap: 16px; flex-wrap: wrap; }
.number-item { text-align: center; padding: 16px 24px; background: #f5f7fa; border-radius: 8px; flex: 1; min-width: 120px; }
.number-label { font-size: 13px; color: #909399; }
.number-value { font-size: 28px; font-weight: 700; color: #303133; margin-top: 4px; }
.sql-detail { margin-top: 8px; }
.sql-header { display: flex; justify-content: space-between; align-items: center; }
.sql-code { background: #f5f7fa; padding: 12px; border-radius: 4px; font-size: 12px; overflow-x: auto; white-space: pre-wrap; word-break: break-all; }
.mt-3 { margin-top: 12px; }
.mb-4 { margin-bottom: 16px; }
</style>
