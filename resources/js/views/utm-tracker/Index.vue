<template>
    <div class="utm-tracker-container">
        <el-page-header :content="t('utm_tracker_page.title')" @back="$router.push('/admin/dashboard')" />

        <el-alert
            :title="t('utm_tracker_page.alert')"
            type="info"
            show-icon
            :closable="false"
            class="alert-info"
        />

        <el-card class="filter-card">
            <el-form :inline="true" :model="filters" size="default">
                <el-form-item :label="t('utm_tracker_page.start_date')">
                    <el-date-picker
                        v-model="filters.start_date"
                        type="date"
                        :placeholder="t('utm_tracker_page.start_ph')"
                        format="YYYY-MM-DD"
                        value-format="YYYY-MM-DD"
                    />
                </el-form-item>
                <el-form-item :label="t('utm_tracker_page.end_date')">
                    <el-date-picker
                        v-model="filters.end_date"
                        type="date"
                        :placeholder="t('utm_tracker_page.end_ph')"
                        format="YYYY-MM-DD"
                        value-format="YYYY-MM-DD"
                    />
                </el-form-item>
                <el-form-item :label="t('utm_tracker_page.attribution_model')">
                    <el-select v-model="attributionModel" :placeholder="t('utm_tracker_page.select_model')">
                        <el-option
                            v-for="(label, key) in options.attribution_models"
                            :key="key"
                            :label="label"
                            :value="key"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadDashboard">{{ t('utm_tracker_page.query') }}</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <el-row :gutter="20" class="stat-cards">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value">{{ dashboard.total_visits }}</div>
                    <div class="stat-label">{{ t('utm_tracker_page.stats.visits') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-success">{{ dashboard.total_conversions }}</div>
                    <div class="stat-label">{{ t('utm_tracker_page.stats.conversions') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-warning">{{ dashboard.overall_rate }}%</div>
                    <div class="stat-label">{{ t('utm_tracker_page.stats.rate') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-primary">{{ attributionReport.total_conversions }}</div>
                    <div class="stat-label">{{ t('utm_tracker_page.stats.attributed') }}</div>
                </el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab">
            <el-tab-pane :label="t('utm_tracker_page.tabs.channels')" name="channels">
                <el-card>
                    <template #header>
                        <span>{{ t('utm_tracker_page.channel_overview') }}</span>
                    </template>
                    <el-table :data="dashboard.by_channel" stripe style="width: 100%">
                        <el-table-column prop="channel" :label="t('utm_tracker_page.cols.channel_group')" />
                        <el-table-column prop="visits" :label="t('utm_tracker_page.cols.visits')" sortable />
                        <el-table-column prop="conversions" :label="t('utm_tracker_page.cols.conversions')" sortable />
                        <el-table-column prop="conversion_rate" :label="t('utm_tracker_page.cols.rate')" sortable>
                            <template #default="{ row }">
                                <el-tag :type="row.conversion_rate > 5 ? 'success' : row.conversion_rate > 1 ? 'warning' : 'info'">
                                    {{ row.conversion_rate }}%
                                </el-tag>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>

            <el-tab-pane :label="t('utm_tracker_page.tabs.attribution')" name="attribution">
                <el-card>
                    <template #header>
                        <span>
                            {{ t('utm_tracker_page.attribution_report', { model: attributionReport.model_label }) }}
                        </span>
                    </template>
                    <el-table :data="attributionReport.channels" stripe style="width: 100%">
                        <el-table-column prop="channel" :label="t('utm_tracker_page.cols.channel')" />
                        <el-table-column prop="conversions" :label="t('utm_tracker_page.cols.conversions')" sortable />
                        <el-table-column prop="percentage" :label="t('utm_tracker_page.cols.pct')" sortable>
                            <template #default="{ row }">
                                <el-progress :percentage="row.percentage" :stroke-width="16" />
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>

            <el-tab-pane :label="t('utm_tracker_page.tabs.sources')" name="sources">
                <el-card>
                    <template #header>
                        <el-space>
                            <span>{{ t('utm_tracker_page.source_detail') }}</span>
                            <el-select
                                v-model="sourceChannelFilter"
                                :placeholder="t('utm_tracker_page.filter_channel')"
                                clearable
                                size="small"
                                style="width: 160px"
                            >
                                <el-option
                                    v-for="ch in dashboard.channel_groups"
                                    :key="ch"
                                    :label="ch"
                                    :value="ch"
                                />
                            </el-select>
                        </el-space>
                    </template>
                    <el-table :data="sourceDetail.sources" stripe style="width: 100%">
                        <el-table-column prop="source" :label="t('utm_tracker_page.cols.source')" />
                        <el-table-column prop="visits" :label="t('utm_tracker_page.cols.visits_short')" sortable />
                        <el-table-column prop="conversions" :label="t('utm_tracker_page.cols.conv_short')" sortable />
                        <el-table-column prop="rate" :label="t('utm_tracker_page.cols.rate')" sortable>
                            <template #default="{ row }">
                                {{ row.rate }}%
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('utm_tracker_page.cols.mediums')">
                            <template #default="{ row }">
                                <el-popover placement="bottom" :width="300" trigger="click">
                                    <template #reference>
                                        <el-button size="small" link>{{ t('utm_tracker_page.view_mediums') }}</el-button>
                                    </template>
                                    <el-table :data="mediumList(row.mediums)" size="small">
                                        <el-table-column prop="medium" :label="t('utm_tracker_page.cols.medium')" />
                                        <el-table-column prop="visits" :label="t('utm_tracker_page.cols.visits_short')" width="80" />
                                        <el-table-column prop="conversions" :label="t('utm_tracker_page.cols.conv_short')" width="80" />
                                    </el-table>
                                </el-popover>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>

            <el-tab-pane :label="t('utm_tracker_page.tabs.records')" name="records">
                <el-card>
                    <el-table :data="records.data" stripe style="width: 100%" v-loading="recordsLoading">
                        <el-table-column prop="created_at" :label="t('utm_tracker_page.cols.time')" width="160" />
                        <el-table-column prop="utm_source" :label="t('utm_tracker_page.cols.source')" width="120" />
                        <el-table-column prop="utm_medium" :label="t('utm_tracker_page.cols.medium')" width="100" />
                        <el-table-column prop="utm_campaign" :label="t('utm_tracker_page.cols.campaign')" width="120" />
                        <el-table-column prop="channel_group" :label="t('utm_tracker_page.cols.channel_group')" width="120">
                            <template #default="{ row }">
                                <el-tag size="small">{{ row.channel_group }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="attribution_type" :label="t('utm_tracker_page.cols.type')" width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.attribution_type === 'conversion' ? 'success' : 'info'" size="small">
                                    {{ row.attribution_type === 'conversion' ? t('utm_tracker_page.type_conversion') : t('utm_tracker_page.type_visit') }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="landing_page" :label="t('utm_tracker_page.cols.landing')" min-width="200" show-overflow-tooltip />
                    </el-table>
                    <el-pagination
                        v-if="records.total > 0"
                        v-model:current-page="records.current_page"
                        :page-size="records.per_page"
                        :total="records.total"
                        layout="prev, pager, next"
                        @current-change="loadRecords"
                        class="pagination"
                    />
                </el-card>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import utmTracker from '@/api/utmTracker'

const { t } = useI18n()

const activeTab = ref('channels')
const attributionModel = ref('first_touch')
const sourceChannelFilter = ref('')
const recordsLoading = ref(false)

const filters = reactive({
    start_date: '',
    end_date: '',
})

const options = reactive({
    channel_groups: [],
    attribution_models: {},
    utm_params: [],
})

const dashboard = reactive({
    total_visits: 0,
    total_conversions: 0,
    overall_rate: 0,
    by_channel: [],
    by_source: [],
    channel_groups: [],
    attribution_models: {},
})

const attributionReport = reactive({
    total_conversions: 0,
    model_label: '',
    channels: [],
})

const sourceDetail = reactive({
    sources: [],
})

const records = reactive({
    data: [],
    total: 0,
    current_page: 1,
    per_page: 20,
})

function mediumList(mediums) {
    if (!mediums) return []
    return Object.entries(mediums).map(([medium, data]) => ({
        medium: medium || t('utm_tracker_page.none'),
        visits: data.visits || 0,
        conversions: data.conversions || 0,
    }))
}

function setDefaultDates() {
    const now = new Date()
    const thirtyDaysAgo = new Date(now)
    thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30)
    filters.start_date = thirtyDaysAgo.toISOString().slice(0, 10)
    filters.end_date = now.toISOString().slice(0, 10)
}

async function loadOptions() {
    try {
        const res = await utmTracker.options()
        Object.assign(options, res.data.data)
    } catch (e) {
        console.error('Failed to load options:', e)
    }
}

async function loadDashboard() {
    try {
        const res = await utmTracker.dashboard({
            start_date: filters.start_date,
            end_date: filters.end_date,
        })
        Object.assign(dashboard, res.data.data)
    } catch (e) {
        console.error('Failed to load dashboard:', e)
    }
}

async function loadAttributionReport() {
    try {
        const res = await utmTracker.attributionReport({
            start_date: filters.start_date,
            end_date: filters.end_date,
            model: attributionModel.value,
        })
        Object.assign(attributionReport, res.data.data)
    } catch (e) {
        console.error('Failed to load attribution report:', e)
    }
}

async function loadSourceDetail() {
    try {
        const params = {
            start_date: filters.start_date,
            end_date: filters.end_date,
        }
        if (sourceChannelFilter.value) {
            params.channel_group = sourceChannelFilter.value
        }
        const res = await utmTracker.sourceDetail(params)
        Object.assign(sourceDetail, res.data.data)
    } catch (e) {
        console.error('Failed to load source detail:', e)
    }
}

async function loadRecords(page) {
    recordsLoading.value = true
    try {
        const params = {
            start_date: filters.start_date,
            end_date: filters.end_date,
            page: page || records.current_page,
            per_page: records.per_page,
        }
        const res = await utmTracker.records(params)
        records.data = res.data.data
        records.total = res.data.total
        records.current_page = res.data.current_page
        records.per_page = res.data.per_page
    } catch (e) {
        console.error('Failed to load records:', e)
    } finally {
        recordsLoading.value = false
    }
}

watch(activeTab, (tab) => {
    if (tab === 'attribution') loadAttributionReport()
    else if (tab === 'sources') loadSourceDetail()
    else if (tab === 'records') loadRecords(1)
})

watch(sourceChannelFilter, () => {
    if (activeTab.value === 'sources') loadSourceDetail()
})

onMounted(() => {
    setDefaultDates()
    loadOptions()
    loadDashboard()
})
</script>

<style scoped>
.utm-tracker-container {
    padding: 20px;
}

.alert-info {
    margin: 16px 0;
}

.filter-card {
    margin-bottom: 16px;
}

.stat-cards {
    margin-bottom: 16px;
}

.stat-cards .el-card {
    text-align: center;
}

.stat-value {
    font-size: 28px;
    font-weight: bold;
    color: #303133;
}

.stat-label {
    font-size: 13px;
    color: #909399;
    margin-top: 4px;
}

.text-success {
    color: #67c23a;
}

.text-warning {
    color: #e6a23c;
}

.text-primary {
    color: #0f172a;
}

.pagination {
    margin-top: 16px;
    text-align: center;
}
</style>
