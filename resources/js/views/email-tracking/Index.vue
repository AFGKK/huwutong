<template>
    <div class="email-tracking-page">
        <div class="page-header">
            <div class="header-left">
                <h2>邮件追踪数据面板</h2>
                <span class="header-subtitle">送达率 / 打开率 / 点击率统计与邮件漏斗分析</span>
            </div>
        </div>

        <!-- 漏斗概览 -->
        <el-row :gutter="16" class="funnel-row">
            <el-col :span="6" v-for="item in funnelItems" :key="item.label">
                <el-card shadow="never" class="funnel-card">
                    <div class="funnel-bar" :style="{ background: item.color, width: item.percent + '%' }"></div>
                    <div class="funnel-content">
                        <div class="funnel-value">{{ item.value }}</div>
                        <div class="funnel-label">{{ item.label }}</div>
                        <div class="funnel-rate">{{ item.rate }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="16" class="info-row">
            <!-- 趋势图表 -->
            <el-col :span="16">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>每日邮件趋势（近 30 天）</span>
                        </div>
                    </template>
                    <div ref="chartRef" class="chart-container" v-loading="loading"></div>
                </el-card>
            </el-col>

            <!-- 退信统计 -->
            <el-col :span="8">
                <el-card shadow="never">
                    <template #header>
                        <span>退信原因归类</span>
                    </template>
                    <div v-loading="bounceLoading">
                        <div v-if="bounceStats.length">
                            <div v-for="item in bounceStats" :key="item.bounce_reason" class="bounce-item">
                                <div class="bounce-reason" :title="item.bounce_reason">{{ item.bounce_reason }}</div>
                                <div class="bounce-bar-wrapper">
                                    <div class="bounce-bar" :style="{ width: bouncePercent(item.count) + '%' }"></div>
                                </div>
                                <div class="bounce-count">{{ item.count }}</div>
                            </div>
                        </div>
                        <el-empty v-else description="暂无退信记录" :image-size="60" />
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 按模板统计 -->
        <el-card shadow="never" class="table-card">
            <template #header>
                <span>按模板统计</span>
            </template>
            <el-table :data="byTemplate" v-loading="loading" stripe>
                <el-table-column label="模板标识" width="200" prop="template_code">
                    <template #default="{ row }">
                        <el-button text type="primary" size="small" @click="drillDown(row)">
                            <code>{{ row.template_code }}</code>
                        </el-button>
                    </template>
                </el-table-column>
                <el-table-column label="发送量" width="80" prop="total_sent" align="center" />
                <el-table-column label="送达" width="80" prop="delivered" align="center" />
                <el-table-column label="送达率" width="90" align="center">
                    <template #default="{ row }">
                        <el-tag :type="rateType(row.total_sent, row.delivered, 95)" size="small">
                            {{ row.total_sent > 0 ? (row.delivered / row.total_sent * 100).toFixed(1) : 0 }}%
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="打开" width="80" prop="opened" align="center" />
                <el-table-column label="打开率" width="90" align="center">
                    <template #default="{ row }">
                        <el-tag :type="rateType(row.delivered, row.opened, 40)" size="small">
                            {{ row.delivered > 0 ? (row.opened / row.delivered * 100).toFixed(1) : 0 }}%
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="点击" width="80" prop="clicked" align="center" />
                <el-table-column label="点击率" width="90" align="center">
                    <template #default="{ row }">
                        <el-tag :type="rateType(row.opened, row.clicked, 20)" size="small">
                            {{ row.opened > 0 ? (row.clicked / row.opened * 100).toFixed(1) : 0 }}%
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="退信" width="80" prop="bounced" align="center" />
                <el-table-column label="失败" width="80" prop="failed" align="center" />
                <el-table-column label="操作" width="100">
                    <template #default="{ row }">
                        <el-button text size="small" type="primary" @click="drillDown(row)">下钻</el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- 模板下钻 Dialog -->
        <el-dialog v-model="drillDownVisible" :title="'模板详情: ' + drillDownCode" width="900px" top="5vh">
            <div v-loading="drillDownLoading">
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6" v-for="item in drillDownFunnel" :key="item.label">
                        <el-card shadow="never" class="funnel-card-small">
                            <div class="funnel-value-sm">{{ item.value }}</div>
                            <div class="funnel-label-sm">{{ item.label }}</div>
                            <div class="funnel-rate-sm">{{ item.rate }}</div>
                        </el-card>
                    </el-col>
                </el-row>

                <h4 class="section-title">每日趋势</h4>
                <div ref="drillChartRef" class="chart-container-sm"></div>

                <h4 class="section-title mt-4">发送记录</h4>
                <el-table :data="drillDownLogs" stripe size="small">
                    <el-table-column label="收件人" width="180" prop="to_email" />
                    <el-table-column label="主题" min-width="200" prop="subject" show-overflow-tooltip />
                    <el-table-column label="状态" width="100" prop="status">
                        <template #default="{ row }">
                            <el-tag :type="row.status === 'delivered' ? 'success' : row.status === 'opened' ? 'primary' : row.status === 'bounced' ? 'danger' : 'info'" size="small">
                                {{ row.status === 'delivered' ? '已送达' : row.status === 'opened' ? '已打开' : row.status === 'clicked' ? '已点击' : row.status === 'bounced' ? '退信' : row.status === 'failed' ? '失败' : row.status }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="发送时间" width="170" prop="created_at">
                        <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
                    </el-table-column>
                    <el-table-column label="打开时间" width="170" prop="opened_at">
                        <template #default="{ row }">{{ formatDate(row.opened_at) || '-' }}</template>
                    </el-table-column>
                </el-table>
            </div>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick } from 'vue';
import { ElMessage } from 'element-plus';
import trackingApi from '@/api/email-tracking';

const loading = ref(false);
const bounceLoading = ref(false);
const chartRef = ref(null);
const drillChartRef = ref(null);
const drillDownVisible = ref(false);
const drillDownLoading = ref(false);
const drillDownCode = ref('');
const drillDownFunnel = ref([]);
const drillDownLogs = ref([]);

const overview = reactive({
    funnel: {},
    daily: [],
    by_template: [],
});

const bounceStats = ref([]);

const funnelItems = computed(() => {
    const f = overview.funnel;
    if (!f.total_sent) return [];
    const maxVal = f.total_sent;

    return [
        { label: '总发送', value: f.total_sent, rate: '100%', color: '#409EFF', percent: 100 },
        { label: '已送达', value: f.delivered, rate: f.delivery_rate + '%', color: '#67C23A', percent: maxVal > 0 ? (f.delivered / maxVal * 100) : 0 },
        { label: '已打开', value: f.opened, rate: f.open_rate + '%', color: '#E6A23C', percent: maxVal > 0 ? (f.opened / maxVal * 100) : 0 },
        { label: '已点击', value: f.clicked, rate: f.click_rate + '%', color: '#F56C6C', percent: maxVal > 0 ? (f.clicked / maxVal * 100) : 0 },
    ];
});

const byTemplate = computed(() => overview.by_template || []);

function rateType(total, achieved, threshold) {
    if (!total) return 'danger';
    const rate = achieved / total * 100;
    if (rate >= threshold) return 'success';
    if (rate >= threshold * 0.7) return 'warning';
    return 'danger';
}

function formatDate(dateStr) {
    if (!dateStr) return null;
    return new Date(dateStr).toLocaleString('zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

function bouncePercent(count) {
    const max = Math.max(...bounceStats.value.map(b => b.count), 1);
    return (count / max * 100);
}

async function loadOverview() {
    loading.value = true;
    try {
        const { data: res } = await trackingApi.overview();
        if (res.success) {
            Object.assign(overview, res.data);
            await nextTick();
            renderChart();
        }
    } catch {
        ElMessage.error('加载邮件追踪数据失败');
    } finally {
        loading.value = false;
    }
}

async function loadBounceStats() {
    bounceLoading.value = true;
    try {
        const { data: res } = await trackingApi.bounceStats();
        if (res.success) {
            bounceStats.value = res.data?.bounce_categories || [];
        }
    } catch {
        bounceStats.value = [];
    } finally {
        bounceLoading.value = false;
    }
}

function renderChart() {
    if (!chartRef.value || !overview.daily?.length) return;

    const ctx = chartRef.value;
    ctx.innerHTML = '';

    const canvas = document.createElement('canvas');
    canvas.width = ctx.clientWidth * 2;
    canvas.height = 300 * 2;
    canvas.style.width = ctx.clientWidth + 'px';
    canvas.style.height = '300px';
    ctx.appendChild(canvas);

    const c = canvas.getContext('2d');
    c.scale(2, 2);
    const w = canvas.width / 2;
    const h = canvas.height / 2;
    const padding = { top: 20, right: 20, bottom: 40, left: 50 };
    const chartW = w - padding.left - padding.right;
    const chartH = h - padding.top - padding.bottom;

    const days = overview.daily;
    const labels = days.map(d => {
        const parts = d.date.split('-');
        return parts[1] + '/' + parts[2];
    });
    const datasets = [
        { label: '发送', key: 'total', color: '#409EFF' },
        { label: '送达', key: 'delivered', color: '#67C23A' },
        { label: '打开', key: 'opened', color: '#E6A23C' },
    ];

    const maxVal = Math.max(...days.map(d => Math.max(d.total, d.delivered, d.opened)), 1);

    // Grid
    c.strokeStyle = '#eee';
    c.lineWidth = 1;
    for (let i = 0; i <= 4; i++) {
        const y = padding.top + chartH * (1 - i / 4);
        c.beginPath();
        c.moveTo(padding.left, y);
        c.lineTo(w - padding.right, y);
        c.stroke();
        c.fillStyle = '#909399';
        c.font = '11px sans-serif';
        c.textAlign = 'right';
        c.fillText(Math.round(maxVal * i / 4), padding.left - 8, y + 4);
    }

    // Lines + dots
    datasets.forEach(ds => {
        c.strokeStyle = ds.color;
        c.lineWidth = 2;
        c.beginPath();

        days.forEach((d, i) => {
            const x = padding.left + (i / Math.max(days.length - 1, 1)) * chartW;
            const y = padding.top + chartH * (1 - (d[ds.key] || 0) / maxVal);
            if (i === 0) c.moveTo(x, y);
            else c.lineTo(x, y);
        });
        c.stroke();

        // Dots
        days.forEach((d, i) => {
            const x = padding.left + (i / Math.max(days.length - 1, 1)) * chartW;
            const y = padding.top + chartH * (1 - (d[ds.key] || 0) / maxVal);
            c.fillStyle = ds.color;
            c.beginPath();
            c.arc(x, y, 3, 0, Math.PI * 2);
            c.fill();
        });
    });

    // X-axis labels
    const step = Math.max(1, Math.floor(days.length / 15));
    labels.forEach((label, i) => {
        if (i % step !== 0 && i !== labels.length - 1) return;
        c.fillStyle = '#909399';
        c.font = '11px sans-serif';
        c.textAlign = 'center';
        const x = padding.left + (i / Math.max(days.length - 1, 1)) * chartW;
        c.fillText(label, x, h - 10);
    });

    // Legend
    let legendX = padding.left;
    datasets.forEach(ds => {
        c.fillStyle = ds.color;
        c.fillRect(legendX, 5, 12, 8);
        c.fillStyle = '#303133';
        c.font = '11px sans-serif';
        c.textAlign = 'left';
        c.fillText(ds.label, legendX + 16, 13);
        legendX += 50 + c.measureText(ds.label).width;
    });
}

async function drillDown(row) {
    drillDownCode.value = row.template_code;
    drillDownVisible.value = true;
    drillDownLoading.value = true;
    try {
        const { data: res } = await trackingApi.templateDetail(row.template_code);
        if (res.success) {
            const f = res.data.funnel;
            drillDownFunnel.value = [
                { label: '发送', value: f.total_sent, rate: '100%' },
                { label: '送达', value: f.delivered, rate: f.delivery_rate + '%' },
                { label: '打开', value: f.opened, rate: f.open_rate + '%' },
                { label: '点击', value: f.clicked, rate: f.click_rate + '%' },
            ];
            drillDownLogs.value = res.data.daily || [];

            await nextTick();
            renderDrillChart(res.data.daily || []);
        }
    } catch {
        ElMessage.error('加载模板详情失败');
    } finally {
        drillDownLoading.value = false;
    }
}

function renderDrillChart(daily) {
    if (!drillChartRef.value || !daily.length) return;
    const ctx = drillChartRef.value;
    ctx.innerHTML = '';

    const canvas = document.createElement('canvas');
    canvas.width = ctx.clientWidth * 2;
    canvas.height = 200 * 2;
    canvas.style.width = ctx.clientWidth + 'px';
    canvas.style.height = '200px';
    ctx.appendChild(canvas);

    const c = canvas.getContext('2d');
    c.scale(2, 2);
    const w = canvas.width / 2;
    const h = canvas.height / 2;
    const padding = { top: 10, right: 10, bottom: 30, left: 40 };
    const chartW = w - padding.left - padding.right;
    const chartH = h - padding.top - padding.bottom;

    const maxVal = Math.max(...daily.map(d => Math.max(d.total, d.opened)), 1);
    const barW = Math.min(20, chartW / daily.length * 0.6);

    daily.forEach((d, i) => {
        const x = padding.left + (i / daily.length) * chartW + (chartW / daily.length - barW) / 2;
        const totalH = (d.total / maxVal) * chartH;
        const openH = (d.opened / maxVal) * chartH;

        // Total bar
        c.fillStyle = '#409EFF';
        c.fillRect(x, padding.top + chartH - totalH, barW / 2, totalH);

        // Opened bar
        c.fillStyle = '#E6A23C';
        c.fillRect(x + barW / 2, padding.top + chartH - openH, barW / 2, openH);

        // Label
        c.fillStyle = '#909399';
        c.font = '9px sans-serif';
        c.textAlign = 'center';
        const label = d.date.split('-').slice(1).join('/');
        c.fillText(label, x + barW / 2, h - 5);
    });
}

onMounted(() => {
    loadOverview();
    loadBounceStats();
});
</script>

<style scoped>
.email-tracking-page { padding: 20px; }

.page-header {
    margin-bottom: 20px;
}
.page-header h2 { margin: 0; font-size: 20px; }
.header-subtitle {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-left: 12px;
}

/* 漏斗 */
.funnel-row { margin-bottom: 16px; }
.funnel-card {
    position: relative;
    overflow: hidden;
}
.funnel-bar {
    position: absolute;
    top: 0;
    left: 0;
    height: 4px;
    transition: width 0.6s ease;
}
.funnel-content { text-align: center; padding: 4px 0; }
.funnel-value { font-size: 28px; font-weight: 700; color: #303133; }
.funnel-label { font-size: 13px; color: var(--el-text-color-secondary); margin-top: 2px; }
.funnel-rate { font-size: 14px; font-weight: 600; color: var(--el-color-primary); margin-top: 2px; }

.info-row { margin-bottom: 16px; }

/* 图表 */
.chart-container {
    width: 100%;
    height: 320px;
}
.chart-container-sm {
    width: 100%;
    height: 220px;
}

.card-header { font-weight: 600; }

/* 退信 */
.bounce-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 0;
    font-size: 13px;
}
.bounce-reason {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.bounce-bar-wrapper {
    width: 80px;
    height: 8px;
    background: var(--el-fill-color-light);
    border-radius: 4px;
    overflow: hidden;
}
.bounce-bar {
    height: 100%;
    background: #F56C6C;
    border-radius: 4px;
    transition: width 0.4s ease;
}
.bounce-count {
    width: 30px;
    text-align: right;
    font-weight: 600;
    color: #F56C6C;
}

.table-card { margin-bottom: 16px; }

/* 下钻 */
.funnel-card-small { text-align: center; padding: 8px; }
.funnel-value-sm { font-size: 22px; font-weight: 700; }
.funnel-label-sm { font-size: 12px; color: var(--el-text-color-secondary); }
.funnel-rate-sm { font-size: 13px; font-weight: 600; color: var(--el-color-primary); }

.section-title {
    font-size: 15px;
    font-weight: 600;
    margin: 0 0 12px;
}
.mt-4 { margin-top: 16px; }
.mb-4 { margin-bottom: 16px; }

code {
    font-family: 'SF Mono', 'Fira Code', monospace;
    font-size: 12px;
}

:deep(.el-card__body) { padding: 16px; }
</style>
