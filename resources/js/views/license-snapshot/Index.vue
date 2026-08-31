<template>
    <div class="snapshot-container">
        <el-page-header :content="t('license_snapshot_page.title')" @back="$router.push('/admin/dashboard')" />

        <el-alert :title="t('license_snapshot_page.alert_desc')" type="info" show-icon :closable="false" class="alert-info" />

        <!-- 统计 -->
        <el-row :gutter="20" class="stat-cards">
            <el-col :span="6"><el-card shadow="hover"><div class="stat-value">{{ dash.total }}</div><div class="stat-label">{{ t('license_snapshot_page.stat_total') }}</div></el-card></el-col>
            <el-col :span="6"><el-card shadow="hover"><div class="stat-value text-primary">{{ dash.today }}</div><div class="stat-label">{{ t('license_snapshot_page.stat_today') }}</div></el-card></el-col>
            <el-col :span="6"><el-card shadow="hover"><div class="stat-value text-warning">{{ dash.last_30d }}</div><div class="stat-label">{{ t('license_snapshot_page.stat_last_30d') }}</div></el-card></el-col>
            <el-col :span="6"><el-card shadow="hover"><div class="stat-value">{{ actions }}</div><div class="stat-label">{{ t('license_snapshot_page.stat_action_types') }}</div></el-card></el-col>
        </el-row>

        <el-card>
            <template #header>
                <el-space>
                    <span>{{ t('license_snapshot_page.list_title') }}</span>
                    <el-select v-model="actionFilter" :placeholder="t('license_snapshot_page.filter_action')" clearable size="small" style="width:140px" @change="loadList">
                        <el-option v-for="opt in actionOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-space>
            </template>
            <el-table :data="snapshots" stripe v-loading="loading">
                <el-table-column prop="created_at" :label="t('license_snapshot_page.col_time')" width="160" />
                <el-table-column :label="t('license_snapshot_page.col_action')" width="120">
                    <template #default="{ row }">
                        <el-tag size="small">{{ actionLabel(row.action) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('license_snapshot_page.col_license')" width="180">
                    <template #default="{ row }">{{ row.license?.license_key || row.license_id }}</template>
                </el-table-column>
                <el-table-column :label="t('license_snapshot_page.col_status_change')" width="160">
                    <template #default="{ row }">{{ row.status_before || '-' }} → {{ row.status_after || '-' }}</template>
                </el-table-column>
                <el-table-column :label="t('license_snapshot_page.col_change_note')" min-width="200">
                    <template #default="{ row }">
                        <template v-if="row.diff?.note">{{ row.diff.note }}</template>
                        <template v-else-if="row.diff">
                            <el-tag v-for="(v, k) in row.diff" :key="k" size="small" style="margin-right:4px">{{ k }}: {{ v?.from }}→{{ v?.to }}</el-tag>
                        </template>
                        <span v-else>-</span>
                    </template>
                </el-table-column>
                <el-table-column :label="t('license_snapshot_page.col_ops')" width="120" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="viewDetail(row)">{{ t('actions.view_details') }}</el-button>
                        <el-popconfirm :title="t('license_snapshot_page.rollback_confirm')" @confirm="handleRollback(row)">
                            <template #reference><el-button size="small" type="warning">{{ t('license_snapshot_page.actions.rollback') }}</el-button></template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>
            <el-pagination v-if="total > perPage" v-model:current-page="page" :page-size="perPage" :total="total" layout="prev, pager, next" @current-change="loadList" class="pagination" />
        </el-card>

        <!-- 详情弹窗 -->
        <el-dialog v-model="detailVisible" :title="t('license_snapshot_page.detail_title')" width="800px">
            <el-descriptions :column="2" border size="small" v-if="detail">
                <el-descriptions-item :label="t('license_snapshot_page.label_snapshot_id')">{{ detail.id }}</el-descriptions-item>
                <el-descriptions-item :label="t('license_snapshot_page.col_action')">{{ actionLabel(detail.action) }}</el-descriptions-item>
                <el-descriptions-item :label="t('license_snapshot_page.label_license_id')">{{ detail.license_id }}</el-descriptions-item>
                <el-descriptions-item :label="t('license_snapshot_page.label_created_at')">{{ detail.created_at }}</el-descriptions-item>
                <el-descriptions-item :label="t('license_snapshot_page.label_status_before')">{{ detail.status_before || '-' }}</el-descriptions-item>
                <el-descriptions-item :label="t('license_snapshot_page.label_status_after')">{{ detail.status_after || '-' }}</el-descriptions-item>
            </el-descriptions>
            <h4>{{ t('license_snapshot_page.section_snapshot_data') }}</h4>
            <pre class="json-view">{{ JSON.stringify(detail?.license_data, null, 2) }}</pre>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import licenseSnapshot from '@/api/licenseSnapshot';

const { t } = useI18n();

const loading = ref(false);
const snapshots = ref([]);
const detailVisible = ref(false);
const detail = ref(null);
const actionFilter = ref('');
const page = ref(1);
const perPage = ref(20);
const total = ref(0);
const dash = reactive({ total: 0, today: 0, last_30d: 0, by_action: {} });
const actions = ref(0);

const actionKeys = ['', 'upgrade', 'downgrade', 'transfer', 'seat_change', 'type_change', 'admin_edit', 'rollback', 'manual'];

const actionOptions = computed(() => actionKeys.map((value) => ({
    value,
    label: value ? t(`license_snapshot_page.actions.${value}`) : t('license_snapshot_page.filter_all'),
})));

const actionLabels = computed(() => Object.fromEntries(
    actionKeys.filter(Boolean).map((key) => [key, t(`license_snapshot_page.actions.${key}`)]),
));

function actionLabel(a) {
    return actionLabels.value[a] || a;
}

async function loadDash() {
    try { const res = await licenseSnapshot.dashboard(); Object.assign(dash, res.data.data); actions.value = Object.keys(dash.by_action).length; } catch {}
}
async function loadList(p) {
    loading.value = true;
    try {
        const params = { page: p || page.value, per_page: perPage.value };
        if (actionFilter.value) params.action = actionFilter.value;
        const res = await licenseSnapshot.list(params);
        snapshots.value = res.data.data.items || [];
        total.value = res.data.data.total;
        page.value = res.data.data.page;
    } catch {} finally { loading.value = false; }
}
async function viewDetail(row) {
    try { const res = await licenseSnapshot.show(row.id); detail.value = res.data.data; detailVisible.value = true; } catch {}
}
async function handleRollback(row) {
    try { await licenseSnapshot.rollback(row.id); ElMessage.success(t('license_snapshot_page.messages.rollback_success')); loadList(); } catch {}
}

onMounted(() => { loadDash(); loadList(); });
</script>

<style scoped>
.snapshot-container { padding: 20px; }
.alert-info { margin: 16px 0; }
.stat-cards { margin-bottom: 16px; }
.stat-cards .el-card { text-align: center; }
.stat-value { font-size: 28px; font-weight: bold; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.text-primary { color: #0f172a; }
.text-warning { color: #e6a23c; }
.pagination { margin-top: 16px; text-align: center; }
.json-view { background: #f5f7fa; padding: 12px; border-radius: 4px; max-height: 400px; overflow: auto; font-size: 12px; }
</style>
