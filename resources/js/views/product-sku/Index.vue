<template>
    <div class="sku-management">
        <div class="page-header">
            <h2>SKU 商品规格管理</h2>
            <div>
                <el-button @click="handleExport"><el-icon><Download /></el-icon> 导出</el-button>
                <el-button @click="handleImport"><el-icon><Upload /></el-icon> 导入</el-button>
                <el-button type="primary" @click="openCreateDialog">
                    <el-icon><Plus /></el-icon> 新建 SKU
                </el-button>
            </div>
        </div>

        <!-- 批量操作栏 -->
        <div v-if="selectedIds.length > 0" class="batch-bar">
            <span class="batch-info">已选 {{ selectedIds.length }} 项</span>
            <el-button size="small" type="success" @click="doBatchAction('activate')">批量上架</el-button>
            <el-button size="small" type="warning" @click="doBatchAction('deactivate')">批量下架</el-button>
            <el-button size="small" type="danger" @click="doBatchAction('delete')">批量删除</el-button>
            <el-button size="small" @click="showBatchPrice = true">批量改价</el-button>
            <el-button size="small" text @click="selectedIds = []">取消</el-button>
        </div>

        <!-- 批量改价对话框 -->
        <el-dialog v-model="showBatchPrice" title="批量修改价格" width="400px">
            <el-form label-width="100px">
                <el-form-item label="新价格">
                    <el-input-number v-model="batchPriceValue" :min="0" :precision="2" style="width:200px" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showBatchPrice = false">取消</el-button>
                <el-button type="primary" @click="doBatchAction('set_price')">确认修改</el-button>
            </template>
        </el-dialog>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value">{{ stats.total_skus || 0 }}</div>
                        <div class="stat-label">SKU 总数</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #67c23a">{{ stats.active_skus || 0 }}</div>
                        <div class="stat-label">已上架</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #e6a23c">{{ stats.low_stock_count || 0 }}</div>
                        <div class="stat-label">低库存（≤10）</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #f56c6c">{{ stats.out_of_stock || 0 }}</div>
                        <div class="stat-label">缺货</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 筛选栏 -->
        <el-card shadow="never" class="mb-4">
            <el-form :inline="true" :model="filters" size="small">
                <el-form-item label="产品">
                    <el-select v-model="filters.product_id" clearable placeholder="全部产品" style="width:180px">
                        <el-option v-for="p in products" :key="p.id" :label="p.name" :value="p.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="filters.is_active" clearable placeholder="全部" style="width:120px">
                        <el-option label="已上架" :value="true" />
                        <el-option label="已下架" :value="false" />
                    </el-select>
                </el-form-item>
                <el-form-item label="计费周期">
                    <el-select v-model="filters.billing_cycle" clearable placeholder="全部" style="width:140px">
                        <el-option label="月付" value="monthly" />
                        <el-option label="季付" value="quarterly" />
                        <el-option label="年付" value="yearly" />
                        <el-option label="一次性" value="one-time" />
                    </el-select>
                </el-form-item>
                <el-form-item label="库存">
                    <el-select v-model="filters.stock_status" clearable placeholder="全部" style="width:140px">
                        <el-option label="低库存" value="low" />
                        <el-option label="缺货" value="out" />
                        <el-option label="无限" value="unlimited" />
                    </el-select>
                </el-form-item>
                <el-form-item label="搜索">
                    <el-input v-model="filters.search" placeholder="SKU编码/名称" clearable style="width:200px" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadSkus(1)">查询</el-button>
                    <el-button @click="resetFilters">重置</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- SKU 列表 -->
        <el-card shadow="never">
            <el-table :data="skus" stripe v-loading="loading" @selection-change="onSelectionChange">
                <el-table-column type="selection" width="40" />
                <el-table-column type="index" label="#" width="50" />
                <el-table-column prop="sku_code" label="SKU编码" width="150" />
                <el-table-column prop="name" label="名称" min-width="180" show-overflow-tooltip />
                <el-table-column prop="product.name" label="所属产品" width="150" show-overflow-tooltip />
                <el-table-column label="售价" width="100">
                    <template #default="{ row }">¥{{ row.price }}</template>
                </el-table-column>
                <el-table-column label="划线价" width="100">
                    <template #default="{ row }">
                        <span v-if="row.compare_at_price" class="text-muted text-line-through">¥{{ row.compare_at_price }}</span>
                        <span v-else>-</span>
                    </template>
                </el-table-column>
                <el-table-column prop="billing_cycle" label="周期" width="100">
                    <template #default="{ row }">
                        <el-tag v-if="row.billing_cycle === 'monthly'" size="small">月付</el-tag>
                        <el-tag v-else-if="row.billing_cycle === 'quarterly'" size="small" type="warning">季付</el-tag>
                        <el-tag v-else-if="row.billing_cycle === 'yearly'" size="small" type="success">年付</el-tag>
                        <el-tag v-else-if="row.billing_cycle === 'one-time'" size="small" type="info">一次性</el-tag>
                        <span v-else>-</span>
                    </template>
                </el-table-column>
                <el-table-column label="库存" width="80">
                    <template #default="{ row }">
                        <span v-if="row.stock === -1" style="color:#909399">无限</span>
                        <span v-else :style="{ color: row.stock <= 0 ? '#f56c6c' : (row.stock <= (row.low_stock_threshold ?? 10)) ? '#e6a23c' : '#67c23a' }">{{ row.stock }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="sold_count" label="已售" width="70" />
                <el-table-column label="佣金率" width="80">
                    <template #default="{ row }">
                        <span v-if="row.commission_rate !== null && row.commission_rate !== undefined" style="color:#e6a23c;font-weight:600">{{ row.commission_rate }}%</span>
                        <span v-else class="text-muted">默认</span>
                    </template>
                </el-table-column>
                <el-table-column prop="currency" label="币种" width="70" />
                <el-table-column label="状态" width="80">
                    <template #default="{ row }">
                        <el-tag v-if="row.is_active" type="success" size="small">上架</el-tag>
                        <el-tag v-else type="info" size="small">下架</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" label="创建时间" width="170" />
                <el-table-column label="操作" width="280" fixed="right">
                    <template #default="{ row }">
                        <el-button text type="primary" size="small" @click="openEditDialog(row)">编辑</el-button>
                        <el-button text type="primary" size="small" @click="handleClone(row)">克隆</el-button>
                        <el-button text type="primary" size="small" @click="openStockLogDialog(row)">库存</el-button>
                        <el-button text type="primary" size="small" @click="openCurrencyDialog(row)">定价</el-button>
                        <el-button text :type="row.is_active ? 'warning' : 'success'" size="small" @click="handleToggle(row)">
                            {{ row.is_active ? '下架' : '上架' }}
                        </el-button>
                        <el-button text type="danger" size="small" @click="handleDelete(row)">删除</el-button>
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
        <el-dialog v-model="dialogVisible" :title="isEditing ? '编辑 SKU' : '新建 SKU'" width="750px">
            <el-form :model="form" label-width="100px" :rules="formRules" ref="formRef">
                <el-form-item label="所属产品" prop="product_id">
                    <el-select v-model="form.product_id" style="width:100%" :disabled="isEditing">
                        <el-option v-for="p in products" :key="p.id" :label="p.name" :value="p.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="SKU编码" prop="sku_code">
                    <el-input v-model="form.sku_code" placeholder="留空自动生成" :disabled="isEditing" />
                </el-form-item>
                <el-form-item label="名称" prop="name">
                    <el-input v-model="form.name" placeholder="如：专业版-年付" />
                </el-form-item>
                <el-form-item label="售价" prop="price">
                    <el-input-number v-model="form.price" :min="0" :precision="2" style="width:200px" />
                </el-form-item>
                <el-form-item label="划线价">
                    <el-input-number v-model="form.compare_at_price" :min="0" :precision="2" style="width:200px" />
                </el-form-item>
                <el-form-item label="币种">
                    <el-select v-model="form.currency" style="width:120px">
                        <el-option label="CNY" value="CNY" />
                        <el-option label="USD" value="USD" />
                        <el-option label="EUR" value="EUR" />
                    </el-select>
                </el-form-item>
                <el-form-item label="计费周期">
                    <el-select v-model="form.billing_cycle" clearable placeholder="不限制" style="width:100%">
                        <el-option label="月付" value="monthly" />
                        <el-option label="季付" value="quarterly" />
                        <el-option label="年付" value="yearly" />
                        <el-option label="一次性" value="one-time" />
                    </el-select>
                </el-form-item>
                <el-form-item label="库存">
                    <el-input-number v-model="form.stock" :min="-1" style="width:200px" />
                    <span class="text-muted ml-2">-1=无限</span>
                </el-form-item>
                <el-form-item label="低库存阈值">
                    <el-input-number v-model="form.low_stock_threshold" :min="0" :max="9999" style="width:200px" />
                    <span class="text-muted ml-2">低于此值显示警告</span>
                </el-form-item>
                <el-form-item label="允许缺货">
                    <el-switch v-model="form.allow_backorder" />
                    <span class="text-muted ml-2">缺货时允许继续下单</span>
                </el-form-item>
                <el-form-item label="佣金率(%)">
                    <el-input-number v-model="form.commission_rate" :min="0" :max="100" :precision="1" style="width:200px" :placeholder="`默认 ${defaultCommissionRate}%`" />
                    <span class="text-muted ml-2">留空使用系统默认 {{ defaultCommissionRate }}%</span>
                </el-form-item>
                <el-divider content-position="left">SKU 图片</el-divider>
                <el-form-item label="图片">
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <template v-if="form.image_url">
                            <el-image :src="form.image_url" fit="cover" style="width:60px;height:60px;border-radius:4px" />
                            <el-button size="small" type="danger" circle @click="form.image_url = ''">
                                <el-icon><Close /></el-icon>
                            </el-button>
                        </template>
                        <el-upload :show-file-list="false" :before-upload="handleSkuImageUpload" accept="image/*">
                            <el-button size="small" type="primary" plain><el-icon><Upload /></el-icon> 上传图片</el-button>
                        </el-upload>
                    </div>
                </el-form-item>
                <el-form-item label="规格">
                    <el-input v-model="specsText" type="textarea" :rows="3" placeholder='JSON格式，如 {"version":"专业版","period":"年"}' />
                </el-form-item>

                <!-- 交付物管理 -->
                <el-divider content-position="left">交付物管理</el-divider>
                <el-form-item label="交付物">
                    <div style="width:100%">
                        <div v-for="(item, idx) in form.deliverables" :key="idx" class="deliverable-item">
                            <el-row :gutter="8" align="middle">
                                <el-col :span="4">
                                    <el-select v-model="item.type" size="small" placeholder="类型" @change="onDeliverableTypeChange(item)">
                                        <el-option label="📦 文件" value="file" />
                                        <el-option label="🔗 链接" value="link" />
                                        <el-option label="📝 文本" value="text" />
                                    </el-select>
                                </el-col>
                                <el-col :span="4">
                                    <el-select v-model="item.category" size="small" placeholder="分类">
                                        <el-option label="💻 软件" value="software" />
                                        <el-option label="📄 文档" value="document" />
                                        <el-option label="🔧 模板" value="template" />
                                        <el-option label="🌐 API" value="api" />
                                        <el-option label="🎓 教程" value="tutorial" />
                                        <el-option label="其他" value="other" />
                                    </el-select>
                                </el-col>
                                <el-col :span="6">
                                    <el-input v-model="item.name" size="small" placeholder="名称，如：AI客户端v2.1" />
                                </el-col>
                                <el-col :span="8">
                                    <el-input v-model="item.description" size="small" placeholder="简短说明" />
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
                                                    <el-icon><Upload /></el-icon> 选择文件
                                                </el-button>
                                            </template>
                                        </el-upload>
                                    </el-col>
                                    <el-col :span="12">
                                        <el-input v-model="item.file_url" size="small" placeholder="或输入网盘/下载链接" />
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
                                <el-input v-model="item.file_url" placeholder="https://pan.baidu.com/s/xxx 或 https://..." size="small" />
                            </div>

                            <div v-if="item.type === 'text'" class="deliverable-content mt-1">
                                <el-input v-model="item.content" type="textarea" :rows="2" placeholder="输入文本内容，如 API地址、端口号、配置说明等" size="small" />
                            </div>
                        </div>

                        <el-button type="primary" plain size="small" @click="addDeliverable" class="mt-2">
                            <el-icon><Plus /></el-icon> 添加交付物
                        </el-button>
                        <span class="text-muted ml-2" style="font-size:12px">支持软件包、文档、模板、API地址、网盘链接等多种类型</span>
                    </div>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="saving" @click="saveSku">保存</el-button>
            </template>
        </el-dialog>

        <!-- 库存日志对话框 -->
        <el-dialog v-model="stockLogVisible" title="库存变更日志" width="700px">
            <div v-if="stockLogSku" style="margin-bottom:12px">
                <strong>{{ stockLogSku.name }}</strong>
                <el-tag size="small" style="margin-left:8px">当前库存: {{ stockLogSku.stock }}</el-tag>
            </div>
            <div style="display:flex;gap:8px;margin-bottom:12px">
                <el-input-number v-model="stockAdjustValue" :min="-99999" placeholder="调整数量" style="width:180px" />
                <el-input v-model="stockAdjustReason" placeholder="调整原因" style="width:250px" />
                <el-button type="primary" :loading="stockAdjusting" @click="handleStockAdjust">确认调整</el-button>
            </div>
            <el-table :data="stockLogs" stripe v-loading="stockLogLoading" max-height="400">
                <el-table-column prop="created_at" label="时间" width="170" />
                <el-table-column label="变更" width="100">
                    <template #default="{ row }">
                        <span :style="{ color: row.change > 0 ? '#67c23a' : '#f56c6c', fontWeight:600 }">
                            {{ row.change > 0 ? '+' : '' }}{{ row.change }}
                        </span>
                    </template>
                </el-table-column>
                <el-table-column label="变更前" width="80" prop="old_stock" />
                <el-table-column label="变更后" width="80" prop="new_stock" />
                <el-table-column prop="reason" label="原因" min-width="150" />
                <el-table-column prop="user.name" label="操作人" width="120" />
            </el-table>
        </el-dialog>

        <!-- 多币种定价对话框 -->
        <el-dialog v-model="currencyVisible" title="多币种定价" width="600px">
            <div v-if="currencySku" style="margin-bottom:12px">
                <strong>{{ currencySku.name }}</strong>
                <el-tag size="small" style="margin-left:8px">基础价: ¥{{ currencySku.price }}</el-tag>
            </div>
            <el-table :data="currencyPrices" stripe>
                <el-table-column label="币种" width="100">
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
                <el-table-column label="售价">
                    <template #default="{ row }">
                        <el-input-number v-model="row.price" :min="0" :precision="2" style="width:140px" />
                    </template>
                </el-table-column>
                <el-table-column label="划线价">
                    <template #default="{ row }">
                        <el-input-number v-model="row.compare_at_price" :min="0" :precision="2" style="width:140px" />
                    </template>
                </el-table-column>
                <el-table-column label="成本价">
                    <template #default="{ row }">
                        <el-input-number v-model="row.cost_price" :min="0" :precision="2" style="width:140px" />
                    </template>
                </el-table-column>
                <el-table-column width="60">
                    <template #default="{ $index }">
                        <el-button text type="danger" size="small" @click="currencyPrices.splice($index,1)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <el-button size="small" @click="currencyPrices.push({ currency: 'USD', price: 0, compare_at_price: null, cost_price: null })" class="mt-2">
                <el-icon><Plus /></el-icon> 添加币种
            </el-button>
            <template #footer>
                <el-button @click="currencyVisible = false">取消</el-button>
                <el-button type="primary" :loading="currencySaving" @click="handleSaveCurrency">保存定价</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch, computed } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Delete, Upload, Download, Close } from '@element-plus/icons-vue';
