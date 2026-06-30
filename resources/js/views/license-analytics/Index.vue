<template>
    <div class="license-analytics-page">
        <!-- 页面标题 + 操作 -->
        <div class="page-header">
            <h2>License 分析引擎</h2>
            <div class="header-actions">
                <el-button @click="detectViolations" :loading="detecting" type="warning">
                    <el-icon><WarnTriangleFilled /></el-icon> 运行违规检测
                </el-button>
                <el-button @click="backfillData" :loading="backfilling">
                    <el-icon><Refresh /></el-icon> 回填历史数据
                </el-button>
                <el-button @click="refreshAll" :loading="loading" type="primary">
                    <el-icon><Refresh /></el-icon> 刷新
                </el-button>
            </div>
        </div>

        <!-- 关键指标卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="3">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ dashboard.total_licenses }}</div>
                    <div class="stat-label">License 总数</div>
                </el-card>
            </el-col>
            <el-col :span="3">
                <el-card shadow="hover" class="stat-card stat-active">
                    <div class="stat-value">{{ dashboard.active_licenses }}</div>
                    <div class="stat-label">活跃中</div>
                </el-card>
            </el-col>
            <el-col :span="3">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ dashboard.total_activations }}</div>
                    <div class="stat-label">激活次数</div>
                </el-card>
            </el-col>
            <el-col :span="3">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ dashboard.total_devices }}</div>
                    <div class="stat-label">设备总数</div>
                </el-card>
            </el-col>
            <el-col :span="3">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ dashboard.total_events }}</div>
                    <div class="stat-label">分析事件</div>
                </el-card>
            </el-col>
            <el-col :span="3">
                <el-card shadow="hover" class="stat-card stat-danger">
                    <div class="stat-value">{{ dashboard.total_violations }}</div>
                    <div class="stat-label">违规事件</div>
                </el-card>
            </el-col>
            <el-col :span="3">
                <el-card shadow="hover" class="stat-card stat-warning">
                    <div class="stat-value">{{ dashboard.blacklisted_devices }}</div>
                    <div class="stat-label">黑名单设备</div>
                </el-card>
            </el-col>
            <el-col :span="3">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ dashboard.utilization?.avg_seat_utilization ?? 0 }}%</div>
                    <div class="stat-label">座位利用率</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Tabs -->
        <el-tabs v-model="activeTab" type="border-card">
            <!-- ===== 1. 地理分布 ===== -->
            <el-tab-pane label="地理分布" name="geo">
                <div v-loading="loading">
                    <el-row :gutter="16">
                        <el-col :span="16">
                            <el-card>
                                <template #header><span>国家/地区分布</span></template>
                                <el-table :data="geoData.countries" stripe style="width: 100%" max-height="500">
                                    <el-table-column type="index" label="#" width="50" />
                                    <el-table-column prop="country_name" label="国家/地区" min-width="180" />
                                    <el-table-column prop="country_code" label="代码" width="80" />
                                    <el-table-column prop="count" label="事件数" width="120" sortable>
                                        <template #default="{ row }">
                                            <el-tag>{{ row.count }}</el-tag>
                                        </template>
                                    </el-table-column>
                                    <el-table-column label="占比" width="200">
                                        <template #default="{ row }">
                                            <el-progress
                                                :percentage="geoData.total > 0 ? Math.round(row.count / geoData.total * 100) : 0"
                                                :stroke-width="16"
                                                :text-inside="true"
                                            />
                                        </template>
                                    </el-table-column>
                                    <el-table-column label="操作" width="100">
                                        <template #default="{ row }">
                                            <el-button size="small" @click="viewGeoDetail(row.country_code)">详情</el-button>
                                        </template>
                                    </el-table-column>
                                </el-table>
                            </el-card>
                        </el-col>
                        <el-col :span="8">
                            <el-card>
                                <template #header><span>城市分布 (Top 20)</span></template>
                                <el-table :data="geoData.cities" stripe style="width: 100%" max-height="500">
                                    <el-table-column type="index" label="#" width="50" />
                                    <el-table-column prop="city" label="城市" min-width="120" />
                                    <el-table-column prop="country_code" label="国家" width="60" />
                                    <el-table-column prop="count" label="事件数" width="100" sortable />
                                </el-table>
                            </el-card>
                        </el-col>
                    </el-row>
                </div>
            </el-tab-pane>

            <!-- ===== 2. 激活趋势 ===== -->
            <el-tab-pane label="激活趋势" name="trend">
                <div v-loading="loading">
                    <el-row :gutter="16" class="mb-4">
                        <el-col :span="6">
                            <el-select v-model="trendDays" @change="fetchTrends" style="width: 200px">
                                <el-option label="近 7 天" :value="7" />
                                <el-option label="近 30 天" :value="30" />
                                <el-option label="近 90 天" :value="90" />
                                <el-option label="近 365 天" :value="365" />
                            </el-select>
                        </el-col>
                    </el-row>
                    <el-row :gutter="16">
                        <el-col :span="12">
                            <el-card>
                                <template #header><span>激活趋势</span></template>
                                <div style="height: 300px; overflow-y: auto">
                                    <el-table :data="activationTrend" stripe size="small" height="280">
                                        <el-table-column prop="date" label="日期" width="120" />
                                        <el-table-column prop="count" label="激活次数" sortable />
                                        <el-table-column label="趋势">
                                            <template #default="{ row, $index }">
                                                <el-progress
                                                    :percentage="maxTrendCount > 0 ? Math.round(row.count / maxTrendCount * 100) : 0"
                                                    :stroke-width="12"
                                                />
                                            </template>
                                        </el-table-column>
                                    </el-table>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="12">
                            <el-card>
                                <template #header><span>违规趋势</span></template>
                                <div style="height: 300px; overflow-y: auto">
                                    <el-table :data="violationTrend" stripe size="small" height="280">
                                        <el-table-column prop="date" label="日期" width="120" />
                                        <el-table-column prop="count" label="违规次数" sortable>
                                            <template #default="{ row }">
                                                <el-tag v-if="row.count > 0" type="danger">{{ row.count }}</el-tag>
                                                <span v-else>0</span>
                                            </template>
                                        </el-table-column>
                                        <el-table-column label="趋势">
                                            <template #default="{ row }">
                                                <el-progress
                                                    :percentage="maxViolationCount > 0 ? Math.round(row.count / maxViolationCount * 100) : 0"
                                                    :stroke-width="12"
                                                    status="exception"
                                                />
                                            </template>
                                        </el-table-column>
                                    </el-table>
                                </div>
                            </el-card>
                        </el-col>
                    </el-row>
                </div>
            </el-tab-pane>

            <!-- ===== 3. 使用饱和度 ===== -->
            <el-tab-pane label="使用饱和度" name="utilization">
                <div v-loading="loading">
                    <el-row :gutter="16">
                        <el-col :span="8">
                            <el-card>
                                <template #header><span>座位利用率</span></template>
                                <div style="text-align: center; padding: 30px 0">
                                    <el-progress type="dashboard" :percentage="dashboard.utilization?.avg_seat_utilization ?? 0" :width="180" />
                                    <p class="mt-2 text-gray-500">
                                        已用 {{ dashboard.utilization?.total_used_seats ?? 0 }} / 总计 {{ dashboard.utilization?.total_seats ?? 0 }} 座位
                                    </p>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="8">
                            <el-card>
                                <template #header><span>设备配额利用率</span></template>
                                <div style="text-align: center; padding: 30px 0">
                                    <el-progress type="dashboard" :percentage="dashboard.utilization?.avg_device_utilization ?? 0" :width="180" />
                                    <p class="mt-2 text-gray-500">
                                        已用 {{ dashboard.utilization?.total_used_devices ?? 0 }} / 总计 {{ dashboard.utilization?.total_max_devices ?? 0 }} 设备配额
                                    </p>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="8">
                            <el-card>
                                <template #header><span>超限概览</span></template>
                                <div style="padding: 20px">
                                    <el-statistic title="超容量 License" :value="dashboard.utilization?.over_capacity_count ?? 0" />
                                    <el-divider />
                                    <el-statistic title="临近容量 (≥80%)" :value="dashboard.utilization?.near_capacity_count ?? 0" />
                                    <el-divider />
                                    <el-statistic title="License 数量" :value="dashboard.utilization?.total_licenses ?? 0" />
                                </div>
                            </el-card>
                        </el-col>
                    </el-row>
                </div>
            </el-tab-pane>

            <!-- ===== 4. SDK 版本分布 ===== -->
            <el-tab-pane label="SDK 版本" name="sdk">
                <div v-loading="loading">
                    <el-row :gutter="16">
                        <el-col :span="12">
                            <el-card>
                                <template #header><span>SDK 版本分布 (近30天)</span></template>
                                <el-table :data="sdkVersionArray" stripe size="small">
                                    <el-table-column prop="version" label="版本" min-width="200" />
                                    <el-table-column prop="count" label="心跳数" width="120" sortable />
                                    <el-table-column label="占比" width="200">
                                        <template #default="{ row }">
                                            <el-progress
                                                :percentage="sdkTotal > 0 ? Math.round(row.count / sdkTotal * 100) : 0"
                                                :stroke-width="16"
                                                :text-inside="true"
                                            />
                                        </template>
                                    </el-table-column>
                                </el-table>
                            </el-card>
                        </el-col>
                        <el-col :span="12">
                            <el-card>
                                <template #header><span>SDK 语言分布 (近30天)</span></template>
                                <el-table :data="sdkLangArray" stripe size="small">
                                    <el-table-column prop="language" label="语言" min-width="200" />
                                    <el-table-column prop="count" label="心跳数" width="120" sortable />
                                    <el-table-column label="占比" width="200">
                                        <template #default="{ row }">
                                            <el-progress
                                                :percentage="sdkLangTotal > 0 ? Math.round(row.count / sdkLangTotal * 100) : 0"
                                                :stroke-width="16"
                                                :text-inside="true"
                                            />
                                        </template>
                                    </el-table-column>
                                </el-table>
                            </el-card>
                        </el-col>
                    </el-row>
                    <el-row :gutter="16" class="mt-4">
                        <el-col :span="24">
                            <el-card>
                                <template #header><span>平台分布</span></template>
                                <el-table :data="platformArray" stripe size="small">
                                    <el-table-column prop="platform" label="平台" min-width="200" />
                                    <el-table-column prop="count" label="设备数" width="120" sortable />
                                    <el-table-column label="占比" width="200">
                                        <template #default="{ row }">
                                            <el-progress
                                                :percentage="platformTotal > 0 ? Math.round(row.count / platformTotal * 100) : 0"
                                                :stroke-width="16"
                                                :text-inside="true"
                                            />
                                        </template>
                                    </el-table-column>
                                </el-table>
                            </el-card>
                        </el-col>
                    </el-row>
                </div>
            </el-tab-pane>

            <!-- ===== 5. 产品统计 ===== -->
            <el-tab-pane label="产品统计" name="products">
                <div v-loading="loading">
                    <el-card>
                        <template #header><span>按产品 License 使用量</span></template>
                        <el-table :data="productStats" stripe style="width: 100%">
                            <el-table-column type="index" label="#" width="50" />
                            <el-table-column prop="product_name" label="产品名称" min-width="200" />
                            <el-table-column prop="total_licenses" label="总 License" width="120" sortable />
                            <el-table-column prop="active_licenses" label="活跃中" width="120" sortable>
                                <template #default="{ row }">
                                    <el-tag type="success">{{ row.active_licenses }}</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column prop="total_events" label="分析事件" width="120" sortable />
                            <el-table-column label="激活率" width="200">
                                <template #default="{ row }">
                                    <el-progress
                                        :percentage="row.total_licenses > 0 ? Math.round(row.active_licenses / row.total_licenses * 100) : 0"
                                        :stroke-width="16"
                                        :text-inside="true"
                                    />
                                </template>
                            </el-table-column>
                        </el-table>
                    </el-card>
                </div>
            </el-tab-pane>

            <!-- ===== 6. 违规检测 ===== -->
            <el-tab-pane label="违规检测" name="violations">
                <div v-loading="loading">
                    <el-card class="mb-4">
                        <el-form :model="violationFilters" inline>
                            <el-form-item label="违规类型">
                                <el-select v-model="violationFilters.violation_type" placeholder="全部" clearable style="width: 180px">
                                    <el-option v-for="(label, key) in violationTypeMap" :key="key" :label="label" :value="key" />
                                </el-select>
                            </el-form-item>
                            <el-form-item label="开始日期">
                                <el-date-picker v-model="violationFilters.date_from" type="date" placeholder="选择日期" />
                            </el-form-item>
                            <el-form-item label="结束日期">
                                <el-date-picker v-model="violationFilters.date_to" type="date" placeholder="选择日期" />
                            </el-form-item>
                            <el-form-item>
                                <el-button type="primary" @click="fetchViolations(1)">搜索</el-button>
                                <el-button @click="resetViolationFilters">重置</el-button>
                            </el-form-item>
                        </el-form>
                        <el-alert
                            v-if="dashboard.violations_by_type && Object.keys(dashboard.violations_by_type).length > 0"
                            :title="violationSummary"
                            type="warning"
                            show-icon
                            :closable="false"
                            class="mb-2"
                        />
                    </el-card>
                    <el-table :data="violationList" stripe style="width: 100%">
                        <el-table-column type="index" label="#" width="50" />
                        <el-table-column label="License Key" width="180">
                            <template #default="{ row }">
                                <router-link :to="`/admin/licenses/${row.license_id}`" class="text-blue-500">
                                    {{ row.license?.license_key ?? row.license_id }}
                                </router-link>
                            </template>
                        </el-table-column>
                        <el-table-column prop="violation_type" label="违规类型" width="140">
                            <template #default="{ row }">
                                <el-tag :type="violationTagType(row.violation_type)" effect="dark">
                                    {{ violationTypeMap[row.violation_type] || row.violation_type }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="violation_detail" label="详情" min-width="300" show-overflow-tooltip />
                        <el-table-column prop="ip_address" label="IP" width="140" />
                        <el-table-column prop="country_name" label="位置" width="140">
                            <template #default="{ row }">
                                {{ [row.country_name, row.city].filter(Boolean).join(', ') || '-' }}
                            </template>
                        </el-table-column>
                        <el-table-column prop="occurred_at" label="发生时间" width="170" sortable>
                            <template #default="{ row }">{{ formatDate(row.occurred_at) }}</template>
                        </el-table-column>
                    </el-table>
                    <div class="pagination-wrapper">
                        <el-pagination
                            :current-page="violationPage"
                            :total="violationTotal"
                            :page-size="20"
                            layout="total, prev, pager, next"
                            @current-change="fetchViolations"
                        />
                    </div>
                </div>
            </el-tab-pane>

            <!-- ===== 7. 违规类型分布 ===== -->
            <el-tab-pane label="违规概览" name="violation-overview">
                <div v-loading="loading">
                    <el-row :gutter="16">
                        <el-col :span="24">
                            <el-card class="mb-4">
                                <template #header>
                                    <div class="flex justify-between items-center">
                                        <span>违规分类统计</span>
                                        <el-button size="small" @click="detectViolations" :loading="detecting" type="warning">
                                            运行检测
                                        </el-button>
                                    </div>
                                </template>
                                <div v-if="violationTypeKeys.length === 0" class="text-center py-8 text-gray-400">
                                    <el-empty description="暂无违规数据" />
                                </div>
                                <div v-else>
                                    <el-row :gutter="16">
                                        <el-col :span="8" v-for="(count, type) in dashboard.violations_by_type" :key="type" class="mb-4">
                                            <el-card shadow="hover">
                                                <div style="text-align: center; padding: 15px">
                                                    <div class="text-2xl font-bold text-red-500">{{ count }}</div>
                                                    <div class="text-gray-500 mt-1">{{ violationTypeMap[type] || type }}</div>
                                                    <el-progress
                                                        :percentage="violationTypeTotal > 0 ? Math.round(count / violationTypeTotal * 100) : 0"
                                                        :stroke-width="8"
                                                        status="exception"
                                                        class="mt-2"
                                                    />
                                                </div>
                                            </el-card>
                                        </el-col>
                                    </el-row>
                                </div>
                            </el-card>
                        </el-col>
                    </el-row>
                </div>
            </el-tab-pane>
        </el-tabs>

        <!-- 地理详情对话框 -->
        <el-dialog v-model="geoDetailVisible" title="国家/地区详情" width="80%" top="5vh">
            <div v-if="geoDetailData">
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6" v-for="s in geoDetailData.stats" :key="s.event_type">
                        <el-card shadow="hover">
                            <div class="stat-value">{{ s.count }}</div>
                            <div class="stat-label">{{ s.event_type }} ({{ s.unique_licenses }} License)</div>
                        </el-card>
                    </el-col>
                </el-row>
                <el-table :data="geoDetailData.events?.data ?? []" stripe size="small">
                    <el-table-column prop="event_type" label="事件类型" width="120" />
                    <el-table-column prop="ip_address" label="IP" width="140" />
                    <el-table-column prop="city" label="城市" width="120" />
                    <el-table-column prop="occurred_at" label="时间" width="170">
                        <template #default="{ row }">{{ formatDate(row.occurred_at) }}</template>
                    </el-table-column>
                    <el-table-column label="License" min-width="200">
                        <template #default="{ row }">
                            {{ row.license?.license_key ?? row.license_id }}
                        </template>
                    </el-table-column>
                </el-table>
            </div>
            <div v-else class="text-center py-8 text-gray-400">加载中...</div>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
    Refresh, WarnTriangleFilled, DataBoard,
} from '@element-plus/icons-vue';
import licenseAnalyticsApi from '../../api/licenseAnalytics';

