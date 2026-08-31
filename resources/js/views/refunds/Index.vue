<template>
    <div class="refund-center-page">
        <el-tabs v-model="refundCenterTab" type="border-card">
            <!-- ────────────────────────────────────────────── -->
            <!-- Tab 1: 退款管理（原 refunds 全部内容）           -->
            <!-- ────────────────────────────────────────────── -->
            <el-tab-pane :label="t('refunds_page.tab_refunds_center')" name="refunds">
                <div class="refunds-page">
                    <!-- 统计卡片 -->
                    <el-row :gutter="16" class="mb-4">
                        <el-col :span="4">
                            <el-card shadow="never" :body-style="{ padding: '16px' }">
                                <div class="stat-card">
                                    <div class="stat-value">{{ stats.total_refunds }}</div>
                                    <div class="stat-label">{{ t('refunds_page.stat_total_refunds') }}</div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="4">
                            <el-card shadow="never" :body-style="{ padding: '16px' }">
                                <div class="stat-card">
                                    <div class="stat-value">{{ formatAmount(stats.total_amount) }}</div>
                                    <div class="stat-label">{{ t('refunds_page.stat_total_amount') }}</div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="4">
                            <el-card shadow="never" :body-style="{ padding: '16px' }">
                                <div class="stat-card">
                                    <div class="stat-value">{{ formatAmount(stats.month_amount) }}</div>
                                    <div class="stat-label">{{ t('refunds_page.stat_month_amount') }}</div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="4">
                            <el-card shadow="never" :body-style="{ padding: '16px' }">
                                <div class="stat-card">
                                    <div class="stat-value">{{ formatAmount(stats.today_amount) }}</div>
                                    <div class="stat-label">{{ t('refunds_page.stat_today_amount') }}</div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="4">
                            <el-card shadow="never" :body-style="{ padding: '16px' }">
                                <div class="stat-card">
                                    <div class="stat-value">{{ riskStats.pending_review ?? 0 }}</div>
                                    <div class="stat-label warn">{{ t('refunds_page.stat_pending_review') }}</div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="4">
                            <el-card shadow="never" :body-style="{ padding: '16px' }">
                                <div class="stat-card">
                                    <div class="stat-value">{{ riskStats.total_assessments ?? 0 }}</div>
                                    <div class="stat-label">{{ t('refunds_page.stat_risk_assessments') }}</div>
                                </div>
                            </el-card>
                        </el-col>
                    </el-row>

                    <!-- 选项卡 -->
                    <el-tabs v-model="activeTab" type="border-card">
                        <!-- 退款列表 -->
                        <el-tab-pane :label="t('refunds_page.tab_refunds')" name="refunds">
                            <div class="toolbar">
                                <el-form :inline="true" :model="filters" size="small">
                                    <el-form-item>
                                        <el-input v-model="filters.search" :placeholder="t('refunds_page.search_ph')" clearable style="width:260px"
                                            @clear="fetchData" @keyup.enter="fetchData">
                                            <template #prefix><el-icon><Search /></el-icon></template>
                                        </el-input>
                                    </el-form-item>
                                    <el-form-item>
                                        <el-select v-model="filters['filter.status']" :placeholder="t('refunds_page.status')" clearable @change="fetchData" style="width:120px">
                                            <el-option v-for="opt in statusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                        </el-select>
                                    </el-form-item>
                                    <el-form-item>
                                        <el-button type="primary" @click="fetchData"><el-icon><Search /></el-icon> {{ t('actions.search') }}</el-button>
                                    </el-form-item>
                                    <el-form-item>
                                        <el-button type="success" @click="showCreateDialog = true"><el-icon><Plus /></el-icon> {{ t('refunds_page.create_refund') }}</el-button>
                                    </el-form-item>
                                </el-form>
                            </div>

                            <el-table :data="refunds" v-loading="loading" stripe>
                                <el-table-column prop="refund_no" :label="t('refunds_page.col_refund_no')" width="170">
                                    <template #default="{ row }"><code class="refund-no">{{ row.refund_no }}</code></template>
                                </el-table-column>
                                <el-table-column label="License" min-width="140">
                                    <template #default="{ row }">{{ row.license?.license_key || '-' }}</template>
                                </el-table-column>
                                <el-table-column :label="t('refunds_page.col_customer')" min-width="130">
                                    <template #default="{ row }">{{ row.customer?.name || '-' }}</template>
                                </el-table-column>
                                <el-table-column :label="t('refunds_page.col_amount')" width="110">
                                    <template #default="{ row }">
                                        <span class="amount-text">{{ row.currency }} {{ formatAmount(row.amount) }}</span>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="t('refunds_page.col_type')" width="70">
                                    <template #default="{ row }">
                                        <el-tag v-if="row.refund_type === 'partial'" type="warning" size="small">{{ t('refunds_page.type_partial') }}</el-tag>
                                        <el-tag v-else type="danger" size="small">{{ t('refunds_page.type_full') }}</el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="t('refunds_page.col_reason')" min-width="160" show-overflow-tooltip>
                                    <template #default="{ row }">{{ row.reason || '-' }}</template>
                                </el-table-column>
                                <el-table-column :label="t('refunds_page.col_risk_decision')" width="120">
                                    <template #default="{ row }">
                                        <el-tag v-if="row.auto_decision" :type="decisionType(row.auto_decision)" size="small">
                                            {{ decisionLabel(row.auto_decision) }}
                                        </el-tag>
                                        <span v-else class="no-data">-</span>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="t('refunds_page.col_status')" width="90">
                                    <template #default="{ row }">
                                        <el-tag :type="statusType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column prop="created_at" :label="t('refunds_page.col_time')" width="160" />
                                <el-table-column :label="t('refunds_page.col_actions')" width="120" fixed="right">
                                    <template #default="{ row }">
                                        <el-button text size="small" type="primary" @click="openDetail(row)">{{ t('refunds_page.detail') }}</el-button>
                                        <el-button v-if="row.status === 'pending'" text size="small" type="warning" @click="handleReview(row)">{{ t('refunds_page.review') }}</el-button>
                                    </template>
                                </el-table-column>
                            </el-table>

                            <div class="pagination-wrap">
                                <el-pagination v-model:current-page="page" v-model:page-size="perPage" :total="total"
                                    :page-sizes="[10, 20, 50]" layout="total, sizes, prev, pager, next" @change="fetchData" />
                            </div>
                        </el-tab-pane>

                        <!-- 风控看板 -->
                        <el-tab-pane :label="t('refunds_page.tab_risk_dashboard')" name="risk-dashboard">
                            <el-row :gutter="16">
                                <el-col :span="8">
                                    <el-card shadow="never">
                                        <template #header><span>{{ t('refunds_page.risk_by_level') }}</span></template>
                                        <div v-if="riskLevelChart.length" class="risk-chart">
                                            <div v-for="item in riskLevelChart" :key="item.name" class="risk-bar-item">
                                                <span class="risk-bar-label">{{ item.label }}</span>
                                                <el-progress :percentage="item.percent" :color="item.color" :stroke-width="18" />
                                            </div>
                                        </div>
                                        <div v-else class="empty-chart">{{ t('refunds_page.no_data') }}</div>
                                    </el-card>
                                </el-col>
                                <el-col :span="8">
                                    <el-card shadow="never">
                                        <template #header><span>{{ t('refunds_page.risk_by_decision') }}</span></template>
                                        <div v-if="riskDecisionChart.length" class="risk-chart">
                                            <div v-for="item in riskDecisionChart" :key="item.name" class="risk-bar-item">
                                                <span class="risk-bar-label">{{ item.label }}</span>
                                                <el-progress :percentage="item.percent" :stroke-width="18" />
                                            </div>
                                        </div>
                                        <div v-else class="empty-chart">{{ t('refunds_page.no_data') }}</div>
                                    </el-card>
                                </el-col>
                                <el-col :span="8">
                                    <el-card shadow="never">
                                        <template #header><span>{{ t('refunds_page.risk_rules') }}</span></template>
                                        <div v-loading="loadingRules">
                                            <div v-for="rule in riskRules" :key="rule.id" class="rule-item">
                                                <div class="rule-header">
                                                    <el-switch :model-value="rule.is_active" size="small" @change="v => handleToggleRule(rule, v)" />
                                                    <span class="rule-name">{{ rule.name }}</span>
                                                    <el-tag :type="ruleTypeTag(rule.rule_type)" size="small">{{ rule.rule_type }}</el-tag>
                                                </div>
                                            </div>
                                        </div>
                                    </el-card>
                                </el-col>
                            </el-row>
                        </el-tab-pane>
                    </el-tabs>

                    <!-- 详情对话框 -->
                    <el-dialog v-model="showDetail" :title="t('refunds_page.detail_title')" width="600px">
                        <el-descriptions v-if="detail" :column="2" border>
                            <el-descriptions-item :label="t('refunds_page.col_refund_no')">{{ detail.refund_no }}</el-descriptions-item>
                            <el-descriptions-item :label="t('refunds_page.col_status')"><el-tag :type="statusType(detail.status)" size="small">{{ statusLabel(detail.status) }}</el-tag></el-descriptions-item>
                            <el-descriptions-item :label="t('refunds_page.label_license_key')">{{ detail.license?.license_key || '-' }}</el-descriptions-item>
                            <el-descriptions-item :label="t('refunds_page.label_refund_type')">{{ detail.refund_type === 'partial' ? t('refunds_page.type_partial_refund') : t('refunds_page.type_full_refund') }}</el-descriptions-item>
                            <el-descriptions-item :label="t('refunds_page.col_customer')">{{ detail.customer?.name || '-' }}</el-descriptions-item>
                            <el-descriptions-item :label="t('refunds_page.label_email')">{{ detail.customer?.email || '-' }}</el-descriptions-item>
                            <el-descriptions-item :label="t('refunds_page.label_refund_amount')">{{ detail.currency }} {{ formatAmount(detail.amount) }}</el-descriptions-item>
                            <el-descriptions-item :label="t('refunds_page.label_payment_method')">{{ paymentMethodLabel(detail.payment_method) }}</el-descriptions-item>
                            <el-descriptions-item :label="t('refunds_page.col_risk_decision')" :span="2">
                                <el-tag v-if="detail.auto_decision" :type="decisionType(detail.auto_decision)" size="small">{{ decisionLabel(detail.auto_decision) }}</el-tag>
                                <span v-else class="no-data">{{ t('refunds_page.not_assessed') }}</span>
                            </el-descriptions-item>
                            <el-descriptions-item :label="t('refunds_page.label_processor')">{{ detail.processor?.name || '-' }}</el-descriptions-item>
                            <el-descriptions-item :label="t('refunds_page.label_completed_at')">{{ detail.completed_at || '-' }}</el-descriptions-item>
                            <el-descriptions-item :label="t('refunds_page.label_created_at')">{{ detail.created_at }}</el-descriptions-item>
                            <el-descriptions-item :label="t('refunds_page.label_payment_refund_id')">{{ detail.payment_refund_id || '-' }}</el-descriptions-item>
                            <el-descriptions-item v-if="detail.failure_reason" :label="t('refunds_page.label_failure_reason')" :span="2">{{ detail.failure_reason }}</el-descriptions-item>
                            <el-descriptions-item :label="t('refunds_page.label_refund_reason')" :span="2">{{ detail.reason || '-' }}</el-descriptions-item>
                        </el-descriptions>
                        <template #footer>
                            <el-button @click="showDetail = false">{{ t('actions.close') }}</el-button>
                        </template>
                    </el-dialog>

                    <!-- 创建退款对话框 -->
                    <el-dialog v-model="showCreateDialog" :title="t('refunds_page.create_title')" width="500px">
                        <el-form ref="createFormRef" :model="createForm" :rules="createRules" label-width="120px">
                            <el-form-item label="License" prop="license_id">
                                <el-select v-model="createForm.license_id" filterable remote :remote-method="searchLicenses"
                                    :loading="searchingLicense" style="width:100%">
                                    <el-option v-for="l in licenseOptions" :key="l.id" :label="`#${l.id} ${l.license_key}`" :value="l.id" />
                                </el-select>
                            </el-form-item>
                            <el-form-item :label="t('refunds_page.label_amount')" prop="amount">
                                <el-input-number v-model="createForm.amount" :min="0.01" :step="10" :precision="2" style="width:200px" />
                            </el-form-item>
                            <el-form-item :label="t('refunds_page.label_refund_type')" prop="refund_type">
                                <el-select v-model="createForm.refund_type" style="width:200px">
                                    <el-option v-for="opt in refundTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                </el-select>
                            </el-form-item>
                            <el-form-item :label="t('refunds_page.label_payment_method')" prop="payment_method">
                                <el-select v-model="createForm.payment_method" style="width:200px">
                                    <el-option v-for="opt in paymentMethodOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                </el-select>
                            </el-form-item>
                            <el-form-item :label="t('refunds_page.col_reason')" prop="reason">
                                <el-input v-model="createForm.reason" type="textarea" :rows="3" maxlength="500" show-word-limit />
                            </el-form-item>
                        </el-form>
                        <template #footer>
                            <el-button @click="showCreateDialog = false">{{ t('actions.cancel') }}</el-button>
                            <el-button type="primary" :loading="creating" @click="handleCreateWithRisk">{{ t('refunds_page.submit_risk') }}</el-button>
                        </template>
                    </el-dialog>

                    <!-- 审核对话框 -->
                    <el-dialog v-model="showReviewDialog" :title="t('refunds_page.review_title')" width="450px">
                        <el-alert v-if="reviewRefund"
                            :title="t('refunds_page.review_alert', { no: reviewRefund.refund_no, amount: `${reviewRefund.currency} ${formatAmount(reviewRefund.amount)}` })"
                            type="info" show-icon style="margin-bottom:16px" />
                        <el-radio-group v-model="reviewAction" style="margin-bottom:16px">
                            <el-radio-button value="approve">{{ t('actions.approve') }}</el-radio-button>
                            <el-radio-button value="reject">{{ t('actions.reject') }}</el-radio-button>
                        </el-radio-group>
                        <el-input v-model="reviewNote" type="textarea" :rows="3" :placeholder="t('refunds_page.review_note_ph')" />
                        <template #footer>
                            <el-button @click="showReviewDialog = false">{{ t('actions.cancel') }}</el-button>
                            <el-button :type="reviewAction === 'approve' ? 'primary' : 'danger'" :loading="reviewing" @click="handleDoReview">
                                {{ reviewAction === 'approve' ? t('refunds_page.approve_refund') : t('refunds_page.reject_refund') }}
                            </el-button>
                        </template>
                    </el-dialog>
                </div>
            </el-tab-pane>

            <!-- ────────────────────────────────────────────── -->
            <!-- Tab 2: 退款审核（原 refund-workflow 全部内容）   -->
            <!-- ────────────────────────────────────────────── -->
            <el-tab-pane :label="t('refund_workflow_page.title')" name="workflow">
                <div class="rw-content" v-if="rw_tabVisited">
                    <h2>{{ t('refund_workflow_page.title') }}</h2>

                    <!-- 统计卡片 -->
                    <el-row :gutter="16" class="mb-4">
                        <el-col :span="4">
                            <el-card shadow="never">
                                <div class="rw-stat-value">{{ rw_stats.total ?? 0 }}</div>
                                <div class="rw-stat-label">{{ t('refund_workflow_page.stats.total') }}</div>
                            </el-card>
                        </el-col>
                        <el-col :span="4">
                            <el-card shadow="never">
                                <div class="rw-stat-value text-warning">{{ rw_stats.pending ?? 0 }}</div>
                                <div class="rw-stat-label">{{ t('refund_workflow_page.stats.pending') }}</div>
                            </el-card>
                        </el-col>
                        <el-col :span="4">
                            <el-card shadow="never">
                                <div class="rw-stat-value text-primary">{{ rw_stats.completed ?? 0 }}</div>
                                <div class="rw-stat-label">{{ t('refund_workflow_page.stats.completed') }}</div>
                            </el-card>
                        </el-col>
                        <el-col :span="4">
                            <el-card shadow="never">
                                <div class="rw-stat-value text-danger">{{ rw_stats.rejected ?? 0 }}</div>
                                <div class="rw-stat-label">{{ t('refund_workflow_page.stats.rejected') }}</div>
                            </el-card>
                        </el-col>
                        <el-col :span="4">
                            <el-card shadow="never">
                                <div class="rw-stat-value">¥{{ (rw_stats.total_amount ?? 0).toFixed(2) }}</div>
                                <div class="rw-stat-label">{{ t('refund_workflow_page.stats.total_amount') }}</div>
                            </el-card>
                        </el-col>
                        <el-col :span="4">
                            <el-card shadow="never">
                                <div class="rw-stat-value">{{ rw_stats.avg_refund_time_hours ?? 0 }}h</div>
                                <div class="rw-stat-label">{{ t('refund_workflow_page.stats.avg_time') }}</div>
                            </el-card>
                        </el-col>
                    </el-row>

                    <!-- 过滤器 -->
                    <el-card shadow="never" class="mb-4">
                        <el-form :inline="true" :model="rw_filters" size="small">
                            <el-form-item :label="t('refund_workflow_page.filter.status')">
                                <el-select v-model="rw_filters.status" clearable :placeholder="t('refund_workflow_page.filter.all')">
                                    <el-option v-for="opt in rw_statusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                </el-select>
                            </el-form-item>
                            <el-form-item :label="t('actions.search')">
                                <el-input v-model="rw_filters.search" :placeholder="t('refund_workflow_page.filter.search_ph')" clearable @keyup.enter="rw_fetchRefunds" />
                            </el-form-item>
                            <el-form-item>
                                <el-button type="primary" @click="rw_fetchRefunds">{{ t('actions.search') }}</el-button>
                            </el-form-item>
                        </el-form>
                    </el-card>

                    <!-- 退款列表 -->
                    <el-card shadow="never">
                        <el-table :data="rw_refunds" v-loading="rw_loading" stripe>
                            <el-table-column prop="refund_no" :label="t('refund_workflow_page.col_refund_no')" width="160" />
                            <el-table-column prop="order.order_no" :label="t('refund_workflow_page.col_order_no')" width="160" />
                            <el-table-column :label="t('refund_workflow_page.col_amount')" width="120" align="center">
                                <template #default="{ row }">¥{{ (row.amount ?? 0).toFixed(2) }}</template>
                            </el-table-column>
                            <el-table-column prop="reason" :label="t('refund_workflow_page.col_reason')" min-width="160" show-overflow-tooltip />
                            <el-table-column prop="status" :label="t('refund_workflow_page.col_status')" width="100" align="center">
                                <template #default="{ row }">
                                    <el-tag :type="rw_statusTag(row.status)" size="small">{{ rw_statusLabel(row.status) }}</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column prop="created_at" :label="t('refund_workflow_page.col_time')" width="160" />
                            <el-table-column :label="t('refund_workflow_page.col_actions')" width="180" fixed="right">
                                <template #default="{ row }">
                                    <el-button v-if="row.status === 'pending'" type="primary" size="small" @click="rw_openReview(row, 'approve')">
                                        {{ t('actions.approve') }}
                                    </el-button>
                                    <el-button v-if="row.status === 'pending'" type="danger" size="small" @click="rw_openReview(row, 'reject')">
                                        {{ t('actions.reject') }}
                                    </el-button>
                                    <span v-else class="text-gray text-sm">{{ rw_statusLabel(row.status) }}</span>
                                </template>
                            </el-table-column>
                        </el-table>
                        <div class="rw-pagination-wrap">
                            <el-pagination
                                v-model:current-page="rw_page"
                                :page-size="rw_perPage"
                                :total="rw_total"
                                layout="prev, pager, next"
                                small
                                @current-change="rw_fetchRefunds"
                            />
                        </div>
                    </el-card>

                    <!-- 审核对话框 -->
                    <el-dialog
                        v-model="rw_reviewVisible"
                        :title="rw_reviewAction === 'approve' ? t('refund_workflow_page.dialog.approve_title') : t('refund_workflow_page.dialog.reject_title')"
                        width="500px"
                    >
                        <el-form ref="rw_reviewFormRef" :model="rw_reviewData" :rules="rw_reviewRules" label-width="100px">
                            <el-form-item :label="t('refund_workflow_page.dialog.refund_no')">{{ rw_reviewTarget?.refund_no }}</el-form-item>
                            <el-form-item :label="t('refund_workflow_page.dialog.amount')">¥{{ (rw_reviewTarget?.amount ?? 0).toFixed(2) }}</el-form-item>
                            <el-form-item :label="t('refund_workflow_page.dialog.reason')">{{ rw_reviewTarget?.reason }}</el-form-item>
                            <el-form-item v-if="rw_reviewAction === 'reject'" :label="t('refund_workflow_page.dialog.reject_reason')" prop="reason">
                                <el-input v-model="rw_reviewData.reason" type="textarea" :rows="3" :placeholder="t('refund_workflow_page.dialog.reject_reason_ph')" maxlength="500" />
                            </el-form-item>
                            <el-form-item :label="t('refund_workflow_page.dialog.notes')" prop="notes">
                                <el-input v-model="rw_reviewData.notes" type="textarea" :rows="2" :placeholder="t('refund_workflow_page.dialog.notes_ph')" maxlength="1000" />
                            </el-form-item>
                        </el-form>
                        <template #footer>
                            <el-button @click="rw_reviewVisible = false">{{ t('actions.cancel') }}</el-button>
                            <el-button type="primary" :loading="rw_reviewSubmitting" @click="rw_submitReview">
                                {{ rw_reviewAction === 'approve' ? t('refund_workflow_page.dialog.confirm_approve') : t('refund_workflow_page.dialog.confirm_reject') }}
                            </el-button>
                        </template>
                    </el-dialog>
                </div>
            </el-tab-pane>

            <!-- ────────────────────────────────────────────── -->
            <!-- Tab 3: 售后工单（原 order-after-sales 全部内容） -->
            <!-- ────────────────────────────────────────────── -->
            <el-tab-pane :label="t('order_after_sales_page.title')" name="tickets">
                <div class="oas-content" v-if="oas_tabVisited">
                    <div class="oas-page-header">
                        <h2>
                            <el-icon style="vertical-align:middle;margin-right:8px"><Service /></el-icon>
                            {{ t('order_after_sales_page.title') }}
                        </h2>
                        <div class="oas-header-actions">
                            <el-button type="primary" @click="oas_openCreateDialog" :loading="oas_submitting">
                                <el-icon><Plus /></el-icon> {{ t('order_after_sales_page.create_ticket') }}
                            </el-button>
                            <el-button @click="oas_refreshAll" :loading="oas_loading">
                                <el-icon><Refresh /></el-icon> {{ t('order_after_sales_page.refresh') }}
                            </el-button>
                        </div>
                    </div>

                    <!-- 统计卡片 -->
                    <el-row :gutter="16" class="mb-4">
                        <el-col :span="4">
                            <el-card shadow="hover" class="oas-stat-card">
                                <div class="oas-stat-value">{{ oas_stats.total }}</div>
                                <div class="oas-stat-label">{{ t('order_after_sales_page.stat_total') }}</div>
                            </el-card>
                        </el-col>
                        <el-col :span="4">
                            <el-card shadow="hover" class="oas-stat-card">
                                <div class="oas-stat-value stat-danger">{{ oas_stats.open }}</div>
                                <div class="oas-stat-label">{{ t('tickets_page.st_open') }}</div>
                            </el-card>
                        </el-col>
                        <el-col :span="4">
                            <el-card shadow="hover" class="oas-stat-card">
                                <div class="oas-stat-value stat-warning">{{ oas_stats.by_priority?.urgent || 0 }}</div>
                                <div class="oas-stat-label">{{ t('order_after_sales_page.stat_urgent_tickets') }}</div>
                            </el-card>
                        </el-col>
                        <el-col :span="4">
                            <el-card shadow="hover" class="oas-stat-card">
                                <div class="oas-stat-value stat-success">{{ oas_stats.resolved }}</div>
                                <div class="oas-stat-label">{{ t('tickets_page.st_resolved') }}</div>
                            </el-card>
                        </el-col>
                        <el-col :span="4">
                            <el-card shadow="hover" class="oas-stat-card">
                                <div class="oas-stat-value">{{ oas_stats.closed }}</div>
                                <div class="oas-stat-label">{{ t('tickets_page.st_closed') }}</div>
                            </el-card>
                        </el-col>
                        <el-col :span="4">
                            <el-card shadow="hover" class="oas-stat-card">
                                <div class="oas-stat-value">{{ oas_stats.avg_response_time ? t('order_after_sales_page.stat_avg_response_min', { n: Math.round(oas_stats.avg_response_time) }) : '-' }}</div>
                                <div class="oas-stat-label">{{ t('order_after_sales_page.stat_avg_response') }}</div>
                            </el-card>
                        </el-col>
                    </el-row>

                    <!-- 搜索/筛选栏 -->
                    <el-card shadow="hover" class="mb-4">
                        <el-form :model="oas_filters" inline>
                            <el-form-item :label="t('tickets_page.status')">
                                <el-select v-model="oas_filters.status" clearable :placeholder="t('tickets_page.all_status')" style="width:130px">
                                    <el-option v-for="opt in oas_statusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                </el-select>
                            </el-form-item>
                            <el-form-item :label="t('tickets_page.priority')">
                                <el-select v-model="oas_filters.priority" clearable :placeholder="t('tickets_page.all_priority')" style="width:120px">
                                    <el-option v-for="opt in oas_priorityOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                </el-select>
                            </el-form-item>
                            <el-form-item :label="t('order_after_sales_page.filter_reason')">
                                <el-select v-model="oas_filters.reason" clearable :placeholder="t('order_after_sales_page.all_reasons')" style="width:120px">
                                    <el-option v-for="(cfg, key) in oas_reasons" :key="key" :label="cfg.label" :value="key" />
                                </el-select>
                            </el-form-item>
                            <el-form-item :label="t('order_after_sales_page.order_no')">
                                <el-input v-model="oas_filters.order_id" :placeholder="t('order_after_sales_page.order_id_ph')" style="width:120px" clearable />
                            </el-form-item>
                            <el-form-item :label="t('order_after_sales_page.filter_keyword')">
                                <el-input v-model="oas_filters.keyword" :placeholder="t('order_after_sales_page.keyword_ph')" style="width:200px" clearable @keyup.enter="oas_loadList" />
                            </el-form-item>
                            <el-form-item>
                                <el-button type="primary" @click="oas_loadList">{{ t('actions.search') }}</el-button>
                                <el-button @click="oas_resetFilters">{{ t('actions.reset') }}</el-button>
                            </el-form-item>
                        </el-form>
                    </el-card>

                    <!-- 工单列表 -->
                    <el-card shadow="hover">
                        <el-table :data="oas_tickets" v-loading="oas_loading" stripe @row-click="oas_openDetail">
                            <el-table-column type="index" label="#" width="50" />
                            <el-table-column prop="id" :label="t('order_after_sales_page.col_ticket_no')" width="80" />
                            <el-table-column :label="t('order_after_sales_page.col_subject')" min-width="200" show-overflow-tooltip>
                                <template #default="{ row }">
                                    <span class="oas-subject-text">{{ row.subject }}</span>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t('tickets_page.col_customer')" width="120">
                                <template #default="{ row }">{{ row.customer?.name || '-' }}</template>
                            </el-table-column>
                            <el-table-column :label="t('tickets_page.col_priority')" width="80">
                                <template #default="{ row }">
                                    <el-tag :type="oas_priorityType(row.priority)" size="small">{{ oas_priorityLabel(row.priority) }}</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t('tickets_page.col_status')" width="90">
                                <template #default="{ row }">
                                    <el-tag :type="oas_statusType(row.status)" size="small">{{ oas_statusLabel(row.status) }}</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t('order_after_sales_page.col_agent')" width="100">
                                <template #default="{ row }">{{ row.assignee?.name || t('tickets_page.unassigned') }}</template>
                            </el-table-column>
                            <el-table-column :label="t('order_after_sales_page.col_rating')" width="70">
                                <template #default="{ row }">
                                    <span v-if="row.satisfaction" class="score-stars">{{ '★'.repeat(row.satisfaction.score) }}</span>
                                    <span v-else class="oas-text-muted">-</span>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t('tickets_page.col_created')" width="160">
                                <template #default="{ row }">{{ row.created_at }}</template>
                            </el-table-column>
                            <el-table-column :label="t('tickets_page.col_actions')" width="180" fixed="right">
                                <template #default="{ row }">
                                    <el-button text type="primary" size="small" @click.stop="oas_openDetail(row)">{{ t('refunds_page.detail') }}</el-button>
                                    <el-button
                                        v-if="row.status === 'open' || row.status === 'in_progress' || row.status === 'replied'"
                                        text type="success" size="small" @click.stop="oas_handleResolve(row)"
                                    >{{ t('order_after_sales_page.resolve') }}</el-button>
                                    <el-button
                                        v-if="row.status !== 'closed'"
                                        text type="info" size="small" @click.stop="oas_handleClose(row)"
                                    >{{ t('tickets_page.close') }}</el-button>
                                </template>
                            </el-table-column>
                        </el-table>
                        <div class="oas-pagination-wrap">
                            <el-pagination
                                v-model:current-page="oas_page"
                                :page-size="oas_pageSize"
                                :total="oas_total"
                                layout="total, prev, pager, next"
                                @current-change="oas_loadList"
                            />
                        </div>
                    </el-card>

                    <!-- 创建工单对话框 -->
                    <el-dialog v-model="oas_createVisible" :title="t('order_after_sales_page.create_dialog_title')" width="600px">
                        <el-form :model="oas_form" label-width="100px">
                            <el-form-item :label="t('order_after_sales_page.label_order')" prop="order_id" required>
                                <el-select v-model="oas_form.order_id" filterable style="width:100%" :placeholder="t('order_after_sales_page.search_order_ph')">
                                    <el-option v-for="o in oas_orders" :key="o.id" :label="`#${o.id} - ¥${o.total_amount} (${o.status})`" :value="o.id" />
                                </el-select>
                            </el-form-item>
                            <el-form-item :label="t('tickets_page.col_customer')" prop="customer_id" required>
                                <el-select v-model="oas_form.customer_id" filterable style="width:100%" :placeholder="t('order_after_sales_page.select_customer_ph')">
                                    <el-option v-for="c in oas_customers" :key="c.id" :label="c.name || c.company || `ID:${c.id}`" :value="c.id" />
                                </el-select>
                            </el-form-item>
                            <el-form-item :label="t('order_after_sales_page.label_reason')" prop="reason" required>
                                <el-select v-model="oas_form.reason" style="width:100%">
                                    <el-option v-for="(cfg, key) in oas_reasons" :key="key" :label="cfg.label" :value="key" />
                                </el-select>
                            </el-form-item>
                            <el-form-item :label="t('order_after_sales_page.label_description')" required>
                                <el-input v-model="oas_form.description" type="textarea" :rows="4" :placeholder="t('order_after_sales_page.description_ph')" />
                            </el-form-item>
                        </el-form>
                        <template #footer>
                            <el-button @click="oas_createVisible = false">{{ t('actions.cancel') }}</el-button>
                            <el-button type="primary" @click="oas_handleCreateSubmit" :loading="oas_submitting">{{ t('actions.submit') }}</el-button>
                        </template>
                    </el-dialog>

                    <!-- 工单详情抽屉 -->
                    <el-drawer v-model="oas_detailVisible" :title="oas_detailTicket?.subject" size="600px" direction="rtl">
                        <template v-if="oas_detailTicket">
                            <div class="oas-detail-section">
                                <el-descriptions :column="2" border size="small">
                                    <el-descriptions-item :label="t('order_after_sales_page.col_ticket_no')">{{ oas_detailTicket.id }}</el-descriptions-item>
                                    <el-descriptions-item :label="t('order_after_sales_page.order_no')">#{{ oas_detailTicket.metadata?.order_id }}</el-descriptions-item>
                                    <el-descriptions-item :label="t('tickets_page.col_priority')">
                                        <el-tag :type="oas_priorityType(oas_detailTicket.priority)" size="small">{{ oas_priorityLabel(oas_detailTicket.priority) }}</el-tag>
                                    </el-descriptions-item>
                                    <el-descriptions-item :label="t('tickets_page.col_status')">
                                        <el-tag :type="oas_statusType(oas_detailTicket.status)" size="small">{{ oas_statusLabel(oas_detailTicket.status) }}</el-tag>
                                    </el-descriptions-item>
                                    <el-descriptions-item :label="t('tickets_page.col_customer')">{{ oas_detailTicket.customer?.name || '-' }}</el-descriptions-item>
                                    <el-descriptions-item :label="t('order_after_sales_page.col_agent')">{{ oas_detailTicket.assignee?.name || t('tickets_page.unassigned') }}</el-descriptions-item>
                                    <el-descriptions-item :label="t('tickets_page.col_created')">{{ oas_detailTicket.created_at }}</el-descriptions-item>
                                    <el-descriptions-item :label="t('tickets_page.sla_deadline')">{{ oas_detailTicket.sla_due_at || '-' }}</el-descriptions-item>
                                </el-descriptions>
                            </div>

                            <div class="oas-detail-section">
                                <h4>{{ t('order_after_sales_page.section_description') }}</h4>
                                <div class="oas-description-box">{{ oas_detailTicket.description }}</div>
                            </div>

                            <!-- 满意度评价 -->
                            <div v-if="oas_detailTicket.satisfaction" class="oas-detail-section">
                                <h4>{{ t('order_after_sales_page.satisfaction_title') }}</h4>
                                <div class="oas-satisfaction-box">
                                    <span class="score-stars-lg">{{ '★'.repeat(oas_detailTicket.satisfaction.score) }}{{ '☆'.repeat(5 - oas_detailTicket.satisfaction.score) }}</span>
                                    <span v-if="oas_detailTicket.satisfaction.comment" class="oas-satisfaction-comment">{{ oas_detailTicket.satisfaction.comment }}</span>
                                </div>
                            </div>

                            <!-- 回复列表 -->
                            <div class="oas-detail-section">
                                <h4>{{ t('tickets_page.replies_title', { n: oas_detailTicket.replies?.length || 0 }) }}</h4>
                                <div v-if="oas_detailTicket.replies?.length" class="oas-reply-list">
                                    <div v-for="r in oas_detailTicket.replies" :key="r.id" class="oas-reply-item" :class="{ 'internal': r.is_internal }">
                                        <div class="oas-reply-header">
                                            <strong>{{ r.user?.name || t('order_after_sales_page.system') }}</strong>
                                            <span class="oas-reply-time">{{ r.created_at }}</span>
                                            <el-tag v-if="r.is_internal" type="warning" size="small">{{ t('order_after_sales_page.internal_note') }}</el-tag>
                                        </div>
                                        <div class="oas-reply-content">{{ r.content }}</div>
                                    </div>
                                </div>
                                <el-empty v-else :description="t('tickets_page.no_replies')" :image-size="40" />
                            </div>

                            <!-- 回复输入 -->
                            <div class="oas-detail-section">
                                <h4>{{ t('order_after_sales_page.reply_section') }}</h4>
                                <el-input v-model="oas_replyContent" type="textarea" :rows="3" :placeholder="t('tickets_page.reply_ph')" />
                                <div class="oas-reply-actions">
                                    <el-checkbox v-model="oas_replyInternal">{{ t('order_after_sales_page.internal_note') }}</el-checkbox>
                                    <div>
                                        <el-button type="primary" size="small" @click="oas_handleReply" :loading="oas_replying">{{ t('tickets_page.send_reply') }}</el-button>
                                        <el-button
                                            v-if="oas_detailTicket.status === 'open' || oas_detailTicket.status === 'in_progress' || oas_detailTicket.status === 'replied'"
                                            type="success" size="small" @click="oas_handleResolve(oas_detailTicket)"
                                        >{{ t('tickets_page.mark_resolved') }}</el-button>
                                        <el-button
                                            v-if="oas_detailTicket.status !== 'closed'"
                                            type="info" size="small" @click="oas_handleClose(oas_detailTicket)"
                                        >{{ t('tickets_page.close_ticket') }}</el-button>
                                    </div>
                                </div>
                            </div>

                            <!-- 分配 -->
                            <div class="oas-detail-section">
                                <h4>{{ t('order_after_sales_page.assign_agent_title') }}</h4>
                                <div class="oas-assign-row">
                                    <el-select v-model="oas_assignUserId" filterable :placeholder="t('tickets_page.select_staff_ph')" style="width:200px">
                                        <el-option v-for="u in oas_agents" :key="u.id" :label="u.name" :value="u.id" />
                                    </el-select>
                                    <el-button type="primary" size="small" @click="oas_handleAssign" :loading="oas_assigning">{{ t('tickets_page.assign') }}</el-button>
                                </div>
                            </div>
                        </template>
                    </el-drawer>
                </div>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Search, Plus, Service, Refresh } from '@element-plus/icons-vue';