import { getSkuDashboard, getSkus, getSkuDetail, createSku, updateSku, deleteSku, toggleSku, uploadDeliverable, cloneSku, adjustStock, getStockLogs, getCurrencyPrices, saveCurrencyPrices, batchActionSku, uploadSkuImage, exportSkuCsv, importSkuCsv, getLowStockSkus } from '@/api/productSku';
import request from '@/utils/request';
import apiClient from '@/api/client';

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
        ElMessage.error('文件上传失败');
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

const formRules = {
    product_id: [{ required: true, message: '请选择所属产品', trigger: 'change' }],
    name: [{ required: true, message: '请输入SKU名称', trigger: 'blur' }],
    price: [{ required: true, message: '请输入售价', trigger: 'blur' }],
};

const loadDashboard = async () => {
    try {
        const res = await getSkuDashboard();
        if (res.data.success) stats.value = res.data.data;
    } catch (e) { /* ignore */ }
};

const loadProducts = async () => {
    try {
        const res = await apiClient.get('/products', { params: { per_page: 200 } });
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
                ElMessage.success('SKU更新成功');
                dialogVisible.value = false;
                loadSkus(page.value);
            }
        } else {
            const res = await createSku(data);
            if (res.data.success) {
                ElMessage.success('SKU创建成功');
                dialogVisible.value = false;
                loadSkus(1);
            }
        }
    } catch (e) {
        ElMessage.error('操作失败');
    }
    finally { saving.value = false; }
};