const loading = ref(false);
const detecting = ref(false);
const backfilling = ref(false);
const activeTab = ref('geo');

// Dashboard data
const dashboard = reactive({
    total_licenses: 0,
    active_licenses: 0,
    total_activations: 0,
    total_devices: 0,
    blacklisted_devices: 0,
    total_events: 0,
    total_violations: 0,
    suspicious_activations: 0,
    violations_by_type: {},
    utilization: {
        avg_seat_utilization: 0,
        avg_device_utilization: 0,
        over_capacity_count: 0,
        near_capacity_count: 0,
        total_licenses: 0,
        total_seats: 0,
        total_used_seats: 0,
        total_max_devices: 0,
        total_used_devices: 0,
    },
});

// Geo
const geoData = reactive({ countries: [], cities: [], total: 0 });
const geoDetailVisible = ref(false);
const geoDetailData = ref(null);

// Trends
const trendDays = ref(30);
const activationTrend = ref([]);
const violationTrend = ref([]);
const maxTrendCount = computed(() => Math.max(...activationTrend.value.map(t => t.count), 1));
const maxViolationCount = computed(() => Math.max(...violationTrend.value.map(t => t.count), 1));

// SDK
const sdkVersionStats = reactive({ by_version: {}, by_language: {} });
const sdkTotal = computed(() => Object.values(sdkVersionStats.by_version).reduce((a, b) => a + b, 0));
const sdkVersionArray = computed(() =>
    Object.entries(sdkVersionStats.by_version).map(([version, count]) => ({ version, count }))
);
const sdkLangTotal = computed(() => Object.values(sdkVersionStats.by_language).reduce((a, b) => a + b, 0));
const sdkLangArray = computed(() =>
    Object.entries(sdkVersionStats.by_language).map(([language, count]) => ({ language, count }))
);

