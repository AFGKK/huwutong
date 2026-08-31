<template>
    <div class="clustering-page">
        <h2>{{ t('customer_clustering_page.title') }}</h2>

        <div class="toolbar">
            <el-button type="primary" @click="handleRunClustering" :loading="running">{{ t('customer_clustering_page.btn_run') }}</el-button>
            <el-button @click="loadDashboard">{{ t('customer_clustering_page.refresh') }}</el-button>
            <span style="margin-left:12px;color:#909399;font-size:13px">{{ t('customer_clustering_page.assigned_count', { count: dashboard.total_assigned || 0 }) }}</span>
        </div>

        <!-- 分段卡片 -->
        <el-row :gutter="20" class="stats-row">
            <el-col :span="4" v-for="(seg, key) in dashboard.segments" :key="key">
                <el-card shadow="hover" :style="{ borderTop: `3px solid ${seg.color}` }">
                    <div class="seg-card">
                        <div class="seg-label">{{ seg.label }}</div>
                        <div class="seg-count">{{ seg.count }}</div>
                        <div class="seg-score">{{ seg.avg_score }}{{ t('customer_clustering_page.score_suffix') }}</div>
                        <div class="seg-actions">
                            <el-button size="small" @click="showSegment(key)">{{ t('customer_clustering_page.view_customers') }}</el-button>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab" type="border-card">
            <el-tab-pane :label="t('customer_clustering_page.tabs.detail')" name="detail">
                <div v-if="selectedSegment">
                    <h3>{{ selectedSegment }} - {{ dashboard.segments?.[selectedSegment]?.label }}</h3>
                    <el-tag v-for="act in (dashboard.segments?.[selectedSegment]?.actions || [])" :key="act" style="margin-right:6px">{{ act }}</el-tag>
                    <el-table :data="segmentCustomers" v-loading="customersLoading" stripe style="margin-top:12px">
                        <el-table-column prop="id" :label="t('customer_clustering_page.cols.id')" width="60" />
                        <el-table-column prop="name" :label="t('customer_clustering_page.cols.customer_name')" min-width="180" />
                        <el-table-column prop="email" :label="t('customer_clustering_page.cols.email')" width="200" />
                        <el-table-column :label="t('customer_clustering_page.cols.actions')" width="120">
                            <template #default="{row}">
                                <el-button size="small" @click="viewCustomerCluster(row)">{{ t('customer_clustering_page.view_cluster') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </div>
                <el-empty v-else :description="t('customer_clustering_page.empty_select_segment')" />
            </el-tab-pane>

            <el-tab-pane :label="t('customer_clustering_page.tabs.customer_cluster')" name="customer-cluster" :disabled="!customerClusterData">
                <div v-if="customerClusterData">
                    <el-descriptions :column="2" border>
                        <el-descriptions-item :label="t('customer_clustering_page.cols.customer')">{{ customerClusterData.customer?.name }}</el-descriptions-item>
                        <el-descriptions-item :label="t('customer_clustering_page.cols.current_segment')">
                            <el-tag :color="dashboard.segments?.[customerClusterData.current_segment]?.color" style="color:#fff">{{ customerClusterData.segment_label }}</el-tag>
                        </el-descriptions-item>
                        <el-descriptions-item :label="t('customer_clustering_page.cols.score')">{{ customerClusterData.score }}</el-descriptions-item>
                        <el-descriptions-item :label="t('customer_clustering_page.cols.assigned_at')">{{ customerClusterData.assigned_at }}</el-descriptions-item>
                    </el-descriptions>
                    <h4 style="margin-top:16px">{{ t('customer_clustering_page.feature_vectors') }}</h4>
                    <el-table :data="featureRows" stripe size="small">
                        <el-table-column prop="key" :label="t('customer_clustering_page.cols.feature')" width="200" />
                        <el-table-column prop="value" :label="t('customer_clustering_page.cols.value')" width="150" />
                    </el-table>
                    <h4 style="margin-top:12px">{{ t('customer_clustering_page.recommended_actions') }}</h4>
                    <el-tag v-for="act in (customerClusterData.recommended_actions || [])" :key="act" style="margin-right:6px" type="success">{{ act }}</el-tag>
                </div>
                <el-empty v-else :description="t('customer_clustering_page.empty_select_customer')" />
            </el-tab-pane>

            <el-tab-pane :label="t('customer_clustering_page.tabs.history')" name="history">
                <el-table :data="history" v-loading="historyLoading" stripe>
                    <el-table-column prop="customer.name" :label="t('customer_clustering_page.cols.customer')" width="180" />
                    <el-table-column prop="segment_key" :label="t('customer_clustering_page.cols.current_segment')" width="140" />
                    <el-table-column prop="score" :label="t('customer_clustering_page.cols.score')" width="70" />
                    <el-table-column prop="assigned_at" :label="t('customer_clustering_page.cols.assigned_at')" width="170" />
                    <el-table-column prop="previous_segment_at" :label="t('customer_clustering_page.cols.previous_change')" width="170" />
                </el-table>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { getClusteringDashboard, runClustering, getSegmentCustomers, getCustomerCluster, getClusteringHistory } from '@/api/customerClustering';

const { t } = useI18n();

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
    try {
        const r = await runClustering();
        ElMessage.success(t('customer_clustering_page.messages.clustering_done', { assigned: r.assigned, total: r.total }));
        loadDashboard();
    }
    catch (e) { ElMessage.error(t('customer_clustering_page.messages.clustering_failed')); } finally { running.value = false; }
}
async function showSegment(key) {
    selectedSegment.value = key;
    customersLoading.value = true;
    try { const r = await getSegmentCustomers(key, { per_page: 50 }); segmentCustomers.value = r.data || []; activeTab.value = 'detail'; }
    catch (e) { console.error(e); } finally { customersLoading.value = false; }
}
async function viewCustomerCluster(row) {
    try { const r = await getCustomerCluster(row.id); customerClusterData.value = r; activeTab.value = 'customer-cluster'; }
    catch (e) { ElMessage.error(t('customer_clustering_page.messages.cluster_detail_failed')); }
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
