<template>
    <div class="renewal-page">
        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card" @click="activeTab = 'expiring'; filterDays = 7">
                        <div class="stat-value warning">{{ stats.licenses?.expiring_7d ?? '-' }}</div>
                        <div class="stat-label">7 天内到期</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card" @click="activeTab = 'expiring'; filterDays = 14">
                        <div class="stat-value" :class="(stats.licenses?.expiring_14d ?? 0) > 0 ? 'warning' : ''">
                            {{ stats.licenses?.expiring_14d ?? '-' }}
                        </div>
                        <div class="stat-label">14 天内到期</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card" @click="activeTab = 'expiring'; filterDays = 30">
                        <div class="stat-value">{{ stats.licenses?.expiring_30d ?? '-' }}</div>
                        <div class="stat-label">30 天内到期</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card" @click="activeTab = 'expired'">
                        <div class="stat-value danger">{{ stats.licenses?.expired ?? '-' }}</div>
                        <div class="stat-label">已过期</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card">
                        <div class="stat-value info">{{ stats.licenses?.expired_30d ?? '-' }}</div>
                        <div class="stat-label">近30天过期</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card">
                        <div class="stat-value">{{ stats.subscriptions?.expiring_7d ?? '-' }}</div>
                        <div class="stat-label">订阅近7天到期</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card">
                        <div class="stat-value">{{ stats.subscriptions?.expiring_30d ?? '-' }}</div>
                        <div class="stat-label">订阅近30天到期</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card">
                        <div class="stat-value money">¥{{ formatMoney(stats.estimated_renewal_amount) }}</div>
                        <div class="stat-label">预估续期金额</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Tabs: 即将到期 | 已过期 | 操作日志 -->
        <el-card shadow="never">
            <el-tabs v-model="activeTab" @tab-change="handleTabChange">
                <!-- 即将到期 -->
                <el-tab-pane label="即将到期" name="expiring">
                    <div class="toolbar">
                        <el-form :inline="true" size="small">
                            <el-form-item>
                                <el-radio-group v-model="filterDays" @change="fetchExpiring">
                                    <el-radio-button :value="7">7天</el-radio-button>
                                    <el-radio-button :value="14">14天</el-radio-button>
                                    <el-radio-button :value="30">30天</el-radio-button>
                                    <el-radio-button :value="60">60天</el-radio-button>
                                </el-radio-group>
                            </el-form-item>
                            <el-form-item>
                                <el-input
                                    v-model="expiringSearch"
                                    placeholder="搜索 License Key / 客户 / 产品"
                                    clearable
                                    @clear="fetchExpiring"
                                    @keyup.enter="fetchExpiring"
                                    style="width: 260px"
                                >
                                    <template #prefix><el-icon><Search /></el-icon></template>
                                </el-input>
                            </el-form-item>
                            <el-form-item>
                                <el-button type="primary" @click="fetchExpiring"><el-icon><Search /></el-icon> 查询</el-button>
                            </el-form-item>
                        </el-form>
                        <div>
                            <el-button
                                type="warning"
                                :disabled="!selectedExpiring.length"
                                @click="showBatchRenew = true"
                            >
                                <el-icon><Refresh /></el-icon> 批量续期 ({{ selectedExpiring.length }})
                            </el-button>
                        </div>
                    </div>

                    <el-table
                        :data="expiringLicenses"
                        v-loading="loading"
                        stripe
                        @selection-change="(val) => selectedExpiring = val.map(v => v.id)"
                    >
                        <el-table-column type="selection" width="40" />
                        <el-table-column prop="license_key" label="License Key" min-width="180" />
                        <el-table-column label="客户" min-width="150">
                            <template #default="{ row }">{{ row.customer?.name || '-' }}</template>
                        </el-table-column>
                        <el-table-column label="产品" width="120">
                            <template #default="{ row }">{{ row.product?.name || '-' }}</template>
                        </el-table-column>
                        <el-table-column prop="seats" label="席位" width="60" align="center" />
                        <el-table-column label="到期时间" width="170" sortable="custom">
                            <template #default="{ row }">{{ row.expires_at || '-' }}</template>
                        </el-table-column>
                        <el-table-column label="剩余天数" width="100">
                            <template #default="{ row }">
                                <el-tag :type="getExpiryTagType(row)" size="small">
                                    {{ row.days_until_expiry ?? '-' }} 天
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="150" fixed="right">
                            <template #default="{ row }">
                                <el-button text size="small" type="primary" @click="openRenewDialog(row)">续期</el-button>
                                <el-button text size="small" type="primary" @click="viewLicense(row)">查看</el-button>
                            </template>
                        </el-table-column>
                    </el-table>

                    <div class="pagination-wrap">
                        <el-pagination
                            v-model:current-page="expiringPage"
                            v-model:page-size="expiringPerPage"
                            :total="expiringTotal"
                            :page-sizes="[10, 20, 50]"
                            layout="total, sizes, prev, pager, next"
                            @change="fetchExpiring"
                        />
                    </div>
                </el-tab-pane>

                <!-- 已过期 -->
                <el-tab-pane label="已过期" name="expired">
                    <div class="toolbar">
                        <el-form :inline="true" size="small">
                            <el-form-item>
                                <el-select v-model="expiredDaysAgo" placeholder="时间范围" @change="fetchExpired" style="width: 140px">
                                    <el-option label="近7天" :value="7" />
                                    <el-option label="近30天" :value="30" />
                                    <el-option label="近90天" :value="90" />
                                    <el-option label="全部" :value="0" />
                                </el-select>
                            </el-form-item>
                            <el-form-item>
                                <el-input
                                    v-model="expiredSearch"
                                    placeholder="搜索 License Key / 客户"
                                    clearable
                                    @clear="fetchExpired"
                                    @keyup.enter="fetchExpired"
                                    style="width: 260px"
                                >
                                    <template #prefix><el-icon><Search /></el-icon></template>
                                </el-input>
                            </el-form-item>
                            <el-form-item>
                                <el-button type="primary" @click="fetchExpired"><el-icon><Search /></el-icon> 查询</el-button>
                            </el-form-item>
                        </el-form>
                    </div>

                    <el-table :data="expiredLicenses" v-loading="loading" stripe>
                        <el-table-column prop="license_key" label="License Key" min-width="180" />
                        <el-table-column label="客户" min-width="150">
                            <template #default="{ row }">{{ row.customer?.name || '-' }}</template>
                        </el-table-column>
                        <el-table-column label="产品" width="120">
                            <template #default="{ row }">{{ row.product?.name || '-' }}</template>
                        </el-table-column>
                        <el-table-column prop="expires_at" label="过期时间" width="170" />
                        <el-table-column label="状态" width="90">
                            <template #default="{ row }">
                                <el-tag type="danger" size="small">已过期</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="120">
                            <template #default="{ row }">
                                <el-button text size="small" type="primary" @click="openRenewDialog(row)">续期</el-button>
                            </template>
                        </el-table-column>
                    </el-table>

                    <div class="pagination-wrap">
                        <el-pagination
                            v-model:current-page="expiredPage"
                            v-model:page-size="expiredPerPage"
                            :total="expiredTotal"
                            :page-sizes="[10, 20, 50]"
                            layout="total, sizes, prev, pager, next"
                            @change="fetchExpired"
                        />
                    </div>
                </el-tab-pane>

                <!-- 操作日志 -->
                <el-tab-pane label="续期日志" name="log">
                    <el-table :data="activityLogs" v-loading="activityLoading" stripe>
                        <el-table-column prop="created_at" label="时间" width="170" />
                        <el-table-column prop="action" label="操作类型" width="100" />
                        <el-table-column prop="description" label="描述" min-width="300" />
                        <el-table-column label="详情" width="200">
                            <template #default="{ row }">
                                <template v-if="row.properties">
                                    <el-tag size="small" type="info">+{{ row.properties.days_added }}天</el-tag>
                                    <span class="ml-1" v-if="row.properties.old_expires_at">
                                        {{ row.properties.old_expires_at }} → {{ row.properties.new_expires_at }}
                                    </span>
                                </template>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>
            </el-tabs>
        </el-card>

        <!-- 单个续期对话框 -->
        <el-dialog v-model="showRenewDialog" title="License 续期" width="400px">
            <div v-if="renewTarget" class="mb-4">
                <p><strong>License:</strong> {{ renewTarget.license_key }}</p>
                <p><strong>客户:</strong> {{ renewTarget.customer?.name || '-' }}</p>
                <p><strong>当前到期:</strong> {{ renewTarget.expires_at || '永久' }}</p>
            </div>
            <el-form label-width="100px">
                <el-form-item label="续期天数">
                    <el-input-number v-model="renewDays" :min="1" :max="3650" :step="30" style="width: 200px" />
                </el-form-item>
                <el-form-item label="发送通知">
                    <el-switch v-model="renewNotify" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showRenewDialog = false">取消</el-button>
                <el-button type="primary" :loading="renewing" @click="confirmRenew">确认续期</el-button>
            </template>
        </el-dialog>

        <!-- 批量续期对话框 -->
        <el-dialog v-model="showBatchRenew" title="批量续期" width="400px">
            <p class="mb-4">已选择 <strong>{{ selectedExpiring.length }}</strong> 个 License</p>
            <el-form label-width="100px">
                <el-form-item label="续期天数">
                    <el-input-number v-model="batchRenewDays" :min="1" :max="3650" :step="30" style="width: 200px" />
                </el-form-item>
                <el-form-item label="发送通知">
                    <el-switch v-model="batchRenewNotify" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showBatchRenew = false">取消</el-button>
                <el-button type="primary" :loading="renewing" @click="confirmBatchRenew">确认批量续期</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Search, Refresh } from '@element-plus/icons-vue';