import refundApi from '@/api/refund';
import refundWorkflowApi from '@/api/refundWorkflow';
import orderAfterSalesApi from '@/api/orderAfterSales';
import adminUserApi from '@/api/adminUser';

export default {
    name: 'RefundCenter',
    components: { Search, Plus, Service, Refresh },
    setup() {
        const { t } = useI18n();

        // ══════════════════════════════════════════════════
        // 外层 Tabs 控制
        // ══════════════════════════════════════════════════
        const refundCenterTab = ref('refunds');

        // ══════════════════════════════════════════════════
        // 懒加载标记
        // ══════════════════════════════════════════════════
        const rw_tabVisited = ref(false);
        const oas_tabVisited = ref(false);

        watch(refundCenterTab, (tab) => {
            if (tab === 'workflow' && !rw_tabVisited.value) {
                rw_tabVisited.value = true;
                rw_fetchStats();
                rw_fetchRefunds();
            }
            if (tab === 'tickets' && !oas_tabVisited.value) {
                oas_tabVisited.value = true;
                oas_refreshAll();
                orderAfterSalesApi.getReasons().then(r => { oas_reasons.value = r.data || {}; }).catch(() => {});
                oas_loadOptions();
                oas_loadAgents();
            }
        });

        // ══════════════════════════════════════════════════
        // Tab 1: 退款管理（原 refunds Index.vue）
        // ══════════════════════════════════════════════════
        const activeTab = ref('refunds');

        const stats = reactive({
            total_refunds: 0, total_amount: 0, completed_count: 0,
            pending_count: 0, failed_count: 0, today_amount: 0, month_amount: 0,
        });

        const refunds = ref([]);
        const loading = ref(false);
        const page = ref(1);
        const perPage = ref(20);
        const total = ref(0);
        const filters = reactive({ search: '', 'filter.status': '' });

        const showDetail = ref(false);
        const detail = ref(null);

        const showCreateDialog = ref(false);
        const createForm = ref({ license_id: null, amount: 100, refund_type: 'full', payment_method: 'original', reason: '' });
        const createRules = computed(() => ({
            license_id: [{ required: true, message: t('refunds_page.license_required'), trigger: 'change' }],
            amount: [{ required: true, message: t('refunds_page.amount_required'), trigger: 'blur' }],
        }));
        const creating = ref(false);
        const licenseOptions = ref([]);
        const searchingLicense = ref(false);

        const statusOptions = computed(() => [
            { value: 'completed', label: t('refunds_page.st_completed') },
            { value: 'pending', label: t('refunds_page.st_pending') },
            { value: 'failed', label: t('refunds_page.st_failed') },
            { value: 'cancelled', label: t('refunds_page.st_cancelled') },
        ]);

        const refundTypeOptions = computed(() => [
            { value: 'full', label: t('refunds_page.type_full_refund') },
            { value: 'partial', label: t('refunds_page.type_partial_refund') },
        ]);

        const paymentMethodOptions = computed(() => [
            { value: 'original', label: t('refunds_page.pm_original') },
            { value: 'balance', label: t('refunds_page.pm_balance') },
            { value: 'other', label: t('refunds_page.pm_other') },
        ]);

        function searchLicenses(query) {
            if (!query) return;
            searchingLicense.value = true;
            import('@/api/license').then(m => m.searchLicenses?.(query) ?? m.default.searchLicenses?.(query))
                .then(res => { licenseOptions.value = res.data ?? []; })
                .catch(() => {})
                .finally(() => { searchingLicense.value = false; });
        }

        async function handleCreateWithRisk() {
            creating.value = true;
            try {
                const res = await refundApi.storeWithRisk(createForm.value);
                const data = res.data?.data || res;
                ElMessage.success(t('refunds_page.create_ok'));
                showCreateDialog.value = false;
                createForm.value = { license_id: null, amount: 100, refund_type: 'full', payment_method: 'original', reason: '' };
                await fetchData();
                if (data.decision_result?.action === 'require_review') {
                    ElMessage.info(t('refunds_page.review_required_hint'));
                }
            } catch (e) {
                ElMessage.error(t('refunds_page.submit_fail'));
            } finally {
                creating.value = false;
            }
        }

        const showReviewDialog = ref(false);
        const reviewRefund = ref(null);
        const reviewAction = ref('approve');
        const reviewNote = ref('');
        const reviewing = ref(false);

        function handleReview(row) {
            reviewRefund.value = row;
            reviewAction.value = 'approve';
            reviewNote.value = '';
            showReviewDialog.value = true;
        }

        async function handleDoReview() {
            reviewing.value = true;
            try {
                await refundApi.reviewRefund(reviewRefund.value.id, reviewAction.value, reviewNote.value);
                ElMessage.success(reviewAction.value === 'approve' ? t('refunds_page.approve_ok') : t('refunds_page.reject_ok'));
                showReviewDialog.value = false;
                await fetchData();
            } catch (e) {
                ElMessage.error(t('refunds_page.action_fail'));
            } finally {
                reviewing.value = false;
            }
        }

        const riskStats = ref({});
        const riskRules = ref([]);
        const loadingRules = ref(false);

        const riskLevelLabels = computed(() => ({
            low: t('refunds_page.risk_low'),
            medium: t('refunds_page.risk_medium'),
            high: t('refunds_page.risk_high'),
            critical: t('refunds_page.risk_critical'),
        }));

        const riskDecisionLabels = computed(() => ({
            auto_approve: t('refunds_page.decision_auto_approve'),
            auto_reject: t('refunds_page.decision_auto_reject'),
            require_review: t('refunds_page.decision_require_review'),
            partial_refund: t('refunds_page.decision_partial_refund'),
        }));

        const riskLevelChart = computed(() => {
            const byLevel = riskStats.value.by_risk_level || {};
            const totalRisk = Object.values(byLevel).reduce((a, b) => a + b, 0);
            if (!totalRisk) return [];
            const colors = { low: '#67c23a', medium: '#e6a23c', high: '#f56c6c', critical: '#f56c6c' };
            return Object.entries(byLevel).map(([k, v]) => ({
                name: k, label: riskLevelLabels.value[k] || k, value: v, percent: Math.round(v / totalRisk * 100), color: colors[k] || '#909399',
            }));
        });

        const riskDecisionChart = computed(() => {
            const byDecision = riskStats.value.by_decision || {};
            const totalDec = Object.values(byDecision).reduce((a, b) => a + b, 0);
            if (!totalDec) return [];
            return Object.entries(byDecision).map(([k, v]) => ({
                name: k, label: riskDecisionLabels.value[k] || k, value: v, percent: Math.round(v / totalDec * 100),
            }));
        });

        function ruleTypeTag(type) {
            return { time_window: 'primary', amount_threshold: 'danger', frequency: 'warning', customer_tier: 'info', license_age: 'success' }[type] || 'info';
        }

        function handleToggleRule(rule, val) {
            refundApi.updateRiskRule(rule.id, { is_active: val }).then(() => {
                ElMessage.success(val ? t('refunds_page.rule_enabled') : t('refunds_page.rule_disabled'));
            }).catch(() => {});
        }

        async function fetchRiskStats() {
            try {
                const res = await refundApi.riskStats();
                riskStats.value = res.data?.data || {};
            } catch { /* ignore */ }
        }

        async function fetchRiskRules() {
            loadingRules.value = true;
            try {
                const res = await refundApi.riskRules();
                riskRules.value = res.data?.data || res.data || [];
            } catch { /* ignore */ }
            finally { loadingRules.value = false; }
        }

        function formatAmount(val) {
            if (val === null || val === undefined) return '0.00';
            return Number(val).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function _statusType(s) { return ({ completed: 'success', pending: 'warning', failed: 'danger', cancelled: 'info' })[s] || 'info'; }

        function statusLabel(s) {
            const key = `refunds_page.st_${s}`;
            const translated = t(key);
            return translated !== key ? translated : s;
        }

        function paymentMethodLabel(m) {
            const key = `refunds_page.pm_${m}`;
            const translated = t(key);
            return translated !== key ? translated : (m || '-');
        }

        const decisionType = (d) => ({ auto_approve: 'success', auto_reject: 'danger', require_review: 'warning', partial_refund: 'info' }[d] || 'info');

        function decisionLabel(d) {
            const map = {
                auto_approve: 'decision_auto_approve',
                auto_reject: 'decision_auto_reject',
                require_review: 'decision_manual_review',
                partial_refund: 'decision_partial_refund',
            };
            const key = map[d];
            return key ? t(`refunds_page.${key}`) : d;
        }

        async function fetchData() {
            loading.value = true;
            try {
                const params = { page: page.value, per_page: perPage.value, ...filters };
                Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
                const [listRes, statsRes] = await Promise.all([refundApi.list(params), refundApi.stats()]);
                refunds.value = listRes.data?.data || [];
                total.value = listRes.data?.meta?.total || 0;
                Object.assign(stats, statsRes.data?.data || {});
            } catch {
                ElMessage.error(t('refunds_page.list_fail'));
            } finally {
                loading.value = false;
            }
        }

        function openDetail(row) {
            detail.value = row;
            showDetail.value = true;
        }

        onMounted(() => {
            fetchData();
            fetchRiskStats();
            fetchRiskRules();
        });

        // ══════════════════════════════════════════════════
        // Tab 2: 退款审核（原 refund-workflow Index.vue，rw_ 前缀）
        // ══════════════════════════════════════════════════
        const rw_stats = ref({});
        const rw_refunds = ref([]);
        const rw_loading = ref(false);
        const rw_filters = ref({ status: '', search: '' });
        const rw_page = ref(1);
        const rw_perPage = ref(20);
        const rw_total = ref(0);

        const rw_reviewVisible = ref(false);
        const rw_reviewTarget = ref(null);
        const rw_reviewAction = ref('approve');
        const rw_reviewData = ref({ reason: '', notes: '' });
        const rw_reviewSubmitting = ref(false);
        const rw_reviewFormRef = ref(null);

        const rw_statusKeys = ['pending', 'approved', 'completed', 'rejected'];

        const rw_statusLabels = computed(() => ({
            pending: t('refund_workflow_page.status.pending'),
            approved: t('refund_workflow_page.status.approved'),
            completed: t('refund_workflow_page.status.completed'),
            rejected: t('refund_workflow_page.status.rejected'),
        }));

        const rw_statusOptions = computed(() =>
            rw_statusKeys.map((value) => ({ value, label: rw_statusLabels.value[value] }))
        );

        const rw_reviewRules = computed(() => ({
            reason: [{ required: true, message: t('refund_workflow_page.rules.reject_reason_required'), trigger: 'blur' }],
        }));

        function rw_statusTag(s) {
            const map = { pending: 'warning', approved: 'primary', completed: 'success', rejected: 'danger' };
            return map[s] ?? 'info';
        }

        function rw_statusLabel(s) {
            return rw_statusLabels.value[s] || s;
        }

        async function rw_fetchStats() {
            try {
                const res = await refundWorkflowApi.stats();
                rw_stats.value = res.data?.data ?? {};
            } catch (e) { console.error(e); }
        }

        async function rw_fetchRefunds() {
            rw_loading.value = true;
            try {
                const params = { ...rw_filters.value, page: rw_page.value, per_page: rw_perPage.value };
                const res = await refundWorkflowApi.list(params);
                rw_refunds.value = res.data?.data?.data ?? res.data?.data ?? [];
                rw_total.value = res.data?.data?.total ?? 0;
            } catch (e) { console.error(e); }
            finally { rw_loading.value = false; }
        }

        function rw_openReview(row, action) {
            rw_reviewTarget.value = row;
            rw_reviewAction.value = action;
            rw_reviewData.value = { reason: '', notes: '' };
            rw_reviewVisible.value = true;
        }

        async function rw_submitReview() {
            if (rw_reviewAction.value === 'reject') {
                const valid = await rw_reviewFormRef.value.validate().catch(() => false);
                if (!valid) return;
            }
            rw_reviewSubmitting.value = true;
            try {
                await refundWorkflowApi.review(rw_reviewTarget.value.id, {
                    action: rw_reviewAction.value,
                    reason: rw_reviewData.value.reason,
                    notes: rw_reviewData.value.notes,
                });
                ElMessage.success(rw_reviewAction.value === 'approve' ? t('refund_workflow_page.messages.approved') : t('refund_workflow_page.messages.rejected'));
                rw_reviewVisible.value = false;
                await rw_fetchRefunds();
                await rw_fetchStats();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || t('messages.failed'));
            } finally {
                rw_reviewSubmitting.value = false;
            }
        }

        // ══════════════════════════════════════════════════
        // Tab 3: 售后工单（原 order-after-sales Index.vue，oas_ 前缀）
        // ══════════════════════════════════════════════════
        const oas_loading = ref(false);
        const oas_submitting = ref(false);
        const oas_replying = ref(false);
        const oas_assigning = ref(false);
        const oas_stats = ref({});
        const oas_reasons = ref({});
        const oas_orders = ref([]);
        const oas_customers = ref([]);
        const oas_agents = ref([]);
        const oas_tickets = ref([]);
        const oas_total = ref(0);
        const oas_page = ref(1);
        const oas_pageSize = ref(15);

        const oas_filters = reactive({
            status: '', priority: '', reason: '', order_id: '', keyword: '',
        });

        const oas_createVisible = ref(false);
        const oas_form = reactive({ order_id: '', customer_id: '', reason: 'not_received', description: '' });

        const oas_detailVisible = ref(false);
        const oas_detailTicket = ref(null);
        const oas_replyContent = ref('');
        const oas_replyInternal = ref(false);
        const oas_assignUserId = ref('');

        const oas_statusOptions = computed(() => [
            { value: 'open', label: t('tickets_page.st_open') },
            { value: 'in_progress', label: t('tickets_page.st_in_progress') },
            { value: 'replied', label: t('tickets_page.st_replied') },
            { value: 'resolved', label: t('tickets_page.st_resolved') },
            { value: 'closed', label: t('tickets_page.st_closed') },
        ]);

        const oas_priorityOptions = computed(() => [
            { value: 'urgent', label: t('tickets_page.pri_urgent') },
            { value: 'high', label: t('tickets_page.pri_high') },
            { value: 'medium', label: t('tickets_page.pri_normal') },
            { value: 'low', label: t('tickets_page.pri_low') },
        ]);

        const oas_STATUS_MAP = computed(() => ({
            open: { type: 'danger', label: t('tickets_page.st_open') },
            in_progress: { type: 'warning', label: t('tickets_page.st_in_progress') },
            replied: { type: 'primary', label: t('tickets_page.st_replied') },
            resolved: { type: 'success', label: t('tickets_page.st_resolved') },
            closed: { type: 'info', label: t('tickets_page.st_closed') },
        }));

        const oas_PRIORITY_MAP = computed(() => ({
            urgent: { type: 'danger', label: t('tickets_page.pri_urgent') },
            high: { type: 'warning', label: t('tickets_page.pri_high') },
            medium: { type: 'primary', label: t('tickets_page.pri_normal') },
            low: { type: 'info', label: t('tickets_page.pri_low') },
        }));

        async function oas_refreshAll() {
            await Promise.all([oas_loadList(), oas_loadStats()]);
        }

        async function oas_loadStats() {
            try {
                const res = await orderAfterSalesApi.getStats();
                oas_stats.value = res.data;
            } catch {}
        }

        async function oas_loadList() {
            oas_loading.value = true;
            try {
                const params = { page: oas_page.value, per_page: oas_pageSize.value };
                Object.entries(oas_filters).forEach(([k, v]) => { if (v) params[k] = v; });
                const res = await orderAfterSalesApi.list(params);
                oas_tickets.value = res.data?.data || [];
                oas_total.value = res.data?.total || 0;
            } finally { oas_loading.value = false; }
        }

        async function oas_loadOptions() {
            try {
                const [oRes, cRes] = await Promise.all([
                    import('@/api/order').then(m => m.default.list({ per_page: 200 })),
                    import('@/api/customer').then(m => m.default.list({ per_page: 200 })),
                ]);
                oas_orders.value = oRes.data?.data || [];
                oas_customers.value = cRes.data?.data || [];
            } catch {}
        }

        async function oas_loadAgents() {
            try {
                const res = await adminUserApi.list({ per_page: 200 });
                oas_agents.value = res.data?.data || [];
            } catch {}
        }

        function oas_resetFilters() {
            oas_filters.status = ''; oas_filters.priority = ''; oas_filters.reason = '';
            oas_filters.order_id = ''; oas_filters.keyword = '';
            oas_page.value = 1;
            oas_loadList();
        }

        function oas_openCreateDialog() {
            oas_form.order_id = ''; oas_form.customer_id = ''; oas_form.reason = 'not_received'; oas_form.description = '';
            oas_createVisible.value = true;
        }

        async function oas_handleCreateSubmit() {
            if (!oas_form.order_id || !oas_form.customer_id || !oas_form.reason || !oas_form.description) {
                ElMessage.warning(t('order_after_sales_page.messages.fill_required'));
                return;
            }
            oas_submitting.value = true;
            try {
                await orderAfterSalesApi.createTicket(oas_form);
                ElMessage.success(t('order_after_sales_page.messages.created'));
                oas_createVisible.value = false;
                oas_refreshAll();
            } catch (e) {
                ElMessage.error(e?.response?.data?.message || t('order_after_sales_page.messages.create_failed'));
            } finally { oas_submitting.value = false; }
        }

        async function oas_openDetail(row) {
            try {
                const res = await orderAfterSalesApi.detail(row.id);
                oas_detailTicket.value = res.data;
                oas_replyContent.value = '';
                oas_replyInternal.value = false;
                oas_assignUserId.value = oas_detailTicket.value?.assignee?.id || '';
                oas_detailVisible.value = true;
            } catch {}
        }

        async function oas_handleReply() {
            if (!oas_replyContent.value.trim()) {
                ElMessage.warning(t('order_after_sales_page.messages.reply_required'));
                return;
            }
            oas_replying.value = true;
            try {
                await orderAfterSalesApi.reply(oas_detailTicket.value.id, {
                    content: oas_replyContent.value,
                    is_internal: oas_replyInternal.value,
                });
                ElMessage.success(t('order_after_sales_page.messages.reply_ok'));
                oas_replyContent.value = '';
                oas_openDetail(oas_detailTicket.value);
                oas_loadList();
            } catch (e) {
                ElMessage.error(e?.response?.data?.message || t('order_after_sales_page.messages.reply_failed'));
            } finally { oas_replying.value = false; }
        }

        async function oas_handleResolve(row) {
            try {
                await ElMessageBox.confirm(t('tickets_page.confirm_resolve'), t('actions.confirm'));
                await orderAfterSalesApi.resolve(row.id);
                ElMessage.success(t('order_after_sales_page.messages.resolved'));
                oas_detailVisible.value = false;
                oas_refreshAll();
            } catch {}
        }

        async function oas_handleClose(row) {
            try {
                await ElMessageBox.confirm(t('tickets_page.confirm_close'), t('actions.confirm'));
                await orderAfterSalesApi.close(row.id);
                ElMessage.success(t('order_after_sales_page.messages.closed'));
                oas_detailVisible.value = false;
                oas_refreshAll();
            } catch {}
        }

        async function oas_handleAssign() {
            if (!oas_assignUserId.value) {
                ElMessage.warning(t('tickets_page.select_assignee_required'));
                return;
            }
            oas_assigning.value = true;
            try {
                await orderAfterSalesApi.assign(oas_detailTicket.value.id, oas_assignUserId.value);
                ElMessage.success(t('order_after_sales_page.messages.assigned'));
                oas_openDetail(oas_detailTicket.value);
                oas_loadList();
            } catch (e) {
                ElMessage.error(e?.response?.data?.message || t('tickets_page.assign_fail'));
            } finally { oas_assigning.value = false; }
        }

        function oas_statusType(s) { return oas_STATUS_MAP.value[s]?.type || 'info'; }
        function oas_statusLabel(s) { return oas_STATUS_MAP.value[s]?.label || s; }
        function oas_priorityType(p) { return oas_PRIORITY_MAP.value[p]?.type || 'info'; }
        function oas_priorityLabel(p) { return oas_PRIORITY_MAP.value[p]?.label || p; }

        // ══════════════════════════════════════════════════
        // Return all template bindings
        // ══════════════════════════════════════════════════
        return {
            t,
            // 外层
            refundCenterTab,
            rw_tabVisited,
            oas_tabVisited,
            // Tab 1: 退款管理
            activeTab,
            stats,
            refunds,
            loading,
            page,
            perPage,
            total,
            filters,
            showDetail,
            detail,
            showCreateDialog,
            createForm,
            createRules,
            creating,
            licenseOptions,
            searchingLicense,
            statusOptions,
            refundTypeOptions,
            paymentMethodOptions,
            searchLicenses,
            handleCreateWithRisk,
            showReviewDialog,
            reviewRefund,
            reviewAction,
            reviewNote,
            reviewing,
            handleReview,
            handleDoReview,
            riskStats,
            riskRules,
            loadingRules,
            riskLevelLabels,
            riskDecisionLabels,
            riskLevelChart,
            riskDecisionChart,
            ruleTypeTag,
            handleToggleRule,
            fetchRiskStats,
            fetchRiskRules,
            formatAmount,
            statusType: _statusType,
            statusLabel,
            paymentMethodLabel,
            decisionType,
            decisionLabel,
            fetchData,
            openDetail,
            // Tab 2: 退款审核
            rw_stats,
            rw_refunds,
            rw_loading,
            rw_filters,
            rw_page,
            rw_perPage,
            rw_total,
            rw_reviewVisible,
            rw_reviewTarget,
            rw_reviewAction,
            rw_reviewData,
            rw_reviewSubmitting,
            rw_reviewFormRef,
            rw_statusKeys,
            rw_statusLabels,
            rw_statusOptions,
            rw_reviewRules,
            rw_statusTag,
            rw_statusLabel,
            rw_fetchStats,
            rw_fetchRefunds,
            rw_openReview,
            rw_submitReview,
            // Tab 3: 售后工单
            oas_loading,
            oas_submitting,
            oas_replying,
            oas_assigning,
            oas_stats,
            oas_reasons,
            oas_orders,
            oas_customers,
            oas_agents,
            oas_tickets,
            oas_total,
            oas_page,
            oas_pageSize,
            oas_filters,
            oas_createVisible,
            oas_form,
            oas_detailVisible,
            oas_detailTicket,
            oas_replyContent,
            oas_replyInternal,
            oas_assignUserId,
            oas_statusOptions,
            oas_priorityOptions,
            oas_STATUS_MAP,
            oas_PRIORITY_MAP,
            oas_refreshAll,
            oas_loadStats,
            oas_loadList,
            oas_loadOptions,
            oas_loadAgents,
            oas_resetFilters,
            oas_openCreateDialog,
            oas_handleCreateSubmit,
            oas_openDetail,
            oas_handleReply,
            oas_handleResolve,
            oas_handleClose,
            oas_handleAssign,
            oas_statusType,
            oas_statusLabel,
            oas_priorityType,
            oas_priorityLabel,
        };
    },
};
</script>