// Platform
const platformDistribution = ref({});
const platformTotal = computed(() => Object.values(platformDistribution.value).reduce((a, b) => a + b, 0));
const platformArray = computed(() =>
    Object.entries(platformDistribution.value).map(([platform, count]) => ({ platform: platform || 'unknown', count }))
);

// Products
const productStats = ref([]);

// Violations
const violationList = ref([]);
const violationPage = ref(1);
const violationTotal = ref(0);
const violationTypeMap = ref({
    excessive_activations: '超量激活',
    expired_use: '过期使用',
    tampered: '信息篡改',
    blacklisted_device: '黑名单设备',
    suspicious_location: '异常地理位置',
});
const violationFilters = reactive({
    violation_type: '',
    date_from: '',
    date_to: '',
});

const violationTypeKeys = computed(() => Object.keys(dashboard.violations_by_type));
const violationTypeTotal = computed(() =>
    Object.values(dashboard.violations_by_type).reduce((a, b) => a + b, 0)
);

const violationSummary = computed(() => {
    const parts = Object.entries(dashboard.violations_by_type)
        .map(([type, count]) => `${violationTypeMap.value[type] || type}: ${count} 次`);
    return `违规概况 — ${parts.join(' | ')}`;
});

function violationTagType(type) {
    const map = {
        excessive_activations: 'danger',
        expired_use: 'warning',
        tampered: 'danger',
        blacklisted_device: 'info',
        suspicious_location: 'warning',
    };
    return map[type] || 'danger';
}