const handleToggle = async (row) => {
    try {
        const res = await toggleSku(row.id);
        if (res.data.success) {
            ElMessage.success(res.data.message || '操作成功');
            loadSkus(page.value);
            loadDashboard();
        }
    } catch (e) { /* ignore */ }
};

const handleDelete = async (row) => {
    try {
        await ElMessageBox.confirm(`确定删除 SKU "${row.name}"？`, '确认删除', { type: 'warning' });
        const res = await deleteSku(row.id);
        if (res.data.success) {
            ElMessage.success('已删除');
            loadSkus(page.value);
            loadDashboard();
        }
    } catch (e) {
        if (e !== 'cancel') ElMessage.error('删除失败');
    }
};

// ── 选择/批量操作 ──
function onSelectionChange(rows) {
    selectedIds.value = rows.map(r => r.id);
}
async function doBatchAction(action) {
    if (!selectedIds.value.length) return ElMessage.warning('请先选择 SKU');
    if (action === 'delete') {
        try { await ElMessageBox.confirm(`确定删除 ${selectedIds.value.length} 个 SKU？`, '确认', { type: 'warning' }); }
        catch { return; }
    }
    const extra = action === 'set_price' ? { price: batchPriceValue.value } : {};
    try {
        const res = await batchActionSku(action, selectedIds.value, extra);
        if (res.data.success) {
            ElMessage.success(res.data.message || '操作成功');
            showBatchPrice.value = false;
            selectedIds.value = [];
            loadSkus(page.value);
            loadDashboard();
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败');
    }
}

// ── 克隆 ──
async function handleClone(row) {
    try {
        await cloneSku(row.id);
        ElMessage.success('SKU 已克隆');
        loadSkus(page.value);
        loadDashboard();
    } catch { ElMessage.error('克隆失败'); }
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
    if (!stockAdjustValue.value) return ElMessage.warning('请输入调整数量');
    stockAdjusting.value = true;
    try {
        const res = await adjustStock(stockLogSku.value.id, stockAdjustValue.value, stockAdjustReason.value || '手动调整');
        if (res.data.success) {
            ElMessage.success(`库存已调整: ${res.data.data.old_stock} → ${res.data.data.new_stock}`);
            stockLogSku.value.stock = res.data.data.new_stock;
            loadStockLogs(stockLogSku.value.id);
            loadDashboard();
        }
    } catch (e) { ElMessage.error(e.response?.data?.message || '调整失败'); }
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
            ElMessage.success('多币种定价已保存');
            currencyVisible.value = false;
        }
    } catch (e) { ElMessage.error(e.response?.data?.message || '保存失败'); }
    finally { currencySaving.value = false; }
}

