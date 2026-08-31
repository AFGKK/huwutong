<template>
    <div class="portal-licenses">
        <div class="page-header">
            <h2>{{ $t('portal.my_licenses') }}</h2>
            <div class="header-actions">
                <el-input
                    v-model="searchKey"
                    :placeholder="$t('portal.licenses_search_ph')"
                    clearable
                    style="width: 240px"
                    :prefix-icon="Search"
                    @clear="fetchLicenses"
                    @keyup.enter="fetchLicenses"
                />
                <el-button @click="fetchLicenses" :icon="Refresh">{{ $t('portal.refresh') }}</el-button>
            </div>
        </div>

        <el-row :gutter="16" class="mb-4">
            <el-col :span="8">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value">{{ stats.total }}</div>
                        <div class="mini-label">{{ $t('portal.stat_all_short') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value" style="color: #67c23a">{{ stats.active }}</div>
                        <div class="mini-label">{{ $t('portal.stat_active') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value" style="color: #f56c6c">{{ stats.expired }}</div>
                        <div class="mini-label">{{ $t('portal.stat_expired') }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <div class="batch-bar" v-if="selectedIds.length > 0">
            <span class="batch-info">{{ $t('portal.selected_n', { n: selectedIds.length }) }}</span>
            <el-button size="small" @click="clearSelection">{{ $t('portal.clear_selection') }}</el-button>
            <el-divider direction="vertical" />
            <el-button size="small" type="primary" @click="handleBatchRenew">
                {{ $t('portal.batch_renew') }}
            </el-button>
            <el-button size="small" type="success" @click="handleBatchActivate">
                {{ $t('portal.batch_activate') }}
            </el-button>
            <el-button size="small" @click="handleBatchExport">
                {{ $t('portal.batch_export') }}
            </el-button>
        </div>

        <el-dialog v-model="renewDialog.visible" :title="$t('portal.batch_renew')" width="450px">
            <el-form :model="renewDialog" label-position="top">
                <el-form-item :label="$t('portal.renew_duration')">
                    <el-radio-group v-model="renewDialog.days">
                        <el-radio-button :value="30">{{ $t('portal.months_1') }}</el-radio-button>
                        <el-radio-button :value="90">{{ $t('portal.months_3') }}</el-radio-button>
                        <el-radio-button :value="180">{{ $t('portal.months_6') }}</el-radio-button>
                        <el-radio-button :value="365">{{ $t('portal.years_1') }}</el-radio-button>
                        <el-radio-button :value="730">{{ $t('portal.years_2') }}</el-radio-button>
                    </el-radio-group>
                </el-form-item>
                <el-form-item :label="$t('portal.involved_licenses')">
                    <el-tag v-for="id in selectedIds" :key="id" size="small" style="margin:2px">
                        #{{ id }}
                    </el-tag>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="renewDialog.visible = false">{{ $t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="renewDialog.loading" @click="confirmBatchRenew">
                    {{ $t('portal.confirm_renew') }}
                </el-button>
            </template>
        </el-dialog>

        <el-card shadow="never">
            <el-table
                :data="licenses"
                v-loading="loading"
                stripe
                @selection-change="onSelectionChange"
            >
                <el-table-column type="selection" width="50" />
                <el-table-column label="License Key" min-width="200">
                    <template #default="{ row }">
                        <el-link type="primary" :underline="'never'" @click="$router.push(`/portal/licenses/${row.id}`)">
                            <code>{{ row.license_key }}</code>
                        </el-link>
                    </template>
                </el-table-column>
                <el-table-column prop="product?.name" :label="$t('portal.product')" min-width="120">
                    <template #default="{ row }">{{ row.product?.name || '-' }}</template>
                </el-table-column>
                <el-table-column :label="$t('portal.type')" width="100">
                    <template #default="{ row }">
                        <el-tag v-if="row.type === 'trial'" type="warning" size="small">{{ $t('portal.type_trial') }}</el-tag>
                        <el-tag v-else-if="row.type === 'enterprise'" type="success" size="small">{{ $t('portal.type_enterprise') }}</el-tag>
                        <el-tag v-else-if="row.type === 'development'" size="small">{{ $t('portal.type_development') }}</el-tag>
                        <span v-else>{{ $t('portal.type_standard') }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('portal.status')" width="90">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)" size="small" effect="dark">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="seats" :label="$t('portal.seats')" width="70" align="center" />
                <el-table-column prop="max_devices" :label="$t('portal.device_limit')" width="80" align="center" />
                <el-table-column prop="expires_at" :label="$t('portal.expires_at')" width="180">
                    <template #default="{ row }">
                        <template v-if="row.expires_at">
                            <span v-if="expiryInfo(row.expires_at).class" :class="'expiry-badge ' + expiryInfo(row.expires_at).class">
                                {{ expiryInfo(row.expires_at).text }}
                            </span>
                            <span v-else>{{ row.expires_at }}</span>
                        </template>
                        <span v-else>{{ $t('portal.lifetime') }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" :label="$t('portal.created_at')" width="160" />
                <el-table-column :label="$t('portal.actions')" width="100" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link size="small" @click="$router.push(`/portal/licenses/${row.id}`)">
                            {{ $t('portal.detail') }}
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="pagination-wrap">
                <el-pagination
                    v-model:current-page="page"
                    v-model:page-size="perPage"
                    :total="total"
                    :page-sizes="[10, 20, 50]"
                    layout="total, sizes, prev, pager, next"
                    @current-change="fetchLicenses"
                    @size-change="fetchLicenses"
                />
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import licenseApi from '@/api/license';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Search, Refresh } from '@element-plus/icons-vue';

const { t } = useI18n();

const loading = ref(false);
const licenses = ref([]);
const total = ref(0);
const page = ref(1);
const perPage = ref(10);
const searchKey = ref('');

const selectedIds = ref([]);
function onSelectionChange(rows) {
    selectedIds.value = rows.map(r => r.id);
}
function clearSelection() {
    selectedIds.value = [];
}

const renewDialog = reactive({
    visible: false,
    days: 365,
    loading: false,
});

const stats = reactive({
    total: 0,
    active: 0,
    expired: 0,
});

const now = ref(Date.now());
let countdownTimer = null;

function daysUntilExpiry(dateStr) {
    if (!dateStr) return Infinity;
    const diff = new Date(dateStr).getTime() - now.value;
    return diff / (1000 * 60 * 60 * 24);
}

function expiryInfo(dateStr) {
    if (!dateStr) return { text: t('portal.lifetime'), class: '', urgent: false };
    const days = daysUntilExpiry(dateStr);
    if (days < 0) return { text: t('portal.expired_days', { n: Math.ceil(Math.abs(days)) }), class: 'expiry-overdue', urgent: true };
    if (days < 1) return { text: t('portal.expires_today'), class: 'expiry-urgent', urgent: true };
    const d = Math.ceil(days);
    if (d <= 3) return { text: t('portal.expires_in', { n: d }), class: 'expiry-urgent', urgent: true };
    if (d <= 7) return { text: t('portal.expires_in', { n: d }), class: 'expiry-warning', urgent: false };
    if (d <= 30) return { text: t('portal.expires_in', { n: d }), class: 'expiry-soon', urgent: false };
    return { text: dateStr, class: '', urgent: false };
}

function statusType(status) {
    const map = {
        pending: 'info', active: 'success', suspended: 'warning', frozen: 'warning',
        expired: 'info', revoked: 'danger', refunded: 'danger', blacklisted: 'danger',
    };
    return map[status] || 'info';
}
function statusLabel(status) {
    const map = {
        pending: t('portal.st_pending'),
        active: t('portal.st_active'),
        suspended: t('portal.st_suspended'),
        frozen: t('portal.st_frozen'),
        expired: t('portal.st_expired'),
        revoked: t('portal.st_revoked'),
        refunded: t('portal.st_refunded'),
        blacklisted: t('portal.st_blacklisted'),
    };
    return map[status] || status;
}

async function fetchLicenses() {
    loading.value = true;
    try {
        const params = {
            page: page.value,
            per_page: perPage.value,
            sort: '-created_at',
        };
        if (searchKey.value) {
            params.search = searchKey.value;
        }
        const { data: res } = await licenseApi.list(params);
        licenses.value = res.data || [];
        total.value = res.meta?.total || res.data?.length || 0;

        const { data: statsRes } = await licenseApi.stats();
        const s = statsRes.data || {};
        stats.total = s.total || 0;
        stats.active = s.active || 0;
        stats.expired = s.expired || 0;
    } catch {
        ElMessage.error(t('portal.licenses_load_failed'));
    } finally {
        loading.value = false;
    }
}

async function handleBatchRenew() {
    if (selectedIds.value.length === 0) return;
    renewDialog.days = 365;
    renewDialog.visible = true;
}

async function confirmBatchRenew() {
    renewDialog.loading = true;
    try {
        const { data: res } = await licenseApi.batchOperation({
            license_ids: selectedIds.value,
            action: 'renew',
            payload: { days: renewDialog.days },
        });
        ElMessage.success(res.message || t('portal.batch_renew_ok'));
        renewDialog.visible = false;
        clearSelection();
        fetchLicenses();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('portal.batch_renew_fail'));
    } finally {
        renewDialog.loading = false;
    }
}

async function handleBatchActivate() {
    if (selectedIds.value.length === 0) return;
    try {
        await ElMessageBox.confirm(
            t('portal.batch_activate_confirm', { n: selectedIds.value.length }),
            t('portal.batch_activate'),
            {
                confirmButtonText: t('actions.confirm'),
                cancelButtonText: t('actions.cancel'),
                type: 'info',
            }
        );
        const { data: res } = await licenseApi.batchOperation({
            license_ids: selectedIds.value,
            action: 'activate',
        });
        ElMessage.success(res.message || t('portal.batch_activate_ok'));
        clearSelection();
        fetchLicenses();
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error(e.response?.data?.message || t('portal.batch_activate_fail'));
        }
    }
}

async function handleBatchExport() {
    try {
        const params = {};
        if (selectedIds.value.length > 0) {
            params.ids = selectedIds.value.join(',');
        }
        const res = await licenseApi.exportCsv(params);
        const blob = new Blob([res.data], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `licenses-export-${new Date().toISOString().slice(0, 10)}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        ElMessage.success(t('portal.export_ok'));
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('portal.export_fail'));
    }
}

onMounted(() => {
    countdownTimer = setInterval(() => { now.value = Date.now(); }, 60000);
    fetchLicenses();
});
onUnmounted(() => { if (countdownTimer) clearInterval(countdownTimer); });
</script>

<style scoped>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.page-header h2 { margin: 0; }

.header-actions {
    display: flex;
    gap: 8px;
}

.mb-4 { margin-bottom: 16px; }

.mini-stat {
    text-align: center;
    padding: 8px 0;
}

.mini-value {
    font-size: 28px;
    font-weight: 700;
    color: #303133;
}

.mini-label {
    font-size: 14px;
    color: #909399;
    margin-top: 4px;
}

.pagination-wrap {
    display: flex;
    justify-content: flex-end;
    padding: 16px 0 0;
}

.expiring-text { color: #e6a23c; font-weight: 500; }
.expiry-badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:12px; font-weight:600; white-space:nowrap; }
.expiry-overdue { background:#fef0f0; color:#f56c6c; }
.expiry-urgent { background:#fdf6ec; color:#e6a23c; animation:pulse 1.5s infinite; }
.expiry-warning { background:#fdf6ec; color:#e6a23c; }
.expiry-soon { background:#f0f9eb; color:#67c23a; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.6} }

.batch-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    margin-bottom: 12px;
    background: #f1f5f9;
    border: 1px solid #b3d8ff;
    border-radius: 6px;
    font-size: 14px;
}
.batch-info {
    font-weight: 600;
    color: #0f172a;
}
</style>
