<template>
    <div class="dashboard">
        <div class="page-header">
            <h2>仪表盘</h2>
            <el-date-picker
                v-model="dateRange"
                type="datetimerange"
                range-separator="至"
                start-placeholder="开始"
                end-placeholder="结束"
                value-format="YYYY-MM-DD HH:mm:ss"
                @change="refreshAll"
                style="width: 280px"
            />
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6" v-for="stat in statCards" :key="stat.label">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-content">
                        <div class="stat-info">
                            <div class="stat-value" :style="{ color: stat.color }">{{ stat.value }}</div>
                            <div class="stat-label">{{ stat.label }}</div>
                        </div>
                        <el-icon :size="40" :color="stat.color">
                            <component :is="stat.icon" />
                        </el-icon>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="16">
            <!-- 最近 License -->
            <el-col :span="12">
                <el-card class="mb-4">
                    <template #header>
                        <div class="card-header">
                            <span>最近 License</span>
                            <el-link type="primary" :underline="false" @click="$router.push('/licenses')">查看全部</el-link>
                        </div>
                    </template>
                    <el-table v-if="recentLicenses.length" :data="recentLicenses" stripe style="width: 100%">
                        <el-table-column prop="license_key" label="License Key" min-width="180">
                            <template #default="{ row }">
                                <el-link type="primary" :underline="false" @click="$router.push(`/licenses/${row.id}`)">
                                    <code>{{ row.license_key }}</code>
                                </el-link>
                            </template>
                        </el-table-column>
                        <el-table-column prop="status" label="状态" width="100">
                            <template #default="{ row }">
                                <el-tag :type="statusType(row.status)" size="small" effect="dark">
                                    {{ statusLabel(row.status) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="created_at" label="创建时间" width="170" />
                    </el-table>
                    <el-empty v-else description="暂无 License 数据" />
                </el-card>

                <!-- 状态分布 -->
                <el-card>
                    <template #header>
                        <span>License 状态分布</span>
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
                    <el-empty v-else description="暂无数据" />
                </el-card>
            </el-col>

            <!-- 右侧 -->
            <el-col :span="12">
                <!-- 快速操作 -->
                <el-card class="mb-4">
                    <template #header>
                        <span>快速操作</span>
                    </template>
                    <div class="quick-actions">
                        <el-row :gutter="12">
                            <el-col :span="12">
                                <el-button type="primary" class="action-btn" @click="$router.push('/licenses')">
                                    <el-icon><Plus /></el-icon> 创建 License
                                </el-button>
                            </el-col>
                            <el-col :span="12">
                                <el-button class="action-btn" @click="$router.push('/customers')">
                                    <el-icon><User /></el-icon> 管理客户
                                </el-button>
                            </el-col>
                        </el-row>
                        <el-row :gutter="12" class="mt-2">
                            <el-col :span="12">
                                <el-button class="action-btn" @click="$router.push('/products')">
                                    <el-icon><Goods /></el-icon> 产品管理
                                </el-button>
                            </el-col>
                            <el-col :span="12">
                                <el-button class="action-btn" @click="$router.push('/mfa')">
                                    <el-icon><Lock /></el-icon> MFA 设置
                                </el-button>
                            </el-col>
                        </el-row>
                        <el-row :gutter="12" class="mt-2">
                            <el-col :span="12">
                                <el-button class="action-btn" @click="$router.push('/billing')">
                                    <el-icon><Coin /></el-icon> 订阅计费
                                </el-button>
                            </el-col>
                            <el-col :span="12">
                                <el-button class="action-btn" @click="$router.push('/health')">
                                    <el-icon><Monitor /></el-icon> 系统健康
                                </el-button>
                            </el-col>
                        </el-row>
                    </div>
                </el-card>

                <!-- 即将到期的 License -->
                <el-card>
                    <template #header>
                        <div class="card-header">
                            <span>即将到期 (30天内)</span>
                            <el-tag v-if="licenseStats.expiring_soon" type="warning" size="small">
                                {{ licenseStats.expiring_soon }} 个
                            </el-tag>
                        </div>
                    </template>
                    <el-table v-if="expiringLicenses.length" :data="expiringLicenses" stripe>
                        <el-table-column prop="license_key" label="License" min-width="160">
                            <template #default="{ row }">
                                <el-link type="primary" :underline="false" @click="$router.push(`/licenses/${row.id}`)">
                                    <code class="small-text">{{ row.license_key }}</code>
                                </el-link>
                            </template>
                        </el-table-column>
                        <el-table-column prop="expires_at" label="到期时间" width="170">
                            <template #default="{ row }">
                                <span class="expiring-text">{{ row.expires_at }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="customer?.name" label="客户" width="120" :formatter="(r) => r.customer?.name || '-'" />
                    </el-table>
                    <el-empty v-else description="暂无即将到期的 License" />
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import licenseApi from '@/api/license';
import { ElMessage } from 'element-plus';
import {
    Key, Plus, User, Goods, Lock, Monitor, Coin, Odometer,
} from '@element-plus/icons-vue';

const loading = ref(false);
const dateRange = ref(null);
const recentLicenses = ref([]);
const expiringLicenses = ref([]);
const licenseStats = reactive({
    total: 0,
    active: 0,
    expired: 0,
    expiring_soon: 0,
    by_status: {},
    by_type: {},
});
const customerCount = ref(0);

const statCards = reactive([
    { label: '全部 License', value: '0', icon: Key, color: '#409eff' },
    { label: '活跃中', value: '0', icon: Odometer, color: '#67c23a' },
    { label: '30天内到期', value: '0', icon: Coin, color: '#e6a23c' },
    { label: '已过期', value: '0', icon: Key, color: '#f56c6c' },
]);

const STATUS_MAP = {
    pending: { type: 'info', label: '待激活' },
    active: { type: 'success', label: '活跃' },
    suspended: { type: 'warning', label: '已暂停' },
    frozen: { type: 'warning', label: '已冻结' },
    expired: { type: 'info', label: '已过期' },
    revoked: { type: 'danger', label: '已吊销' },
    refunded: { type: 'danger', label: '已退款' },
    blacklisted: { type: 'danger', label: '黑名单' },
};

function statusType(status) { return STATUS_MAP[status]?.type || 'info'; }
function statusLabel(status) { return STATUS_MAP[status]?.label || status; }

function statusColor(status) {
    const map = {
        pending: '#909399', active: '#67c23a', suspended: '#e6a23c',
        frozen: '#e6a23c', expired: '#909399', revoked: '#f56c6c',
        refunded: '#f56c6c', blacklisted: '#f56c6c',
    };
    return map[status] || '#409eff';
}

function calcPercent(count, total) {
    if (!total) return 0;
    return Math.round((count / total) * 100);
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

        statCards[0].value = String(stats.total || 0);
        statCards[1].value = String(stats.active || 0);
        statCards[2].value = String(stats.expiring_soon || 0);
        statCards[3].value = String(stats.expired || 0);

        // 最近 License
        const params = { per_page: 8, sort: '-created_at' };
        const { data: listRes } = await licenseApi.list(params);
        recentLicenses.value = listRes.data?.data || [];
    } catch {
        ElMessage.error('获取数据失败');
    } finally {
        loading.value = false;
    }
}

onMounted(refreshAll);
</script>

<style scoped>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.page-header h2 { margin: 0; }
.mb-4 { margin-bottom: 16px; }
.mt-2 { margin-top: 8px; }

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.stat-card {
    cursor: default;
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
    font-size: 32px;
    font-weight: 700;
}
.stat-label {
    font-size: 14px;
    color: #909399;
    margin-top: 4px;
}

.quick-actions .action-btn {
    width: 100%;
    margin-bottom: 0;
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
</style>