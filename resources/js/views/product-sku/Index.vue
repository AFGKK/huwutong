<template>
    <div class="sku-management">
        <div class="page-header">
            <h2>{{ t('product_sku_page.title') }}</h2>
            <div>
                <el-button @click="handleExport"><el-icon><Download /></el-icon> {{ t('actions.export') }}</el-button>
                <el-button @click="handleImport"><el-icon><Upload /></el-icon> {{ t('actions.import') }}</el-button>
                <el-button type="primary" @click="openCreateDialog">
                    <el-icon><Plus /></el-icon> {{ t('product_sku_page.create_sku') }}
                </el-button>
            </div>
        </div>

        <!-- 批量操作栏 -->
        <div v-if="selectedIds.length > 0" class="batch-bar">
            <span class="batch-info">{{ t('product_sku_page.selected_count', { n: selectedIds.length }) }}</span>
            <el-button size="small" type="success" @click="doBatchAction('activate')">{{ t('product_sku_page.batch_activate') }}</el-button>
            <el-button size="small" type="warning" @click="doBatchAction('deactivate')">{{ t('product_sku_page.batch_deactivate') }}</el-button>
            <el-button size="small" type="danger" @click="doBatchAction('delete')">{{ t('product_sku_page.batch_delete') }}</el-button>
            <el-button size="small" @click="showBatchPrice = true">{{ t('product_sku_page.batch_set_price') }}</el-button>
            <el-button size="small" text @click="selectedIds = []">{{ t('actions.cancel') }}</el-button>
        </div>

        <!-- 批量改价对话框 -->
        <el-dialog v-model="showBatchPrice" :title="t('product_sku_page.batch_price_title')" width="400px">
            <el-form label-width="100px">
                <el-form-item :label="t('product_sku_page.new_price')">
                    <el-input-number v-model="batchPriceValue" :min="0" :precision="2" style="width:200px" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showBatchPrice = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="doBatchAction('set_price')">{{ t('product_sku_page.confirm_price_change') }}</el-button>
            </template>
        </el-dialog>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value">{{ stats.total_skus || 0 }}</div>
                        <div class="stat-label">{{ t('product_sku_page.stat_total') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #67c23a">{{ stats.active_skus || 0 }}</div>
                        <div class="stat-label">{{ t('product_sku_page.stat_active') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #e6a23c">{{ stats.low_stock_count || 0 }}</div>
                        <div class="stat-label">{{ t('product_sku_page.stat_low_stock') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #f56c6c">{{ stats.out_of_stock || 0 }}</div>
                        <div class="stat-label">{{ t('product_sku_page.stat_out_of_stock') }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 筛选栏 -->
        <el-card shadow="never" class="mb-4">
            <el-form :inline="true" :model="filters" size="small">
                <el-form-item :label="t('product_sku_page.filter_product')">
                    <el-select v-model="filters.product_id" clearable :placeholder="t('product_sku_page.all_products')" style="width:180px">
                        <el-option v-for="p in products" :key="p.id" :label="p.name" :value="p.id" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('product_sku_page.filter_status')">
                    <el-select v-model="filters.is_active" clearable :placeholder="t('product_sku_page.all')" style="width:120px">
                        <el-option v-for="opt in statusFilterOptions" :key="String(opt.value)" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('product_sku_page.filter_billing_cycle')">
                    <el-select v-model="filters.billing_cycle" clearable :placeholder="t('product_sku_page.all')" style="width:140px">
                        <el-option v-for="opt in billingCycleOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('product_sku_page.filter_stock')">
                    <el-select v-model="filters.stock_status" clearable :placeholder="t('product_sku_page.all')" style="width:140px">
                        <el-option v-for="opt in stockStatusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('product_sku_page.filter_search')">
                    <el-input v-model="filters.search" :placeholder="t('product_sku_page.search_ph')" clearable style="width:200px" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadSkus(1)">{{ t('product_sku_page.query') }}</el-button>
                    <el-button @click="resetFilters">{{ t('actions.reset') }}</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- SKU 列表 -->
        <el-card shadow="never">
            <el-table :data="skus" stripe v-loading="loading" @selection-change="onSelectionChange">
                <el-table-column type="selection" width="40" />
                <el-table-column type="index" label="#" width="50" />
                <el-table-column prop="sku_code" :label="t('product_sku_page.col_sku_code')" width="150" />
                <el-table-column prop="name" :label="t('product_sku_page.col_name')" min-width="180" show-overflow-tooltip />
                <el-table-column prop="product.name" :label="t('product_sku_page.col_product')" width="150" show-overflow-tooltip />
                <el-table-column :label="t('product_sku_page.col_price')" width="100">
                    <template #default="{ row }">¥{{ row.price }}</template>
                </el-table-column>
                <el-table-column :label="t('product_sku_page.col_compare_price')" width="100">
                    <template #default="{ row }">
                        <span v-if="row.compare_at_price" class="text-muted text-line-through">¥{{ row.compare_at_price }}</span>
                        <span v-else>-</span>
                    </template>
                </el-table-column>
                <el-table-column prop="billing_cycle" :label="t('product_sku_page.col_cycle')" width="100">
                    <template #default="{ row }">
                        <el-tag v-if="billingCycleLabels[row.billing_cycle]" size="small">{{ billingCycleLabels[row.billing_cycle] }}</el-tag>
                        <span v-else>-</span>
                    </template>
                </el-table-column>
                <el-table-column :label="t('product_sku_page.col_stock')" width="80">
                    <template #default="{ row }">
                        <span v-if="row.stock === -1" style="color:#909399">{{ t('product_sku_page.stock_unlimited') }}</span>
                        <span v-else :style="{ color: row.stock <= 0 ? '#f56c6c' : (row.stock <= (row.low_stock_threshold ?? 10)) ? '#e6a23c' : '#67c23a' }">{{ row.stock }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="sold_count" :label="t('product_sku_page.col_sold')" width="70" />
                <el-table-column :label="t('product_sku_page.col_commission')" width="80">
                    <template #default="{ row }">
                        <span v-if="row.commission_rate !== null && row.commission_rate !== undefined" style="color:#e6a23c;font-weight:600">{{ row.commission_rate }}%</span>
                        <span v-else class="text-muted">{{ t('product_sku_page.commission_default') }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="currency" :label="t('product_sku_page.col_currency')" width="70" />
                <el-table-column :label="t('product_sku_page.col_status')" width="80">
                    <template #default="{ row }">
                        <el-tag v-if="row.is_active" type="success" size="small">{{ t('product_sku_page.status_listed') }}</el-tag>
                        <el-tag v-else type="info" size="small">{{ t('product_sku_page.status_delisted') }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" :label="t('product_sku_page.col_created_at')" width="170" />
                <el-table-column :label="t('product_sku_page.col_actions')" width="280" fixed="right">
                    <template #default="{ row }">
                        <el-button text type="primary" size="small" @click="openEditDialog(row)">{{ t('actions.edit') }}</el-button>
                        <el-button text type="primary" size="small" @click="handleClone(row)">{{ t('product_sku_page.clone') }}</el-button>
                        <el-button text type="primary" size="small" @click="openStockLogDialog(row)">{{ t('product_sku_page.stock') }}</el-button>
                        <el-button text type="primary" size="small" @click="openCurrencyDialog(row)">{{ t('product_sku_page.pricing') }}</el-button>
                        <el-button text :type="row.is_active ? 'warning' : 'success'" size="small" @click="handleToggle(row)">
                            {{ row.is_active ? t('product_sku_page.status_delisted') : t('product_sku_page.status_listed') }}
                        </el-button>
                        <el-button text type="danger" size="small" @click="handleDelete(row)">{{ t('actions.delete') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="pagination-wrap">
                <el-pagination
                    v-model:current-page="page"
                    :page-size="perPage"
                    :total="total"
                    layout="total, prev, pager, next"
                    @current-change="loadSkus" />
            </div>
        </el-card>

        <!-- 创建/编辑弹窗 -->
        <el-dialog v-model="dialogVisible" :title="isEditing ? t('product_sku_page.edit_title') : t('product_sku_page.create_title')" width="750px">
            <el-form :model="form" label-width="100px" :rules="formRules" ref="formRef">
                <el-form-item :label="t('product_sku_page.field_product')" prop="product_id">
                    <el-select v-model="form.product_id" style="width:100%" :disabled="isEditing">
                        <el-option v-for="p in products" :key="p.id" :label="p.name" :value="p.id" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('product_sku_page.field_sku_code')" prop="sku_code">
                    <el-input v-model="form.sku_code" :placeholder="t('product_sku_page.sku_code_ph')" :disabled="isEditing" />
                </el-form-item>
                <el-form-item :label="t('product_sku_page.field_name')" prop="name">
                    <el-input v-model="form.name" :placeholder="t('product_sku_page.name_ph')" />
                </el-form-item>
                <el-form-item :label="t('product_sku_page.field_price')" prop="price">
                    <el-input-number v-model="form.price" :min="0" :precision="2" style="width:200px" />
                </el-form-item>
                <el-form-item :label="t('product_sku_page.field_compare_price')">
                    <el-input-number v-model="form.compare_at_price" :min="0" :precision="2" style="width:200px" />
                </el-form-item>
                <el-form-item :label="t('product_sku_page.field_currency')">
                    <el-select v-model="form.currency" style="width:120px">
                        <el-option label="CNY" value="CNY" />
                        <el-option label="USD" value="USD" />
                        <el-option label="EUR" value="EUR" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('product_sku_page.field_billing_cycle')">
                    <el-select v-model="form.billing_cycle" clearable :placeholder="t('product_sku_page.billing_no_limit')" style="width:100%">
                        <el-option v-for="opt in billingCycleOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('product_sku_page.field_stock')">
                    <el-input-number v-model="form.stock" :min="-1" style="width:200px" />
                    <span class="text-muted ml-2">{{ t('product_sku_page.stock_unlimited_hint') }}</span>
                </el-form-item>
                <el-form-item :label="t('product_sku_page.field_low_stock_threshold')">
                    <el-input-number v-model="form.low_stock_threshold" :min="0" :max="9999" style="width:200px" />
                    <span class="text-muted ml-2">{{ t('product_sku_page.low_stock_hint') }}</span>
                </el-form-item>
                <el-form-item :label="t('product_sku_page.field_allow_backorder')">
                    <el-switch v-model="form.allow_backorder" />
                    <span class="text-muted ml-2">{{ t('product_sku_page.backorder_hint') }}</span>
                </el-form-item>
                <el-form-item :label="t('product_sku_page.field_commission_rate')">
                    <el-input-number v-model="form.commission_rate" :min="0" :max="100" :precision="1" style="width:200px" :placeholder="t('product_sku_page.commission_default_ph', { rate: defaultCommissionRate })" />
                    <span class="text-muted ml-2">{{ t('product_sku_page.commission_default_hint', { rate: defaultCommissionRate }) }}</span>
                </el-form-item>
                <el-divider content-position="left">{{ t('product_sku_page.section_sku_image') }}</el-divider>
                <el-form-item :label="t('product_sku_page.field_image')">
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <template v-if="form.image_url">
                            <el-image :src="form.image_url" fit="cover" style="width:60px;height:60px;border-radius:4px" />
                            <el-button size="small" type="danger" circle @click="form.image_url = ''">
                                <el-icon><Close /></el-icon>
                            </el-button>
                        </template>
                        <el-upload :show-file-list="false" :before-upload="handleSkuImageUpload" accept="image/*">
                            <el-button size="small" type="primary" plain><el-icon><Upload /></el-icon> {{ t('product_sku_page.upload_image') }}</el-button>
                        </el-upload>
                    </div>
                </el-form-item>
                <el-form-item :label="t('product_sku_page.field_specs')">
                    <el-input v-model="specsText" type="textarea" :rows="3" :placeholder="t('product_sku_page.specs_ph')" />
                </el-form-item>

                <!-- 交付物管理 -->
                <el-divider content-position="left">{{ t('product_sku_page.section_deliverables') }}</el-divider>
                <el-form-item :label="t('product_sku_page.field_deliverables')">
                    <div style="width:100%">
                        <div v-for="(item, idx) in form.deliverables" :key="idx" class="deliverable-item">
                            <el-row :gutter="8" align="middle">
                                <el-col :span="4">
                                    <el-select v-model="item.type" size="small" :placeholder="t('product_sku_page.deliverable_type')" @change="onDeliverableTypeChange(item)">
                                        <el-option v-for="opt in deliverableTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                    </el-select>
                                </el-col>
                                <el-col :span="4">
                                    <el-select v-model="item.category" size="small" :placeholder="t('product_sku_page.deliverable_category')">
                                        <el-option v-for="opt in deliverableCategoryOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                    </el-select>
                                </el-col>
                                <el-col :span="6">
                                    <el-input v-model="item.name" size="small" :placeholder="t('product_sku_page.deliverable_name_ph')" />
                                </el-col>
                                <el-col :span="8">
                                    <el-input v-model="item.description" size="small" :placeholder="t('product_sku_page.deliverable_desc_ph')" />
                                </el-col>
                                <el-col :span="2" style="text-align:right">
                                    <el-button text type="danger" size="small" @click="removeDeliverable(idx)">
                                        <el-icon><Delete /></el-icon>
                                    </el-button>
                                </el-col>
                            </el-row>

                            <!-- 不同类型的内容输入 -->
                            <div v-if="item.type === 'file'" class="deliverable-content">
                                <el-row :gutter="8" class="mt-1">
                                    <el-col :span="12">
                                        <el-upload
                                            :ref="el => setUploadRef(idx, el)"
                                            :auto-upload="false"
                                            :show-file-list="false"
                                            :on-change="(f) => onFileSelect(idx, f)"
                                            accept="*">
                                            <template #trigger>
                                                <el-button size="small" type="primary" plain>
                                                    <el-icon><Upload /></el-icon> {{ t('product_sku_page.select_file') }}
                                                </el-button>
                                            </template>
                                        </el-upload>
                                    </el-col>
                                    <el-col :span="12">
                                        <el-input v-model="item.file_url" size="small" :placeholder="t('product_sku_page.file_url_ph')" />
                                    </el-col>
                                </el-row>
                                <div v-if="item.original_name" class="file-info mt-1">
                                    <el-tag size="small" closable @close="clearFile(idx)">
                                        {{ item.original_name }}
                                        <span v-if="item.file_size" class="file-size">({{ formatFileSize(item.file_size) }})</span>
                                    </el-tag>
                                </div>
                            </div>

                            <div v-if="item.type === 'link'" class="deliverable-content mt-1">
                                <el-input v-model="item.file_url" :placeholder="t('product_sku_page.link_ph')" size="small" />
                            </div>

                            <div v-if="item.type === 'text'" class="deliverable-content mt-1">
                                <el-input v-model="item.content" type="textarea" :rows="2" :placeholder="t('product_sku_page.text_content_ph')" size="small" />
                            </div>
                        </div>

                        <el-button type="primary" plain size="small" @click="addDeliverable" class="mt-2">
                            <el-icon><Plus /></el-icon> {{ t('product_sku_page.add_deliverable') }}
                        </el-button>
                        <span class="text-muted ml-2" style="font-size:12px">{{ t('product_sku_page.deliverables_hint') }}</span>
                    </div>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="saving" @click="saveSku">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>

        <!-- 库存日志对话框 -->
        <el-dialog v-model="stockLogVisible" :title="t('product_sku_page.stock_log_title')" width="700px">
            <div v-if="stockLogSku" style="margin-bottom:12px">
                <strong>{{ stockLogSku.name }}</strong>
                <el-tag size="small" style="margin-left:8px">{{ t('product_sku_page.current_stock', { n: stockLogSku.stock }) }}</el-tag>
            </div>
            <div style="display:flex;gap:8px;margin-bottom:12px">
                <el-input-number v-model="stockAdjustValue" :min="-99999" :placeholder="t('product_sku_page.adjust_qty')" style="width:180px" />
                <el-input v-model="stockAdjustReason" :placeholder="t('product_sku_page.adjust_reason')" style="width:250px" />
                <el-button type="primary" :loading="stockAdjusting" @click="handleStockAdjust">{{ t('product_sku_page.confirm_adjust') }}</el-button>
            </div>
            <el-table :data="stockLogs" stripe v-loading="stockLogLoading" max-height="400">
                <el-table-column prop="created_at" :label="t('product_sku_page.col_time')" width="170" />
                <el-table-column :label="t('product_sku_page.col_change')" width="100">
                    <template #default="{ row }">
                        <span :style="{ color: row.change > 0 ? '#67c23a' : '#f56c6c', fontWeight:600 }">
                            {{ row.change > 0 ? '+' : '' }}{{ row.change }}
                        </span>
                    </template>
                </el-table-column>
                <el-table-column :label="t('product_sku_page.col_before')" width="80" prop="old_stock" />
                <el-table-column :label="t('product_sku_page.col_after')" width="80" prop="new_stock" />
                <el-table-column prop="reason" :label="t('product_sku_page.col_reason')" min-width="150" />
                <el-table-column prop="user.name" :label="t('product_sku_page.col_operator')" width="120" />
            </el-table>
        </el-dialog>

        <!-- 多币种定价对话框 -->
        <el-dialog v-model="currencyVisible" :title="t('product_sku_page.currency_title')" width="600px">
            <div v-if="currencySku" style="margin-bottom:12px">
                <strong>{{ currencySku.name }}</strong>
                <el-tag size="small" style="margin-left:8px">{{ t('product_sku_page.base_price', { price: currencySku.price }) }}</el-tag>
            </div>
            <el-table :data="currencyPrices" stripe>
                <el-table-column :label="t('product_sku_page.col_currency')" width="100">
                    <template #default="{ row }">
                        <el-select v-model="row.currency" style="width:100px">
                            <el-option label="CNY" value="CNY" />
                            <el-option label="USD" value="USD" />
                            <el-option label="EUR" value="EUR" />
                            <el-option label="GBP" value="GBP" />
                            <el-option label="JPY" value="JPY" />
                            <el-option label="KRW" value="KRW" />
                        </el-select>
                    </template>
                </el-table-column>
                <el-table-column :label="t('product_sku_page.col_price')">
                    <template #default="{ row }">
                        <el-input-number v-model="row.price" :min="0" :precision="2" style="width:140px" />
                    </template>
                </el-table-column>
                <el-table-column :label="t('product_sku_page.col_compare_price')">
                    <template #default="{ row }">
                        <el-input-number v-model="row.compare_at_price" :min="0" :precision="2" style="width:140px" />
                    </template>
                </el-table-column>
                <el-table-column :label="t('product_sku_page.col_cost_price')">
                    <template #default="{ row }">
                        <el-input-number v-model="row.cost_price" :min="0" :precision="2" style="width:140px" />
                    </template>
                </el-table-column>
                <el-table-column width="60">
                    <template #default="{ $index }">
                        <el-button text type="danger" size="small" @click="currencyPrices.splice($index,1)">{{ t('actions.delete') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <el-button size="small" @click="currencyPrices.push({ currency: 'USD', price: 0, compare_at_price: null, cost_price: null })" class="mt-2">
                <el-icon><Plus /></el-icon> {{ t('product_sku_page.add_currency') }}
            </el-button>
            <template #footer>
                <el-button @click="currencyVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="currencySaving" @click="handleSaveCurrency">{{ t('product_sku_page.save_pricing') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Delete, Upload, Download, Close } from '@element-plus/icons-vue';
import { getSkuDashboard, getSkus, createSku, updateSku, deleteSku, toggleSku, uploadDeliverable, cloneSku, adjustStock, getStockLogs, getCurrencyPrices, saveCurrencyPrices, batchActionSku, uploadSkuImage, exportSkuCsv, importSkuCsv } from '@/api/productSku';
import { getBillingCycleOptions } from '@/api/billingCycle';
import productApi from '@/api/product';

const { t } = useI18n();

const loading = ref(false);
const skus = ref([]);
const page = ref(1);
const perPage = ref(15);
const total = ref(0);
const stats = ref({});
const products = ref([]);
const dialogVisible = ref(false);
const isEditing = ref(false);
const saving = ref(false);
const formRef = ref(null);
const editingId = ref(null);
const selectedIds = ref([]);
const showBatchPrice = ref(false);
const batchPriceValue = ref(0);
const stockLogVisible = ref(false);
const stockLogSku = ref(null);
const stockLogs = ref([]);
const stockLogLoading = ref(false);
const stockAdjustValue = ref(0);
const stockAdjustReason = ref('');
const stockAdjusting = ref(false);
const currencyVisible = ref(false);
const currencySku = ref(null);
const currencyPrices = ref([]);
const currencySaving = ref(false);

const filters = reactive({
    product_id: '',
    is_active: '',
    billing_cycle: '',
    stock_status: '',
    search: '',
});

const form = reactive({
    product_id: '',
    sku_code: '',
    name: '',
    price: 0,
    compare_at_price: null,
    currency: 'CNY',
    billing_cycle: '',
    stock: -1,
    low_stock_threshold: 10,
    allow_backorder: false,
    commission_rate: null,
    image_url: '',
    specs: null,
    deliverables: [],
});

const statusFilterOptions = computed(() => [
    { label: t('product_sku_page.status_active'), value: true },
    { label: t('product_sku_page.status_inactive'), value: false },
]);

const billingCycles = ref([]);

async function loadBillingCycleOptions() {
    try {
        const res = await getBillingCycleOptions();
        billingCycles.value = res.data?.data || [];
    } catch {
        // fallback to empty
    }
}

const billingCycleOptions = computed(() =>
    billingCycles.value.map((c) => ({ label: c.name, value: c.code }))
);

const billingCycleLabels = computed(() => {
    const map = {};
    billingCycles.value.forEach((c) => { map[c.code] = c.name; });
    return map;
});

const stockStatusOptions = computed(() => [
    { label: t('product_sku_page.stock_low'), value: 'low' },
    { label: t('product_sku_page.stock_out'), value: 'out' },
    { label: t('product_sku_page.stock_unlimited'), value: 'unlimited' },
]);

const deliverableTypeOptions = computed(() => [
    { label: t('product_sku_page.type_file'), value: 'file' },
    { label: t('product_sku_page.type_link'), value: 'link' },
    { label: t('product_sku_page.type_text'), value: 'text' },
]);

const deliverableCategoryOptions = computed(() => [
    { label: t('product_sku_page.cat_software'), value: 'software' },
    { label: t('product_sku_page.cat_document'), value: 'document' },
    { label: t('product_sku_page.cat_template'), value: 'template' },
    { label: t('product_sku_page.cat_api'), value: 'api' },
    { label: t('product_sku_page.cat_tutorial'), value: 'tutorial' },
    { label: t('product_sku_page.cat_other'), value: 'other' },
]);

// 交付物上传相关
const uploadRefs = ref({});
const uploadingIdx = ref(-1);
const uploading = ref(false);

const setUploadRef = (idx, el) => {
    uploadRefs.value[idx] = el;
};

const addDeliverable = () => {
    form.deliverables.push({
        type: 'file',
        category: 'software',
        name: '',
        description: '',
        file_url: '',
        file_size: 0,
        mime_type: '',
        original_name: '',
        content: '',
    });
};

const removeDeliverable = (idx) => {
    form.deliverables.splice(idx, 1);
};

const onDeliverableTypeChange = (item) => {
    // 切换类型时清空不相关字段
    if (item.type === 'file') {
        item.content = '';
    } else if (item.type === 'link') {
        item.content = '';
        item.original_name = '';
        item.file_size = 0;
        item.mime_type = '';
    } else if (item.type === 'text') {
        item.file_url = '';
        item.original_name = '';
        item.file_size = 0;
        item.mime_type = '';
    }
};

const onFileSelect = async (idx, uploadFile) => {
    const rawFile = uploadFile.raw;
    if (!rawFile) return;

    uploadingIdx.value = idx;
    uploading.value = true;

    try {
        const res = await uploadDeliverable(rawFile);
        const data = res.data?.data || res.data;
        if (data.url) {
            form.deliverables[idx].file_url = data.url;
            form.deliverables[idx].original_name = data.original_name || rawFile.name;
            form.deliverables[idx].file_size = data.file_size || rawFile.size;
            form.deliverables[idx].mime_type = data.mime_type || rawFile.type;
        }
    } catch (e) {
        ElMessage.error(t('product_sku_page.file_upload_fail'));
    } finally {
        uploading.value = false;
        uploadingIdx.value = -1;
    }
};

const clearFile = (idx) => {
    form.deliverables[idx].file_url = '';
    form.deliverables[idx].original_name = '';
    form.deliverables[idx].file_size = 0;
    form.deliverables[idx].mime_type = '';
};

const formatFileSize = (bytes) => {
    if (!bytes) return '';
    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unitIdx = 0;
    while (size >= 1024 && unitIdx < units.length - 1) {
        size /= 1024;
        unitIdx++;
    }
    return size.toFixed(1) + ' ' + units[unitIdx];
};
const defaultCommissionRate = 10;

const specsText = computed({
    get: () => form.specs ? JSON.stringify(form.specs, null, 2) : '',
    set: (val) => {
        try {
            form.specs = val ? JSON.parse(val) : null;
        } catch {
            // ignore parse error
        }
    }
});

const formRules = computed(() => ({
    product_id: [{ required: true, message: t('product_sku_page.product_required'), trigger: 'change' }],
    name: [{ required: true, message: t('product_sku_page.name_required'), trigger: 'blur' }],
    price: [{ required: true, message: t('product_sku_page.price_required'), trigger: 'blur' }],
}));

const loadDashboard = async () => {
    try {
        const res = await getSkuDashboard();
        if (res.data.success) stats.value = res.data.data;
    } catch (e) { /* ignore */ }
};

const loadProducts = async () => {
    try {
        const res = await productApi.list({ per_page: 200 });
        const raw = res.data?.data || res.data || [];
        products.value = Array.isArray(raw) ? raw : (raw?.data || raw?.items || []);
    } catch (e) { /* ignore */ }
};

const loadSkus = async (p = 1) => {
    page.value = p;
    loading.value = true;
    try {
        const params = { page: page.value, per_page: perPage.value, ...filters };
        Object.keys(params).forEach(k => { if (params[k] === '' || params[k] === null || params[k] === undefined) delete params[k]; });

        const res = await getSkus(params);
        if (res.data.success) {
            const body = res.data;
            skus.value = Array.isArray(body.data) ? body.data : (body.data?.items || []);
            total.value = body.meta?.total || body.data?.total || 0;
        }
    } catch (e) { /* ignore */ }
    finally { loading.value = false; }
};

const resetFilters = () => {
    filters.product_id = '';
    filters.is_active = '';
    filters.billing_cycle = '';
    filters.stock_status = '';
    filters.search = '';
    loadSkus(1);
};

const openCreateDialog = () => {
    isEditing.value = false;
    editingId.value = null;
    form.product_id = '';
    form.sku_code = '';
    form.name = '';
    form.price = 0;
    form.compare_at_price = null;
    form.currency = 'CNY';
    form.billing_cycle = '';
    form.stock = -1;
    form.low_stock_threshold = 10;
    form.allow_backorder = false;
    form.commission_rate = null;
    form.image_url = '';
    form.specs = null;
    form.deliverables = [];
    dialogVisible.value = true;
};

const openEditDialog = (row) => {
    isEditing.value = true;
    editingId.value = row.id;
    form.product_id = row.product_id;
    form.sku_code = row.sku_code;
    form.name = row.name;
    form.price = row.price;
    form.compare_at_price = row.compare_at_price;
    form.currency = row.currency;
    form.billing_cycle = row.billing_cycle;
    form.stock = row.stock;
    form.low_stock_threshold = row.low_stock_threshold ?? 10;
    form.allow_backorder = row.allow_backorder ?? false;
    form.commission_rate = row.commission_rate ?? null;
    form.image_url = row.image_url || '';
    form.specs = row.specs || null;
    form.deliverables = row.deliverables && row.deliverables.length ? JSON.parse(JSON.stringify(row.deliverables)) : [];
    dialogVisible.value = true;
};

const saveSku = async () => {
    const valid = await formRef.value?.validate().catch(() => false);
    if (!valid) return;

    saving.value = true;
    try {
        const data = { ...form };
        if (data.commission_rate === '' || data.commission_rate === null) data.commission_rate = null;
        if (isEditing.value) {
            const res = await updateSku(editingId.value, data);
            if (res.data.success) {
                ElMessage.success(t('product_sku_page.update_ok'));
                dialogVisible.value = false;
                loadSkus(page.value);
            }
        } else {
            const res = await createSku(data);
            if (res.data.success) {
                ElMessage.success(t('product_sku_page.create_ok'));
                dialogVisible.value = false;
                loadSkus(1);
            }
        }
    } catch (e) {
        ElMessage.error(t('messages.failed'));
    }
    finally { saving.value = false; }
};

const handleToggle = async (row) => {
    try {
        const res = await toggleSku(row.id);
        if (res.data.success) {
            ElMessage.success(res.data.message || t('messages.success'));
            loadSkus(page.value);
            loadDashboard();
        }
    } catch (e) { /* ignore */ }
};

const handleDelete = async (row) => {
    try {
        await ElMessageBox.confirm(
            t('product_sku_page.delete_confirm', { name: row.name }),
            t('product_sku_page.confirm_delete_title'),
            { type: 'warning' },
        );
        const res = await deleteSku(row.id);
        if (res.data.success) {
            ElMessage.success(t('product_sku_page.deleted_ok'));
            loadSkus(page.value);
            loadDashboard();
        }
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('product_sku_page.delete_fail'));
    }
};

// ── 选择/批量操作 ──
function onSelectionChange(rows) {
    selectedIds.value = rows.map(r => r.id);
}
async function doBatchAction(action) {
    if (!selectedIds.value.length) return ElMessage.warning(t('product_sku_page.select_sku_first'));
    if (action === 'delete') {
        try {
            await ElMessageBox.confirm(
                t('product_sku_page.batch_delete_confirm', { n: selectedIds.value.length }),
                t('product_sku_page.confirm_title'),
                { type: 'warning' },
            );
        }
        catch { return; }
    }
    const extra = action === 'set_price' ? { price: batchPriceValue.value } : {};
    try {
        const res = await batchActionSku(action, selectedIds.value, extra);
        if (res.data.success) {
            ElMessage.success(res.data.message || t('messages.success'));
            showBatchPrice.value = false;
            selectedIds.value = [];
            loadSkus(page.value);
            loadDashboard();
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('messages.failed'));
    }
}

// ── 克隆 ──
async function handleClone(row) {
    try {
        await cloneSku(row.id);
        ElMessage.success(t('product_sku_page.clone_ok'));
        loadSkus(page.value);
        loadDashboard();
    } catch { ElMessage.error(t('product_sku_page.clone_fail')); }
}

// ── 库存日志 ──
function openStockLogDialog(row) {
    stockLogSku.value = row;
    stockLogVisible.value = true;
    stockAdjustValue.value = 0;
    stockAdjustReason.value = '';
    loadStockLogs(row.id);
}
async function loadStockLogs(skuId) {
    stockLogLoading.value = true;
    try {
        const res = await getStockLogs(skuId);
        if (res.data.success) stockLogs.value = res.data.data?.items || res.data.data || [];
    } catch { /* ignore */ }
    finally { stockLogLoading.value = false; }
}
async function handleStockAdjust() {
    if (!stockAdjustValue.value) return ElMessage.warning(t('product_sku_page.adjust_qty_required'));
    stockAdjusting.value = true;
    try {
        const res = await adjustStock(stockLogSku.value.id, stockAdjustValue.value, stockAdjustReason.value || t('product_sku_page.manual_adjust'));
        if (res.data.success) {
            ElMessage.success(t('product_sku_page.stock_adjusted', {
                old: res.data.data.old_stock,
                new: res.data.data.new_stock,
            }));
            stockLogSku.value.stock = res.data.data.new_stock;
            loadStockLogs(stockLogSku.value.id);
            loadDashboard();
        }
    } catch (e) { ElMessage.error(e.response?.data?.message || t('product_sku_page.adjust_fail')); }
    finally { stockAdjusting.value = false; }
}

// ── 多币种定价 ──
async function openCurrencyDialog(row) {
    currencySku.value = row;
    currencyVisible.value = true;
    try {
        const res = await getCurrencyPrices(row.id);
        if (res.data.success) currencyPrices.value = res.data.data || [];
        else currencyPrices.value = [];
    } catch { currencyPrices.value = []; }
}
async function handleSaveCurrency() {
    currencySaving.value = true;
    try {
        const res = await saveCurrencyPrices(currencySku.value.id, currencyPrices.value.map(p => ({
            currency: p.currency, price: p.price,
            compare_at_price: p.compare_at_price || null,
            cost_price: p.cost_price || null,
        })));
        if (res.data.success) {
            ElMessage.success(t('product_sku_page.currency_saved'));
            currencyVisible.value = false;
        }
    } catch (e) { ElMessage.error(e.response?.data?.message || t('product_sku_page.save_fail')); }
    finally { currencySaving.value = false; }
}

// ── 图片上传 ──
async function handleSkuImageUpload(file) {
    try {
        const res = await uploadSkuImage(file);
        if (res.data.success) form.image_url = res.data.data.url;
        else ElMessage.error(res.data.message || t('product_sku_page.upload_fail'));
    } catch { ElMessage.error(t('product_sku_page.upload_fail')); }
    return false;
}

// ── 导入/导出 ──
async function handleExport() {
    try {
        const res = await exportSkuCsv();
        const url = window.URL.createObjectURL(new Blob([res.data]));
        const link = document.createElement('a');
        link.href = url; link.setAttribute('download', 'skus.csv');
        document.body.appendChild(link); link.click();
        document.body.removeChild(link); window.URL.revokeObjectURL(url);
        ElMessage.success(t('product_sku_page.export_ok'));
    } catch { ElMessage.error(t('product_sku_page.export_fail')); }
}
async function handleImport() {
    try {
        const { value: csvText } = await ElMessageBox.prompt(
            t('product_sku_page.import_prompt'),
            t('product_sku_page.import_title'),
            {
                inputType: 'textarea',
                inputPlaceholder: t('product_sku_page.import_ph'),
                confirmButtonText: t('product_sku_page.import_btn'),
                cancelButtonText: t('actions.cancel'),
            },
        );
        if (!csvText) return;
        const res = await importSkuCsv(csvText);
        if (res.data.success) {
            const data = res.data.data;
            ElMessage.success(data?.message || t('product_sku_page.import_done'));
            if (data?.errors?.length) ElMessage.warning(data.errors.join('；'));
            loadSkus(); loadDashboard();
        }
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('product_sku_page.import_fail'));
    }
}

onMounted(() => {
    loadDashboard();
    loadProducts();
    loadSkus();
    loadBillingCycleOptions();
});
</script>

<style scoped>
.stat-card { text-align: center; padding: 8px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; }
.pagination-wrap { display: flex; justify-content: flex-end; margin-top: 16px; }
.mb-4 { margin-bottom: 16px; }
.text-muted { color: #909399; }
.text-line-through { text-decoration: line-through; }
.ml-2 { margin-left: 8px; }
.mt-1 { margin-top: 8px; }.batch-bar {
    margin-bottom: 12px; padding: 8px 16px;
    background: #f1f5f9; border-radius: 4px;
    display: flex; align-items: center; gap: 8px;
}
.batch-bar .batch-info { font-size: 13px; color: #0f172a; margin-right: 8px; }.mt-2 { margin-top: 12px; }

.deliverable-item {
    background: #fafafa;
    border: 1px solid #ebeef5;
    border-radius: 6px;
    padding: 12px;
    margin-bottom: 10px;
    transition: all 0.2s;
}
.deliverable-item:hover {
    border-color: #c0c4cc;
    background: #f5f7fa;
}
.deliverable-content {
    padding: 8px 0 0 0;
}
.file-info {
    display: flex;
    align-items: center;
}
.file-size {
    color: #909399;
    margin-left: 4px;
    font-size: 12px;
}
</style>
