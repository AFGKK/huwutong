<template>
    <div class="pricing-experiments-page">
        <div class="page-header">
            <h2>{{ t('pricing_experiments_page.title') }}</h2>
            <p class="text-muted">{{ t('pricing_experiments_page.subtitle') }}</p>
            <div class="header-actions">
                <el-button @click="loadAll" :loading="loading">
                    <el-icon><Refresh /></el-icon> {{ t('pricing_experiments_page.refresh') }}
                </el-button>
                <el-button @click="showRecsPanel" :loading="loadingRecs">
                    <el-icon><DataAnalysis /></el-icon> {{ t('pricing_experiments_page.btn_recommendations') }}
                </el-button>
                <el-button type="primary" @click="showCreateDialog = true">
                    <el-icon><Plus /></el-icon> {{ t('pricing_experiments_page.btn_create') }}
                </el-button>
            </div>
        </div>

        <el-row :gutter="16" class="mb-4">
            <el-col :span="4">
                <el-card shadow="hover">
                    <div class="stat-label">{{ t('pricing_experiments_page.stats.total') }}</div>
                    <div class="stat-value">{{ stats.total }}</div>
                </el-card>
            </el-col>
            <el-col :span="5">
                <el-card shadow="hover" class="stat-success">
                    <div class="stat-label">{{ t('pricing_experiments_page.stats.running') }}</div>
                    <div class="stat-value">{{ stats.running }}</div>
                </el-card>
            </el-col>
            <el-col :span="5">
                <el-card shadow="hover" class="stat-info">
                    <div class="stat-label">{{ t('pricing_experiments_page.stats.draft') }}</div>
                    <div class="stat-value">{{ stats.draft }}</div>
                </el-card>
            </el-col>
            <el-col :span="5">
                <el-card shadow="hover" class="stat-warning">
                    <div class="stat-label">{{ t('pricing_experiments_page.stats.paused') }}</div>
                    <div class="stat-value">{{ stats.paused || 0 }}</div>
                </el-card>
            </el-col>
            <el-col :span="5">
                <el-card shadow="hover">
                    <div class="stat-label">{{ t('pricing_experiments_page.stats.completed') }}</div>
                    <div class="stat-value">{{ stats.completed }}</div>
                </el-card>
            </el-col>
        </el-row>

        <el-card class="mb-4">
            <el-form :model="filters" inline size="small">
                <el-form-item :label="t('pricing_experiments_page.filters.status')">
                    <el-select v-model="filters.status" :placeholder="t('pricing_experiments_page.filters.all')" clearable style="width:140px">
                        <el-option v-for="opt in statusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('pricing_experiments_page.filters.type')">
                    <el-select v-model="filters.experiment_type" :placeholder="t('pricing_experiments_page.filters.all')" clearable style="width:140px">
                        <el-option v-for="opt in experimentTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('pricing_experiments_page.filters.search')">
                    <el-input v-model="filters.search" :placeholder="t('pricing_experiments_page.filters.name_ph')" style="width:200px" clearable />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadList">{{ t('actions.search') }}</el-button>
                    <el-button @click="resetFilters">{{ t('actions.reset') }}</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <el-card>
            <el-table :data="experiments" stripe v-loading="loading">
                <el-table-column :label="t('pricing_experiments_page.cols.name')" min-width="200">
                    <template #default="{ row }">
                        <div class="exp-name">{{ row.name }}</div>
                        <small class="text-muted">{{ row.slug }}</small>
                    </template>
                </el-table-column>
                <el-table-column :label="t('pricing_experiments_page.cols.status')" width="90">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)" size="small">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('pricing_experiments_page.cols.type')" width="80">
                    <template #default="{ row }">
                        <el-tag effect="plain" size="small">{{ row.experiment_type }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('pricing_experiments_page.cols.traffic_split')" width="80" align="center">
                    <template #default="{ row }">{{ row.traffic_split ?? 50 }}%</template>
                </el-table-column>
                <el-table-column :label="t('pricing_experiments_page.cols.participants')" width="80" align="center">
                    <template #default="{ row }">{{ row.participants_count || 0 }}</template>
                </el-table-column>
                <el-table-column :label="t('pricing_experiments_page.cols.target_metric')" width="100" prop="target_metric" />
                <el-table-column :label="t('pricing_experiments_page.cols.significant')" width="70" align="center">
                    <template #default="{ row }">
                        <el-tag v-if="row.results?.significant" type="success" size="small">{{ t('pricing_experiments_page.yes') }}</el-tag>
                        <el-tag v-else type="info" size="small">{{ t('pricing_experiments_page.no') }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('pricing_experiments_page.cols.creator')" width="100" prop="creator?.name" />
                <el-table-column :label="t('pricing_experiments_page.cols.time')" width="160">
                    <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
                </el-table-column>
                <el-table-column :label="t('pricing_experiments_page.cols.actions')" width="260" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="viewDetail(row)">{{ t('pricing_experiments_page.row_actions.detail') }}</el-button>
                        <el-button v-if="row.status === 'draft'" size="small" type="success" @click="startExp(row)">{{ t('pricing_experiments_page.row_actions.start') }}</el-button>
                        <el-button v-if="row.status === 'running'" size="small" type="warning" @click="pauseExp(row)">{{ t('pricing_experiments_page.row_actions.pause') }}</el-button>
                        <el-button v-if="row.status === 'paused'" size="small" type="success" @click="startExp(row)">{{ t('pricing_experiments_page.row_actions.resume') }}</el-button>
                        <el-button v-if="row.status === 'running'" size="small" type="danger" @click="completeExp(row)">{{ t('pricing_experiments_page.row_actions.complete') }}</el-button>
                        <el-button v-if="row.status === 'completed'" size="small" @click="calculateExp(row)">{{ t('pricing_experiments_page.row_actions.recalculate') }}</el-button>
                        <el-button v-if="row.status === 'draft' || row.status === 'cancelled'"
                            size="small" type="danger" @click="deleteExp(row)">{{ t('actions.delete') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="pagination-wrapper">
                <el-pagination v-if="pagination.total > 0"
                    :current-page="pagination.current_page" :total="pagination.total"
                    :page-size="pagination.per_page" layout="total, prev, pager, next"
                    @current-change="onPageChange" />
            </div>
        </el-card>

        <el-dialog v-model="showCreateDialog" :title="t('pricing_experiments_page.create.title')" width="700px">
            <el-form :model="createForm" label-position="top">
                <el-row :gutter="16">
                    <el-col :span="16">
                        <el-form-item :label="t('pricing_experiments_page.create.name')" :rules="[{ required: true }]">
                            <el-input v-model="createForm.name" :placeholder="t('pricing_experiments_page.create.name_ph')" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t('pricing_experiments_page.create.type')">
                            <el-select v-model="createForm.experiment_type" style="width:100%">
                                <el-option v-for="opt in experimentTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item :label="t('pricing_experiments_page.create.description')">
                    <el-input v-model="createForm.description" type="textarea" :rows="2" />
                </el-form-item>
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item :label="t('pricing_experiments_page.create.target_metric')">
                            <el-select v-model="createForm.target_metric" style="width:100%">
                                <el-option v-for="opt in targetMetricOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t('pricing_experiments_page.create.traffic_split')">
                            <el-input-number v-model="createForm.traffic_split" :min="1" :max="99" style="width:100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t('pricing_experiments_page.create.min_sample')">
                            <el-input-number v-model="createForm.minimum_sample_size" :min="10" :step="100" style="width:100%" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-divider>{{ t('pricing_experiments_page.segment.divider') }}</el-divider>
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item :label="t('pricing_experiments_page.segment.region')">
                            <el-select v-model="createForm.segment_filters.region" multiple clearable :placeholder="t('pricing_experiments_page.segment.all_regions')" style="width:100%">
                                <el-option v-for="opt in regionOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t('pricing_experiments_page.segment.channel')">
                            <el-select v-model="createForm.segment_filters.channel" multiple clearable :placeholder="t('pricing_experiments_page.segment.all_channels')" style="width:100%">
                                <el-option v-for="opt in channelOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t('pricing_experiments_page.segment.customer_tier')">
                            <el-select v-model="createForm.segment_filters.customer_tier" multiple clearable :placeholder="t('pricing_experiments_page.segment.all_tiers')" style="width:100%">
                                <el-option v-for="opt in tierOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item :label="t('pricing_experiments_page.segment.industry')">
                            <el-select v-model="createForm.segment_filters.industry" multiple clearable :placeholder="t('pricing_experiments_page.segment.all_industries')" style="width:100%">
                                <el-option v-for="opt in industryOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t('pricing_experiments_page.segment.new_vs_returning')">
                            <el-select v-model="createForm.segment_filters.new_vs_returning" multiple clearable :placeholder="t('pricing_experiments_page.segment.all')" style="width:100%">
                                <el-option v-for="opt in customerTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t('pricing_experiments_page.segment.device_type')">
                            <el-select v-model="createForm.segment_filters.device_type" multiple clearable :placeholder="t('pricing_experiments_page.segment.all')" style="width:100%">
                                <el-option v-for="opt in deviceTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-divider>{{ t('pricing_experiments_page.group_config.divider') }}</el-divider>
                <el-form-item :label="t('pricing_experiments_page.group_config.control_json')">
                    <el-input v-model="createForm.controlConfigStr" type="textarea" :rows="2"
                        :placeholder="t('pricing_experiments_page.group_config.control_ph')" />
                </el-form-item>
                <el-form-item :label="t('pricing_experiments_page.group_config.treatment_json')">
                    <el-input v-model="createForm.treatmentConfigStr" type="textarea" :rows="2"
                        :placeholder="t('pricing_experiments_page.group_config.treatment_ph')" />
                </el-form-item>
                <el-divider>{{ t('pricing_experiments_page.time_range.divider') }}</el-divider>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t('pricing_experiments_page.time_range.starts_at')">
                            <el-date-picker v-model="createForm.starts_at" type="datetime" :placeholder="t('pricing_experiments_page.time_range.immediate')" style="width:100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('pricing_experiments_page.time_range.ends_at')">
                            <el-date-picker v-model="createForm.ends_at" type="datetime" :placeholder="t('pricing_experiments_page.time_range.no_end')" style="width:100%" />
                        </el-form-item>
                    </el-col>
                </el-row>
            </el-form>
            <template #footer>
                <el-button @click="showCreateDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="doCreate" :loading="creating">{{ t('pricing_experiments_page.create.save_draft') }}</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="showDetailDialog" :title="t('pricing_experiments_page.detail.title')" width="700px">
            <template v-if="detailData">
                <el-descriptions :column="2" border>
                    <el-descriptions-item :label="t('pricing_experiments_page.detail.name')" :span="2">{{ detailData.name }}</el-descriptions-item>
                    <el-descriptions-item :label="t('pricing_experiments_page.detail.status')">
                        <el-tag :type="statusType(detailData.status)">{{ statusLabel(detailData.status) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('pricing_experiments_page.detail.type')">{{ detailData.experiment_type }}</el-descriptions-item>
                    <el-descriptions-item :label="t('pricing_experiments_page.detail.target_metric')">{{ detailData.target_metric }}</el-descriptions-item>
                    <el-descriptions-item :label="t('pricing_experiments_page.detail.traffic_split')">{{ detailData.traffic_split }}%</el-descriptions-item>
                    <el-descriptions-item :label="t('pricing_experiments_page.detail.min_sample')">{{ detailData.minimum_sample_size }}</el-descriptions-item>
                    <el-descriptions-item :label="t('pricing_experiments_page.detail.current_sample')">{{ detailData.sample_size || 0 }}</el-descriptions-item>
                    <el-descriptions-item :label="t('pricing_experiments_page.detail.starts_at')">{{ formatTime(detailData.starts_at) || t('pricing_experiments_page.time_range.immediate') }}</el-descriptions-item>
                    <el-descriptions-item :label="t('pricing_experiments_page.detail.ends_at')">{{ formatTime(detailData.ends_at) || t('pricing_experiments_page.time_range.no_end') }}</el-descriptions-item>
                    <el-descriptions-item :label="t('pricing_experiments_page.detail.creator')">{{ detailData.creator?.name }}</el-descriptions-item>
                    <el-descriptions-item :label="t('pricing_experiments_page.detail.confidence_level')">{{ detailData.confidence_level || 95 }}%</el-descriptions-item>
                </el-descriptions>

                <template v-if="detailData.results">
                    <el-divider />
                    <div class="d-flex justify-between align-center mb-2">
                        <h4>{{ t('pricing_experiments_page.detail.results_title') }}</h4>
                        <div v-if="detailData.status === 'completed'" class="header-actions">
                            <el-button size="small" type="primary" @click="applyWinner(detailData)">
                                <el-icon><Check /></el-icon> {{ t('pricing_experiments_page.winner.apply_btn') }}
                            </el-button>
                            <el-button size="small" @click="calculateExp(detailData)">{{ t('pricing_experiments_page.winner.recalculate_btn') }}</el-button>
                        </div>
                    </div>

                    <el-row :gutter="12" class="mb-3">
                        <el-col :span="6">
                            <el-card shadow="never" class="result-card control-card">
                                <div class="result-label">{{ t('pricing_experiments_page.detail.control_group') }}</div>
                                <div class="result-value">{{ detailData.results.control?.count ?? 0 }}</div>
                                <div class="result-sub">{{ t('pricing_experiments_page.detail.participants_sub') }}</div>
                            </el-card>
                        </el-col>
                        <el-col :span="6">
                            <el-card shadow="never" class="result-card treatment-card">
                                <div class="result-label">{{ t('pricing_experiments_page.detail.treatment_group') }}</div>
                                <div class="result-value">{{ detailData.results.treatment?.count ?? 0 }}</div>
                                <div class="result-sub">{{ t('pricing_experiments_page.detail.participants_sub') }}</div>
                            </el-card>
                        </el-col>
                        <el-col :span="6">
                            <el-card shadow="never" class="result-card"
                                :class="(detailData.results.improvement?.conversion_rate ?? 0) >= 0 ? 'result-positive' : 'result-negative'">
                                <div class="result-label">{{ t('pricing_experiments_page.detail.conversion_change') }}</div>
                                <div class="result-value">
                                    {{ formatPercent(detailData.results.improvement?.conversion_rate) }}
                                </div>
                                <div class="result-sub">{{ t('pricing_experiments_page.detail.vs_control') }}</div>
                            </el-card>
                        </el-col>
                        <el-col :span="6">
                            <el-card shadow="never" class="result-card"
                                :class="(detailData.results.significance?.significant) ? 'result-positive' : ''">
                                <div class="result-label">{{ t('pricing_experiments_page.detail.statistical_significance') }}</div>
                                <div class="result-value">
                                    <el-tag :type="detailData.results.significance?.significant ? 'success' : 'info'" size="large" effect="dark">
                                        {{ detailData.results.significance?.significant ? t('pricing_experiments_page.significance.significant') : t('pricing_experiments_page.significance.not_significant') }}
                                    </el-tag>
                                </div>
                                <div class="result-sub">P={{ detailData.results.significance?.p_value ?? '-' }}</div>
                            </el-card>
                        </el-col>
                    </el-row>

                    <el-descriptions :column="4" border size="small">
                        <el-descriptions-item :label="t('pricing_experiments_page.detail.control_conversions')">{{ detailData.results.control?.converted ?? 0 }}</el-descriptions-item>
                        <el-descriptions-item :label="t('pricing_experiments_page.detail.treatment_conversions')">{{ detailData.results.treatment?.converted ?? 0 }}</el-descriptions-item>
                        <el-descriptions-item :label="t('pricing_experiments_page.detail.control_conversion_rate')">{{ detailData.results.control?.conversion_rate ?? 0 }}%</el-descriptions-item>
                        <el-descriptions-item :label="t('pricing_experiments_page.detail.treatment_conversion_rate')">{{ detailData.results.treatment?.conversion_rate ?? 0 }}%</el-descriptions-item>
                    </el-descriptions>
                    <el-descriptions :column="4" border size="small" class="mt-2">
                        <el-descriptions-item :label="t('pricing_experiments_page.detail.control_avg_revenue')">{{ formatMoney(detailData.results.control?.avg_revenue) }}</el-descriptions-item>
                        <el-descriptions-item :label="t('pricing_experiments_page.detail.treatment_avg_revenue')">{{ formatMoney(detailData.results.treatment?.avg_revenue) }}</el-descriptions-item>
                        <el-descriptions-item :label="t('pricing_experiments_page.detail.control_churn_rate')">{{ detailData.results.control?.churn_rate ?? 0 }}%</el-descriptions-item>
                        <el-descriptions-item :label="t('pricing_experiments_page.detail.treatment_churn_rate')">{{ detailData.results.treatment?.churn_rate ?? 0 }}%</el-descriptions-item>
                    </el-descriptions>
                    <el-descriptions :column="3" border size="small" class="mt-2">
                        <el-descriptions-item :label="t('pricing_experiments_page.detail.z_score')">{{ detailData.results.significance?.z_score ?? '-' }}</el-descriptions-item>
                        <el-descriptions-item :label="t('pricing_experiments_page.detail.p_value')">{{ detailData.results.significance?.p_value ?? '-' }}</el-descriptions-item>
                        <el-descriptions-item :label="t('pricing_experiments_page.detail.confidence_level')">{{ detailData.confidence_level || 95 }}%</el-descriptions-item>
                    </el-descriptions>

                    <template v-if="detailData.metadata?.winning_recommendation">
                        <el-divider />
                        <el-alert :title="t('pricing_experiments_page.winner.alert_title')" :description="detailData.metadata.winning_recommendation.reason"
                            type="success" show-icon :closable="false" class="mb-2" />
                        <div v-if="detailData.metadata.winning_applied_at" class="text-success">
                            {{ t('pricing_experiments_page.winner.applied_at', { time: formatTime(detailData.metadata.winning_applied_at) }) }}
                        </div>
                        <el-descriptions :column="1" border size="small" class="mt-2">
                            <el-descriptions-item :label="t('pricing_experiments_page.winner.recommended_config')">
                                <pre class="json-block">{{ JSON.stringify(detailData.metadata.winning_recommendation.winning_config, null, 2) }}</pre>
                            </el-descriptions-item>
                            <el-descriptions-item :label="t('pricing_experiments_page.winner.reason')">{{ detailData.metadata.winning_recommendation.reason }}</el-descriptions-item>
                        </el-descriptions>
                    </template>
                </template>

                <template v-if="detailData.control_config || detailData.treatment_config">
                    <el-divider />
                    <el-row :gutter="16">
                        <el-col :span="12">
                            <h4>{{ t('pricing_experiments_page.detail.control_config') }}</h4>
                            <pre class="json-block">{{ JSON.stringify(detailData.control_config, null, 2) }}</pre>
                        </el-col>
                        <el-col :span="12">
                            <h4>{{ t('pricing_experiments_page.detail.treatment_config') }}</h4>
                            <pre class="json-block">{{ JSON.stringify(detailData.treatment_config, null, 2) }}</pre>
                        </el-col>
                    </el-row>
                </template>

                <template v-if="detailData.participants?.length">
                    <el-divider />
                    <h4>{{ t('pricing_experiments_page.detail.participants_title') }} ({{ detailData.participants.length }})</h4>
                    <el-table :data="detailData.participants" size="small" max-height="200">
                        <el-table-column prop="customer_id" :label="t('pricing_experiments_page.detail.customer_id')" width="80" />
                        <el-table-column prop="group" :label="t('pricing_experiments_page.detail.group')" width="80">
                            <template #default="{ row }">
                                <el-tag :type="row.group === 'treatment' ? 'warning' : 'info'" size="small">
                                    {{ groupLabel(row.group) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="original_price" :label="t('pricing_experiments_page.detail.original_price')" width="100" />
                        <el-table-column prop="experiment_price" :label="t('pricing_experiments_page.detail.experiment_price')" width="100" />
                        <el-table-column prop="revenue_impact" :label="t('pricing_experiments_page.detail.revenue_impact')" width="100" />
                        <el-table-column prop="assigned_at" :label="t('pricing_experiments_page.detail.assigned_at')" width="160">
                            <template #default="{ row }">{{ formatTime(row.assigned_at) }}</template>
                        </el-table-column>
                    </el-table>
                </template>
            </template>
        </el-dialog>

        <el-dialog v-model="showRecommendations" :title="t('pricing_experiments_page.recommendations.title')" width="800px">
            <template v-if="recommendations.length === 0">
                <el-empty :description="t('pricing_experiments_page.recommendations.empty')" />
            </template>
            <template v-else>
                <el-alert type="info" :closable="false" class="mb-2">
                    <template #title>
                        {{ t('pricing_experiments_page.recommendations.summary', { total: recommendations.length, significant: significantCount }) }}
                    </template>
                </el-alert>
                <el-table :data="recommendations" stripe size="small">
                    <el-table-column :label="t('pricing_experiments_page.cols.experiment_name')" min-width="160">
                        <template #default="{ row }">
                            <div class="exp-name">{{ row.experiment_name }}</div>
                            <small class="text-muted">{{ row.experiment_type }}</small>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('pricing_experiments_page.cols.significance')" width="90" align="center">
                        <template #default="{ row }">
                            <el-tag :type="row.is_significant ? 'success' : 'info'" size="small">
                                {{ row.is_significant ? t('pricing_experiments_page.significance.significant') : t('pricing_experiments_page.significance.not_significant') }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('pricing_experiments_page.cols.conversion_change')" width="130" align="center">
                        <template #default="{ row }">
                            <span :class="row.improvement_rate > 0 ? 'text-success' : (row.improvement_rate < 0 ? 'text-danger' : '')">
                                {{ formatPercent(row.improvement_rate) }}
                            </span>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('pricing_experiments_page.cols.revenue_impact')" width="130" align="center">
                        <template #default="{ row }">
                            <span :class="row.revenue_impact > 0 ? 'text-success' : (row.revenue_impact < 0 ? 'text-danger' : '')">
                                {{ formatMoney(row.revenue_impact) }}
                            </span>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('pricing_experiments_page.cols.priority')" width="80" align="center">
                        <template #default="{ row }">
                            <el-tag :type="row.priority === 'high' ? 'danger' : (row.priority === 'medium' ? 'warning' : 'info')" size="small">
                                {{ priorityLabel(row.priority) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('pricing_experiments_page.cols.suggestion')" min-width="220">
                        <template #default="{ row }">
                            <small>{{ row.suggestion }}</small>
                        </template>
                    </el-table-column>
                </el-table>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh, Plus, DataAnalysis, Check } from '@element-plus/icons-vue';
import dynamicPricingApi from '@/api/dynamicPricing';

const { t, locale } = useI18n();

const loading = ref(false);
const creating = ref(false);
const showCreateDialog = ref(false);
const showDetailDialog = ref(false);

const experiments = ref([]);
const detailData = ref(null);

const stats = reactive({
    total: 0, running: 0, completed: 0, draft: 0, paused: 0, total_participants: 0,
});

const filters = reactive({
    status: '', experiment_type: '', search: '',
});

const pagination = reactive({
    current_page: 1, total: 0, per_page: 20,
});

const createForm = ref({
    name: '', description: '', experiment_type: 'pricing', target_metric: 'conversion',
    traffic_split: 50, minimum_sample_size: 100, confidence_level: 95,
    controlConfigStr: '', treatmentConfigStr: '', starts_at: null, ends_at: null,
    segment_filters: {
        region: [], channel: [], customer_tier: [], industry: [],
        new_vs_returning: [], device_type: [],
    },
});

const recommendations = ref([]);
const showRecommendations = ref(false);
const loadingRecs = ref(false);

const statusKeys = ['draft', 'running', 'paused', 'completed', 'cancelled'];
const experimentTypeKeys = ['pricing', 'discount', 'bundle', 'tier', 'promotion'];
const targetMetricKeys = ['conversion', 'revenue', 'retention', 'profit'];
const regionKeys = ['china', 'hk_mo_tw', 'north_america', 'europe', 'southeast_asia', 'jp_kr', 'other'];
const channelKeys = ['direct', 'seo', 'sem', 'social', 'email', 'agent', 'affiliate'];
const tierKeys = ['free', 'basic', 'pro', 'enterprise', 'vip'];
const industryKeys = ['tech', 'finance', 'education', 'healthcare', 'manufacturing', 'gaming', 'ecommerce'];
const customerTypeKeys = ['new', 'returning'];
const deviceTypeKeys = [
    { value: 'windows', label: 'Windows' },
    { value: 'macos', label: 'macOS' },
    { value: 'linux', label: 'Linux' },
    { value: 'ios', label: 'iOS' },
    { value: 'android', label: 'Android' },
    { value: 'web', label: 'Web' },
];
const priorityKeys = ['high', 'medium', 'low', 'need_more_data'];

const statusMap = computed(() =>
    Object.fromEntries(statusKeys.map((key) => [key, t(`pricing_experiments_page.status.${key}`)]))
);
const priorityMap = computed(() =>
    Object.fromEntries(priorityKeys.map((key) => [key, t(`pricing_experiments_page.priority.${key}`)]))
);

const statusOptions = computed(() =>
    statusKeys.map((key) => ({ value: key, label: t(`pricing_experiments_page.status.${key}`) }))
);
const experimentTypeOptions = computed(() =>
    experimentTypeKeys.map((key) => ({ value: key, label: t(`pricing_experiments_page.experiment_type.${key}`) }))
);
const targetMetricOptions = computed(() =>
    targetMetricKeys.map((key) => ({ value: key, label: t(`pricing_experiments_page.target_metric.${key}`) }))
);
const regionOptions = computed(() =>
    regionKeys.map((key) => ({ value: key, label: t(`pricing_experiments_page.segment.regions.${key}`) }))
);
const channelOptions = computed(() =>
    channelKeys.map((key) => ({ value: key, label: t(`pricing_experiments_page.segment.channels.${key}`) }))
);
const tierOptions = computed(() =>
    tierKeys.map((key) => ({ value: key, label: t(`pricing_experiments_page.segment.tiers.${key}`) }))
);
const industryOptions = computed(() =>
    industryKeys.map((key) => ({ value: key, label: t(`pricing_experiments_page.segment.industries.${key}`) }))
);
const customerTypeOptions = computed(() =>
    customerTypeKeys.map((key) => ({ value: key, label: t(`pricing_experiments_page.segment.customer_type.${key}`) }))
);
const deviceTypeOptions = computed(() => deviceTypeKeys);

const significantCount = computed(() => recommendations.value.filter((r) => r.is_significant).length);

const dateLocale = computed(() => (locale.value === 'zh_CN' ? 'zh-CN' : 'en-US'));

function statusLabel(s) {
    return statusMap.value[s] || s;
}

function priorityLabel(p) {
    return priorityMap.value[p] || p;
}

function groupLabel(g) {
    return g === 'treatment'
        ? t('pricing_experiments_page.group.treatment')
        : t('pricing_experiments_page.group.control');
}

function statusType(s) {
    const map = { draft: 'info', running: 'success', paused: 'warning', completed: '', cancelled: 'info' };
    return map[s] || 'info';
}

function formatTime(tVal) {
    return tVal ? new Date(tVal).toLocaleString(dateLocale.value) : '-';
}

async function loadAll() {
    loading.value = true;
    try {
        const [listRes, statsRes] = await Promise.all([
            dynamicPricingApi.getExperiments({ ...filters, page: pagination.current_page, per_page: pagination.per_page }),
            dynamicPricingApi.getExperimentStats(),
        ]);
        const d = listRes.data;
        const listData = d?.data || {};
        experiments.value = listData.data || (Array.isArray(listData) ? listData : []);
        pagination.current_page = listData.current_page || 1;
        pagination.total = listData.total || experiments.value.length;
        Object.assign(stats, statsRes.data?.data || {});
    } catch (err) {
        console.error(err);
    } finally {
        loading.value = false;
    }
}

async function loadList() {
    pagination.current_page = 1;
    await loadAll();
}

function resetFilters() {
    filters.status = '';
    filters.experiment_type = '';
    filters.search = '';
    loadList();
}

function onPageChange(page) {
    pagination.current_page = page;
    loadAll();
}

async function doCreate() {
    if (!createForm.value.name) {
        ElMessage.warning(t('pricing_experiments_page.messages.name_required'));
        return;
    }
    creating.value = true;
    try {
        const payload = {
            name: createForm.value.name,
            description: createForm.value.description,
            experiment_type: createForm.value.experiment_type,
            target_metric: createForm.value.target_metric,
            traffic_split: createForm.value.traffic_split,
            minimum_sample_size: createForm.value.minimum_sample_size,
            confidence_level: createForm.value.confidence_level,
            starts_at: createForm.value.starts_at,
            ends_at: createForm.value.ends_at,
        };
        const sf = createForm.value.segment_filters;
        const hasSegments = Object.values(sf).some(v => v && v.length > 0);
        if (hasSegments) {
            payload.segment_filters = {};
            Object.entries(sf).forEach(([key, val]) => {
                if (val && val.length > 0) payload.segment_filters[key] = val;
            });
        }
        if (createForm.value.controlConfigStr) {
            try { payload.control_config = JSON.parse(createForm.value.controlConfigStr); } catch { ElMessage.warning(t('pricing_experiments_page.messages.control_json_error')); creating.value = false; return; }
        }
        if (createForm.value.treatmentConfigStr) {
            try { payload.treatment_config = JSON.parse(createForm.value.treatmentConfigStr); } catch { ElMessage.warning(t('pricing_experiments_page.messages.treatment_json_error')); creating.value = false; return; }
        }
        await dynamicPricingApi.createExperiment(payload);
        ElMessage.success(t('pricing_experiments_page.messages.draft_created'));
        showCreateDialog.value = false;
        createForm.value = { name: '', description: '', experiment_type: 'pricing', target_metric: 'conversion', traffic_split: 50, minimum_sample_size: 100, confidence_level: 95, controlConfigStr: '', treatmentConfigStr: '', starts_at: null, ends_at: null, segment_filters: { region: [], channel: [], customer_tier: [], industry: [], new_vs_returning: [], device_type: [] } };
        await loadAll();
    } catch (err) {
        console.error(err);
    } finally {
        creating.value = false;
    }
}

async function viewDetail(row) {
    showDetailDialog.value = true;
    detailData.value = null;
    try {
        const res = await dynamicPricingApi.getExperiment(row.id);
        detailData.value = res.data?.data;
    } catch (err) {
        console.error(err);
    }
}

async function startExp(row) {
    try {
        await dynamicPricingApi.startExperiment(row.id);
        ElMessage.success(t('pricing_experiments_page.messages.started'));
        await loadAll();
    } catch (err) {
        console.error(err);
    }
}

async function pauseExp(row) {
    try {
        await dynamicPricingApi.pauseExperiment(row.id);
        ElMessage.success(t('pricing_experiments_page.messages.paused'));
        await loadAll();
    } catch (err) {
        console.error(err);
    }
}

async function completeExp(row) {
    try {
        await ElMessageBox.confirm(t('pricing_experiments_page.confirm.complete', { name: row.name }), t('actions.confirm'), { type: 'warning' });
        await dynamicPricingApi.completeExperiment(row.id);
        ElMessage.success(t('pricing_experiments_page.messages.completed'));
        await loadAll();
    } catch (err) {}
}

async function calculateExp(row) {
    try {
        await dynamicPricingApi.calculateResults(row.id);
        ElMessage.success(t('pricing_experiments_page.messages.recalculated'));
        await viewDetail(row);
        await loadAll();
    } catch (err) {
        console.error(err);
    }
}

async function deleteExp(row) {
    try {
        await ElMessageBox.confirm(t('pricing_experiments_page.confirm.delete', { name: row.name }), t('actions.confirm'), { type: 'warning' });
        await dynamicPricingApi.deleteExperiment(row.id);
        ElMessage.success(t('pricing_experiments_page.messages.deleted'));
        await loadAll();
    } catch (err) {}
}

function formatPercent(val) {
    if (val === null || val === undefined || val === 0) return '0%';
    return (val > 0 ? '+' : '') + val.toFixed(2) + '%';
}

function formatMoney(val) {
    if (val === null || val === undefined) {
        return locale.value === 'zh_CN' ? '¥0.00' : '$0.00';
    }
    const prefix = locale.value === 'zh_CN' ? '¥' : '$';
    return prefix + Number(val).toLocaleString(dateLocale.value, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

async function applyWinner(row) {
    try {
        await ElMessageBox.confirm(
            t('pricing_experiments_page.confirm.apply_winner', { name: row.name }),
            t('pricing_experiments_page.confirm.apply_winner_title'),
            { type: 'info', confirmButtonText: t('pricing_experiments_page.confirm.apply_winner_confirm'), cancelButtonText: t('actions.cancel') }
        );
        const res = await dynamicPricingApi.applyWinningTreatment(row.id);
        ElMessage.success(t('pricing_experiments_page.messages.winner_applied'));
        await viewDetail(row);
        await loadAll();
    } catch (err) {
        if (err !== 'cancel') console.error(err);
    }
}

async function loadRecommendations() {
    loadingRecs.value = true;
    try {
        const res = await dynamicPricingApi.getRecommendations();
        recommendations.value = res.data?.data?.recommendations || [];
        showRecommendations.value = true;
    } catch (err) {
        console.error(err);
        ElMessage.error(t('pricing_experiments_page.messages.load_recommendations_failed'));
    } finally {
        loadingRecs.value = false;
    }
}

async function showRecsPanel() {
    await loadRecommendations();
}

onMounted(loadAll);
</script>

<style scoped>
.pricing-experiments-page { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; flex-wrap: wrap; gap: 8px; flex-direction: column; }
.page-header h2 { margin: 0; font-size: 20px; }
.page-header .text-muted { margin: 4px 0 0; color: #909399; font-size: 13px; }
.header-actions { display: flex; gap: 8px; margin-top: 8px; }

.mb-4 { margin-bottom: 16px; }
.mt-2 { margin-top: 8px; }

.stat-label { font-size: 13px; color: #909399; margin-bottom: 4px; }
.stat-value { font-size: 22px; font-weight: 700; }
.stat-success .stat-value { color: #67c23a; }
.stat-info .stat-value { color: #0f172a; }
.stat-warning .stat-value { color: #e6a23c; }

.pagination-wrapper { display: flex; justify-content: flex-end; padding: 16px 0; }

.text-muted { color: #909399; font-size: 12px; }
.text-success { color: #67c23a; font-weight: 600; }
.exp-name { font-weight: 500; }

.json-block { background: #f5f7fa; padding: 12px; border-radius: 4px; font-size: 12px; overflow-x: auto; max-height: 200px; }

.d-flex { display: flex; }
.justify-between { justify-content: space-between; }
.align-center { align-items: center; }
.mb-2 { margin-bottom: 8px; }
.mb-3 { margin-bottom: 12px; }

.result-card { text-align: center; padding: 4px 0; border-radius: 8px; }
.result-label { font-size: 12px; color: #909399; margin-bottom: 4px; }
.result-value { font-size: 18px; font-weight: 700; margin-bottom: 4px; }
.result-sub { font-size: 11px; color: #c0c4cc; }
.control-card { border-left: 3px solid #0f172a; }
.treatment-card { border-left: 3px solid #e6a23c; }
.result-positive { border-left: 3px solid #67c23a; }
.result-negative { border-left: 3px solid #f56c6c; }
.text-danger { color: #f56c6c; font-weight: 600; }
</style>
