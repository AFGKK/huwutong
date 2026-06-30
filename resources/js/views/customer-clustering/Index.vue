<template>
    <div class="clustering-page">
        <h2>AI 客户行为聚类</h2>

        <div class="toolbar">
            <el-button type="primary" @click="handleRunClustering" :loading="running">执行聚类分析</el-button>
            <el-button @click="loadDashboard">刷新</el-button>
            <span style="margin-left:12px;color:#909399;font-size:13px">已分配: {{ dashboard.total_assigned || 0 }} 客户</span>
        </div>

        <!-- 分段卡片 -->
        <el-row :gutter="20" class="stats-row">
            <el-col :span="4" v-for="(seg, key) in dashboard.segments" :key="key">
                <el-card shadow="hover" :style="{ borderTop: `3px solid ${seg.color}` }">
                    <div class="seg-card">
                        <div class="seg-label">{{ seg.label }}</div>
                        <div class="seg-count">{{ seg.count }}</div>
                        <div class="seg-score">{{ seg.avg_score }}分</div>
                        <div class="seg-actions">
                            <el-button size="small" @click="showSegment(key)">查看客户</el-button>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab" type="border-card">
            <el-tab-pane label="分群明细" name="detail">
                <div v-if="selectedSegment">
                    <h3>{{ selectedSegment }} - {{ dashboard.segments?.[selectedSegment]?.label }}</h3>
                    <el-tag v-for="act in (dashboard.segments?.[selectedSegment]?.actions || [])" :key="act" style="margin-right:6px">{{ act }}</el-tag>
                    <el-table :data="segmentCustomers" v-loading="customersLoading" stripe style="margin-top:12px">
                        <el-table-column prop="id" label="ID" width="60" />
                        <el-table-column prop="name" label="客户名称" min-width="180" />
                        <el-table-column prop="email" label="邮箱" width="200" />
                        <el-table-column label="操作" width="120">
                            <template #default="{row}">
                                <el-button size="small" @click="viewCustomerCluster(row)">查看聚类</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </div>
                <el-empty v-else description="请点击上方的分群卡片查看客户" />
            </el-tab-pane>

            <el-tab-pane label="客户聚类详情" name="customer-cluster" :disabled="!customerClusterData">
                <div v-if="customerClusterData">
                    <el-descriptions :column="2" border>
                        <el-descriptions-item label="客户">{{ customerClusterData.customer?.name }}</el-descriptions-item>
                        <el-descriptions-item label="当前分群">
                            <el-tag :color="dashboard.segments?.[customerClusterData.current_segment]?.color" style="color:#fff">{{ customerClusterData.segment_label }}</el-tag>
                        </el-descriptions-item>
                        <el-descriptions-item label="归属分数">{{ customerClusterData.score }}</el-descriptions-item>
                        <el-descriptions-item label="分配时间">{{ customerClusterData.assigned_at }}</el-descriptions-item>
                    </el-descriptions>
                    <h4 style="margin-top:16px">特征向量</h4>
                    <el-table :data="featureRows" stripe size="small">
                        <el-table-column prop="key" label="特征" width="200" />
                        <el-table-column prop="value" label="值" width="150" />
                    </el-table>
                    <h4 style="margin-top:12px">推荐运营动作</h4>
                    <el-tag v-for="act in (customerClusterData.recommended_actions || [])" :key="act" style="margin-right:6px" type="success">{{ act }}</el-tag>
                </div>
                <el-empty v-else description="请从分群明细中选择客户" />
            </el-tab-pane>

            <el-tab-pane label="变更历史" name="history">
                <el-table :data="history" v-loading="historyLoading" stripe>
                    <el-table-column prop="customer.name" label="客户" width="180" />
                    <el-table-column prop="segment_key" label="当前分群" width="140" />
                    <el-table-column prop="score" label="分数" width="70" />
                    <el-table-column prop="assigned_at" label="分配时间" width="170" />
                    <el-table-column prop="previous_segment_at" label="上次变更" width="170" />
                </el-table>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { getClusteringDashboard, runClustering, getSegmentCustomers, getCustomerCluster, getClusteringHistory } from '@/api/customerClustering';

const activeTab = ref('detail');
const running = ref(false);
const customersLoading = ref(false);
const historyLoading = ref(false);
const dashboard = ref({ segments: {}, total_assigned: 0 });
const selectedSegment = ref(null);
const segmentCustomers = ref([]);
const customerClusterData = ref(null);
const history = ref([]);

const featureRows = computed(() => {
    if (!customerClusterData.value?.features) return [];
    return Object.entries(customerClusterData.value.features).map(([key, value]) => ({ key, value }));
});

async function loadDashboard() {
    try { dashboard.value = await getClusteringDashboard(); }
    catch (e) { console.error(e); }
}
async function handleRunClustering() {
    running.value = true;
    try { const r = await runClustering(); ElMessage.success(`聚类完成: ${r.assigned}/${r.total} 客户已分配`); loadDashboard(); }
    catch (e) { ElMessage.error('聚类分析失败'); } finally { running.value = false; }
}
async function showSegment(key) {
    selectedSegment.value = key;
    customersLoading.value = true;
    try { const r = await getSegmentCustomers(key, { per_page: 50 }); segmentCustomers.value = r.data || []; activeTab.value = 'detail'; }
    catch (e) { console.error(e); } finally { customersLoading.value = false; }
}
async function viewCustomerCluster(row) {
    try { const r = await getCustomerCluster(row.id); customerClusterData.value = r; activeTab.value = 'customer-cluster'; }
    catch (e) { ElMessage.error('获取聚类详情失败'); }
}
async function loadHistory() {
    historyLoading.value = true;
    try { const r = await getClusteringHistory({ per_page: 50 }); history.value = r.data || []; }
    catch (e) { console.error(e); } finally { historyLoading.value = false; }
}

onMounted(() => { loadDashboard(); loadHistory(); });
</script>

<style scoped>
.clustering-page { padding: 20px; }
.toolbar { margin-bottom: 20px; display: flex; align-items: center; }
.stats-row { margin-bottom: 20px; }
.seg-card { text-align: center; padding: 6px 0; }
.seg-label { font-size: 14px; font-weight: 600; margin-bottom: 8px; }
.seg-count { font-size: 32px; font-weight: 700; color: #303133; }
.seg-score { font-size: 12px; color: #909399; margin: 4px 0 8px; }
.seg-actions { margin-top: 8px; }
</style>
