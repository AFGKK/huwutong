<template>
    <div class="currency-manager">
        <el-page-header :content="t('nav.currency') + ' ' + activeTabText" @back="router.push('/')" />

        <el-tabs v-model="activeTab" class="mt-4">
            <!-- ═══ 汇率管理 ═══ -->
            <el-tab-pane :label="t('currency_page.tabs.rates')" name="rates">
                <div class="flex justify-between mb-4">
                    <el-button type="primary" @click="openRateDialog">{{ t('currency_page.buttons.add_rate') }}</el-button>
                    <el-button @click="syncRates" :loading="loading">{{ t('currency_page.buttons.sync_ecb') }}</el-button>
                </div>

                <el-table :data="rates" v-loading="loading" stripe border>
                    <el-table-column prop="from_currency" :label="t('currency_page.cols.from_currency')" width="100" />
                    <el-table-column prop="to_currency" :label="t('currency_page.cols.to_currency')" width="100" />
                    <el-table-column :label="t('currency_page.cols.rate')">
                        <template #default="{ row }">
                            <span class="font-mono">{{ Number(row.rate).toFixed(8) }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="provider" :label="t('currency_page.cols.provider')" width="100" />
                    <el-table-column :label="t('currency_page.cols.effective_at')" width="180">
                        <template #default="{ row }">{{ row.effective_at ? new Date(row.effective_at).toLocaleString() : '-' }}</template>
                    </el-table-column>
                    <el-table-column :label="t('currency_page.cols.expires_at')" width="180">
                        <template #default="{ row }">{{ row.expires_at ? new Date(row.expires_at).toLocaleString() : t('currency_page.never_expires') }}</template>
                    </el-table-column>
                    <el-table-column :label="t('product_sku_page.col_actions')" width="120" fixed="right">
                        <template #default="{ row }">
                            <el-button size="small" type="danger" @click="confirmDeleteRate(row.id)">{{ t('actions.delete') }}</el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </el-tab-pane>

            <!-- ═══ 定价计划 ═══ -->
            <el-tab-pane :label="t('currency_page.tabs.plans')" name="plans">
                <div class="flex justify-between mb-4">
                    <el-button type="primary" @click="openPlanDialog">{{ t('currency_page.buttons.create_plan') }}</el-button>
                </div>

                <el-table :data="pricingPlans" v-loading="loading" stripe border>
                    <el-table-column prop="name" :label="t('currency_page.cols.plan_name')" min-width="150" />
                    <el-table-column prop="slug" :label="t('currency_page.cols.slug')" width="120" />
                    <el-table-column prop="billing_period" :label="t('currency_page.cols.billing_period')" width="100">
                        <template #default="{ row }">
                            <el-tag :type="row.billing_period === 'yearly' ? 'success' : row.billing_period === 'one_time' ? 'info' : ''">
                                {{ billingPeriodLabel(row.billing_period) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('currency_page.cols.multi_currency_prices')" min-width="300">
                        <template #default="{ row }">
                            <div class="flex flex-wrap gap-2">
                                <el-tag v-for="p in (row.prices || [])" :key="p.currency" type="warning" effect="plain" class="currency-price-tag">
                                    {{ p.currency }} {{ formatCurrency(p.price, p.currency) }}
                                </el-tag>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column prop="is_active" :label="t('product_sku_page.col_status')" width="80">
                        <template #default="{ row }">
                            <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">
                                {{ row.is_active ? t('currency_page.status.enabled') : t('currency_page.status.disabled') }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('product_sku_page.col_actions')" width="160" fixed="right">
                        <template #default="{ row }">
                            <el-button size="small" @click="editPlan(row)">{{ t('actions.edit') }}</el-button>
                            <el-button size="small" type="danger" @click="confirmDeletePlan(row.id)">{{ t('actions.delete') }}</el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </el-tab-pane>

            <!-- ═══ 货币转换工具 ═══ -->
            <el-tab-pane :label="t('currency_page.tabs.convert')" name="convert">
                <el-card class="convert-card">
                    <el-form :model="{ amount: convertAmount, from: convertFrom, to: convertTo }" label-width="100">
                        <el-form-item :label="t('currency_page.cols.amount')">
                            <el-input-number v-model="convertAmount" :min="0" :precision="2" style="width: 200px" />
                        </el-form-item>
                        <el-form-item :label="t('currency_page.cols.from_currency')">
                            <el-select v-model="convertFrom" filterable style="width: 140px">
                                <el-option v-for="c in currencies" :key="c.code" :label="`${c.code} ${c.symbol}`" :value="c.code" />
                            </el-select>
                        </el-form-item>
                        <el-form-item :label="t('currency_page.cols.to_currency')">
                            <el-select v-model="convertTo" filterable style="width: 140px">
                                <el-option v-for="c in currencies" :key="c.code" :label="`${c.code} ${c.symbol}`" :value="c.code" />
                            </el-select>
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="doConvert">{{ t('currency_page.buttons.convert') }}</el-button>
                        </el-form-item>
                    </el-form>

                    <div v-if="convertResult" class="convert-result mt-4 p-4 bg-gray-50 rounded">
                        <p class="text-lg">
                            {{ formatCurrency(convertResult.amount / (convertResult.rate || 1), convertResult.from) }}
                            <el-icon><ArrowRight /></el-icon>
                            <strong class="text-primary text-xl">{{ formatCurrency(convertResult.amount, convertResult.to) }}</strong>
                        </p>
                        <p class="text-sm text-gray-500 mt-1">
                            {{ t('currency_page.convert.rate_line', {
                                from: convertResult.from,
                                rate: convertResult.rate?.toFixed(8),
                                to: convertResult.to,
                            }) }}
                        </p>
                        <p v-if="convertResult.error" class="text-red-500 mt-1">{{ convertResult.error }}</p>
                    </div>
                </el-card>
            </el-tab-pane>

            <!-- ═══ 商品定价 (来自 multi-currency-pricing) ═══ -->
            <el-tab-pane :label="t('currency_page.tabs.product_pricing')" name="productPricing">
                <div v-if="mcp_tabVisited" class="mcp-content">
                    <el-tabs v-model="mcp_activeTab" class="mt-4">
                        <!-- ─── 概览 ─── -->
                        <el-tab-pane :label="t('multi_currency_pricing_page.tabs.overview')" name="overview">
                            <el-row :gutter="16" class="mb-4">
                                <el-col :span="6">
                                    <el-card shadow="hover">
                                        <el-statistic :title="t('product_sku_page.stat_total')" :value="mcp_dashboard.total_skus || 0" />
                                    </el-card>
                                </el-col>
                                <el-col :span="6">
                                    <el-card shadow="hover">
                                        <el-statistic :title="t('multi_currency_pricing_page.stats.multi_currency_skus')" :value="mcp_dashboard.multi_currency_skus || 0" />
                                    </el-card>
                                </el-col>
                                <el-col :span="6">
                                    <el-card shadow="hover">
                                        <el-statistic :title="t('multi_currency_pricing_page.stats.coverage')" :value="mcp_coverageRate" suffix="%" />
                                    </el-card>
                                </el-col>
                                <el-col :span="6">
                                    <el-card shadow="hover">
                                        <el-statistic :title="t('multi_currency_pricing_page.stats.currency_count')" :value="mcp_dashboard.currency_count || 0" />
                                    </el-card>
                                </el-col>
                            </el-row>

                            <el-card v-loading="mcp_dashboardLoading">
                                <template #header>
                                    <span>{{ t('multi_currency_pricing_page.overview.coverage_title') }}</span>
                                </template>
                                <el-table :data="mcp_currencyCoverage" stripe border :empty-text="t('messages.no_data')">
                                    <el-table-column prop="currency" :label="t('product_sku_page.col_currency')" width="100" />
                                    <el-table-column prop="total" :label="t('multi_currency_pricing_page.overview.col_sku_count')" width="120" />
                                    <el-table-column :label="t('multi_currency_pricing_page.overview.col_share')" min-width="200">
                                        <template #default="{ row }">
                                            <div class="flex items-center gap-2">
                                                <el-progress :percentage="row.percentage || 0" :stroke-width="16" />
                                            </div>
                                        </template>
                                    </el-table-column>
                                    <el-table-column prop="last_updated" :label="t('multi_currency_pricing_page.overview.col_last_updated')" min-width="160">
                                        <template #default="{ row }">
                                            {{ row.last_updated ? new Date(row.last_updated).toLocaleString() : '-' }}
                                        </template>
                                    </el-table-column>
                                </el-table>
                            </el-card>
                        </el-tab-pane>

                        <!-- ─── SKU 定价管理 ─── -->
                        <el-tab-pane :label="t('multi_currency_pricing_page.tabs.skus')" name="skus">
                            <div class="flex justify-between mb-4">
                                <div class="flex items-center gap-2">
                                    <el-input
                                        v-model="mcp_skuFilter.product_id"
                                        :placeholder="t('multi_currency_pricing_page.skus.search_product_id_ph')"
                                        clearable
                                        style="width: 220px"
                                        @keyup.enter="mcp_loadSkus"
                                    />
                                    <el-button type="primary" @click="mcp_loadSkus">{{ t('actions.search') }}</el-button>
                                    <el-button @click="mcp_resetSkuFilter">{{ t('actions.reset') }}</el-button>
                                </div>
                            </div>

                            <el-table :data="mcp_skus" v-loading="mcp_skusLoading" stripe border>
                                <el-table-column prop="sku_code" :label="t('product_sku_page.col_sku_code')" width="140" />
                                <el-table-column prop="product_name" :label="t('multi_currency_pricing_page.skus.col_product_name')" min-width="180" />
                                <el-table-column :label="t('multi_currency_pricing_page.skus.col_base_price')" width="120">
                                    <template #default="{ row }">
                                        <span class="font-mono">{{ row.base_price ? '¥' + Number(row.base_price).toFixed(2) : '-' }}</span>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="t('multi_currency_pricing_page.skus.col_currency_count')" width="80" align="center">
                                    <template #default="{ row }">
                                        <el-tag>{{ row.currency_count || 0 }}</el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="t('product_sku_page.col_status')" width="100">
                                    <template #default="{ row }">
                                        <el-tag :type="row.is_enabled ? 'success' : 'info'" size="small">
                                            {{ row.is_enabled ? t('dynamic_pricing_page.status.enabled') : t('dynamic_pricing_page.status.disabled') }}
                                        </el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="t('product_sku_page.col_actions')" width="200" fixed="right">
                                    <template #default="{ row }">
                                        <el-button size="small" type="primary" @click="mcp_openPriceDialog(row)">{{ t('multi_currency_pricing_page.skus.edit_prices') }}</el-button>
                                        <el-button
                                            size="small"
                                            :type="row.is_enabled ? 'danger' : 'default'"
                                            @click="mcp_toggleEnabled(row)"
                                        >
                                            {{ row.is_enabled ? t('actions.disable') : t('actions.enable') }}
                                        </el-button>
                                    </template>
                                </el-table-column>
                            </el-table>

                            <div v-if="mcp_skusPagination.total > 0" class="flex justify-center mt-4">
                                <el-pagination
                                    v-model:current-page="mcp_skusPagination.current_page"
                                    :page-size="mcp_skusPagination.per_page"
                                    :total="mcp_skusPagination.total"
                                    layout="prev, pager, next, total"
                                    @current-change="mcp_loadSkus"
                                />
                            </div>
                        </el-tab-pane>

                        <!-- ─── 批量更新 ─── -->
                        <el-tab-pane :label="t('multi_currency_pricing_page.tabs.batch')" name="batch">
                            <el-alert
                                :title="t('multi_currency_pricing_page.batch.alert_title')"
                                :description="t('multi_currency_pricing_page.batch.alert_desc')"
                                type="info"
                                show-icon
                                :closable="false"
                                class="mb-4"
                            />

                            <el-row :gutter="16">
                                <el-col :span="16">
                                    <el-card>
                                        <template #header>
                                            <div class="flex justify-between items-center">
                                                <span>{{ t('multi_currency_pricing_page.batch.input_title') }}</span>
                                                <div>
                                                    <el-upload
                                                        accept=".json,.csv"
                                                        :show-file-list="false"
                                                        :before-upload="mcp_handleFileUpload"
                                                        :auto-upload="false"
                                                    >
                                                        <el-button size="small">{{ t('multi_currency_pricing_page.batch.upload_file') }}</el-button>
                                                    </el-upload>
                                                </div>
                                            </div>
                                        </template>
                                        <el-input
                                            v-model="mcp_batchInput"
                                            type="textarea"
                                            :rows="12"
                                            :placeholder="t('multi_currency_pricing_page.batch.json_placeholder')"
                                            class="font-mono"
                                        />
                                    </el-card>
                                </el-col>
                                <el-col :span="8">
                                    <el-card>
                                        <template #header><span>{{ t('multi_currency_pricing_page.batch.format_title') }}</span></template>
                                        <div class="text-sm text-gray-600">
                                            <p class="mb-2">{{ t('multi_currency_pricing_page.batch.format_intro') }}</p>
                                            <ul class="list-disc pl-4 space-y-1">
                                                <li><code>sku_code</code> - {{ t('multi_currency_pricing_page.batch.format_sku_code') }}</li>
                                                <li><code>prices</code> - {{ t('multi_currency_pricing_page.batch.format_prices') }}</li>
                                                <li>{{ t('multi_currency_pricing_page.batch.format_price_fields') }}</li>
                                            </ul>
                                        </div>
                                    </el-card>
                                </el-col>
                            </el-row>

                            <div class="mt-4 flex gap-2">
                                <el-button type="primary" @click="mcp_handleBulkEdit" :loading="mcp_batchLoading">{{ t('multi_currency_pricing_page.batch.submit') }}</el-button>
                                <el-button @click="mcp_batchInput = ''">{{ t('multi_currency_pricing_page.batch.clear') }}</el-button>
                            </div>

                            <el-card v-if="mcp_batchResult" class="mt-4">
                                <template #header><span>{{ t('multi_currency_pricing_page.batch.result_title') }}</span></template>
                                <el-alert
                                    :title="mcp_batchResult.message"
                                    :type="mcp_batchResult.success ? 'success' : 'warning'"
                                    show-icon
                                    :closable="false"
                                />
                                <pre class="mt-2 text-sm">{{ JSON.stringify(mcp_batchResult.data, null, 2) }}</pre>
                            </el-card>
                        </el-tab-pane>
                    </el-tabs>

                    <!-- ─── 价格编辑对话框 ─── -->
                    <el-dialog v-model="mcp_priceDialogVisible" :title="t('multi_currency_pricing_page.dialog.edit_title')" width="650px" :close-on-click-modal="false">
                        <div v-if="mcp_selectedSku" class="mb-4 text-sm text-gray-500">
                            {{ t('multi_currency_pricing_page.dialog.sku_label') }}: <strong>{{ mcp_selectedSku.sku_code }}</strong>
                            &nbsp;|&nbsp; {{ t('multi_currency_pricing_page.dialog.product_label') }}: <strong>{{ mcp_selectedSku.product_name }}</strong>
                        </div>
                        <el-form v-loading="mcp_priceFormLoading" label-width="140">
                            <div v-for="(cp, currency) in mcp_priceForm" :key="currency" class="mcp-price-row mb-3 p-3 bg-gray-50 rounded">
                                <div class="flex items-center justify-between mb-2">
                                    <strong class="text-sm">{{ currency }}</strong>
                                    <el-tag v-if="cp.is_converted" size="small" type="warning" effect="plain">
                                        {{ t('multi_currency_pricing_page.dialog.converted_from', { currency: cp.source_currency || '?' }) }}
                                    </el-tag>
                                </div>
                                <el-row :gutter="12">
                                    <el-col :span="12">
                                        <el-form-item :label="t('product_sku_page.field_price')" required>
                                            <el-input-number v-model="cp.price" :min="0" :precision="2" :step="0.01" style="width: 100%" />
                                        </el-form-item>
                                    </el-col>
                                    <el-col :span="12">
                                        <el-form-item :label="t('product_sku_page.field_compare_price')">
                                            <el-input-number v-model="cp.compare_at_price" :min="0" :precision="2" :step="0.01" :placeholder="t('multi_currency_pricing_page.dialog.none_ph')" style="width: 100%" />
                                        </el-form-item>
                                    </el-col>
                                </el-row>
                            </div>
                            <el-empty v-if="Object.keys(mcp_priceForm).length === 0" :description="t('multi_currency_pricing_page.dialog.no_price_data')" />
                        </el-form>
                        <template #footer>
                            <el-button @click="mcp_priceDialogVisible = false">{{ t('actions.cancel') }}</el-button>
                            <el-button type="primary" :loading="mcp_priceSaving" @click="mcp_saveSkuPrices">{{ t('actions.save') }}</el-button>
                        </template>
                    </el-dialog>
                </div>
                <div v-else class="mcp-loading-placeholder">
                    <el-skeleton :rows="5" animated />
                </div>
            </el-tab-pane>
        </el-tabs>

        <!-- ─── 汇率对话框 ─── -->
        <el-dialog v-model="rateDialogVisible" :title="t('currency_page.rate_dialog.title')" width="500px">
            <el-form :model="rateForm" label-width="120">
                <el-form-item :label="t('currency_page.cols.from_currency')" required>
                    <el-select v-model="rateForm.from_currency" filterable style="width: 200px">
                        <el-option v-for="c in currencies" :key="c.code" :label="`${c.code} - ${c.name}`" :value="c.code" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('currency_page.cols.to_currency')" required>
                    <el-select v-model="rateForm.to_currency" filterable style="width: 200px">
                        <el-option v-for="c in currencies" :key="c.code" :label="`${c.code} - ${c.name}`" :value="c.code" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('currency_page.cols.rate')" required>
                    <el-input-number v-model="rateForm.rate" :min="0.00000001" :precision="8" :step="0.01" style="width: 240px" />
                </el-form-item>
                <el-form-item :label="t('currency_page.cols.provider')">
                    <el-select v-model="rateForm.provider" style="width: 200px">
                        <el-option v-for="p in providerOptions" :key="p.value" :label="p.label" :value="p.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('currency_page.cols.effective_at')">
                    <el-date-picker v-model="rateForm.effective_at" type="datetime" :placeholder="t('currency_page.effective_immediately')" style="width: 100%" />
                </el-form-item>
                <el-form-item :label="t('currency_page.cols.expires_at')">
                    <el-date-picker v-model="rateForm.expires_at" type="datetime" :placeholder="t('currency_page.never_expires')" style="width: 100%" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="rateDialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="submitRate">{{ t('actions.confirm') }}</el-button>
            </template>
        </el-dialog>

        <!-- ─── 定价计划对话框 ─── -->
        <el-dialog v-model="planDialogVisible" :title="isEditingPlan ? t('currency_page.plan_dialog.edit_title') : t('currency_page.plan_dialog.create_title')" width="700px">
            <el-form :model="planForm" label-width="120">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t('currency_page.plan_dialog.name')" required>
                            <el-input v-model="planForm.name" :placeholder="t('currency_page.plan_dialog.name_ph')" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('currency_page.plan_dialog.slug')" required>
                            <el-input v-model="planForm.slug" :placeholder="t('currency_page.plan_dialog.slug_ph')" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item :label="t('currency_page.plan_dialog.description')">
                    <el-input v-model="planForm.description" type="textarea" :rows="2" />
                </el-form-item>
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item :label="t('currency_page.plan_dialog.billing_period')" required>
                            <el-select v-model="planForm.billing_period">
                                <el-option v-for="bp in billingPeriodOptions" :key="bp.value" :label="bp.label" :value="bp.value" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t('currency_page.plan_dialog.sort_order')">
                            <el-input-number v-model="planForm.sort_order" :min="0" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t('product_sku_page.col_status')">
                            <el-switch
                                v-model="planForm.is_active"
                                :active-text="t('currency_page.status.enabled')"
                                :inactive-text="t('currency_page.status.disabled')"
                            />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-divider>{{ t('currency_page.plan_dialog.multi_currency_prices') }}</el-divider>

                <div v-for="(priceRow, idx) in planForm.prices" :key="idx" class="price-row mb-3 p-3 bg-gray-50 rounded">
                    <el-row :gutter="12" align="middle">
                        <el-col :span="4">
                            <el-select v-model="priceRow.currency" filterable style="width: 100%">
                                <el-option v-for="c in currencies" :key="c.code" :label="c.code" :value="c.code" />
                            </el-select>
                        </el-col>
                        <el-col :span="6">
                            <el-input v-model="priceRow.price" type="number" step="0.01" min="0" :placeholder="t('currency_page.plan_dialog.price_ph')">
                                <template #prefix>{{ formatSymbol(priceRow.currency) }}</template>
                            </el-input>
                        </el-col>
                        <el-col :span="5">
                            <el-input v-model="priceRow.setup_fee" type="number" step="0.01" min="0" :placeholder="t('currency_page.plan_dialog.setup_fee_ph')" />
                        </el-col>
                        <el-col :span="5">
                            <el-input v-model="priceRow.trial_price" type="number" step="0.01" min="0" :placeholder="t('currency_page.plan_dialog.trial_price_ph')" />
                        </el-col>
                        <el-col :span="4" class="text-right">
                            <el-button size="small" type="danger" :disabled="planForm.prices.length <= 1" @click="removePriceRow(idx)">{{ t('actions.delete') }}</el-button>
                        </el-col>
                    </el-row>
                </div>

                <el-button size="small" @click="addPriceRow">{{ t('currency_page.buttons.add_currency_price') }}</el-button>
            </el-form>
            <template #footer>
                <el-button @click="planDialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="submitPlan">{{ isEditingPlan ? t('actions.update') : t('actions.create') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script>
import { defineComponent, ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { ArrowRight } from '@element-plus/icons-vue';
import currencyApi from '@/api/currency';
import {
    getMcpDashboard,
    getEnabledSkus,
    getSkuPrices,
    updateSkuPrices,
    batchUpdatePrices,
    disableMultiCurrency,
} from '@/api/multiCurrencyPricing';

export default defineComponent({
    name: 'CurrencyIndex',
    components: { ArrowRight },
    setup() {
        const router = useRouter();
        const { t } = useI18n();

        // ─── 外层 Tab 状态 ───
        const activeTab = ref('rates');
        const loading = ref(false);
        const currencies = ref([]);
        const rates = ref([]);
        const pricingPlans = ref([]);

        const activeTabText = computed(() => t(`currency_page.tab_suffix.${activeTab.value}`));

        const billingPeriodOptions = computed(() => [
            { value: 'monthly', label: t('currency_page.billing_period.monthly') },
            { value: 'yearly', label: t('currency_page.billing_period.yearly') },
            { value: 'one_time', label: t('currency_page.billing_period.one_time') },
        ]);

        const providerOptions = computed(() => [
            { value: 'manual', label: t('currency_page.providers.manual') },
            { value: 'ecb', label: t('currency_page.providers.ecb') },
            { value: 'stripe', label: t('currency_page.providers.stripe') },
            { value: 'alipay', label: t('currency_page.providers.alipay') },
        ]);

        function billingPeriodLabel(period) {
            return t(`currency_page.billing_period.${period}`, period);
        }

        const rateForm = ref({
            from_currency: 'USD',
            to_currency: 'CNY',
            rate: '',
            provider: 'manual',
            effective_at: new Date().toISOString().slice(0, 16),
            expires_at: '',
        });
        const rateDialogVisible = ref(false);
        const isEditingRate = ref(false);

        const planDialogVisible = ref(false);
        const isEditingPlan = ref(false);
        const editingPlanId = ref(null);
        const planForm = ref({
            product_id: '',
            slug: '',
            name: '',
            description: '',
            billing_period: 'monthly',
            is_active: true,
            sort_order: 0,
            prices: [{ currency: 'CNY', price: '', setup_fee: 0, trial_price: '' }],
        });

        const convertAmount = ref('');
        const convertFrom = ref('USD');
        const convertTo = ref('CNY');
        const convertResult = ref(null);

        async function fetchCurrencies() {
            try {
                const res = await currencyApi.getCurrencies();
                currencies.value = res.data.data || [];
            } catch (e) {
                console.error('Failed to fetch currencies', e);
            }
        }

        async function fetchRates() {
            loading.value = true;
            try {
                const res = await currencyApi.getRates();
                const grouped = res.data.data || {};
                rates.value = Object.values(grouped).flat();
            } catch (e) {
                console.error('Failed to fetch rates', e);
            } finally {
                loading.value = false;
            }
        }

        async function fetchPricingPlans() {
            loading.value = true;
            try {
                const res = await currencyApi.getPricingPlans();
                pricingPlans.value = res.data.data || [];
            } catch (e) {
                console.error('Failed to fetch pricing plans', e);
            } finally {
                loading.value = false;
            }
        }

        function openRateDialog() {
            isEditingRate.value = false;
            rateForm.value = {
                from_currency: 'USD',
                to_currency: 'CNY',
                rate: '',
                provider: 'manual',
                effective_at: new Date().toISOString().slice(0, 16),
                expires_at: '',
            };
            rateDialogVisible.value = true;
        }

        async function submitRate() {
            try {
                await currencyApi.setRate(rateForm.value);
                ElMessage.success(t('currency_page.messages.rate_set_ok'));
                rateDialogVisible.value = false;
                await fetchRates();
            } catch (e) {
                const msg = e.response?.data?.errors
                    ? Object.values(e.response.data.errors).flat().join('; ')
                    : t('currency_page.messages.rate_set_failed');
                ElMessage.error(msg);
            }
        }

        async function confirmDeleteRate(id) {
            try {
                await ElMessageBox.confirm(
                    t('currency_page.messages.rate_delete_confirm'),
                    t('actions.confirm'),
                    { type: 'warning' },
                );
                await currencyApi.deleteRate(id);
                ElMessage.success(t('currency_page.messages.rate_deleted'));
                await fetchRates();
            } catch (e) {
                if (e !== 'cancel') ElMessage.error(t('messages.failed'));
            }
        }

        async function syncRates() {
            try {
                const res = await currencyApi.syncRates('ecb');
                ElMessage.success(res.data.message || t('currency_page.messages.sync_done'));
                await fetchRates();
            } catch (e) {
                ElMessage.error(t('currency_page.messages.sync_failed'));
            }
        }

        async function doConvert() {
            if (!convertAmount.value || convertAmount.value <= 0) {
                ElMessage.warning(t('currency_page.messages.amount_required'));
                return;
            }
            try {
                const res = await currencyApi.convert(
                    convertAmount.value,
                    convertFrom.value,
                    convertTo.value
                );
                convertResult.value = res.data.data;
            } catch (e) {
                convertResult.value = null;
                ElMessage.error(t('currency_page.messages.convert_failed'));
            }
        }

        function openPlanDialog() {
            isEditingPlan.value = false;
            editingPlanId.value = null;
            planForm.value = {
                product_id: '',
                slug: '',
                name: '',
                description: '',
                billing_period: 'monthly',
                is_active: true,
                sort_order: 0,
                prices: [{ currency: 'CNY', price: '', setup_fee: 0, trial_price: '' }],
            };
            planDialogVisible.value = true;
        }

        function editPlan(plan) {
            isEditingPlan.value = true;
            editingPlanId.value = plan.id;
            planForm.value = {
                product_id: plan.product_id || '',
                slug: plan.slug,
                name: plan.name,
                description: plan.description || '',
                billing_period: plan.billing_period,
                is_active: plan.is_active,
                sort_order: plan.sort_order ?? 0,
                prices: plan.prices?.length
                    ? plan.prices.map(p => ({
                        currency: p.currency,
                        price: String(p.price),
                        setup_fee: p.setup_fee ?? 0,
                        trial_price: p.trial_price ? String(p.trial_price) : '',
                    }))
                    : [{ currency: 'CNY', price: '', setup_fee: 0, trial_price: '' }],
            };
            planDialogVisible.value = true;
        }

        function addPriceRow() {
            planForm.value.prices.push({
                currency: 'USD',
                price: '',
                setup_fee: 0,
                trial_price: '',
            });
        }

        function removePriceRow(index) {
            if (planForm.value.prices.length <= 1) {
                ElMessage.warning(t('currency_page.messages.min_one_price'));
                return;
            }
            planForm.value.prices.splice(index, 1);
        }

        async function submitPlan() {
            try {
                if (isEditingPlan.value) {
                    await currencyApi.updatePricingPlan(editingPlanId.value, planForm.value);
                    ElMessage.success(t('currency_page.messages.plan_updated'));
                } else {
                    await currencyApi.createPricingPlan(planForm.value);
                    ElMessage.success(t('currency_page.messages.plan_created'));
                }
                planDialogVisible.value = false;
                await fetchPricingPlans();
            } catch (e) {
                const msg = e.response?.data?.errors
                    ? Object.values(e.response.data.errors).flat().join('; ')
                    : t('messages.failed');
                ElMessage.error(msg);
            }
        }

        async function confirmDeletePlan(id) {
            try {
                await ElMessageBox.confirm(
                    t('currency_page.messages.plan_delete_confirm'),
                    t('actions.confirm'),
                    { type: 'warning' },
                );
                await currencyApi.deletePricingPlan(id);
                ElMessage.success(t('currency_page.messages.plan_deleted'));
                await fetchPricingPlans();
            } catch (e) {
                if (e !== 'cancel') ElMessage.error(t('messages.failed'));
            }
        }

        function formatSymbol(currency) {
            const symbols = { CNY: '¥', USD: '$', EUR: '€', JPY: '¥', GBP: '£', HKD: 'HK$', SGD: 'S$', KRW: '₩' };
            return symbols[currency] || currency;
        }

        function formatCurrency(amount, currency) {
            const dec = ['JPY', 'KRW'].includes(currency) ? 0 : 2;
            return formatSymbol(currency) + Number(amount).toFixed(dec);
        }

        // ════════════════════════════════════════
        // ─── 商品定价 (multi-currency-pricing) 合并 ───
        // ════════════════════════════════════════

        const mcp_tabVisited = ref(false);

        // ─── 内层 Tab 状态 ───
        const mcp_activeTab = ref('overview');
        const mcp_activeTabText = computed(() => t(`multi_currency_pricing_page.tab_suffix.${mcp_activeTab.value}`));

        // ─── 概览 ───
        const mcp_dashboard = ref({});
        const mcp_dashboardLoading = ref(false);

        const mcp_coverageRate = computed(() => {
            const total = mcp_dashboard.value.total_skus || 0;
            const mc = mcp_dashboard.value.multi_currency_skus || 0;
            return total ? Number(((mc / total) * 100).toFixed(1)) : 0;
        });

        const mcp_currencyCoverage = computed(() => {
            return (mcp_dashboard.value.currency_coverage || []).map((item) => ({
                ...item,
                percentage: item.percentage ? Math.round(item.percentage) : 0,
            }));
        });

        async function mcp_loadDashboard() {
            mcp_dashboardLoading.value = true;
            try {
                const res = await getMcpDashboard();
                mcp_dashboard.value = res.data.data || {};
            } catch (e) {
                console.error('Failed to fetch dashboard', e);
            } finally {
                mcp_dashboardLoading.value = false;
            }
        }

        // ─── SKU 定价管理 ───
        const mcp_skus = ref([]);
        const mcp_skusLoading = ref(false);
        const mcp_skusPagination = ref({ current_page: 1, per_page: 15, total: 0 });
        const mcp_skuFilter = ref({ product_id: '' });

        async function mcp_loadSkus() {
            mcp_skusLoading.value = true;
            try {
                const params = {
                    page: mcp_skusPagination.value.current_page,
                    per_page: mcp_skusPagination.value.per_page,
                    ...(mcp_skuFilter.value.product_id ? { product_id: mcp_skuFilter.value.product_id } : {}),
                };
                const res = await getEnabledSkus(params);
                const body = res.data.data || {};
                mcp_skus.value = body.data || [];
                mcp_skusPagination.value = {
                    current_page: body.current_page || 1,
                    per_page: body.per_page || 15,
                    total: body.total || 0,
                };
            } catch (e) {
                console.error('Failed to fetch SKUs', e);
            } finally {
                mcp_skusLoading.value = false;
            }
        }

        function mcp_resetSkuFilter() {
            mcp_skuFilter.value = { product_id: '' };
            mcp_skusPagination.value.current_page = 1;
            mcp_loadSkus();
        }

        // ─── 价格编辑对话框 ───
        const mcp_priceDialogVisible = ref(false);
        const mcp_selectedSku = ref(null);
        const mcp_priceForm = ref({});
        const mcp_priceFormLoading = ref(false);
        const mcp_priceSaving = ref(false);

        async function mcp_openPriceDialog(row) {
            mcp_selectedSku.value = row;
            mcp_priceDialogVisible.value = true;
            mcp_priceFormLoading.value = true;
            try {
                const res = await getSkuPrices(row.id);
                const prices = res.data.data || {};
                mcp_priceForm.value = {};
                for (const [currency, data] of Object.entries(prices)) {
                    mcp_priceForm.value[currency] = {
                        price: data.price ?? 0,
                        compare_at_price: data.compare_at_price ?? null,
                        is_converted: !!data.is_converted,
                        source_currency: data.source_currency || null,
                    };
                }
            } catch (e) {
                console.error('Failed to fetch prices', e);
                ElMessage.error(t('multi_currency_pricing_page.messages.fetch_prices_failed'));
            } finally {
                mcp_priceFormLoading.value = false;
            }
        }

        async function mcp_saveSkuPrices() {
            mcp_priceSaving.value = true;
            try {
                const prices = {};
                for (const [currency, data] of Object.entries(mcp_priceForm.value)) {
                    prices[currency] = {
                        price: data.price,
                        ...(data.compare_at_price != null ? { compare_at_price: data.compare_at_price } : {}),
                    };
                }
                await updateSkuPrices(mcp_selectedSku.value.id, prices);
                ElMessage.success(t('multi_currency_pricing_page.messages.prices_saved'));
                mcp_priceDialogVisible.value = false;
                mcp_loadSkus();
            } catch (e) {
                console.error('Failed to save prices', e);
                ElMessage.error(t('product_sku_page.save_fail'));
            } finally {
                mcp_priceSaving.value = false;
            }
        }

        async function mcp_toggleEnabled(row) {
            try {
                if (row.is_enabled) {
                    await ElMessageBox.confirm(
                        t('multi_currency_pricing_page.messages.disable_confirm', { code: row.sku_code }),
                        t('multi_currency_pricing_page.messages.confirm_title'),
                    );
                    await disableMultiCurrency(row.id);
                    ElMessage.success(t('multi_currency_pricing_page.messages.disabled_ok'));
                } else {
                    ElMessage.info(t('multi_currency_pricing_page.messages.enable_hint'));
                }
                mcp_loadSkus();
            } catch (e) {
                if (e !== 'cancel') console.error(e);
            }
        }

        // ─── 批量更新 ───
        const mcp_batchInput = ref('');
        const mcp_batchLoading = ref(false);
        const mcp_batchResult = ref(null);

        function mcp_handleFileUpload(file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                mcp_batchInput.value = e.target.result;
            };
            reader.readAsText(file);
            return false;
        }

        async function mcp_handleBulkEdit() {
            if (!mcp_batchInput.value.trim()) {
                ElMessage.warning(t('multi_currency_pricing_page.messages.batch_input_required'));
                return;
            }
            let parsed;
            try {
                parsed = JSON.parse(mcp_batchInput.value);
                if (!Array.isArray(parsed)) throw new Error(t('multi_currency_pricing_page.messages.json_array_required'));
            } catch (e) {
                ElMessage.error(t('multi_currency_pricing_page.messages.json_format_error', { error: e.message }));
                return;
            }
            mcp_batchLoading.value = true;
            mcp_batchResult.value = null;
            try {
                const res = await batchUpdatePrices(parsed);
                const body = res.data;
                mcp_batchResult.value = {
                    success: true,
                    message: body.message || t('multi_currency_pricing_page.messages.batch_done'),
                    data: body.data || {},
                };
                ElMessage.success(t('multi_currency_pricing_page.messages.batch_submit_ok'));
                mcp_batchInput.value = '';
            } catch (e) {
                const msg = e.response?.data?.message || e.message;
                mcp_batchResult.value = {
                    success: false,
                    message: t('multi_currency_pricing_page.messages.batch_failed', { error: msg }),
                    data: e.response?.data?.data || {},
                };
                ElMessage.error(t('multi_currency_pricing_page.messages.batch_submit_failed'));
            } finally {
                mcp_batchLoading.value = false;
            }
        }

        // ─── 懒加载: 首次切换到商品定价时加载数据 ───
        watch(activeTab, (newTab) => {
            if (newTab === 'productPricing' && !mcp_tabVisited.value) {
                mcp_tabVisited.value = true;
                mcp_loadDashboard();
            }
        });

        // ─── 生命周期 ───
        onMounted(() => {
            fetchCurrencies();
            fetchRates();
            fetchPricingPlans();
        });

        return {
            t,
            router, activeTab, activeTabText, loading, currencies, rates, pricingPlans,
            billingPeriodOptions, providerOptions, billingPeriodLabel,
            rateForm, rateDialogVisible, isEditingRate,
            planForm, planDialogVisible, isEditingPlan,
            convertAmount, convertFrom, convertTo, convertResult,
            openRateDialog, submitRate, confirmDeleteRate, syncRates,
            doConvert, formatSymbol, formatCurrency,
            openPlanDialog, editPlan, addPriceRow, removePriceRow,
            submitPlan, confirmDeletePlan,
            // ─── 商品定价 (mcp_) ───
            mcp_tabVisited,
            mcp_activeTab,
            mcp_activeTabText,
            mcp_dashboard,
            mcp_dashboardLoading,
            mcp_coverageRate,
            mcp_currencyCoverage,
            mcp_loadDashboard,
            mcp_skus,
            mcp_skusLoading,
            mcp_skusPagination,
            mcp_skuFilter,
            mcp_loadSkus,
            mcp_resetSkuFilter,
            mcp_priceDialogVisible,
            mcp_selectedSku,
            mcp_priceForm,
            mcp_priceFormLoading,
            mcp_priceSaving,
            mcp_openPriceDialog,
            mcp_saveSkuPrices,
            mcp_toggleEnabled,
            mcp_batchInput,
            mcp_batchLoading,
            mcp_batchResult,
            mcp_handleFileUpload,
            mcp_handleBulkEdit,
        };
    },
});
</script>

<style scoped>
.currency-manager {
    padding: 20px;
}
.currency-price-tag {
    margin: 2px;
}
.convert-card {
    max-width: 600px;
}
.price-row {
    border: 1px solid #e5e7eb;
}

/* ─── 商品定价 (mcp_) 样式 —— 用父级类名包裹避免冲突 ─── */
.mcp-content .mcp-loading-placeholder {
    padding: 40px;
}
.mcp-content .mcp-price-row + .mcp-price-row {
    margin-top: 8px;
}
.mcp-content .font-mono {
    font-family: 'Courier New', Courier, monospace;
}
</style>
