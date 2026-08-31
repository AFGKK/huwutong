<template>
    <div class="license-trash-container">
        <el-page-header :content="t('license_trash_page.title')" @back="$router.push('/admin/dashboard')" />

        <el-alert :title="t('license_trash_page.alert_desc')" type="warning" show-icon :closable="false" class="alert-info" />

        <!-- 统计 -->
        <el-row :gutter="20" class="stat-cards">
            <el-col v-for="item in statItems" :key="item.key" :span="6">
                <el-card shadow="hover">
                    <div class="stat-value" :class="item.valueClass">{{ stats[item.key] }}</div>
                    <div class="stat-label">{{ item.label }}</div>
                </el-card>
            </el-col>
        </el-row>

        <el-card>
            <template #header>
                <el-space>
                    <span>{{ t('license_trash_page.list_title') }}</span>
                    <el-button size="small" type="primary" @click="handleBatchRestore">{{ t('licenses_page.batch_restore') }}</el-button>
                    <el-popconfirm :title="t('license_trash_page.clear_confirm')" @confirm="handleClear">
                        <template #reference><el-button size="small" type="danger">{{ t('license_trash_page.clear_trash') }}</el-button></template>
                    </el-popconfirm>
                </el-space>
            </template>
            <el-table :data="trashed" stripe v-loading="loading" @selection-change="onSelectionChange">
                <el-table-column type="selection" width="40" />
                <el-table-column prop="license_key" :label="t('licenses_page.license_key')" width="200" />
                <el-table-column prop="product.name" :label="t('licenses_page.product')" width="120" />
                <el-table-column prop="customer.name" :label="t('licenses_page.customer')" width="120" />
                <el-table-column prop="status" :label="t('license_trash_page.col_status_before')" width="100">
                    <template #default="{ row }"><el-tag size="small">{{ row.status }}</el-tag></template>
                </el-table-column>
                <el-table-column prop="deleted_at" :label="t('license_trash_page.col_deleted_at')" width="160" />
                <el-table-column prop="deleted_by" :label="t('license_trash_page.col_deleted_by')" width="90" />
                <el-table-column :label="t('licenses_page.col_actions')" width="160" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" type="primary" @click="handleRestore(row)">{{ t('licenses_page.restore') }}</el-button>
                        <el-popconfirm :title="t('license_trash_page.force_delete_confirm')" @confirm="handleForceDelete(row)">
                            <template #reference><el-button size="small" type="danger">{{ t('license_trash_page.force_delete') }}</el-button></template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>
            <el-pagination v-if="total > perPage" v-model:current-page="page" :page-size="perPage" :total="total" layout="prev, pager, next" @current-change="loadList" class="pagination" />
            <el-empty v-if="!loading && trashed.length === 0" :description="t('license_trash_page.empty')" :image-size="80" />
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import licenseTrash from '@/api/licenseTrash';

const { t } = useI18n();

const statItems = computed(() => [
    { key: 'total', label: t('license_trash_page.stats.total'), valueClass: '' },
    { key: 'today', label: t('license_trash_page.stats.today'), valueClass: 'text-warning' },
    { key: 'last_7d', label: t('license_trash_page.stats.last_7d'), valueClass: 'text-primary' },
    { key: 'expiring_soon', label: t('license_trash_page.stats.expiring_soon'), valueClass: 'text-danger' },
]);

const loading = ref(false);
const trashed = ref([]);
const selected = ref([]);
const page = ref(1);
const perPage = ref(20);
const total = ref(0);
const stats = reactive({ total: 0, today: 0, last_7d: 0, last_30d: 0, expiring_soon: 0 });

async function loadStats() {
    try { const res = await licenseTrash.stats(); Object.assign(stats, res.data.data); } catch {}
}
async function loadList(p) {
    loading.value = true;
    try {
        const res = await licenseTrash.list({ page: p || page.value, per_page: perPage.value });
        trashed.value = res.data.data.items || [];
        total.value = res.data.data.total;
        page.value = res.data.data.page;
    } catch {} finally { loading.value = false; }
}
function onSelectionChange(val) { selected.value = val.map(v => v.id); }

async function handleRestore(row) {
    try { await licenseTrash.restore(row.id); ElMessage.success(t('license_trash_page.messages.restored')); loadList(); loadStats(); } catch {}
}
async function handleBatchRestore() {
    if (!selected.value.length) { ElMessage.warning(t('license_trash_page.messages.select_first')); return; }
    try { await licenseTrash.batchRestore(selected.value); ElMessage.success(t('license_trash_page.messages.batch_restored')); loadList(); loadStats(); selected.value = []; } catch {}
}
async function handleForceDelete(row) {
    try { await licenseTrash.forceDelete(row.id); ElMessage.success(t('license_trash_page.messages.force_deleted')); loadList(); loadStats(); } catch {}
}
async function handleClear() {
    try { await licenseTrash.clear(); ElMessage.success(t('license_trash_page.messages.cleared')); loadList(); loadStats(); } catch {}
}

onMounted(() => { loadStats(); loadList(); });
</script>

<style scoped>
.license-trash-container { padding: 20px; }
.alert-info { margin: 16px 0; }
.stat-cards { margin-bottom: 16px; }
.stat-cards .el-card { text-align: center; }
.stat-value { font-size: 28px; font-weight: bold; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.text-warning { color: #e6a23c; }
.text-primary { color: #0f172a; }
.text-danger { color: #f56c6c; }
.pagination { margin-top: 16px; text-align: center; }
</style>
