<template>
    <div class="promotion-engine-page">
        <div class="page-header">
            <h2>满减/满折促销引擎</h2>
            <div class="header-actions">
                <el-button @click="loadStats" :loading="loading">刷新统计</el-button>
                <el-button type="primary" @click="showEditDialog(null)">
                    <el-icon><Plus /></el-icon> 新建规则
                </el-button>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-label">总规则数</div>
                    <div class="stat-value">{{ stats.total_rules }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-active">
                    <div class="stat-label">进行中</div>
                    <div class="stat-value">{{ stats.active_rules }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-info">
                    <div class="stat-label">总使用次数</div>
                    <div class="stat-value">{{ stats.total_redemptions }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-warning">
                    <div class="stat-label">总优惠金额</div>
                    <div class="stat-value">¥{{ stats.total_discount_amount }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 规则列表 -->
        <el-card>
            <template #header>
                <div class="card-header-flex">
                    <span>促销规则</span>
                    <div>
                        <el-select v-model="filterStatus" placeholder="状态" clearable class="mr-2" style="width:120px" @change="loadRules">
                            <el-option label="草稿" value="draft" />
                            <el-option label="进行中" value="active" />
                            <el-option label="已暂停" value="paused" />
                            <el-option label="已过期" value="expired" />
                        </el-select>
                        <el-select v-model="filterType" placeholder="类型" clearable style="width:140px" @change="loadRules">
                            <el-option label="满减" value="amount_off" />
                            <el-option label="满折" value="percent_off" />
                            <el-option label="买N送N" value="buy_x_get_y" />
                            <el-option label="一口价" value="fixed_price" />
                        </el-select>
                    </div>
                </div>
            </template>

            <el-table :data="rules" stripe v-loading="loadingRules" @row-click="showEditDialog">
                <el-table-column prop="name" label="规则名称" min-width="160" />
                <el-table-column label="类型" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.type === 'amount_off' ? 'danger' : row.type === 'percent_off' ? 'warning' : 'info'"
                            size="small">{{ typeLabel(row.type) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="门槛" width="120" align="center">
                    <template #default="{ row }">
                        <span v-if="row.condition_type === 'subtotal'">满¥{{ row.condition_value }}</span>
                        <span v-else-if="row.condition_type === 'quantity'">满{{ row.condition_value }}件</span>
                        <span v-else>—</span>
                    </template>
                </el-table-column>
                <el-table-column label="折扣" width="120" align="center">
                    <template #default="{ row }">
                        <span v-if="row.type === 'amount_off'">减¥{{ row.discount_value }}</span>
                        <span v-else-if="row.type === 'percent_off'">{{ row.discount_value }}%</span>
                        <span v-else-if="row.type === 'buy_x_get_y'">买{{ row.buy_quantity }}送{{ row.free_quantity }}</span>
                        <span v-else>¥{{ row.discount_value }}</span>
                    </template>
                </el-table-column>
                <el-table-column label="叠加" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag v-if="row.stackable_with_coupon || row.stackable_with_other_rules" size="small" type="success">可叠加</el-tag>
                        <span v-else class="text-muted">—</span>
                    </template>
                </el-table-column>
                <el-table-column prop="usage_count" label="已用/限制" width="110" align="center">
                    <template #default="{ row }">
                        {{ row.usage_count }}{{ row.usage_limit ? '/' + row.usage_limit : '' }}
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="90">
                    <template #default="{ row }">
                        <el-tag :type="statusTagType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="时间" width="170">
                    <template #default="{ row }">
                        <div class="time-col">
                            <span v-if="row.starts_at" class="time-text">{{ row.starts_at.slice(0, 10) }}</span>
                            <span v-if="row.ends_at" class="time-text"> ~ {{ row.ends_at.slice(0, 10) }}</span>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="180" fixed="right">
                    <template #default="{ row }">
                        <el-button-group>
                            <el-button v-if="row.status === 'draft'" size="small" type="primary" @click.stop="toggleStatus(row, 'active')">发布</el-button>
                            <el-button v-if="row.status === 'active'" size="small" type="warning" @click.stop="toggleStatus(row, 'paused')">暂停</el-button>
                            <el-button v-if="row.status === 'paused'" size="small" type="primary" @click.stop="toggleStatus(row, 'active')">恢复</el-button>
                            <el-button size="small" type="danger" @click.stop="deleteRule(row)">删除</el-button>
                        </el-button-group>
                    </template>
                </el-table-column>
            </el-table>

            <div class="pagination-wrap" v-if="pagination.total > pagination.per_page">
                <el-pagination
                    v-model:current-page="pagination.current_page"
                    :page-size="pagination.per_page"
                    :total="pagination.total"
                    layout="prev, pager, next"
                    @current-change="loadRules"
                />
            </div>
        </el-card>

        <!-- 编辑/新建对话框 -->
        <el-dialog v-model="showDialog" :title="editingRule ? '编辑规则' : '新建规则'" width="750px"
            :close-on-click-modal="false" @close="resetForm">
            <el-form :model="form" label-width="120px" :rules="formRules" ref="formRef" v-loading="saving">
                <el-tabs v-model="formTab">
                    <el-tab-pane label="基本信息" name="basic">
                        <el-form-item label="规则名称" prop="name">
                            <el-input v-model="form.name" maxlength="200" />
                        </el-form-item>
                        <el-form-item label="促销类型" prop="type">
                            <el-radio-group v-model="form.type">
                                <el-radio value="amount_off">满减（减固定金额）</el-radio>
                                <el-radio value="percent_off">满折（百分比折扣）</el-radio>
                                <el-radio value="buy_x_get_y">买N送N</el-radio>
                                <el-radio value="fixed_price">一口价</el-radio>
                            </el-radio-group>
                        </el-form-item>
                        <el-form-item label="描述">
                            <el-input v-model="form.description" type="textarea" :rows="2" maxlength="1000" />
                        </el-form-item>
                    </el-tab-pane>

                    <el-tab-pane label="折扣条件" name="conditions">
                        <el-form-item label="条件类型" prop="condition_type">
                            <el-radio-group v-model="form.condition_type">
                                <el-radio value="subtotal">按订单总额</el-radio>
                                <el-radio value="quantity">按商品数量</el-radio>
                                <el-radio value="items_count">按商品件数</el-radio>
                            </el-radio-group>
                        </el-form-item>
                        <el-form-item label="条件值" prop="condition_value">
                            <el-input-number v-model="form.condition_value" :min="0" :precision="2" style="width:200px" />
                        </el-form-item>

                        <template v-if="form.type !== 'buy_x_get_y'">
                            <el-form-item label="折扣值" prop="discount_value">
                                <el-input-number v-model="form.discount_value" :min="0" :precision="2" style="width:200px" />
                                <span class="ml-2 text-muted">{{ form.type === 'percent_off' ? '%' : '元' }}</span>
                            </el-form-item>
                            <el-form-item label="最大折扣">
                                <el-input-number v-model="form.max_discount" :min="0" :precision="2" placeholder="不限" style="width:200px" />
                                <span class="ml-2 text-muted">百分比折扣时的封顶金额</span>
                            </el-form-item>
                        </template>

                        <template v-if="form.type === 'buy_x_get_y'">
                            <el-form-item label="买N件" prop="buy_quantity">
                                <el-input-number v-model="form.buy_quantity" :min="1" style="width:200px" />
                            </el-form-item>
                            <el-form-item label="送N件" prop="free_quantity">
                                <el-input-number v-model="form.free_quantity" :min="1" style="width:200px" />
                            </el-form-item>
                        </template>

                        <el-form-item label="最低订单金额">
                            <el-input-number v-model="form.min_order_amount" :min="0" :precision="2" style="width:200px" />
                        </el-form-item>
                    </el-tab-pane>

                    <el-tab-pane label="多级阶梯">
                        <p class="text-muted">配置多个门槛级别，系统自动匹配最合适的级别。</p>
                        <div v-for="(tier, i) in form.tiers" :key="i" class="tier-row">
                            <el-row :gutter="8" align="middle">
                                <el-col :span="6">
                                    <el-input-number v-model="tier.from" :min="0" :precision="2" placeholder="最低" size="small" style="width:100%" />
                                </el-col>
                                <el-col :span="1" class="text-center">~</el-col>
                                <el-col :span="6">
                                    <el-input-number v-model="tier.to" :min="0" :precision="2" placeholder="最高(留空∞)" size="small" style="width:100%" />
                                </el-col>
                                <el-col :span="5">
                                    <el-select v-model="tier.type" size="small" style="width:100%">
                                        <el-option label="减金额" value="amount_off" />
                                        <el-option label="打折扣" value="percent_off" />
                                    </el-select>
                                </el-col>
                                <el-col :span="4">
                                    <el-input-number v-model="tier.value" :min="0" :precision="2" size="small" style="width:100%" />
                                </el-col>
                                <el-col :span="2">
                                    <el-button type="danger" size="small" @click="removeTier(i)">×</el-button>
                                </el-col>
                            </el-row>
                        </div>
                        <el-button size="small" @click="addTier" class="mt-2">+ 添加阶梯</el-button>
                    </el-tab-pane>

                    <el-tab-pane label="适用范围">
                        <el-form-item label="适用商品">
                            <el-select v-model="form.applicable_products" multiple filterable remote
                                :remote-method="searchProducts" placeholder="留空=全部商品" style="width:100%">
                                <el-option v-for="p in productOptions" :key="p.id" :label="p.name" :value="p.id" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="排除商品">
                            <el-select v-model="form.excluded_products" multiple filterable placeholder="选择排除的商品" style="width:100%">
                                <el-option v-for="p in productOptions" :key="p.id" :label="p.name" :value="p.id" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="叠加优惠券">
                            <el-switch v-model="form.stackable_with_coupon" />
                            <span class="ml-2 text-muted">允许与优惠券叠加使用</span>
                        </el-form-item>
                        <el-form-item label="叠加其他规则">
                            <el-switch v-model="form.stackable_with_other_rules" />
                            <span class="ml-2 text-muted">允许与其他促销规则叠加</span>
                        </el-form-item>
                        <el-form-item label="优先级">
                            <el-input-number v-model="form.priority" :min="0" :max="999" style="width:120px" />
                            <span class="ml-2 text-muted">数字越小越优先</span>
                        </el-form-item>
                    </el-tab-pane>

                    <el-tab-pane label="使用限制">
                        <el-form-item label="总使用次数">
                            <el-input-number v-model="form.usage_limit" :min="1" placeholder="不限" style="width:200px" />
                        </el-form-item>
                        <el-form-item label="每客户限制">
                            <el-input-number v-model="form.usage_limit_per_customer" :min="1" placeholder="不限" style="width:200px" />
                        </el-form-item>
                        <el-form-item label="预算总额">
                            <el-input-number v-model="form.budget" :min="0" :precision="2" placeholder="不限" style="width:200px" />
                        </el-form-item>
                        <el-form-item label="生效时间">
                            <el-date-picker v-model="form.starts_at" type="datetime" placeholder="立即生效" style="width:200px" />
                        </el-form-item>
                        <el-form-item label="失效时间">
                            <el-date-picker v-model="form.ends_at" type="datetime" placeholder="永久有效" style="width:200px" />
                        </el-form-item>
                    </el-tab-pane>
                </el-tabs>
            </el-form>

            <template #footer>
                <el-button @click="showDialog = false" :disabled="saving">取消</el-button>
                <el-button type="primary" @click="saveRule" :loading="saving">{{ editingRule ? '保存' : '创建' }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import promotionEngineApi from '@/api/promotionEngine';

const loading = ref(false);
const loadingRules = ref(false);
const rules = ref([]);
const pagination = reactive({ current_page: 1, per_page: 20, total: 0 });

// 统计
const stats = reactive({
    total_rules: 0, active_rules: 0,
    total_redemptions: 0, total_discount_amount: 0,
});

// 过滤
const filterStatus = ref('');
const filterType = ref('');

// 对话框
const showDialog = ref(false);
const editingRule = ref(null);
const saving = ref(false);
const formRef = ref(null);
const formTab = ref('basic');
const productOptions = ref([]);

const form = reactive({
    name: '', type: 'amount_off', description: '',
    condition_type: 'subtotal', condition_value: 0,
    discount_value: 0, max_discount: null, min_order_amount: 0,
    applicable_products: [], applicable_categories: [],
    excluded_products: [],
    stackable_with_coupon: false, stackable_with_other_rules: false, priority: 0,
    usage_limit: null, usage_limit_per_customer: null,
    budget: null,
    starts_at: null, ends_at: null,
    tiers: [],
    buy_quantity: null, free_quantity: null, free_products: [],
    status: 'draft',
});

const formRules = {
    name: [{ required: true, message: '请输入规则名称', trigger: 'blur' }],
    condition_value: [{ required: true, message: '请输入条件值', trigger: 'blur' }],
    discount_value: [{ required: true, message: '请输入折扣值', trigger: 'blur' }],
};

function typeLabel(type) {
    return { amount_off: '满减', percent_off: '满折', buy_x_get_y: '买N送N', fixed_price: '一口价' }[type] || type;
}
function statusLabel(s) {
    return { draft: '草稿', active: '进行中', paused: '已暂停', expired: '已过期' }[s] || s;
}
function statusTagType(s) {
    return { draft: 'info', active: 'success', paused: 'warning', expired: 'danger' }[s] || 'info';
}

async function loadStats() {
    try {
        const res = await promotionEngineApi.getStats();
        Object.assign(stats, res.data?.data || {});
    } catch {}
}

async function loadRules(page) {
    loadingRules.value = true;
    try {
        const params = { page: page || pagination.current_page };
        if (filterStatus.value) params.status = filterStatus.value;
        if (filterType.value) params.type = filterType.value;
        const res = await promotionEngineApi.getRules(params);
        const data = res.data?.data || {};
        rules.value = data.data || [];
        pagination.current_page = data.current_page || 1;
        pagination.per_page = data.per_page || 20;
        pagination.total = data.total || 0;
    } catch {
        ElMessage.error('加载规则失败');
    } finally {
        loadingRules.value = false;
    }
}

function showEditDialog(row) {
    if (!row) {
        editingRule.value = null;
        resetForm();
        showDialog.value = true;
        return;
    }
    editingRule.value = row;
    Object.keys(form).forEach(k => {
        if (k === 'starts_at' || k === 'ends_at') {
            form[k] = row[k] || null;
        } else {
            form[k] = row[k] !== undefined ? row[k] : form[k];
        }
    });
    formTab.value = 'basic';
    showDialog.value = true;
}

function resetForm() {
    form.name = ''; form.type = 'amount_off'; form.description = '';
    form.condition_type = 'subtotal'; form.condition_value = 0;
    form.discount_value = 0; form.max_discount = null; form.min_order_amount = 0;
    form.applicable_products = []; form.applicable_categories = []; form.excluded_products = [];
    form.stackable_with_coupon = false; form.stackable_with_other_rules = false; form.priority = 0;
    form.usage_limit = null; form.usage_limit_per_customer = null;
    form.budget = null; form.starts_at = null; form.ends_at = null;
    form.tiers = []; form.buy_quantity = null; form.free_quantity = null;
    form.free_products = []; form.status = 'draft';
    editingRule.value = null;
}

async function saveRule() {
    const valid = await formRef.value?.validate().catch(() => false);
    if (!valid) return;

    saving.value = true;
    try {
        const data = { ...form };
        if (data.starts_at) data.starts_at = data.starts_at.toISOString();
        if (data.ends_at) data.ends_at = data.ends_at.toISOString();
        if (data.max_discount === null) delete data.max_discount;
        if (data.usage_limit === null) delete data.usage_limit;
        if (data.usage_limit_per_customer === null) delete data.usage_limit_per_customer;
        if (data.budget === null) delete data.budget;

        if (editingRule.value) {
            await promotionEngineApi.updateRule(editingRule.value.id, data);
            ElMessage.success('更新成功');
        } else {
            await promotionEngineApi.createRule(data);
            ElMessage.success('创建成功');
        }
        showDialog.value = false;
        await loadRules(1);
        await loadStats();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || '保存失败');
    } finally {
        saving.value = false;
    }
}

async function toggleStatus(row, newStatus) {
    try {
        await promotionEngineApi.toggleStatus(row.id, newStatus);
        ElMessage.success(`状态已切换为 ${statusLabel(newStatus)}`);
        await loadRules();
        await loadStats();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || '切换失败');
    }
}

async function deleteRule(row) {
    try {
        await ElMessageBox.confirm(`确定要${row.redemptions_count > 0 ? '过期' : '删除'}规则「${row.name}」？`, '确认', { type: 'warning' });
        await promotionEngineApi.deleteRule(row.id);
        ElMessage.success('操作成功');
        await loadRules();
        await loadStats();
    } catch (err) {
        if (err !== 'cancel') ElMessage.error(err.response?.data?.message || '操作失败');
    }
}

// 阶梯管理
function addTier() {
    form.tiers.push({ from: 0, to: null, type: 'amount_off', value: 0 });
}
function removeTier(i) {
    form.tiers.splice(i, 1);
}

// 商品搜索
async function searchProducts(query) {
    try {
        const { default: productApi } = await import('@/api/product');
        const res = await productApi.list({ search: query, per_page: 20 });
        productOptions.value = res.data?.data?.data || [];
    } catch {
        productOptions.value = [];
    }
}

onMounted(() => {
    loadStats();
    loadRules();
});
</script>

<style scoped>
.promotion-engine-page { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { margin: 0; font-size: 20px; }
.header-actions { display: flex; gap: 8px; }
.mb-4 { margin-bottom: 16px; }
.mt-2 { margin-top: 8px; }
.ml-2 { margin-left: 8px; }
.mr-2 { margin-right: 8px; }

.stat-label { font-size: 13px; color: #909399; margin-bottom: 4px; }
.stat-value { font-size: 22px; font-weight: 700; }
.stat-active .stat-value { color: #67c23a; }
.stat-info .stat-value { color: #409eff; }
.stat-warning .stat-value { color: #e6a23c; }

.card-header-flex { display: flex; justify-content: space-between; align-items: center; }
.text-muted { color: #909399; font-size: 12px; }
.text-center { text-align: center; }

.pagination-wrap { margin-top: 16px; display: flex; justify-content: center; }

.time-col { font-size: 12px; }
.time-text { white-space: nowrap; }

.tier-row { margin-bottom: 8px; padding: 8px; background: #f5f7fa; border-radius: 4px; }
</style>
