<template>
    <div class="cross-sell-page">
        <h2>{{ t('cross_sell_page.title') }}</h2>

        <el-row :gutter="20" class="stats-row">
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ stats.totalRecs || 0 }}</div><div class="stat-label">{{ t('cross_sell_page.stats.total_recs') }}</div></div></el-card></el-col>
            <el-col :span="5"><el-card shadow="hover"><div class="stat-card"><div class="stat-value info">{{ stats.shown || 0 }}</div><div class="stat-label">{{ t('cross_sell_page.stats.shown') }}</div></div></el-card></el-col>
            <el-col :span="5"><el-card shadow="hover"><div class="stat-card"><div class="stat-value warning">{{ stats.clicked || 0 }}</div><div class="stat-label">{{ t('cross_sell_page.stats.clicked') }}</div></div></el-card></el-col>
            <el-col :span="5"><el-card shadow="hover"><div class="stat-card"><div class="stat-value success">{{ stats.converted || 0 }}</div><div class="stat-label">{{ t('cross_sell_page.stats.converted') }}</div></div></el-card></el-col>
            <el-col :span="5"><el-card shadow="hover"><div class="stat-card"><div class="stat-value" style="color:#722ed1">{{ stats.conversionRate || 0 }}%</div><div class="stat-label">{{ t('cross_sell_page.stats.conversion_rate') }}</div></div></el-card></el-col>
        </el-row>

        <el-tabs v-model="activeTab" type="border-card">
            <el-tab-pane :label="t('cross_sell_page.tabs.list')" name="list">
                <div class="toolbar">
                    <el-input v-model="customerId" :placeholder="t('cross_sell_page.customer_id_ph')" style="width:160px;margin-right:8px" />
                    <el-button type="primary" @click="handleGenerate" :loading="generating">{{ t('cross_sell_page.btn_generate') }}</el-button>
                    <el-button @click="loadRecommendations">{{ t('cross_sell_page.refresh') }}</el-button>
                </div>
                <el-table :data="recs" v-loading="loading" stripe @row-click="showDetail">
                    <el-table-column prop="id" :label="t('cross_sell_page.cols.id')" width="60" />
                    <el-table-column :label="t('cross_sell_page.cols.customer')" width="120"><template #default="{row}">{{ row.customer?.name || row.customer_id }}</template></el-table-column>
                    <el-table-column :label="t('cross_sell_page.cols.strategy')" width="120"><template #default="{row}"><el-tag size="small">{{ row.strategy }}</el-tag></template></el-table-column>
                    <el-table-column :label="t('cross_sell_page.cols.recommendation_type')" width="100"><template #default="{row}">{{ row.recommendation_type }}</template></el-table-column>
                    <el-table-column :label="t('cross_sell_page.cols.recommendation_content')" min-width="200" show-overflow-tooltip>
                        <template #default="{row}">{{ row.recommendable?.name || row.recommendable_type }}#{{ row.recommendable_id }}</template>
                    </el-table-column>
                    <el-table-column prop="score" :label="t('cross_sell_page.cols.score')" width="70" align="center" />
                    <el-table-column prop="reason" :label="t('cross_sell_page.cols.reason')" min-width="250" show-overflow-tooltip />
                    <el-table-column :label="t('cross_sell_page.cols.status')" width="90"><template #default="{row}"><el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag></template></el-table-column>
                    <el-table-column prop="created_at" :label="t('cross_sell_page.cols.time')" width="170" />
                </el-table>
            </el-tab-pane>

            <el-tab-pane :label="t('cross_sell_page.tabs.detail')" name="detail" :disabled="!selectedRec">
                <div v-if="selectedRec">
                    <el-descriptions :column="2" border>
                        <el-descriptions-item :label="t('cross_sell_page.detail.strategy')">{{ selectedRec.strategy }}</el-descriptions-item>
                        <el-descriptions-item :label="t('cross_sell_page.detail.recommendation_type')">{{ selectedRec.recommendation_type }}</el-descriptions-item>
                        <el-descriptions-item :label="t('cross_sell_page.detail.score')">{{ selectedRec.score }}</el-descriptions-item>
                        <el-descriptions-item :label="t('cross_sell_page.detail.confidence')">{{ selectedRec.confidence }}</el-descriptions-item>
                        <el-descriptions-item :label="t('cross_sell_page.detail.status')">{{ statusLabel(selectedRec.status) }}</el-descriptions-item>
                        <el-descriptions-item :label="t('cross_sell_page.detail.reason')" :span="2">{{ selectedRec.reason }}</el-descriptions-item>
                    </el-descriptions>
                    <div style="margin-top:12px">
                        <el-button v-for="evt in eventActions" :key="evt.type" size="small" :type="evt.btnType || undefined" @click="recordEvent(selectedRec.id, evt.type)">{{ evt.label }}</el-button>
                    </div>
                </div>
                <el-empty v-else :description="t('cross_sell_page.empty_select')" />
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { getCrossSellDashboard, getRecommendations, generateRecommendations, recordRecommendationEvent, getRecommendationDetail } from '@/api/crossSell';