<style scoped>
/* ══════════════════════════════════════════════════ */
/* Tab 1: 退款管理 样式（原 refunds）                */
/* ══════════════════════════════════════════════════ */
.mb-4 { margin-bottom: 16px; }
.stat-card { text-align: center; }
.stat-value { font-size: 26px; font-weight: 700; color: var(--el-text-color-primary); }
.stat-label { font-size: 13px; color: var(--el-text-color-secondary); margin-top: 4px; }
.stat-label.warn { color: var(--el-color-danger); }
.toolbar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 16px; }
.refund-no { font-size: 12px; letter-spacing: 0.5px; }
.amount-text { font-weight: 600; color: var(--el-color-danger); }
.no-data { color: var(--el-text-color-placeholder); }
.pagination-wrap { display: flex; justify-content: center; margin-top: 16px; }
.risk-chart { padding: 8px 0; }
.risk-bar-item { margin-bottom: 12px; }
.risk-bar-label { display: inline-block; width: 80px; font-size: 13px; }
.empty-chart { text-align: center; padding: 40px 0; color: var(--el-text-color-placeholder); }
.rule-item { margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid var(--el-border-color-light); }
.rule-header { display: flex; align-items: center; gap: 8px; }
.rule-name { flex: 1; font-size: 13px; }