function formatDate(date) {
    if (!date) return '-';
    return new Date(date).toLocaleString('zh-CN', { hour12: false });
}

async function refreshAll() {
    loading.value = true;
    try {
        await Promise.all([
            fetchDashboard(),
            fetchGeoDistribution(),
            fetchTrends(),
            fetchSdkStats(),
            fetchProductStats(),
            fetchViolationTypes(),
        ]);
        ElMessage.success('数据已刷新');
    } catch (e) {
        ElMessage.error('加载数据失败');
    } finally {
        loading.value = false;
    }
}

async function fetchDashboard() {
    try {
        const { data: res } = await licenseAnalyticsApi.dashboard();
        const d = res.data || res;
        Object.assign(dashboard, d);
        platformDistribution.value = d.platform_distribution || {};
    } catch (e) {
        console.error('Failed to fetch dashboard', e);
    }
}

async function fetchGeoDistribution() {
    try {
        const { data: res } = await licenseAnalyticsApi.geoDistribution();
        Object.assign(geoData, res.data || res);
    } catch (e) {
        console.error('Failed to fetch geo distribution', e);
    }
}

async function fetchTrends() {
    try {
        const [actResp, violResp] = await Promise.all([
            licenseAnalyticsApi.activationTrend(trendDays.value),
            licenseAnalyticsApi.violationTrend(trendDays.value),
        ]);
        activationTrend.value = actResp.data?.data || actResp.data || [];
        violationTrend.value = violResp.data?.data || violResp.data || [];
    } catch (e) {
        console.error('Failed to fetch trends', e);
    }
}

