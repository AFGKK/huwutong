<template>
    <div class="billing-page">
        <div class="page-header">
            <h2>订阅计费</h2>
            <div class="header-actions">
                <el-button type="primary" @click="showCreate = true">
                    <el-icon><Plus /></el-icon> 创建订阅
                </el-button>
            </div>
        </div>

        <!-- Stats Cards -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value">{{ stats.active }}</div>
                        <div class="stat-label">活跃订阅</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #e6a23c">{{ stats.in_grace_period }}</div>
                        <div class="stat-label">宽限期</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #409eff">{{ stats.mrr }}</div>
                        <div class="stat-label">MRR (¥)</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #67c23a">{{ stats.estimated_arr }}</div>
                        <div class="stat-label">ARR (¥)</div>
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
                        <div class="mini-label">定价方案</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value">{{ stats.active_coupons }}</div>
                        <div class="mini-label">活跃优惠券</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value">{{ stats.coupon_usage_30d }}</div>
                        <div class="mini-label">近30天用券</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value">¥{{ stats.recent_revenue || 0 }}</div>
                        <div class="mini-label">本月收入</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value">¥{{ stats.coupon_savings_30d || 0 }}</div>
                        <div class="mini-label">近30天折扣</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Tabs -->
        <el-card shadow="never">
            <el-tabs v-model="activeTab">
                <el-tab-pane label="订阅列表" name="subscriptions">
                    <el-table :data="subscriptions" v-loading="loading" stripe>
                        <el-table-column prop="id" label="ID" width="60" />
                        <el-table-column label="客户" min-width="150">
                            <template #default="{ row }">
                                {{ row.customer?.name || row.customer?.user?.name || '—' }}
                            </template>
                        </el-table-column>
                        <el-table-column label="产品" min-width="120">
                            <template #default="{ row }">
                                {{ row.product?.name || '—' }}
                            </template>
                        </el-table-column>
                        <el-table-column prop="plan" label="方案" width="100" />
                        <el-table-column label="金额" width="130">
                            <template #default="{ row }">
                                ¥{{ row.price }} / {{ periodLabel(row.billing_period) }}
                            </template>
                        </el-table-column>
                        <el-table-column label="状态" width="100">
                            <template #default="{ row }">
                                <el-tag :type="statusType(row.status)" size="small">
                                    {{ statusLabel(row.status) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="到期" width="170">
                            <template #default="{ row }">
                                {{ row.ends_at ? formatTime(row.ends_at) : '—' }}
                            </template>
                        </el-table-column>
                        <el-table-column label="自动续费" width="90">
                            <template #default="{ row }">
                                <el-tag :type="row.auto_renew ? 'success' : 'info'" size="small">
                                    {{ row.auto_renew ? '开启' : '关闭' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="200" fixed="right">
                            <template #default="{ row }">
                                <el-button text size="small" @click="viewSubscription(row)">详情</el-button>
                                <el-button v-if="row.status === 'active'" text size="small" type="warning" @click="handleCancel(row)">取消</el-button>
                                <el-button v-if="row.status !== 'active' && row.status !== 'expired'" text size="small" type="primary" @click="handleResume(row)">恢复</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <el-tab-pane label="发票记录" name="invoices">
                    <el-table :data="invoices" v-loading="loadingInvoices" stripe>
                        <el-table-column prop="invoice_no" label="发票号" width="180" />
                        <el-table-column label="客户" min-width="130">
                            <template #default="{ row }">
                                {{ row.customer?.name || row.customer?.user?.name || '—' }}
                            </template>
                        </el-table-column>
                        <el-table-column label="金额" width="110">
                            <template #default="{ row }">
                                <span class="font-mono">¥{{ row.amount }}</span>
                                <el-tag v-if="row.discount_amount > 0" size="small" type="warning" class="ml-1">已折扣</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="折扣" width="90">
                            <template #default="{ row }">
                                <span v-if="row.discount_amount > 0" class="font-mono" style="color:#e6a23c">-¥{{ row.discount_amount }}</span>
                                <span v-else>—</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="状态" width="90">
                            <template #default="{ row }">
                                <el-tag :type="invoiceStatusType(row.status)" size="small">
                                    {{ invoiceStatusLabel(row.status) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="billing_reason" label="原因" width="140" />
                        <el-table-column label="创建时间" width="170">
                            <template #default="{ row }">
                                {{ formatTime(row.created_at) }}
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="120">
                            <template #default="{ row }">
                                <el-button text size="small" @click="viewInvoice(row)">详情</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <el-tab-pane label="定价方案" name="plans">
                    <div class="tab-header mb-3">
                        <el-button type="primary" @click="showCreatePlanDialog">
                            <el-icon><Plus /></el-icon> 创建方案
                        </el-button>
                    </div>
                    <div v-loading="loadingPlans">
                        <div v-if="plans.length === 0" class="text-center text-gray-400 py-8">暂无定价方案</div>
                        <el-table v-else :data="plans" stripe>
                            <el-table-column prop="name" label="方案名称" width="150" />
                            <el-table-column prop="slug" label="标识" width="120" />
                            <el-table-column label="月付" width="100">
                                <template #default="{ row }">¥{{ row.price_monthly || '—' }}</template>
                            </el-table-column>
                            <el-table-column label="季付" width="100">
                                <template #default="{ row }">¥{{ row.price_quarterly || '—' }}</template>
                            </el-table-column>
                            <el-table-column label="半年付" width="100">
                                <template #default="{ row }">¥{{ row.price_semi_annually || '—' }}</template>
                            </el-table-column>
                            <el-table-column label="年付" width="100">
                                <template #default="{ row }">¥{{ row.price_yearly || '—' }}</template>
                            </el-table-column>
                            <el-table-column label="试用" width="70">
                                <template #default="{ row }">{{ row.trial_days }}天</template>
                            </el-table-column>
                            <el-table-column label="状态" width="80">
                                <template #default="{ row }">
                                    <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">
                                        {{ row.is_active ? '启用' : '停用' }}
                                    </el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column label="角标" width="80">
                                <template #default="{ row }">
                                    <el-tag v-if="row.badge" type="warning" size="small">{{ row.badge }}</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column label="操作" width="150">
                                <template #default="{ row }">
                                    <el-button text size="small" @click="editPlan(row)">编辑</el-button>
                                    <el-button text size="small" type="danger" @click="handleDeletePlan(row)">停用</el-button>
                                </template>
                            </el-table-column>
                        </el-table>
                    </div>
                </el-tab-pane>

                <el-tab-pane label="优惠券" name="coupons">
                    <div class="tab-header mb-3">
                        <el-button type="primary" @click="showCreateCouponDialog">
                            <el-icon><Plus /></el-icon> 创建优惠券
                        </el-button>
                    </div>

                    <div class="coupon-stats mb-3">
                        <el-row :gutter="12">
                            <el-col :span="6">
                                <el-statistic title="总优惠券" :value="couponStats.total" />
                            </el-col>
                            <el-col :span="6">
                                <el-statistic title="活跃" :value="couponStats.active" />
                            </el-col>
                            <el-col :span="6">
                                <el-statistic title="总使用次数" :value="couponStats.total_redemptions" />
                            </el-col>
                            <el-col :span="6">
                                <el-statistic title="总折扣金额" :value="'¥' + (couponStats.total_discount_amount || 0)" />
                            </el-col>
                        </el-row>
                    </div>

                    <el-table :data="coupons" v-loading="loadingCoupons" stripe>
                        <el-table-column prop="code" label="优惠码" width="120" />
                        <el-table-column prop="name" label="名称" min-width="120" />
                        <el-table-column label="类型" width="100">
                            <template #default="{ row }">
                                {{ couponTypeLabel(row.type) }}
                            </template>
                        </el-table-column>
                        <el-table-column label="折扣" width="100">
                            <template #default="{ row }">
                                {{ row.type === 'percentage' ? row.value + '%' : '¥' + row.value }}
                            </template>
                        </el-table-column>
                        <el-table-column label="状态" width="70">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'active' ? 'success' : 'danger'" size="small">
                                    {{ row.status === 'active' ? '启用' : '停用' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="使用/限制" width="100">
                            <template #default="{ row }">
                                {{ row.usage_count || 0 }} / {{ row.usage_limit || '不限' }}
                            </template>
                        </el-table-column>
                        <el-table-column label="有效期" width="170">
                            <template #default="{ row }">
                                {{ row.expires_at ? formatTime(row.expires_at) : '永久' }}
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="150">
                            <template #default="{ row }">
                                <el-button text size="small" @click="editCoupon(row)">编辑</el-button>
                                <el-button text size="small" @click="showCouponRedemptions(row)">记录</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>
            </el-tabs>
        </el-card>

        <!-- 创建订阅 Dialog -->
        <el-dialog v-model="showCreate" title="创建订阅" width="600px" :close-on-click-modal="false">
            <el-form ref="createFormRef" :model="createForm" label-width="100px" v-loading="submitting">
                <el-form-item label="客户" prop="customer_id" required>
                    <el-select v-model="createForm.customer_id" filterable remote
                        :remote-method="searchCustomers" :loading="searchingCustomer"
                        placeholder="搜索客户" style="width:100%">
                        <el-option v-for="c in customerOptions" :key="c.id"
                            :label="c.name || c.user?.name || 'ID:'+c.id"
                            :value="c.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="产品" prop="product_id" required>
                    <el-select v-model="createForm.product_id" style="width:100%">
                        <el-option v-for="p in productOptions" :key="p.id"
                            :label="p.name" :value="p.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="定价方案" prop="plan_slug" required>
                    <el-select v-model="createForm.plan_slug" style="width:100%">
                        <el-option v-for="p in planOptions" :key="p.slug"
                            :label="`${p.name} (${p.slug})`" :value="p.slug" />
                    </el-select>
                </el-form-item>
                <el-form-item label="计费周期" prop="billing_period">
                    <el-select v-model="createForm.billing_period" style="width:100%">
                        <el-option label="月付" value="monthly" />
                        <el-option label="季付" value="quarterly" />
                        <el-option label="半年付" value="semi_annually" />
                        <el-option label="年付" value="yearly" />
                    </el-select>
                </el-form-item>
                <el-form-item label="自动续费">
                    <el-switch v-model="createForm.auto_renew" />
                </el-form-item>
                <el-form-item label="试用天数">
                    <el-input-number v-model="createForm.trial_days" :min="0" :max="90" />
                </el-form-item>
                <el-form-item label="宽限天数">
                    <el-input-number v-model="createForm.grace_days" :min="0" :max="90" />
                </el-form-item>
                <el-form-item label="优惠码">
                    <el-input v-model="createForm.coupon_code" placeholder="可选" />
                </el-form-item>
                <el-form-item label="关联 License">
                    <el-input v-model="createForm.license_id" placeholder="License ID，可选" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showCreate = false">取消</el-button>
                <el-button type="primary" :loading="submitting" @click="handleCreate">创建</el-button>
            </template>
        </el-dialog>

        <!-- 定价方案 Dialog -->
        <el-dialog v-model="showPlanForm" :title="planForm.id ? '编辑定价方案' : '创建定价方案'" width="700px">
            <el-form ref="planFormRef" :model="planForm" label-width="100px" v-loading="planSubmitting">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="方案标识" prop="slug" required>
                            <el-input v-model="planForm.slug" :disabled="!!planForm.id" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="方案名称" prop="name" required>
                            <el-input v-model="planForm.name" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item label="描述" prop="description">
                    <el-input v-model="planForm.description" type="textarea" :rows="2" />
                </el-form-item>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="关联产品" prop="product_id">
                            <el-select v-model="planForm.product_id" clearable style="width:100%">
                                <el-option v-for="p in productOptions" :key="p.id"
                                    :label="p.name" :value="p.id" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="货币" prop="currency">
                            <el-input v-model="planForm.currency" placeholder="CNY" maxlength="3" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-divider>价格设置</el-divider>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="月付" prop="price_monthly">
                            <el-input-number v-model="planForm.price_monthly" :min="0" :precision="2" style="width:100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="季付" prop="price_quarterly">
                            <el-input-number v-model="planForm.price_quarterly" :min="0" :precision="2" style="width:100%" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="半年付" prop="price_semi_annually">
                            <el-input-number v-model="planForm.price_semi_annually" :min="0" :precision="2" style="width:100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="年付" prop="price_yearly">
                            <el-input-number v-model="planForm.price_yearly" :min="0" :precision="2" style="width:100%" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-divider>其他设置</el-divider>
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item label="试用天数">
                            <el-input-number v-model="planForm.trial_days" :min="0" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="排序">
                            <el-input-number v-model="planForm.sort_order" :min="0" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="角标">
                            <el-input v-model="planForm.badge" placeholder="推荐/热门" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item label="公开可见">
                            <el-switch v-model="planForm.is_public" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="启用">
                            <el-switch v-model="planForm.is_active" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-divider>功能特性 (JSON)</el-divider>
                <el-input v-model="featuresText" type="textarea" :rows="4"
                    placeholder='["功能1", "功能2", "功能3"]' />
                <el-divider>使用限制 (JSON)</el-divider>
                <el-input v-model="limitsText" type="textarea" :rows="4"
                    placeholder='{"max_users": 10, "max_projects": 5}' />
            </el-form>
            <template #footer>
                <el-button @click="showPlanForm = false">取消</el-button>
                <el-button type="primary" :loading="planSubmitting" @click="handleSavePlan">保存</el-button>
            </template>
        </el-dialog>

        <!-- 优惠券 Dialog -->
        <el-dialog v-model="showCouponForm" :title="couponForm.id ? '编辑优惠券' : '创建优惠券'" width="700px">
            <el-form ref="couponFormRef" :model="couponForm" label-width="120px" v-loading="couponSubmitting">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="优惠码" prop="code" required>
                            <el-input v-model="couponForm.code" :disabled="!!couponForm.id" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="名称" prop="name" required>
                            <el-input v-model="couponForm.name" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item label="描述" prop="description">
                    <el-input v-model="couponForm.description" type="textarea" :rows="2" />
                </el-form-item>
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item label="类型" prop="type" required>
                            <el-select v-model="couponForm.type" style="width:100%">
                                <el-option label="百分比" value="percentage" />
                                <el-option label="固定金额" value="fixed_amount" />
                                <el-option label="免费试用" value="free_trial" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="折扣值" prop="value" required>
                            <el-input-number v-model="couponForm.value" :min="0" :precision="2" style="width:100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="货币" prop="currency">
                            <el-input v-model="couponForm.currency" placeholder="CNY" maxlength="3" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-divider>使用限制</el-divider>
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item label="总使用上限">
                            <el-input-number v-model="couponForm.usage_limit" :min="0" style="width:100%" placeholder="0=不限" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="每用户上限">
                            <el-input-number v-model="couponForm.usage_limit_per_user" :min="0" style="width:100%" placeholder="0=不限" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="最高折扣">
                            <el-input-number v-model="couponForm.maximum_discount" :min="0" :precision="2" style="width:100%" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="最低订单金额">
                            <el-input-number v-model="couponForm.minimum_order_amount" :min="0" :precision="2" style="width:100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="允许与其他券叠加">
                            <el-switch v-model="couponForm.is_redeemable_with_other_coupons" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-divider>有效期</el-divider>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="开始时间">
                            <el-date-picker v-model="couponForm.starts_at" type="datetime" style="width:100%"
                                placeholder="立即生效" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="过期时间">
                            <el-date-picker v-model="couponForm.expires_at" type="datetime" style="width:100%"
                                placeholder="永不过期" />
                        </el-form-item>
                    </el-col>
                </el-row>
            </el-form>
            <template #footer>
                <el-button @click="showCouponForm = false">取消</el-button>
                <el-button type="primary" :loading="couponSubmitting" @click="handleSaveCoupon">保存</el-button>
            </template>
        </el-dialog>

        <!-- 发票详情 Dialog -->
        <el-dialog v-model="showInvoiceDetail" title="发票详情" width="550px">
            <el-descriptions v-if="invoiceDetail" :column="2" border>
                <el-descriptions-item label="发票号">{{ invoiceDetail.invoice_no }}</el-descriptions-item>
                <el-descriptions-item label="状态">
                    <el-tag :type="invoiceDetail.status === 'paid' ? 'success' : 'warning'" size="small">
                        {{ invoiceDetail.status === 'paid' ? '已支付' : invoiceDetail.status === 'pending' ? '待支付' : invoiceDetail.status }}
                    </el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="金额">¥{{ invoiceDetail.amount }}</el-descriptions-item>
                <el-descriptions-item label="折扣">¥{{ invoiceDetail.discount_amount || 0 }}</el-descriptions-item>
                <el-descriptions-item label="原因">{{ invoiceDetail.billing_reason }}</el-descriptions-item>
                <el-descriptions-item label="创建时间">{{ formatTime(invoiceDetail.created_at) }}</el-descriptions-item>
                <el-descriptions-item label="支付时间">{{ invoiceDetail.paid_at ? formatTime(invoiceDetail.paid_at) : '—' }}</el-descriptions-item>
                <el-descriptions-item label="到期时间">{{ invoiceDetail.due_at ? formatTime(invoiceDetail.due_at) : '—' }}</el-descriptions-item>
            </el-descriptions>
            <template #footer>
                <el-button @click="showInvoiceDetail = false">关闭</el-button>
            </template>
        </el-dialog>

        <!-- 优惠券使用记录 Dialog -->
        <el-dialog v-model="showRedemption" :title="'优惠券使用记录 - ' + (redemptionCoupon?.code || '')" width="650px">
            <el-table :data="redemptions" v-loading="loadingRedemptions" stripe max-height="400">
                <el-table-column label="客户" min-width="130">
                    <template #default="{ row }">{{ row.customer?.user?.name || '—' }}</template>
                </el-table-column>
                <el-table-column label="订阅方案" width="100">
                    <template #default="{ row }">{{ row.subscription?.plan || '—' }}</template>
                </el-table-column>
                <el-table-column label="折扣金额" width="110">
                    <template #default="{ row }">¥{{ row.discount_amount }}</template>
                </el-table-column>
                <el-table-column label="原始金额" width="110">
                    <template #default="{ row }">¥{{ row.original_amount }}</template>
                </el-table-column>
                <el-table-column label="最终金额" width="110">
                    <template #default="{ row }">¥{{ row.final_amount }}</template>
                </el-table-column>
                <el-table-column label="使用时间" width="170">
                    <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
                </el-table-column>
            </el-table>
            <template #footer>
                <el-button @click="showRedemption = false">关闭</el-button>
            </template>
        </el-dialog>
    </div>
</template>
<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Coin, CircleClose, CircleCheck, Refresh, ArrowLeft } from '@element-plus/icons-vue'
import billingApi from '@/api/billing'
import customerApi from '@/api/customer'
import productApi from '@/api/product'

const router = useRouter()

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

// ── Methods ──

// Period labels
function periodLabel(p) {
    const map = { monthly: '月', quarterly: '季', semi_annually: '半年', yearly: '年' }
    return map[p] || p
}
function statusType(s) {
    const map = { active: 'success', grace: 'warning', expired: 'danger', canceled: 'info', suspended: 'info' }
    return map[s] || 'info'
}
function statusLabel(s) {
    const map = { active: '活跃', grace: '宽限期', expired: '已过期', canceled: '已取消', suspended: '已暂停', trialing: '试用中' }
    return map[s] || s
}
function invoiceStatusType(s) {
    const map = { paid: 'success', pending: 'warning', cancelled: 'info', refunded: 'danger' }
    return map[s] || 'info'
}
function invoiceStatusLabel(s) {
    const map = { paid: '已支付', pending: '待支付', cancelled: '已取消', refunded: '已退款' }
    return map[s] || s
}
function couponTypeLabel(t) {
    const map = { percentage: '百分比', fixed_amount: '固定金额', free_trial: '免费试用', custom: '自定义' }
    return map[t] || t
}

function formatTime(t) {
    if (!t) return '—'
    return new Date(t).toLocaleString('zh-CN', { hour12: false })
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
        ElMessage.error('加载订阅列表失败')
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
        ElMessage.error('加载发票记录失败')
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
        ElMessage.error('加载定价方案失败')
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
        ElMessage.error('加载优惠券失败')
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
        ElMessage.warning('请填写必要信息')
        return
    }
    submitting.value = true
    try {
        const payload = { ...createForm }
        if (!payload.coupon_code) delete payload.coupon_code
        if (!payload.license_id) delete payload.license_id
        const { data } = await billingApi.create(payload)
        if (data.success) {
            ElMessage.success('订阅创建成功')
            showCreate.value = false
            createForm.customer_id = null
            createForm.product_id = null
            createForm.plan_slug = ''
            createForm.coupon_code = ''
            createForm.license_id = ''
            fetchSubscriptions()
            fetchStats()
        } else {
            ElMessage.error(data.message || '创建失败')
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '创建失败')
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
        await ElMessageBox.confirm(`确定取消 ${row.customer?.name || ''} 的订阅？`, '确认', { type: 'warning' })
        const { data } = await billingApi.cancel(row.id, '管理员取消')
        if (data.success) {
            ElMessage.success('订阅已取消')
            fetchSubscriptions()
            fetchStats()
        }
    } catch (e) {
        if (e !== 'cancel') ElMessage.error('取消失败')
    }
}

async function handleResume(row) {
    try {
        await ElMessageBox.confirm(`确定恢复 ${row.customer?.name || ''} 的订阅？`, '确认')
        const { data } = await billingApi.resume(row.id)
        if (data.success) {
            ElMessage.success('订阅已恢复')
            fetchSubscriptions()
            fetchStats()
        }
    } catch (e) {
        if (e !== 'cancel') ElMessage.error('恢复失败')
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
        ElMessage.warning('请填写方案标识和名称')
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
            ElMessage.success(planForm.id ? '方案已更新' : '方案已创建')
            showPlanForm.value = false
            fetchPlans()
        } else {
            ElMessage.error(res.data.message || '操作失败')
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败')
    } finally {
        planSubmitting.value = false
    }
}

async function handleDeletePlan(row) {
    try {
        await ElMessageBox.confirm(`确定停用定价方案「${row.name}」？`, '确认', { type: 'warning' })
        const { data } = await billingApi.deletePlan(row.id)
        if (data.success) {
            ElMessage.success('方案已停用')
            fetchPlans()
        } else {
            ElMessage.error(data.message || '停用失败')
        }
    } catch (e) {
        if (e !== 'cancel') ElMessage.error('操作失败')
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
        ElMessage.warning('请填写必要信息')
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
            ElMessage.success(couponForm.id ? '优惠券已更新' : '优惠券已创建')
            showCouponForm.value = false
            fetchCoupons()
            fetchCouponStats()
        } else {
            ElMessage.error(res.data.message || '操作失败')
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败')
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
        ElMessage.error('加载发票详情失败')
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
        ElMessage.error('加载使用记录失败')
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