/* ══════════════════════════════════════════════════ */
/* Tab 2: 退款审核 样式（原 refund-workflow，rw_ 前缀） */
/* ══════════════════════════════════════════════════ */
.refund-center-page .rw-content .rw-stat-value { font-size: 24px; font-weight: 700; }
.refund-center-page .rw-content .rw-stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.refund-center-page .rw-content .text-warning { color: #e6a23c; }
.refund-center-page .rw-content .text-primary { color: #0f172a; }
.refund-center-page .rw-content .text-danger { color: #f56c6c; }
.refund-center-page .rw-content .text-gray { color: #909399; }
.refund-center-page .rw-content .rw-pagination-wrap { display: flex; justify-content: flex-end; margin-top: 16px; }

/* ══════════════════════════════════════════════════ */
/* Tab 3: 售后工单 样式（原 order-after-sales，oas_ 前缀） */
/* ══════════════════════════════════════════════════ */
.refund-center-page .oas-content { padding: 16px; }
.refund-center-page .oas-content .oas-page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.refund-center-page .oas-content .oas-page-header h2 { margin: 0; font-size: 20px; }
.refund-center-page .oas-content .oas-stat-card { text-align: center; cursor: default; }
.refund-center-page .oas-content .oas-stat-value { font-size: 26px; font-weight: 700; color: #303133; }
.refund-center-page .oas-content .oas-stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.refund-center-page .oas-content .stat-success { color: #67C23A; }
.refund-center-page .oas-content .stat-warning { color: #E6A23C; }
.refund-center-page .oas-content .stat-danger { color: #F56C6C; }
.refund-center-page .oas-content .oas-text-muted { color: #c0c4cc; }
.refund-center-page .oas-content .oas-pagination-wrap { margin-top: 16px; display: flex; justify-content: flex-end; }
.refund-center-page .oas-content .oas-subject-text { font-weight: 500; cursor: pointer; }
.refund-center-page .oas-content .oas-subject-text:hover { color: #0f172a; }
.refund-center-page .oas-content .oas-detail-section { margin-bottom: 20px; }
.refund-center-page .oas-content .oas-detail-section h4 { margin: 0 0 8px; font-size: 15px; color: #303133; border-left: 3px solid #0f172a; padding-left: 8px; }
.refund-center-page .oas-content .oas-description-box { background: #f5f7fa; padding: 12px; border-radius: 4px; white-space: pre-wrap; font-size: 13px; line-height: 1.6; }
.refund-center-page .oas-content .oas-reply-list { max-height: 400px; overflow-y: auto; }
.refund-center-page .oas-content .oas-reply-item { padding: 10px; border: 1px solid #ebeef5; border-radius: 4px; margin-bottom: 8px; }
.refund-center-page .oas-content .oas-reply-item.internal { background: #fdf6ec; border-color: #e6a23c33; }
.refund-center-page .oas-content .oas-reply-header { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; font-size: 13px; }
.refund-center-page .oas-content .oas-reply-time { color: #909399; font-size: 12px; }
.refund-center-page .oas-content .oas-reply-content { white-space: pre-wrap; font-size: 13px; line-height: 1.5; }
.refund-center-page .oas-content .oas-reply-actions { display: flex; justify-content: space-between; align-items: center; margin-top: 8px; }
.refund-center-page .oas-content .oas-assign-row { display: flex; align-items: center; gap: 8px; }
.refund-center-page .oas-content .score-stars { color: #f7ba2a; font-size: 13px; }
.refund-center-page .oas-content .score-stars-lg { color: #f7ba2a; font-size: 18px; letter-spacing: 2px; }
.refund-center-page .oas-content .oas-satisfaction-box { padding: 10px; background: #f0f9eb; border-radius: 4px; }
.refund-center-page .oas-content .oas-satisfaction-comment { display: block; margin-top: 4px; font-size: 13px; color: #606266; }
</style>