async function fetchSdkStats() {
    try {
        const { data: res } = await licenseAnalyticsApi.sdkStats();
        const d = res.data || res;
        sdkVersionStats.by_version = d.by_version || {};
        sdkVersionStats.by_language = d.by_language || {};
    } catch (e) {
        console.error('Failed to fetch SDK stats', e);
    }
}

async function fetchProductStats() {
    try {
        const { data: res } = await licenseAnalyticsApi.productStats();
        productStats.value = res.data || res || [];
    } catch (e) {
        console.error('Failed to fetch product stats', e);
    }
}

async function fetchViolationTypes() {
    try {
        const { data: res } = await licenseAnalyticsApi.violationTypes();
        violationTypeMap.value = res.data || res || violationTypeMap.value;
    } catch (e) {
        console.error('Failed to fetch violation types', e);
    }
}

async function fetchViolations(page = 1) {
    loading.value = true;
    try {
        const params = { page, per_page: 20 };
        if (violationFilters.violation_type) params.violation_type = violationFilters.violation_type;
        if (violationFilters.date_from) params.date_from = violationFilters.date_from;
        if (violationFilters.date_to) params.date_to = violationFilters.date_to;

        const { data: res } = await licenseAnalyticsApi.violations(params);
        const d = res.data || res;
        violationList.value = d.data || [];
        violationPage.value = d.current_page || 1;
        violationTotal.value = d.total || 0;

        activeTab.value = 'violations';
    } catch (e) {
        ElMessage.error('获取违规数据失败');
    } finally {
        loading.value = false;
    }
}

