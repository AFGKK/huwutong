<template>
    <div class="health-score-manager">
        <el-page-header :content="'客户健康度 ' + activeTabText" @back="router.push('/')" />

        <el-tabs v-model="activeTab" class="mt-4">
            <!-- ═══ 概览看板 ═══ -->
            <el-tab-pane label="概览看板" name="dashboard">
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6">
                        <el-card shadow="hover">
                            <div class="stat-card">
                                <div class="stat-value text-3xl font-bold text-primary">{{ stats.total_customers || '-' }}</div>
                                <div class="stat-label text-gray-500 mt-1">总客户数</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="hover">
                            <div class="stat-card">
                                <div class="stat-value text-3xl font-bold text-green-500">{{ stats.healthy || '-' }}</div>
                                <div class="stat-label text-gray-500 mt-1">健康 ({{ stats.avg_score || '-' }} 均分)</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="hover">
                            <div class="stat-card">
                                <div class="stat-value text-3xl font-bold text-orange-500">{{ stats.warning || '-' }}</div>
                                <div class="stat-label text-gray-500 mt-1">警告</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="hover">
                            <div class="stat-card">
                                <div class="stat-value text-3xl font-bold text-red-500">{{ stats.critical || '-' }}</div>
                                <div class="stat-label text-gray-500 mt-1">危险 (高风险流失 {{ stats.high_risk_churn || 0 }})</div>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>

                <el-card title="各维度平均分" class="mb-4">
                    <div class="flex gap-6 flex-wrap">
                        <div v-for="(val, key) in (stats.dimension_averages || {})" :key="key" class="dimension-box">
                            <div class="dimension-label">{{ dimLabels[key] || key }}</div>
                            <el-progress :percentage="val" :color="scoreColor(val)" :stroke-width="16" />
                        </div>
                    </div>
                </el-card>

                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-button type="primary" @click="handleCalculateAll" :loading="calculatingAll" class="w-full">
                            {{ calculatingAll ? '计算中...' : '批量更新所有客户健康分' }}
                        </el-button>
                    </el-col>
                    <el-col :span="12">
                        <el-button @click="refreshDashboard" :loading="loading" class="w-full">刷新数据</el-button>
                    </el-col>
                </el-row>
            </el-tab-pane>

            <!-- ═══ 客户评分排行 ═══ -->
            <el-tab-pane label="客户评分排行" name="ranking">
                <div class="flex gap-4 mb-4">
                    <el-select v-model="gradeFilter" placeholder="筛选等级" clearable style="width: 140px">
                        <el-option label="健康" value="healthy" />
                        <el-option label="警告" value="warning" />
                        <el-option label="危险" value="critical" />
                    </el-select>
                    <el-button type="primary" @click="fetchList">查询</el-button>
                </div>

                <el-table :data="scoreList" v-loading="loading" stripe border>
                    <el-table-column label="客户" min-width="200">
                        <template #default="{ row }">
                            <div>
                                <span class="font-medium">{{ row.customer?.user?.name || `ID:${row.customer_id}` }}</span>
                                <div class="text-xs text-gray-400">{{ row.customer?.user?.email }}</div>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column label="健康分" width="120" sortable prop="score">
                        <template #default="{ row }">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-lg" :style="{ color: scoreColor(row.score) }">{{ Number(row.score).toFixed(1) }}</span>
                                <el-tag :type="gradeTagType(row.grade)" size="small">{{ gradeLabel(row.grade) }}</el-tag>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column label="激活" width="100" prop="activation_score">
                        <template #default="{ row }">{{ Number(row.activation_score).toFixed(1) }}</template>
                    </el-table-column>
                    <el-table-column label="续费" width="100" prop="renewal_score">
                        <template #default="{ row }">{{ Number(row.renewal_score).toFixed(1) }}</template>
                    </el-table-column>
                    <el-table-column label="工单" width="100" prop="ticket_score">
                        <template #default="{ row }">{{ Number(row.ticket_score).toFixed(1) }}</template>
                    </el-table-column>
                    <el-table-column label="设备" width="100" prop="device_score">
                        <template #default="{ row }">{{ Number(row.device_score).toFixed(1) }}</template>
                    </el-table-column>
                    <el-table-column label="支付" width="100" prop="payment_score">
                        <template #default="{ row }">{{ Number(row.payment_score).toFixed(1) }}</template>
                    </el-table-column>
                    <el-table-column label="预警" min-width="200">
                        <template #default="{ row }">
                            <div class="flex flex-wrap gap-1">
                                <el-tag v-for="w in (row.warnings || [])" :key="w.type" :type="w.severity === 'critical' ? 'danger' : w.severity === 'high' ? 'warning' : 'info'" size="small" effect="dark">
                                    {{ w.message }}
                                </el-tag>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column label="评分时间" width="170" prop="calculated_at">
                        <template #default="{ row }">{{ row.calculated_at ? new Date(row.calculated_at).toLocaleString() : '-' }}</template>
                    </el-table-column>
                </el-table>

                <div class="flex justify-center mt-4">
                    <el-pagination
                        v-model:current-page="page"
                        :page-size="perPage"
                        :total="total"
                        layout="prev, pager, next"
                        @current-change="fetchList"
                    />
                </div>
            </el-tab-pane>

            <!-- ═══ 流失预警 ═══ -->
            <el-tab-pane label="流失预警" name="churn">
                <div class="flex gap-4 mb-4">
                    <el-select v-model="riskFilter" placeholder="筛选风险等级" clearable style="width: 160px">
                        <el-option label="低风险" value="low" />
                        <el-option label="中风险" value="medium" />
                        <el-option label="高风险" value="high" />
                        <el-option label="严重" value="critical" />
                    </el-select>
                    <el-button type="primary" @click="fetchChurnList">查询</el-button>
                </div>

                <el-table :data="churnList" v-loading="loading" stripe border>
                    <el-table-column label="客户" min-width="200">
                        <template #default="{ row }">
                            <div>
                                <span class="font-medium">{{ row.customer?.user?.name || `ID:${row.customer_id}` }}</span>
                                <div class="text-xs text-gray-400">{{ row.customer?.user?.email }}</div>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column label="流失概率" width="130">
                        <template #default="{ row }">
                            <span class="font-mono" :style="{ color: riskColor(row.risk_level) }">
                                {{ (row.churn_probability * 100).toFixed(1) }}%
                            </span>
                        </template>
                    </el-table-column>
                    <el-table-column label="风险等级" width="120">
                        <template #default="{ row }">
                            <el-tag :type="riskTagType(row.risk_level)" effect="dark" size="small">
                                {{ riskLabel(row.risk_level) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="流失信号" min-width="200">
                        <template #default="{ row }">
                            <div class="flex flex-wrap gap-1">
                                <el-tag v-for="s in (row.top_signals || [])" :key="s" size="small">
                                    {{ signalLabel(s) }}
                                </el-tag>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column label="干预建议" min-width="250">
                        <template #default="{ row }">
                            <ul class="list-disc pl-4 text-sm">
                                <li v-for="r in (row.recommendations || [])" :key="r.action">{{ r.detail }}</li>
                            </ul>
                        </template>
                    </el-table-column>
                    <el-table-column label="预测时间" width="170" prop="predicted_at">
                        <template #default="{ row }">{{ row.predicted_at ? new Date(row.predicted_at).toLocaleString() : '-' }}</template>
                    </el-table-column>
                </el-table>

                <div class="flex justify-center mt-4">
                    <el-pagination
                        v-model:current-page="churnPage"
                        :page-size="churnPerPage"
                        :total="churnTotal"
                        layout="prev, pager, next"
                        @current-change="fetchChurnList"
                    />
                </div>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script>
import { defineComponent, ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import healthScoreApi from '@/api/healthScore';

export default defineComponent({
    name: 'HealthScoreIndex',
    setup() {
        const router = useRouter();
        const activeTab = ref('dashboard');
        const loading = ref(false);
        const calculatingAll = ref(false);

        const activeTabText = ref('- 概览看板');

        // ─── Dashboard ───
        const stats = ref({});

        // ─── Ranking ───
        const scoreList = ref([]);
        const gradeFilter = ref('');
        const page = ref(1);
        const perPage = ref(20);
        const total = ref(0);

        // ─── Churn ───
        const churnList = ref([]);
        const riskFilter = ref('');
        const churnPage = ref(1);
        const churnPerPage = ref(20);
        const churnTotal = ref(0);

        const dimLabels = {
            activation: '激活活跃度',
            renewal: '续费健康度',
            ticket: '工单体验',
            device: '设备安全',
            payment: '支付健康度',
        };

        function scoreColor(score) {
            const s = Number(score);
            if (s >= 70) return '#67c23a';
            if (s >= 40) return '#e6a23c';
            return '#f56c6c';
        }

        function gradeLabel(grade) {
            return { healthy: '健康', warning: '警告', critical: '危险' }[grade] || grade;
        }

        function gradeTagType(grade) {
            return { healthy: 'success', warning: 'warning', critical: 'danger' }[grade] || 'info';
        }

        function riskLabel(level) {
            return { low: '低风险', medium: '中风险', high: '高风险', critical: '严重' }[level] || level;
        }

        function riskTagType(level) {
            return { low: 'info', medium: 'warning', high: 'danger', critical: 'danger' }[level] || 'info';
        }

        function riskColor(level) {
            return { low: '#909399', medium: '#e6a23c', high: '#f56c6c', critical: '#b91c1c' }[level] || '#909399';
        }

        function signalLabel(signal) {
            const labels = {
                renewal_score_low: '续费分低',
                payment_overdue: '支付逾期',
                low_activation: '激活不足',
                ticket_frustration: '工单不满',
            };
            return labels[signal] || signal;
        }

        async function refreshDashboard() {
            loading.value = true;
            try {
                const res = await healthScoreApi.getDashboard();
                stats.value = res.data.data || {};
            } catch (e) {
                console.error('Failed to fetch dashboard', e);
            } finally {
                loading.value = false;
            }
        }

        async function handleCalculateAll() {
            try {
                await ElMessageBox.confirm('将批量计算所有活跃客户的健康分，该操作可能需要一些时间。是否继续？', '确认', { type: 'warning' });
                calculatingAll.value = true;
                const res = await healthScoreApi.calculateAll();
                ElMessage.success(res.data.message || '批量计算完成');
                await refreshDashboard();
            } catch (e) {
                if (e !== 'cancel') ElMessage.error('批量计算失败');
            } finally {
                calculatingAll.value = false;
            }
        }

        async function fetchList() {
            loading.value = true;
            try {
                const params = { page: page.value, per_page: perPage.value };
                if (gradeFilter.value) params.grade = gradeFilter.value;
                const res = await healthScoreApi.getList(params);
                scoreList.value = res.data.data || [];
                total.value = res.data.total || 0;
            } catch (e) {
                console.error('Failed to fetch list', e);
            } finally {
                loading.value = false;
            }
        }

        async function fetchChurnList() {
            loading.value = true;
            try {
                const params = { page: churnPage.value, per_page: churnPerPage.value };
                if (riskFilter.value) params.risk_level = riskFilter.value;
                const res = await healthScoreApi.getChurnList(params);
                churnList.value = res.data.data || [];
                churnTotal.value = res.data.total || 0;
            } catch (e) {
                console.error('Failed to fetch churn list', e);
            } finally {
                loading.value = false;
            }
        }

        onMounted(() => {
            refreshDashboard();
        });

        return {
            router, activeTab, activeTabText, loading, calculatingAll,
            stats, scoreList, gradeFilter, page, perPage, total,
            churnList, riskFilter, churnPage, churnPerPage, churnTotal,
            dimLabels, scoreColor, gradeLabel, gradeTagType,
            riskLabel, riskTagType, riskColor, signalLabel,
            refreshDashboard, handleCalculateAll, fetchList, fetchChurnList,
        };
    },
});
</script>

<style scoped>
.health-score-manager {
    padding: 20px;
}
.stat-card {
    text-align: center;
    padding: 8px 0;
}
.dimension-box {
    flex: 1;
    min-width: 160px;
    padding: 8px 12px;
}
.dimension-label {
    font-size: 13px;
    color: #606266;
    margin-bottom: 6px;
    font-weight: 500;
}
</style>
