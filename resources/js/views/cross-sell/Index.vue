<template>
    <div class="cross-sell-page">
        <h2>AI 交叉销售推荐引擎</h2>

        <el-row :gutter="20" class="stats-row">
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ stats.totalRecs || 0 }}</div><div class="stat-label">总推荐</div></div></el-card></el-col>
            <el-col :span="5"><el-card shadow="hover"><div class="stat-card"><div class="stat-value info">{{ stats.shown || 0 }}</div><div class="stat-label">已展示</div></div></el-card></el-col>
            <el-col :span="5"><el-card shadow="hover"><div class="stat-card"><div class="stat-value warning">{{ stats.clicked || 0 }}</div><div class="stat-label">已点击</div></div></el-card></el-col>
            <el-col :span="5"><el-card shadow="hover"><div class="stat-card"><div class="stat-value success">{{ stats.converted || 0 }}</div><div class="stat-label">已转化</div></div></el-card></el-col>
            <el-col :span="5"><el-card shadow="hover"><div class="stat-card"><div class="stat-value" style="color:#722ed1">{{ stats.conversionRate || 0 }}%</div><div class="stat-label">转化率</div></div></el-card></el-col>
        </el-row>

        <el-tabs v-model="activeTab" type="border-card">
            <el-tab-pane label="推荐列表" name="list">
                <div class="toolbar">
                    <el-input v-model="customerId" placeholder="客户ID" style="width:160px;margin-right:8px" />
                    <el-button type="primary" @click="handleGenerate" :loading="generating">生成推荐</el-button>
                    <el-button @click="loadRecommendations">刷新</el-button>
                </div>
                <el-table :data="recs" v-loading="loading" stripe @row-click="showDetail">
                    <el-table-column prop="id" label="ID" width="60" />
                    <el-table-column label="客户" width="120"><template #default="{row}">{{ row.customer?.name || row.customer_id }}</template></el-table-column>
                    <el-table-column label="策略" width="120"><template #default="{row}"><el-tag size="small">{{ row.strategy }}</el-tag></template></el-table-column>
                    <el-table-column label="推荐类型" width="100"><template #default="{row}">{{ row.recommendation_type }}</template></el-table-column>
                    <el-table-column label="推荐内容" min-width="200" show-overflow-tooltip>
                        <template #default="{row}">{{ row.recommendable?.name || row.recommendable_type }}#{{ row.recommendable_id }}</template>
                    </el-table-column>
                    <el-table-column prop="score" label="评分" width="70" align="center" />
                    <el-table-column prop="reason" label="推荐理由" min-width="250" show-overflow-tooltip />
                    <el-table-column prop="status" label="状态" width="90"><template #default="{row}"><el-tag :type="statusTag(row.status)" size="small">{{ row.status }}</el-tag></template></el-table-column>
                    <el-table-column prop="created_at" label="时间" width="170" />
                </el-table>
            </el-tab-pane>

            <el-tab-pane label="推荐详情" name="detail" :disabled="!selectedRec">
                <div v-if="selectedRec">
                    <el-descriptions :column="2" border>
                        <el-descriptions-item label="策略">{{ selectedRec.strategy }}</el-descriptions-item>
                        <el-descriptions-item label="推荐类型">{{ selectedRec.recommendation_type }}</el-descriptions-item>
                        <el-descriptions-item label="评分">{{ selectedRec.score }}</el-descriptions-item>
                        <el-descriptions-item label="可信度">{{ selectedRec.confidence }}</el-descriptions-item>
                        <el-descriptions-item label="状态">{{ selectedRec.status }}</el-descriptions-item>
                        <el-descriptions-item label="推荐理由" :span="2">{{ selectedRec.reason }}</el-descriptions-item>
                    </el-descriptions>
                    <div style="margin-top:12px">
                        <el-button size="small" @click="recordEvent(selectedRec.id, 'shown')">标记已展示</el-button>
                        <el-button size="small" type="warning" @click="recordEvent(selectedRec.id, 'clicked')">标记已点击</el-button>
                        <el-button size="small" type="success" @click="recordEvent(selectedRec.id, 'converted')">标记已转化</el-button>
                        <el-button size="small" @click="recordEvent(selectedRec.id, 'dismissed')">标记已忽略</el-button>
                    </div>
                </div>
                <el-empty v-else description="请选择推荐记录" />
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { getCrossSellDashboard, getRecommendations, generateRecommendations, recordRecommendationEvent, getRecommendationDetail } from '@/api/crossSell';

const activeTab = ref('list');
const stats = ref({});
const recs = ref([]);
const loading = ref(false);
const generating = ref(false);
const customerId = ref('');
const selectedRec = ref(null);

const statusTag = (s) => ({ pending: 'info', shown: 'primary', clicked: 'warning', converted: 'success', dismissed: 'danger' }[s] || 'info');

async function loadDashboard() {
    try { stats.value = await getCrossSellDashboard(); } catch (e) { console.error(e); }
}
async function loadRecommendations() {
    loading.value = true;
    try { const r = await getRecommendations({ per_page: 50 }); recs.value = r.data || []; }
    catch (e) { console.error(e); } finally { loading.value = false; }
}
async function handleGenerate() {
    if (!customerId.value) { ElMessage.warning('请输入客户ID'); return; }
    generating.value = true;
    try { await generateRecommendations(customerId.value); ElMessage.success('推荐生成完成'); loadRecommendations(); }
    catch (e) { ElMessage.error('生成失败'); } finally { generating.value = false; }
}
async function showDetail(row) {
    try { const r = await getRecommendationDetail(row.id); selectedRec.value = r; activeTab.value = 'detail'; }
    catch (e) { ElMessage.error('获取详情失败'); }
}
async function recordEvent(id, type) {
    try { await recordRecommendationEvent(id, type); ElMessage.success('事件已记录'); loadRecommendations(); }
    catch (e) { ElMessage.error('记录失败'); }
}

onMounted(() => { loadDashboard(); loadRecommendations(); });
</script>

<style scoped>
.cross-sell-page { padding: 20px; }
.stats-row { margin-bottom: 20px; }
.stat-card { text-align: center; padding: 6px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: #409eff; }
.stat-value.info { color: #909399; }
.stat-value.warning { color: #e6a23c; }
.stat-value.success { color: #67c23a; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.toolbar { margin-bottom: 16px; display: flex; align-items: center; }
</style>