import renewalApi from '@/api/renewal';

// ─── 统计 ───
const stats = reactive({
    licenses: {},
    subscriptions: {},
    estimated_renewal_amount: 0,
});

// ─── Tabs ───
const activeTab = ref('expiring');

// ─── 即将到期 ───
const expiringLicenses = ref([]);
const loading = ref(false);
const expiringPage = ref(1);
const expiringPerPage = ref(20);
const expiringTotal = ref(0);
const expiringSearch = ref('');
const filterDays = ref(30);
const selectedExpiring = ref([]);

async function fetchExpiring() {
    loading.value = true;
    try {
        const res = await renewalApi.expiringLicenses({
            days: filterDays.value,
            search: expiringSearch.value || undefined,
            page: expiringPage.value,
            per_page: expiringPerPage.value,
        });
        expiringLicenses.value = res.data?.data || [];
        expiringTotal.value = res.data?.meta?.total || 0;
    } catch {
        ElMessage.error('获取即将到期 License 列表失败');
    } finally {
        loading.value = false;
    }
}

// ─── 已过期 ───
const expiredLicenses = ref([]);
const expiredPage = ref(1);
const expiredPerPage = ref(20);
const expiredTotal = ref(0);
const expiredSearch = ref('');
const expiredDaysAgo = ref(30);