function resetViolationFilters() {
    violationFilters.violation_type = '';
    violationFilters.date_from = '';
    violationFilters.date_to = '';
    fetchViolations(1);
}

async function detectViolations() {
    detecting.value = true;
    try {
        const { data: res } = await licenseAnalyticsApi.detectViolations();
        const d = res.data || res;
        ElMessage.success(`违规检测完成：发现 ${d.violations_found} 个问题，新记录 ${d.violations_recorded} 条`);
        await fetchDashboard();
        if (activeTab.value === 'violations') await fetchViolations(1);
    } catch (e) {
        ElMessage.error('违规检测失败');
    } finally {
        detecting.value = false;
    }
}

async function backfillData() {
    try {
        await ElMessageBox.confirm('将从现有激活记录回填数据到分析引擎，确定继续？', '确认', {
            type: 'warning',
            confirmButtonText: '确定',
            cancelButtonText: '取消',
        });
    } catch {
        return;
    }

    backfilling.value = true;
    try {
        const { data: res } = await licenseAnalyticsApi.backfill();
        const d = res.data || res;
        ElMessage.success(`回填完成：${d.activations} 条激活记录`);
        await refreshAll();
    } catch (e) {
        ElMessage.error('回填失败');
    } finally {
        backfilling.value = false;
    }
}