const { t } = useI18n();

const activeTab = ref('list');
const stats = ref({});
const recs = ref([]);
const loading = ref(false);
const generating = ref(false);
const customerId = ref('');
const selectedRec = ref(null);

const statusKeys = ['pending', 'shown', 'clicked', 'converted', 'dismissed'];

const statusMap = computed(() =>
    Object.fromEntries(statusKeys.map((key) => [key, t(`cross_sell_page.status.${key}`)]))
);

const eventActions = computed(() => [
    { type: 'shown', label: t('cross_sell_page.events.shown') },
    { type: 'clicked', label: t('cross_sell_page.events.clicked'), btnType: 'warning' },
    { type: 'converted', label: t('cross_sell_page.events.converted'), btnType: 'success' },
    { type: 'dismissed', label: t('cross_sell_page.events.dismissed') },
]);

const statusTag = (s) => ({ pending: 'info', shown: 'primary', clicked: 'warning', converted: 'success', dismissed: 'danger' }[s] || 'info');

function statusLabel(status) {
    return statusMap.value[status] || status;
}

async function loadDashboard() {
    try { stats.value = await getCrossSellDashboard(); } catch (e) { console.error(e); }
}
async function loadRecommendations() {
    loading.value = true;
    try { const r = await getRecommendations({ per_page: 50 }); recs.value = r.data || []; }
    catch (e) { console.error(e); } finally { loading.value = false; }
}
async function handleGenerate() {
    if (!customerId.value) { ElMessage.warning(t('cross_sell_page.messages.customer_id_required')); return; }
    generating.value = true;
    try { await generateRecommendations(customerId.value); ElMessage.success(t('cross_sell_page.messages.generate_done')); loadRecommendations(); }
    catch (e) { ElMessage.error(t('cross_sell_page.messages.generate_failed')); } finally { generating.value = false; }
}
async function showDetail(row) {
    try { const r = await getRecommendationDetail(row.id); selectedRec.value = r; activeTab.value = 'detail'; }
    catch (e) { ElMessage.error(t('messages.load_failed')); }
}
async function recordEvent(id, type) {
    try { await recordRecommendationEvent(id, type); ElMessage.success(t('messages.success')); loadRecommendations(); }
    catch (e) { ElMessage.error(t('messages.failed')); }
}

onMounted(() => { loadDashboard(); loadRecommendations(); });
</script>

<style scoped>
.cross-sell-page { padding: 20px; }
.stats-row { margin-bottom: 20px; }
.stat-card { text-align: center; padding: 6px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: #0f172a; }
.stat-value.info { color: #909399; }
.stat-value.warning { color: #e6a23c; }
.stat-value.success { color: #67c23a; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.toolbar { margin-bottom: 16px; display: flex; align-items: center; }
</style>
