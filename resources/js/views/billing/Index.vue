<template>
    <div class="billing-page">
        <div class="page-header">
            <h2>{{ t('billing_page.title') }}</h2>
            <div class="header-actions">
                <el-button type="primary" @click="showCreate = true">
                    <el-icon><Plus /></el-icon> {{ t('billing_page.create_subscription') }}
                </el-button>
            </div>
        </div>

        <!-- Stats Cards -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value">{{ stats.active }}</div>
                        <div class="stat-label">{{ t('billing_page.stat_active') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #e6a23c">{{ stats.in_grace_period }}</div>
                        <div class="stat-label">{{ t('billing_page.stat_grace_period') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #0f172a">{{ stats.mrr }}</div>
                        <div class="stat-label">{{ t('billing_page.stat_mrr') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #67c23a">{{ stats.estimated_arr }}</div>
                        <div class="stat-label">{{ t('billing_page.stat_arr') }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Sub Tab: Stats Row 2 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value">{{ stats.total_plans }}</div>
                        <div class="mini-label">{{ t('billing_page.stat_total_plans') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value">{{ stats.active_coupons }}</div>
                        <div class="mini-label">{{ t('billing_page.stat_active_coupons') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value">{{ stats.coupon_usage_30d }}</div>
                        <div class="mini-label">{{ t('billing_page.stat_coupon_usage_30d') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value">¥{{ stats.recent_revenue || 0 }}</div>
                        <div class="mini-label">{{ t('billing_page.stat_recent_revenue') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value">¥{{ stats.coupon_savings_30d || 0 }}</div>
                        <div class="mini-label">{{ t('billing_page.stat_coupon_savings_30d') }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Tabs -->
        <el-card shadow="never">
            <el-tabs v-model="activeTab">
                <el-tab-pane :label="t('billing_page.tab_subscriptions')" name="subscriptions">
                    <el-table :data="subscriptions" v-loading="loading" stripe>
                        <el-table-column prop="id" label="ID" width="60" />
                        <el-table-column :label="t('billing_page.col_customer')" min-width="150">
                            <template #default="{ row }">
                                {{ row.customer?.name || row.customer?.user?.name || emDash }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('billing_page.col_product')" min-width="120">
                            <template #default="{ row }">
                                {{ row.product?.name || emDash }}
                            </template>
                        </el-table-column>
                        <el-table-column prop="plan" :label="t('billing_page.col_plan')" width="100" />
                        <el-table-column :label="t('billing_page.col_amount')" width="130">
                            <template #default="{ row }">
                                ¥{{ row.price }} / {{ periodLabel(row.billing_period) }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('billing_page.col_status')" width="100">
                            <template #default="{ row }">
                                <el-tag :type="statusType(row.status)" size="small">
                                    {{ statusLabel(row.status) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('billing_page.col_expires')" width="170">
                            <template #default="{ row }">
                                {{ row.ends_at ? formatTime(row.ends_at) : emDash }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('billing_page.col_auto_renew')" width="90">
                            <template #default="{ row }">
                                <el-tag :type="row.auto_renew ? 'success' : 'info'" size="small">
                                    {{ row.auto_renew ? t('billing_page.auto_renew_on') : t('billing_page.auto_renew_off') }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('billing_page.col_actions')" width="200" fixed="right">
                            <template #default="{ row }">
                                <el-button text size="small" @click="viewSubscription(row)">{{ t('billing_page.detail') }}</el-button>
                                <el-button v-if="row.status === 'active'" text size="small" type="warning" @click="handleCancel(row)">{{ t('billing_page.cancel_sub') }}</el-button>
                                <el-button v-if="row.status !== 'active' && row.status !== 'expired'" text size="small" type="primary" @click="handleResume(row)">{{ t('billing_page.resume') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <el-tab-pane :label="t('billing_page.tab_invoices')" name="invoices">
                    <el-table :data="invoices" v-loading="loadingInvoices" stripe>
                        <el-table-column prop="invoice_no" :label="t('billing_page.col_invoice_no')" width="180" />
                        <el-table-column :label="t('billing_page.col_customer')" min-width="130">
                            <template #default="{ row }">
                                {{ row.customer?.name || row.customer?.user?.name || emDash }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('billing_page.col_amount')" width="110">
                            <template #default="{ row }">
                                <span class="font-mono">¥{{ row.amount }}</span>
                                <el-tag v-if="row.discount_amount > 0" size="small" type="warning" class="ml-1">{{ t('billing_page.discounted') }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('billing_page.col_discount')" width="90">
                            <template #default="{ row }">
                                <span v-if="row.discount_amount > 0" class="font-mono" style="color:#e6a23c">-¥{{ row.discount_amount }}</span>
                                <span v-else>{{ emDash }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('billing_page.col_status')" width="90">
                            <template #default="{ row }">
                                <el-tag :type="invoiceStatusType(row.status)" size="small">
                                    {{ invoiceStatusLabel(row.status) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="billing_reason" :label="t('billing_page.col_reason')" width="140" />
                        <el-table-column :label="t('billing_page.col_created')" width="170">
                            <template #default="{ row }">
                                {{ formatTime(row.created_at) }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('billing_page.col_actions')" width="120">
                            <template #default="{ row }">
                                <el-button text size="small" @click="viewInvoice(row)">{{ t('billing_page.detail') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <el-tab-pane :label="t('billing_page.tab_plans')" name="plans">
                    <div class="tab-header mb-3">
                        <el-button type="primary" @click="showCreatePlanDialog">
                            <el-icon><Plus /></el-icon> {{ t('billing_page.create_plan') }}
                        </el-button>
                    </div>
                    <div v-loading="loadingPlans">
                        <div v-if="plans.length === 0" class="text-center text-gray-400 py-8">{{ t('billing_page.no_plans') }}</div>
                        <el-table v-else :data="plans" stripe>
                            <el-table-column prop="name" :label="t('billing_page.col_plan_name')" width="150" />
                            <el-table-column prop="slug" :label="t('billing_page.col_slug')" width="120" />
                            <el-table-column :label="t('billing_page.period_monthly')" width="100">
                                <template #default="{ row }">¥{{ row.price_monthly || emDash }}</template>
                            </el-table-column>
                            <el-table-column :label="t('billing_page.period_quarterly')" width="100">
                                <template #default="{ row }">¥{{ row.price_quarterly || emDash }}</template>
                            </el-table-column>
                            <el-table-column :label="t('billing_page.period_semi_annually')" width="100">
                                <template #default="{ row }">¥{{ row.price_semi_annually || emDash }}</template>
                            </el-table-column>
                            <el-table-column :label="t('billing_page.period_yearly')" width="100">
                                <template #default="{ row }">¥{{ row.price_yearly || emDash }}</template>
                            </el-table-column>
                            <el-table-column :label="t('billing_page.col_trial')" width="70">
                                <template #default="{ row }">{{ t('billing_page.days_suffix', { n: row.trial_days }) }}</template>
                            </el-table-column>
                            <el-table-column :label="t('billing_page.col_status')" width="80">
                                <template #default="{ row }">
                                    <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">
                                        {{ row.is_active ? t('actions.enable') : t('actions.disable') }}
                                    </el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t('billing_page.col_badge')" width="80">
                                <template #default="{ row }">
                                    <el-tag v-if="row.badge" type="warning" size="small">{{ row.badge }}</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t('billing_page.col_actions')" width="150">
                                <template #default="{ row }">
                                    <el-button text size="small" @click="editPlan(row)">{{ t('actions.edit') }}</el-button>
                                    <el-button text size="small" type="danger" @click="handleDeletePlan(row)">{{ t('billing_page.deactivate') }}</el-button>
                                </template>
                            </el-table-column>
                        </el-table>
                    </div>
                </el-tab-pane>

                <el-tab-pane :label="t('billing_page.tab_coupons')" name="coupons">
                    <div class="tab-header mb-3">
                        <el-button type="primary" @click="showCreateCouponDialog">
                            <el-icon><Plus /></el-icon> {{ t('billing_page.create_coupon') }}
                        </el-button>
                    </div>

                    <div class="coupon-stats mb-3">
                        <el-row :gutter="12">
                            <el-col :span="6">
                                <el-statistic :title="t('billing_page.coupon_stat_total')" :value="couponStats.total" />
                            </el-col>
                            <el-col :span="6">
                                <el-statistic :title="t('billing_page.coupon_stat_active')" :value="couponStats.active" />
                            </el-col>
                            <el-col :span="6">
                                <el-statistic :title="t('billing_page.coupon_stat_redemptions')" :value="couponStats.total_redemptions" />
                            </el-col>
                            <el-col :span="6">
                                <el-statistic :title="t('billing_page.coupon_stat_discount_total')" :value="'¥' + (couponStats.total_discount_amount || 0)" />
                            </el-col>
                        </el-row>
                    </div>

                    <el-table :data="coupons" v-loading="loadingCoupons" stripe>
                        <el-table-column prop="code" :label="t('billing_page.col_code')" width="120" />
                        <el-table-column prop="name" :label="t('billing_page.col_name')" min-width="120" />
                        <el-table-column :label="t('billing_page.col_type')" width="100">
                            <template #default="{ row }">
                                {{ couponTypeLabel(row.type) }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('billing_page.col_discount')" width="100">
                            <template #default="{ row }">
                                {{ row.type === 'percentage' ? row.value + '%' : '¥' + row.value }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('billing_page.col_status')" width="70">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'active' ? 'success' : 'danger'" size="small">
                                    {{ row.status === 'active' ? t('actions.enable') : t('actions.disable') }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('billing_page.col_usage_limit')" width="100">
                            <template #default="{ row }">
                                {{ row.usage_count || 0 }} / {{ row.usage_limit || t('billing_page.unlimited') }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('billing_page.col_validity')" width="170">
                            <template #default="{ row }">
                                {{ row.expires_at ? formatTime(row.expires_at) : t('billing_page.forever') }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('billing_page.col_actions')" width="150">
                            <template #default="{ row }">
                                <el-button text size="small" @click="editCoupon(row)">{{ t('actions.edit') }}</el-button>
                                <el-button text size="small" @click="showCouponRedemptions(row)">{{ t('billing_page.records') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>
            </el-tabs>
        </el-card>

        <!-- Create subscription dialog -->
        <el-dialog v-model="showCreate" :title="t('billing_page.dialog_create_subscription')" width="600px" :close-on-click-modal="false">
            <el-form ref="createFormRef" :model="createForm" label-width="100px" v-loading="submitting">
                <el-form-item :label="t('billing_page.form_customer')" prop="customer_id" required>
                    <el-select v-model="createForm.customer_id" filterable remote
                        :remote-method="searchCustomers" :loading="searchingCustomer"
                        :placeholder="t('billing_page.ph_search_customer')" style="width:100%">
                        <el-option v-for="c in customerOptions" :key="c.id"
                            :label="c.name || c.user?.name || 'ID:'+c.id"
                            :value="c.id" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('billing_page.form_product')" prop="product_id" required>
                    <el-select v-model="createForm.product_id" style="width:100%">
                        <el-option v-for="p in productOptions" :key="p.id"
                            :label="p.name" :value="p.id" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('billing_page.form_plan')" prop="plan_slug" required>
                    <el-select v-model="createForm.plan_slug" style="width:100%">
                        <el-option v-for="p in planOptions" :key="p.slug"
                            :label="`${p.name} (${p.slug})`" :value="p.slug" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('billing_page.form_billing_period')" prop="billing_period">
                    <el-select v-model="createForm.billing_period" style="width:100%">
                        <el-option v-for="opt in billingPeriodOptions" :key="opt.value"
                            :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('billing_page.form_auto_renew')">
                    <el-switch v-model="createForm.auto_renew" />
                </el-form-item>
                <el-form-item :label="t('billing_page.form_trial_days')">
                    <el-input-number v-model="createForm.trial_days" :min="0" :max="90" />
                </el-form-item>
                <el-form-item :label="t('billing_page.form_grace_days')">
                    <el-input-number v-model="createForm.grace_days" :min="0" :max="90" />
                </el-form-item>
                <el-form-item :label="t('billing_page.form_coupon_code')">
                    <el-input v-model="createForm.coupon_code" :placeholder="t('billing_page.ph_optional')" />
                </el-form-item>
                <el-form-item :label="t('billing_page.form_license')">
                    <el-input v-model="createForm.license_id" :placeholder="t('billing_page.ph_license_id')" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showCreate = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="submitting" @click="handleCreate">{{ t('actions.create') }}</el-button>
            </template>
        </el-dialog>

        <!-- Pricing plan dialog -->
        <el-dialog v-model="showPlanForm" :title="planForm.id ? t('billing_page.dialog_edit_plan') : t('billing_page.dialog_create_plan')" width="700px">
            <el-form ref="planFormRef" :model="planForm" label-width="100px" v-loading="planSubmitting">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t('billing_page.form_slug')" prop="slug" required>
                            <el-input v-model="planForm.slug" :disabled="!!planForm.id" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('billing_page.form_plan_name')" prop="name" required>
                            <el-input v-model="planForm.name" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item :label="t('billing_page.form_description')" prop="description">
                    <el-input v-model="planForm.description" type="textarea" :rows="2" />
                </el-form-item>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t('billing_page.form_linked_product')" prop="product_id">
                            <el-select v-model="planForm.product_id" clearable style="width:100%">
                                <el-option v-for="p in productOptions" :key="p.id"
                                    :label="p.name" :value="p.id" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('billing_page.form_currency')" prop="currency">
                            <el-input v-model="planForm.currency" :placeholder="t('billing_page.ph_currency')" maxlength="3" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-divider>{{ t('billing_page.form_price_settings') }}</el-divider>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t('billing_page.period_monthly')" prop="price_monthly">
                            <el-input-number v-model="planForm.price_monthly" :min="0" :precision="2" style="width:100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('billing_page.period_quarterly')" prop="price_quarterly">
                            <el-input-number v-model="planForm.price_quarterly" :min="0" :precision="2" style="width:100%" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t('billing_page.period_semi_annually')" prop="price_semi_annually">
                            <el-input-number v-model="planForm.price_semi_annually" :min="0" :precision="2" style="width:100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('billing_page.period_yearly')" prop="price_yearly">
                            <el-input-number v-model="planForm.price_yearly" :min="0" :precision="2" style="width:100%" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-divider>{{ t('billing_page.form_other_settings') }}</el-divider>
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item :label="t('billing_page.form_trial_days')">
                            <el-input-number v-model="planForm.trial_days" :min="0" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t('billing_page.form_sort_order')">
                            <el-input-number v-model="planForm.sort_order" :min="0" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t('billing_page.col_badge')">
                            <el-input v-model="planForm.badge" :placeholder="t('billing_page.ph_badge')" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item :label="t('billing_page.form_public')">
                            <el-switch v-model="planForm.is_public" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t('actions.enable')">
                            <el-switch v-model="planForm.is_active" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-divider>{{ t('billing_page.form_features_json') }}</el-divider>
                <el-input v-model="featuresText" type="textarea" :rows="4"
                    :placeholder="t('billing_page.ph_features')" />
                <el-divider>{{ t('billing_page.form_limits_json') }}</el-divider>
                <el-input v-model="limitsText" type="textarea" :rows="4"
                    :placeholder="t('billing_page.ph_limits')" />
            </el-form>
            <template #footer>
                <el-button @click="showPlanForm = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="planSubmitting" @click="handleSavePlan">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>

        <!-- Coupon dialog -->
        <el-dialog v-model="showCouponForm" :title="couponForm.id ? t('billing_page.dialog_edit_coupon') : t('billing_page.dialog_create_coupon')" width="700px">
            <el-form ref="couponFormRef" :model="couponForm" label-width="120px" v-loading="couponSubmitting">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t('billing_page.col_code')" prop="code" required>
                            <el-input v-model="couponForm.code" :disabled="!!couponForm.id" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('billing_page.col_name')" prop="name" required>
                            <el-input v-model="couponForm.name" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item :label="t('billing_page.form_description')" prop="description">
                    <el-input v-model="couponForm.description" type="textarea" :rows="2" />
                </el-form-item>
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item :label="t('billing_page.col_type')" prop="type" required>
                            <el-select v-model="couponForm.type" style="width:100%">
                                <el-option v-for="opt in couponTypeOptions" :key="opt.value"
                                    :label="opt.label" :value="opt.value" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t('billing_page.form_discount_value')" prop="value" required>
                            <el-input-number v-model="couponForm.value" :min="0" :precision="2" style="width:100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t('billing_page.form_currency')" prop="currency">
                            <el-input v-model="couponForm.currency" :placeholder="t('billing_page.ph_currency')" maxlength="3" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-divider>{{ t('billing_page.form_usage_limits') }}</el-divider>
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item :label="t('billing_page.form_total_usage_limit')">
                            <el-input-number v-model="couponForm.usage_limit" :min="0" style="width:100%" :placeholder="t('billing_page.ph_unlimited')" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t('billing_page.form_per_user_limit')">
                            <el-input-number v-model="couponForm.usage_limit_per_user" :min="0" style="width:100%" :placeholder="t('billing_page.ph_unlimited')" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t('billing_page.form_max_discount')">
                            <el-input-number v-model="couponForm.maximum_discount" :min="0" :precision="2" style="width:100%" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t('billing_page.form_min_order')">
                            <el-input-number v-model="couponForm.minimum_order_amount" :min="0" :precision="2" style="width:100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('billing_page.form_stackable')">
                            <el-switch v-model="couponForm.is_redeemable_with_other_coupons" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-divider>{{ t('billing_page.form_validity') }}</el-divider>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t('billing_page.form_starts_at')">
                            <el-date-picker v-model="couponForm.starts_at" type="datetime" style="width:100%"
                                :placeholder="t('billing_page.ph_starts_immediately')" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('billing_page.form_expires_at')">
                            <el-date-picker v-model="couponForm.expires_at" type="datetime" style="width:100%"
                                :placeholder="t('billing_page.ph_never_expires')" />
                        </el-form-item>
                    </el-col>
                </el-row>
            </el-form>
            <template #footer>
                <el-button @click="showCouponForm = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="couponSubmitting" @click="handleSaveCoupon">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>

        <!-- Invoice detail dialog -->
        <el-dialog v-model="showInvoiceDetail" :title="t('billing_page.dialog_invoice_detail')" width="550px">
            <el-descriptions v-if="invoiceDetail" :column="2" border>
                <el-descriptions-item :label="t('billing_page.col_invoice_no')">{{ invoiceDetail.invoice_no }}</el-descriptions-item>
                <el-descriptions-item :label="t('billing_page.col_status')">
                    <el-tag :type="invoiceDetail.status === 'paid' ? 'success' : 'warning'" size="small">
                        {{ invoiceStatusLabel(invoiceDetail.status) }}
                    </el-tag>
                </el-descriptions-item>
                <el-descriptions-item :label="t('billing_page.col_amount')">¥{{ invoiceDetail.amount }}</el-descriptions-item>
                <el-descriptions-item :label="t('billing_page.col_discount')">¥{{ invoiceDetail.discount_amount || 0 }}</el-descriptions-item>
                <el-descriptions-item :label="t('billing_page.col_reason')">{{ invoiceDetail.billing_reason }}</el-descriptions-item>
                <el-descriptions-item :label="t('billing_page.col_created')">{{ formatTime(invoiceDetail.created_at) }}</el-descriptions-item>
                <el-descriptions-item :label="t('billing_page.col_paid_at')">{{ invoiceDetail.paid_at ? formatTime(invoiceDetail.paid_at) : emDash }}</el-descriptions-item>
                <el-descriptions-item :label="t('billing_page.col_due_at')">{{ invoiceDetail.due_at ? formatTime(invoiceDetail.due_at) : emDash }}</el-descriptions-item>
            </el-descriptions>
            <template #footer>
                <el-button @click="showInvoiceDetail = false">{{ t('actions.close') }}</el-button>
            </template>
        </el-dialog>

        <!-- Coupon redemption dialog -->
        <el-dialog v-model="showRedemption" :title="t('billing_page.dialog_redemption_title', { code: redemptionCoupon?.code || '' })" width="650px">
            <el-table :data="redemptions" v-loading="loadingRedemptions" stripe max-height="400">
                <el-table-column :label="t('billing_page.col_customer')" min-width="130">
                    <template #default="{ row }">{{ row.customer?.user?.name || emDash }}</template>
                </el-table-column>
                <el-table-column :label="t('billing_page.col_sub_plan')" width="100">
                    <template #default="{ row }">{{ row.subscription?.plan || emDash }}</template>
                </el-table-column>
                <el-table-column :label="t('billing_page.col_discount_amount')" width="110">
                    <template #default="{ row }">¥{{ row.discount_amount }}</template>
                </el-table-column>
                <el-table-column :label="t('billing_page.col_original_amount')" width="110">
                    <template #default="{ row }">¥{{ row.original_amount }}</template>
                </el-table-column>
                <el-table-column :label="t('billing_page.col_final_amount')" width="110">
                    <template #default="{ row }">¥{{ row.final_amount }}</template>
                </el-table-column>
                <el-table-column :label="t('billing_page.col_used_at')" width="170">
                    <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
                </el-table-column>
            </el-table>
            <template #footer>
                <el-button @click="showRedemption = false">{{ t('actions.close') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>
<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Coin, CircleClose, CircleCheck, Refresh, ArrowLeft } from '@element-plus/icons-vue'
import billingApi from '@/api/billing'
import customerApi from '@/api/customer'
import productApi from '@/api/product'

const router = useRouter()
const { t, locale } = useI18n()

// ── State ──
const activeTab = ref('subscriptions')
const loading = ref(false)
const loadingInvoices = ref(false)
const loadingPlans = ref(false)
const loadingCoupons = ref(false)
const submitting = ref(false)
const planSubmitting = ref(false)
const couponSubmitting = ref(false)

// Stats
const stats = reactive({
    active: 0, in_grace_period: 0, mrr: 0, estimated_arr: 0,
    total_plans: 0, active_coupons: 0, coupon_usage_30d: 0,
    recent_revenue: 0, coupon_savings_30d: 0,
})

// Subscriptions
const subscriptions = ref([])
const subQuery = reactive({ page: 1, search: '', status: '' })

// Invoices
const invoices = ref([])
const invoiceQuery = reactive({ page: 1, status: '' })
const showInvoiceDetail = ref(false)
const invoiceDetail = ref(null)

// Pricing Plans
const plans = ref([])
const showPlanForm = ref(false)
const planForm = reactive({
    id: null, slug: '', name: '', description: '', product_id: null, currency: 'CNY',
    price_monthly: null, price_quarterly: null, price_semi_annually: null, price_yearly: null,
    features: [], limits: {}, trial_days: 0, sort_order: 0, is_public: true, is_active: true, badge: '',
})
const featuresText = ref('')
const limitsText = ref('')

// Coupons
const coupons = ref([])
const couponStatsData = reactive({
    total: 0, active: 0, expired: 0, total_redemptions: 0,
    total_discount_amount: 0, recent_30d_redemptions: 0, recent_30d_discount: 0,
})
const showCouponForm = ref(false)
const couponForm = reactive({
    id: null, code: '', name: '', description: '', type: 'percentage', value: 0, currency: 'CNY',
    minimum_order_amount: null, maximum_discount: null,
    usage_limit: null, usage_limit_per_user: null,
    applicable_plans: [], applicable_products: [], applicable_billing_periods: [],
    is_redeemable_with_other_coupons: false, status: 'active',
    starts_at: null, expires_at: null,
})

// Create subscription
const showCreate = ref(false)
const createForm = reactive({
    customer_id: null, product_id: null, plan_slug: '', billing_period: 'monthly',
    auto_renew: true, trial_days: 0, grace_days: 7, coupon_code: '', license_id: '',
})
const customerOptions = ref([])
const productOptions = ref([])
const planOptions = ref([])
const searchingCustomer = ref(false)

// Redemption
const showRedemption = ref(false)
const redemptionCoupon = ref(null)
const redemptions = ref([])
const loadingRedemptions = ref(false)

// ── Computed ──
const couponStats = computed(() => couponStatsData)
const emDash = '—'

const billingPeriodOptions = computed(() => [
    { label: t('billing_page.period_monthly'), value: 'monthly' },
    { label: t('billing_page.period_quarterly'), value: 'quarterly' },
    { label: t('billing_page.period_semi_annually'), value: 'semi_annually' },
    { label: t('billing_page.period_yearly'), value: 'yearly' },
])

const couponTypeOptions = computed(() => [
    { label: t('billing_page.coupon_percentage'), value: 'percentage' },
    { label: t('billing_page.coupon_fixed'), value: 'fixed_amount' },
    { label: t('billing_page.coupon_free_trial'), value: 'free_trial' },
])

const periodLabels = computed(() => ({
    monthly: t('billing_page.period_short_monthly'),
    quarterly: t('billing_page.period_short_quarterly'),
    semi_annually: t('billing_page.period_short_semi_annually'),
    yearly: t('billing_page.period_short_yearly'),
}))

const subscriptionStatusLabels = computed(() => ({
    active: t('billing_page.sub_active'),
    grace: t('billing_page.sub_grace'),
    expired: t('billing_page.sub_expired'),
    canceled: t('billing_page.sub_canceled'),
    suspended: t('billing_page.sub_suspended'),
    trialing: t('billing_page.sub_trialing'),
}))

const invoiceStatusLabels = computed(() => ({
    paid: t('billing_page.inv_paid'),
    pending: t('billing_page.inv_pending'),
    cancelled: t('billing_page.inv_cancelled'),
    refunded: t('billing_page.inv_refunded'),
}))

const couponTypeLabels = computed(() => ({
    percentage: t('billing_page.coupon_percentage'),
    fixed_amount: t('billing_page.coupon_fixed'),
    free_trial: t('billing_page.coupon_free_trial'),
    custom: t('billing_page.coupon_custom'),
}))

// ── Methods ──

function periodLabel(p) {
    return periodLabels.value[p] || p
}
function statusType(s) {
    const map = { active: 'success', grace: 'warning', expired: 'danger', canceled: 'info', suspended: 'info' }
    return map[s] || 'info'
}
function statusLabel(s) {
    return subscriptionStatusLabels.value[s] || s
}
function invoiceStatusType(s) {
    const map = { paid: 'success', pending: 'warning', cancelled: 'info', refunded: 'danger' }
    return map[s] || 'info'
}
function invoiceStatusLabel(s) {
    return invoiceStatusLabels.value[s] || s
}
function couponTypeLabel(type) {
    return couponTypeLabels.value[type] || type
}

function formatTime(time) {
    if (!time) return emDash
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN'
    return new Date(time).toLocaleString(loc, { hour12: false })
}

// Fetch data
async function fetchStats() {
    try {
        const { data } = await billingApi.stats()
        if (data.success) {
            Object.assign(stats, data.data)
        }
    } catch (e) { /* ignore */ }
}

async function fetchSubscriptions() {
    loading.value = true
    try {
        const params = { page: subQuery.page, per_page: 15 }
        if (subQuery.search) params.search = subQuery.search
        if (subQuery.status) params.status = subQuery.status
        const { data } = await billingApi.list(params)
        if (data.success) {
            subscriptions.value = data.data.data || data.data
        }
    } catch (e) {
        ElMessage.error(t('billing_page.load_subscriptions_fail'))
    } finally {
        loading.value = false
    }
}

async function fetchInvoices() {
    loadingInvoices.value = true
    try {
        const params = { page: invoiceQuery.page, per_page: 15 }
        if (invoiceQuery.status) params.status = invoiceQuery.status
        const { data } = await billingApi.invoices(params)
        if (data.success) {
            invoices.value = data.data.data || data.data
        }
    } catch (e) {
        ElMessage.error(t('billing_page.load_invoices_fail'))
    } finally {
        loadingInvoices.value = false
    }
}

async function fetchPlans() {
    loadingPlans.value = true
    try {
        const { data } = await billingApi.getPlans({ per_page: 50 })
        if (data.success) {
            plans.value = data.data.data || data.data
        }
    } catch (e) {
        ElMessage.error(t('billing_page.load_plans_fail'))
    } finally {
        loadingPlans.value = false
    }
}

async function fetchCoupons() {
    loadingCoupons.value = true
    try {
        const { data } = await billingApi.getCoupons({ per_page: 50 })
        if (data.success) {
            coupons.value = data.data.data || data.data
        }
    } catch (e) {
        ElMessage.error(t('billing_page.load_coupons_fail'))
    } finally {
        loadingCoupons.value = false
    }
}

async function fetchCouponStats() {
    try {
        const { data } = await billingApi.getCouponStats()
        if (data.success) {
            Object.assign(couponStatsData, data.data)
        }
    } catch (e) { /* ignore */ }
}

// Create subscription
async function searchCustomers(query) {
    if (!query) return
    searchingCustomer.value = true
    try {
        const { data } = await customerApi.list({ search: query, per_page: 10 })
        customerOptions.value = data.data?.data || data.data || []
    } catch (e) {
        customerOptions.value = []
    } finally {
        searchingCustomer.value = false
    }
}

async function handleCreate() {
    if (!createForm.customer_id || !createForm.product_id || !createForm.plan_slug) {
        ElMessage.warning(t('billing_page.fill_required'))
        return
    }
    submitting.value = true
    try {
        const payload = { ...createForm }
        if (!payload.coupon_code) delete payload.coupon_code
        if (!payload.license_id) delete payload.license_id
        const { data } = await billingApi.create(payload)
        if (data.success) {
            ElMessage.success(t('billing_page.subscription_created'))
            showCreate.value = false
            createForm.customer_id = null
            createForm.product_id = null
            createForm.plan_slug = ''
            createForm.coupon_code = ''
            createForm.license_id = ''
            fetchSubscriptions()
            fetchStats()
        } else {
            ElMessage.error(data.message || t('billing_page.create_fail'))
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('billing_page.create_fail'))
    } finally {
        submitting.value = false
    }
}

// Subscription actions
function viewSubscription(row) {
    router.push(`/billing/${row.id}`)
}

async function handleCancel(row) {
    try {
        await ElMessageBox.confirm(
            t('billing_page.cancel_confirm', { name: row.customer?.name || '' }),
            t('actions.confirm'),
            { type: 'warning' },
        )
        const { data } = await billingApi.cancel(row.id, t('billing_page.admin_cancel_reason'))
        if (data.success) {
            ElMessage.success(t('billing_page.subscription_canceled'))
            fetchSubscriptions()
            fetchStats()
        }
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('billing_page.cancel_fail'))
    }
}

async function handleResume(row) {
    try {
        await ElMessageBox.confirm(
            t('billing_page.resume_confirm', { name: row.customer?.name || '' }),
            t('actions.confirm'),
        )
        const { data } = await billingApi.resume(row.id)
        if (data.success) {
            ElMessage.success(t('billing_page.subscription_resumed'))
            fetchSubscriptions()
            fetchStats()
        }
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('billing_page.resume_fail'))
    }
}

// Plans CRUD
function showCreatePlanDialog() {
    resetPlanForm()
    showPlanForm.value = true
}

function editPlan(row) {
    Object.assign(planForm, {
        id: row.id, slug: row.slug, name: row.name, description: row.description || '',
        product_id: row.product_id, currency: row.currency || 'CNY',
        price_monthly: row.price_monthly, price_quarterly: row.price_quarterly,
        price_semi_annually: row.price_semi_annually, price_yearly: row.price_yearly,
        trial_days: row.trial_days || 0, sort_order: row.sort_order || 0,
        is_public: row.is_public ?? true, is_active: row.is_active ?? true,
        badge: row.badge || '',
    })
    featuresText.value = row.features ? JSON.stringify(row.features, null, 2) : ''
    limitsText.value = row.limits ? JSON.stringify(row.limits, null, 2) : ''
    showPlanForm.value = true
}

function resetPlanForm() {
    planForm.id = null
    planForm.slug = ''
    planForm.name = ''
    planForm.description = ''
    planForm.product_id = null
    planForm.currency = 'CNY'
    planForm.price_monthly = null
    planForm.price_quarterly = null
    planForm.price_semi_annually = null
    planForm.price_yearly = null
    planForm.trial_days = 0
    planForm.sort_order = 0
    planForm.is_public = true
    planForm.is_active = true
    planForm.badge = ''
    featuresText.value = ''
    limitsText.value = ''
}

async function handleSavePlan() {
    if (!planForm.slug || !planForm.name) {
        ElMessage.warning(t('billing_page.fill_plan_required'))
        return
    }
    planSubmitting.value = true
    try {
        const payload = {
            ...planForm,
            features: featuresText.value ? tryParseJson(featuresText.value, []) : [],
            limits: limitsText.value ? tryParseJson(limitsText.value, {}) : {},
        }
        delete payload.id

        let res
        if (planForm.id) {
            res = await billingApi.updatePlan(planForm.id, payload)
        } else {
            res = await billingApi.createPlan(payload)
        }
        if (res.data.success) {
            ElMessage.success(planForm.id ? t('billing_page.plan_updated') : t('billing_page.plan_created'))
            showPlanForm.value = false
            fetchPlans()
        } else {
            ElMessage.error(res.data.message || t('messages.failed'))
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('messages.failed'))
    } finally {
        planSubmitting.value = false
    }
}

async function handleDeletePlan(row) {
    try {
        await ElMessageBox.confirm(
            t('billing_page.deactivate_plan_confirm', { name: row.name }),
            t('actions.confirm'),
            { type: 'warning' },
        )
        const { data } = await billingApi.deletePlan(row.id)
        if (data.success) {
            ElMessage.success(t('billing_page.plan_deactivated'))
            fetchPlans()
        } else {
            ElMessage.error(data.message || t('billing_page.deactivate_fail'))
        }
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('messages.failed'))
    }
}

// Coupons CRUD
function showCreateCouponDialog() {
    resetCouponForm()
    showCouponForm.value = true
}

function editCoupon(row) {
    Object.assign(couponForm, {
        id: row.id, code: row.code, name: row.name, description: row.description || '',
        type: row.type, value: row.value, currency: row.currency || 'CNY',
        minimum_order_amount: row.minimum_order_amount,
        maximum_discount: row.maximum_discount,
        usage_limit: row.usage_limit, usage_limit_per_user: row.usage_limit_per_user,
        applicable_plans: row.applicable_plans || [],
        applicable_products: row.applicable_products || [],
        applicable_billing_periods: row.applicable_billing_periods || [],
        is_redeemable_with_other_coupons: row.is_redeemable_with_other_coupons ?? false,
        status: row.status || 'active',
        starts_at: row.starts_at ? new Date(row.starts_at) : null,
        expires_at: row.expires_at ? new Date(row.expires_at) : null,
    })
    showCouponForm.value = true
}

function resetCouponForm() {
    couponForm.id = null
    couponForm.code = ''
    couponForm.name = ''
    couponForm.description = ''
    couponForm.type = 'percentage'
    couponForm.value = 0
    couponForm.currency = 'CNY'
    couponForm.minimum_order_amount = null
    couponForm.maximum_discount = null
    couponForm.usage_limit = null
    couponForm.usage_limit_per_user = null
    couponForm.applicable_plans = []
    couponForm.applicable_products = []
    couponForm.applicable_billing_periods = []
    couponForm.is_redeemable_with_other_coupons = false
    couponForm.status = 'active'
    couponForm.starts_at = null
    couponForm.expires_at = null
}

async function handleSaveCoupon() {
    if (!couponForm.code || !couponForm.name || !couponForm.type) {
        ElMessage.warning(t('billing_page.fill_required'))
        return
    }
    couponSubmitting.value = true
    try {
        const payload = { ...couponForm }
        delete payload.id
        if (payload.starts_at) payload.starts_at = payload.starts_at.toISOString()
        if (payload.expires_at) payload.expires_at = payload.expires_at.toISOString()

        let res
        if (couponForm.id) {
            res = await billingApi.updateCoupon(couponForm.id, payload)
        } else {
            res = await billingApi.createCoupon(payload)
        }
        if (res.data.success) {
            ElMessage.success(couponForm.id ? t('billing_page.coupon_updated') : t('billing_page.coupon_created'))
            showCouponForm.value = false
            fetchCoupons()
            fetchCouponStats()
        } else {
            ElMessage.error(res.data.message || t('messages.failed'))
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('messages.failed'))
    } finally {
        couponSubmitting.value = false
    }
}

// Invoice detail
async function viewInvoice(row) {
    try {
        const { data } = await billingApi.showInvoice(row.id)
        if (data.success) {
            invoiceDetail.value = data.data
            showInvoiceDetail.value = true
        }
    } catch (e) {
        ElMessage.error(t('billing_page.load_invoice_fail'))
    }
}

// Coupon redemptions
async function showCouponRedemptions(row) {
    redemptionCoupon.value = row
    showRedemption.value = true
    loadingRedemptions.value = true
    try {
        const { data } = await billingApi.getCouponRedemptions(row.id, { per_page: 50 })
        if (data.success) {
            redemptions.value = data.data.data || data.data || []
        }
    } catch (e) {
        ElMessage.error(t('billing_page.load_redemptions_fail'))
    } finally {
        loadingRedemptions.value = false
    }
}

// Helpers
function tryParseJson(str, fallback) {
    try { return JSON.parse(str) }
    catch { return fallback }
}

// Init
onMounted(async () => {
    // Load plan options for create form
    try {
        const { data } = await billingApi.getPlans({ per_page: 50 })
        if (data.success) {
            planOptions.value = data.data.data || data.data || []
        }
    } catch (e) { /* ignore */ }
    try {
        const { data } = await productApi.list({ per_page: 100 })
        if (data.success) {
            productOptions.value = data.data?.data || data.data || []
        }
    } catch (e) { /* ignore */ }

    fetchStats()
    fetchSubscriptions()
    fetchInvoices()
    fetchPlans()
    fetchCoupons()
    fetchCouponStats()
})
</script>

<style scoped>
.billing-page :deep(.page-header) {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}
.billing-page :deep(.page-header h2) {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
}
.stat-card {
    text-align: center;
    padding: 8px 0;
}
.stat-card .stat-value {
    font-size: 28px;
    font-weight: 700;
    color: #303133;
    line-height: 1.2;
}
.stat-card .stat-label {
    font-size: 13px;
    color: #909399;
    margin-top: 4px;
}
.mini-stat {
    text-align: center;
    padding: 4px 0;
}
.mini-stat .mini-value {
    font-size: 20px;
    font-weight: 600;
    color: #303133;
}
.mini-stat .mini-label {
    font-size: 12px;
    color: #909399;
    margin-top: 2px;
}
.font-mono {
    font-family: 'SF Mono', 'Fira Code', monospace;
}
.mb-3 { margin-bottom: 12px; }
.mb-4 { margin-bottom: 16px; }
.ml-1 { margin-left: 4px; }
.text-center { text-align: center; }
.text-gray-400 { color: #909399; }
.py-8 { padding-top: 32px; padding-bottom: 32px; }
.tab-header { display: flex; justify-content: flex-end; }
</style>

