async function viewGeoDetail(countryCode) {
    geoDetailVisible.value = true;
    geoDetailData.value = null;
    try {
        const { data: res } = await licenseAnalyticsApi.geoDetail(countryCode);
        geoDetailData.value = res.data || res;
    } catch (e) {
        ElMessage.error('获取地理详情失败');
    }
}

onMounted(() => {
    refreshAll();
});
</script>

<style scoped>
.license-analytics-page {
    padding: 16px;
}
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}
.page-header h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
}
.header-actions {
    display: flex;
    gap: 8px;
}
.stat-card {
    text-align: center;
    cursor: default;
}
.stat-value {
    font-size: 1.75rem;
    font-weight: 700;
    line-height: 1.2;
}
.stat-label {
    font-size: 0.8rem;
    color: #909399;
    margin-top: 4px;
}
.stat-active .stat-value {
    color: #67c23a;
}
.stat-danger .stat-value {
    color: #f56c6c;
}
.stat-warning .stat-value {
    color: #e6a23c;
}
.mb-4 {
    margin-bottom: 16px;
}
.mb-2 {
    margin-bottom: 8px;
}
.mt-2 {
    margin-top: 8px;
}
.mt-4 {
    margin-top: 16px;
}
.pagination-wrapper {
    display: flex;
    justify-content: flex-end;
    padding: 16px 0;
}
.text-gray-500 {
    color: #909399;
}
.text-red-500 {
    color: #f56c6c;
}
.text-blue-500 {
    color: #409eff;
    text-decoration: none;
}
.text-blue-500:hover {
    text-decoration: underline;
}
.text-2xl {
    font-size: 1.75rem;
}
.font-bold {
    font-weight: 700;
}
.flex {
    display: flex;
}
.justify-between {
    justify-content: space-between;
}
.items-center {
    align-items: center;
}
.text-center {
    text-align: center;
}
.py-8 {
    padding-top: 2rem;
    padding-bottom: 2rem;
}
</style>