// ── 图片上传 ──
async function handleSkuImageUpload(file) {
    try {
        const res = await uploadSkuImage(file);
        if (res.data.success) form.image_url = res.data.data.url;
        else ElMessage.error(res.data.message || '上传失败');
    } catch { ElMessage.error('上传失败'); }
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
        ElMessage.success('导出成功');
    } catch { ElMessage.error('导出失败'); }
}
async function handleImport() {
    try {
        const { value: csvText } = await ElMessageBox.prompt('粘贴 CSV 内容（第一行为表头：product_name,sku_code,name,price,compare_at_price,currency,stock,billing_cycle,is_active,commission_rate）', '导入 SKU', {
            inputType: 'textarea',
            inputPlaceholder: 'product_name,sku_code,name,price,currency,stock\n示例产品,DEMO-001,演示SKU,99,CNY,-1',
            confirmButtonText: '导入',
            cancelButtonText: '取消',
        });
        if (!csvText) return;
        const res = await importSkuCsv(csvText);
        if (res.data.success) {
            const data = res.data.data;
            ElMessage.success(data?.message || '导入完成');
            if (data?.errors?.length) ElMessage.warning(data.errors.join('；'));
            loadSkus(); loadDashboard();
        }
    } catch (e) {
        if (e !== 'cancel') ElMessage.error('导入失败');
    }
}

onMounted(() => {
    loadDashboard();
    loadProducts();
    loadSkus();
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
    background: #ecf5ff; border-radius: 4px;
    display: flex; align-items: center; gap: 8px;
}
.batch-bar .batch-info { font-size: 13px; color: #409eff; margin-right: 8px; }.mt-2 { margin-top: 12px; }

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
