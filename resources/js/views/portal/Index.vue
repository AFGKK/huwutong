<template>
    <div class="portal-dashboard">
        <div class="page-header">
            <div>
                <h2>{{ $t('portal.dash_title') }}</h2>
                <p class="text-muted">{{ $t('portal.dash_welcome', { name: authStore.userName }) }}</p>
            </div>
            <el-button type="primary" @click="refreshAll" :loading="loading" :icon="Refresh">
                {{ $t('portal.refresh') }}
            </el-button>
        </div>

        <!-- 个性化推荐模块 (M3-80) -->
        <PersonalizedSection @loaded="onPersonalizationLoaded" />

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6" v-for="card in statCards" :key="card.label">
                <el-card shadow="hover" class="stat-card" @click="card.action">
                    <div class="stat-content">
                        <div class="stat-info">
                            <div class="stat-value" :style="{ color: card.color }">{{ card.value }}</div>
                            <div class="stat-label">{{ card.label }}</div>
                        </div>
                        <el-icon :size="36" :color="card.color">
                            <component :is="card.icon" />
                        </el-icon>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="16">
            <!-- 左侧：我的 License 列表 -->
            <el-col :span="14">
                <el-card class="mb-4">
                    <template #header>
                        <div class="card-header">
                            <span>{{ $t('portal.my_licenses') }}</span>
                            <el-link type="primary" :underline="'never'" @click="$router.push('/portal/licenses')">{{ $t('portal.view_all') }}</el-link>
                        </div>
                    </template>
                    <el-table v-if="licenses.length" :data="licenses" stripe style="width: 100%" v-loading="loading">
                        <el-table-column prop="license_key" label="License Key" min-width="160">
                            <template #default="{ row }">
                                <el-link type="primary" :underline="'never'" @click="$router.push(`/portal/licenses/${row.id}`)">
                                    <code class="small-text">{{ row.license_key }}</code>
                                </el-link>
                            </template>
                        </el-table-column>
                        <el-table-column prop="product?.name" :label="$t('portal.product')" width="100">
                            <template #default="{ row }">{{ row.product?.name || '-' }}</template>
                        </el-table-column>
                        <el-table-column prop="status" :label="$t('portal.status')" width="90">
                            <template #default="{ row }">
                                <el-tag :type="statusType(row.status)" size="small" effect="dark">
                                    {{ statusLabel(row.status) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="expires_at" :label="$t('portal.expires_at')" width="180">
                            <template #default="{ row }">
                                <span v-if="row.expires_at" :class="'expiry-badge ' + expiryClass(row.expires_at)">
                                    {{ expiryText(row.expires_at) }}
                                </span>
                                <span v-else>{{ $t('portal.lifetime') }}</span>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-else-if="!loading" :description="$t('portal.no_licenses')" />
                </el-card>

                <!-- 最近设备 -->
                <el-card>
                    <template #header>
                        <div class="card-header">
                            <span>{{ $t('portal.recent_devices') }}</span>
                            <el-link type="primary" :underline="'never'" @click="$router.push('/portal/devices')">{{ $t('portal.view_all') }}</el-link>
                        </div>
                    </template>
                    <el-table v-if="devices.length" :data="devices" stripe style="width: 100%">
                        <el-table-column prop="name" :label="$t('portal.device_name')" min-width="120">
                            <template #default="{ row }">{{ row.name || row.hostname || $t('portal.unknown_device') }}</template>
                        </el-table-column>
                        <el-table-column prop="fingerprint" :label="$t('portal.fingerprint')" width="120">
                            <template #default="{ row }"><code class="small-text">{{ row.fingerprint?.substring(0, 12) }}...</code></template>
                        </el-table-column>
                        <el-table-column prop="last_seen_at" :label="$t('portal.last_seen')" width="160" />
                        <el-table-column prop="is_active" :label="$t('portal.status')" width="70">
                            <template #default="{ row }">
                                <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                                    {{ row.is_active ? $t('portal.online') : $t('portal.offline') }}
                                </el-tag>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-else-if="!loading" :description="$t('portal.no_devices')" />
                </el-card>
            </el-col>

            <!-- 右侧：状态分布 & 即将到期 -->
            <el-col :span="10">
                <!-- 状态分布 -->
                <el-card class="mb-4">
                    <template #header>
                        <span>{{ $t('portal.status_dist') }}</span>
                    </template>
                    <div v-if="Object.keys(licenseStats.by_status || {}).length" class="status-distribution">
                        <div v-for="(count, status) in licenseStats.by_status" :key="status" class="status-row">
                            <span class="status-name">
                                <el-tag :type="statusType(status)" size="small" effect="dark">
                                    {{ statusLabel(status) }}
                                </el-tag>
                            </span>
                            <el-progress
                                :percentage="calcPercent(count, licenseStats.total)"
                                :color="statusColor(status)"
                                :stroke-width="16"
                                :text-inside="true"
                            >
                                <span>{{ count }}</span>
                            </el-progress>
                        </div>
                    </div>
                    <el-empty v-else :description="$t('portal.no_data')" />
                </el-card>

                <!-- 即将到期 -->
                <el-card class="mb-4">
                    <template #header>
                        <div class="card-header">
                            <span>{{ $t('portal.expiring_soon') }}</span>
                            <el-tag v-if="licenseStats.expiring_soon" type="warning" size="small">
                                {{ $t('portal.count_n', { n: licenseStats.expiring_soon }) }}
                            </el-tag>
                        </div>
                    </template>
                    <el-table v-if="expiringLicenses.length" :data="expiringLicenses" stripe>
                        <el-table-column prop="license_key" label="License" minWidth="140">
                            <template #default="{ row }">
                                <el-link type="primary" :underline="'never'" @click="$router.push(`/portal/licenses/${row.id}`)">
                                    <code class="small-text">{{ row.license_key }}</code>
                                </el-link>
                            </template>
                        </el-table-column>
                        <el-table-column prop="product?.name" :label="$t('portal.product')" width="80" />
                        <el-table-column prop="expires_at" :label="$t('portal.expires_at')" width="150">
                            <template #default="{ row }">
                                <span v-if="row.expires_at" :class="'expiry-badge ' + expiryClass(row.expires_at)">
                                    {{ expiryText(row.expires_at) }}
                                </span>
                                <span v-else>{{ $t('portal.lifetime') }}</span>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-else :description="$t('portal.no_expiring')" />
                </el-card>

                <!-- 快捷入口 -->
                <el-card>
                    <template #header>
                        <span>{{ $t('portal.shortcuts') }}</span>
                    </template>
                    <el-row :gutter="12">
                        <el-col :span="12" class="mb-2">
                            <el-button class="action-btn" @click="$router.push('/portal/licenses')">
                                <el-icon><Key /></el-icon> {{ $t('portal.view_licenses') }}
                            </el-button>
                        </el-col>
                        <el-col :span="12" class="mb-2">
                            <el-button class="action-btn" @click="$router.push('/portal/devices')">
                                <el-icon><Monitor /></el-icon> {{ $t('portal.manage_devices') }}
                            </el-button>
                        </el-col>
                    </el-row>
                    <el-row :gutter="12">
                        <el-col :span="12" class="mb-2">
                            <el-button class="action-btn" @click="$router.push('/portal/billing')">
                                <el-icon><Goods /></el-icon> {{ $t('portal.billing_invoices') }}
                            </el-button>
                        </el-col>
                        <el-col :span="12" class="mb-2">
                            <el-button class="action-btn" @click="$router.push('/portal/settings')">
                                <el-icon><Setting /></el-icon> {{ $t('portal.settings') }}
                            </el-button>
                        </el-col>
                    </el-row>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { ref, reactive, markRaw, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import licenseApi from '@/api/license';
import deviceApi from '@/api/device';
import customerApi from '@/api/customer';
import { ElMessage } from 'element-plus';
import PersonalizedSection from './PersonalizedSection.vue';
import {
    Key, Monitor, Goods, Setting, Refresh, Odometer,
} from '@element-plus/icons-vue';

const router = useRouter();
const authStore = useAuthStore();
const { t } = useI18n();

const loading = ref(false);
const licenses = ref([]);
const expiringLicenses = ref([]);
const devices = ref([]);
const deviceCount = ref(0);

const licenseStats = reactive({
    total: 0,
    active: 0,
    expired: 0,
    expiring_soon: 0,
    by_status: {},
    by_type: {},
});

const statCards = computed(() => [
    { label: t('portal.stat_all'), value: String(licenseStats.total || 0), icon: markRaw(Key), color: '#0f172a', action: () => router.push('/portal/licenses') },
    { label: t('portal.stat_active'), value: String(licenseStats.active || 0), icon: markRaw(Odometer), color: '#67c23a', action: () => router.push('/portal/licenses') },
    { label: t('portal.stat_expired'), value: String(licenseStats.expired || 0), icon: markRaw(Key), color: '#f56c6c', action: () => router.push('/portal/licenses') },
    { label: t('portal.stat_devices'), value: String(deviceCount.value || 0), icon: markRaw(Monitor), color: '#e6a23c', action: () => router.push('/portal/devices') },
]);

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

function statusColor(status) {
    const map = {
        pending: '#909399', active: '#67c23a', suspended: '#e6a23c',
        frozen: '#e6a23c', expired: '#909399', revoked: '#f56c6c',
        refunded: '#f56c6c', blacklisted: '#f56c6c',
    };
    return map[status] || '#0f172a';
}

function calcPercent(count, total) {
    if (!total) return 0;
    return Math.round((count / total) * 100);
}

function isExpiring(dateStr) {
    if (!dateStr) return false;
    const diff = new Date(dateStr) - new Date();
    const days = diff / (1000 * 60 * 60 * 24);
    return days <= 30 && days >= 0;
}

function daysUntil(dateStr) {
    if (!dateStr) return Infinity;
    return (new Date(dateStr).getTime() - Date.now()) / (1000 * 60 * 60 * 24);
}

function expiryClass(dateStr) {
    if (!dateStr) return '';
    const d = daysUntil(dateStr);
    if (d < 0) return 'expiry-overdue';
    if (d < 1) return 'expiry-urgent';
    const cd = Math.ceil(d);
    if (cd <= 3) return 'expiry-urgent';
    if (cd <= 7) return 'expiry-warning';
    if (cd <= 30) return 'expiry-soon';
    return '';
}

function expiryText(dateStr) {
    if (!dateStr) return t('portal.lifetime');
    const d = daysUntil(dateStr);
    if (d < 0) return t('portal.expired_days', { n: Math.ceil(Math.abs(d)) });
    if (d < 1) return t('portal.expires_today');
    const cd = Math.ceil(d);
    if (cd <= 30) return t('portal.expires_in', { n: cd });
    return dateStr;
}

async function refreshAll() {
    loading.value = true;
    try {
        // License 统计
        const { data: statsRes } = await licenseApi.stats();
        const stats = statsRes.data || {};
        licenseStats.total = stats.total || 0;
        licenseStats.active = stats.active || 0;
        licenseStats.expired = stats.expired || 0;
        licenseStats.expiring_soon = stats.expiring_soon || 0;
        licenseStats.by_status = stats.by_status || {};
        licenseStats.by_type = stats.by_type || {};

        // 我的 License 列表
        const { data: listRes } = await licenseApi.list({ per_page: 5, sort: '-created_at' });
        licenses.value = listRes.data?.data || [];

        // 即将到期
        const { data: expRes } = await licenseApi.list({ per_page: 5, expiring: true, sort: 'expires_at' });
        expiringLicenses.value = expRes.data?.data || [];

        // 设备统计 & 最近设备
        const { data: devStatsRes } = await deviceApi.stats();
        deviceCount.value = Number(devStatsRes.data?.active ?? devStatsRes.data?.total ?? 0);

        const { data: devRes } = await deviceApi.list({ per_page: 5, sort: '-last_seen_at' });
        devices.value = devRes.data?.data || [];
    } catch (e) {
        ElMessage.error(t('portal.load_failed'));
    } finally {
        loading.value = false;
    }
}

function onPersonalizationLoaded(data) {
    // 个性化数据已加载，可在此处记录或扩展
}

onMounted(refreshAll);
</script>

<style scoped>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}

.page-header h2 {
    margin: 0 0 4px;
}

.text-muted {
    color: #909399;
    font-size: 14px;
    margin: 0;
}

.mb-4 { margin-bottom: 16px; }
.mb-2 { margin-bottom: 8px; }
.mt-2 { margin-top: 8px; }

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.stat-card {
    cursor: pointer;
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.stat-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.stat-value {
    font-size: 30px;
    font-weight: 700;
}

.stat-label {
    font-size: 14px;
    color: #909399;
    margin-top: 4px;
}

.status-distribution {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.status-row {
    display: flex;
    align-items: center;
    gap: 12px;
}

.status-name {
    min-width: 80px;
}

.small-text { font-size: 11px; }
.expiring-text { color: #e6a23c; font-weight: 500; }
.expiry-badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:12px; font-weight:600; white-space:nowrap; }
.expiry-overdue { background:#fef0f0; color:#f56c6c; }
.expiry-urgent { background:#fdf6ec; color:#e6a23c; animation:pulse 1.5s infinite; }
.expiry-warning { background:#fdf6ec; color:#e6a23c; }
.expiry-soon { background:#f0f9eb; color:#67c23a; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.6} }

.action-btn {
    width: 100%;
}
</style>