async function fetchExpired() {
    loading.value = true;
    try {
        const res = await renewalApi.expiredLicenses({
            days_ago: expiredDaysAgo.value,
            search: expiredSearch.value || undefined,
            page: expiredPage.value,
            per_page: expiredPerPage.value,
        });
        expiredLicenses.value = res.data?.data || [];
        expiredTotal.value = res.data?.meta?.total || 0;
    } catch {
        ElMessage.error('获取已过期 License 列表失败');
    } finally {
        loading.value = false;
    }
}

// ─── 操作日志 ───
const activityLogs = ref([]);
const activityLoading = ref(false);

async function fetchActivityLog() {
    activityLoading.value = true;
    try {
        const res = await renewalApi.activityLog();
        activityLogs.value = res.data?.data || [];
    } catch {
        // silent
    } finally {
        activityLoading.value = false;
    }
}

function handleTabChange() {
    if (activeTab.value === 'expiring') fetchExpiring();
    else if (activeTab.value === 'expired') fetchExpired();
    else if (activeTab.value === 'log') fetchActivityLog();
}

// ─── 单个续期 ───
const showRenewDialog = ref(false);
const renewTarget = ref(null);
const renewDays = ref(365);
const renewNotify = ref(true);
const renewing = ref(false);

function openRenewDialog(license) {
    renewTarget.value = license;
    renewDays.value = 365;
    renewNotify.value = true;
    showRenewDialog.value = true;
}

async function confirmRenew() {
    renewing.value = true;
    try {
        await renewalApi.renew(renewTarget.value.id, {
            days: renewDays.value,
            notify: renewNotify.value,
        });
        ElMessage.success(`License 已续期 ${renewDays.value} 天`);
        showRenewDialog.value = false;
        await fetchExpiring();
        await fetchStats();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || '续期失败');
    } finally {
        renewing.value = false;
    }
}

// ─── 批量续期 ───
const showBatchRenew = ref(false);
const batchRenewDays = ref(365);
const batchRenewNotify = ref(true);

async function confirmBatchRenew() {
    renewing.value = true;
    try {
        const res = await renewalApi.batchRenew({
            license_ids: selectedExpiring.value,
            days: batchRenewDays.value,
            notify: batchRenewNotify.value,
        });
        ElMessage.success(res.data?.message || '批量续期成功');
        showBatchRenew.value = false;
        selectedExpiring.value = [];
        await fetchExpiring();
        await fetchStats();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || '批量续期失败');
    } finally {
        renewing.value = false;
    }
}

// ─── 辅助 ───
function getExpiryTagType(row) {
    if (!row.days_until_expiry) return 'info';
    if (row.days_until_expiry <= 7) return 'danger';
    if (row.days_until_expiry <= 14) return 'warning';
    if (row.days_until_expiry <= 30) return '';
    return 'info';
}

function formatMoney(val) {
    if (val === null || val === undefined) return '-';
    return Number(val).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function viewLicense(row) {
    // 打开新窗口查看 License 详情
    const url = `/admin/licenses/${row.id}`;
    window.open(url, '_blank');
}

async function fetchStats() {
    try {
        const res = await renewalApi.stats();
        Object.assign(stats, res.data?.data || {});
    } catch {
        // silent
    }
}

onMounted(async () => {
    await fetchStats();
    await fetchExpiring();
});
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
.ml-1 { margin-left: 4px; }

.stat-card {
    text-align: center; cursor: pointer; transition: transform 0.1s;
}
.stat-card:hover { transform: translateY(-2px); }
.stat-value { font-size: 28px; font-weight: 700; color: var(--el-text-color-primary); }
.stat-value.warning { color: var(--el-color-warning); }
.stat-value.danger { color: var(--el-color-danger); }
.stat-value.info { color: var(--el-color-info); }
.stat-value.money { color: var(--el-color-success); }
.stat-label { font-size: 13px; color: var(--el-text-color-secondary); margin-top: 4px; }

.toolbar {
    display: flex; justify-content: space-between; align-items: center;
    flex-wrap: wrap; gap: 12px; margin-bottom: 16px;
}

.pagination-wrap { display: flex; justify-content: center; margin-top: 16px; }
</style>
