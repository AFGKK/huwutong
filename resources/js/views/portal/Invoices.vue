<template>
    <div class="portal-invoices">
        <div class="page-header">
            <div>
                <h2>自助发票</h2>
                <p class="text-muted">管理发票抬头，为已支付订单申请开具增值税发票。</p>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="8">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value">{{ stats.total_invoices }}</div>
                        <div class="mini-label">总发票数</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value" style="color:#67c23a">¥{{ stats.total_amount }}</div>
                        <div class="mini-label">开票总金额</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value" style="color:#409eff">{{ stats.billable_orders }}</div>
                        <div class="mini-label">待开票订单</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 发票抬头管理 -->
        <el-card shadow="never" class="mb-4">
            <template #header>
                <div class="card-header">
                    <span>发票抬头</span>
                    <el-button size="small" type="primary" @click="openTitleDialog()">+ 新增抬头</el-button>
                </div>
            </template>
            <div v-if="titles.length === 0 && !loadingTitles" class="empty-tip">
                <el-empty :image-size="50" description="暂无发票抬头，请先添加" />
            </div>
            <div v-loading="loadingTitles" class="title-list">
                <el-card
                    v-for="item in titles"
                    :key="item.id"
                    shadow="hover"
                    class="title-card"
                    :class="{ 'is-default': item.is_default }"
                >
                    <div class="title-main">
                        <div class="title-name">
                            {{ item.title }}
                            <el-tag v-if="item.is_default" size="small" type="primary" style="margin-left:6px">默认</el-tag>
                        </div>
                        <div class="title-meta">
                            <span v-if="item.tax_no">税号: {{ item.tax_no }}</span>
                            <span v-if="item.address">地址: {{ item.address }}</span>
                            <span v-if="item.phone">电话: {{ item.phone }}</span>
                            <span v-if="item.bank_name && item.bank_account">开户行: {{ item.bank_name }} ({{ item.bank_account }})</span>
                        </div>
                    </div>
                    <div class="title-actions">
                        <el-button link size="small" type="primary" @click="openTitleDialog(item)">编辑</el-button>
                        <el-button link size="small" type="danger" @click="handleDeleteTitle(item)">删除</el-button>
                    </div>
                </el-card>
            </div>
        </el-card>

        <!-- 可开票订单 -->
        <el-card shadow="never" class="mb-4">
            <template #header>
                <div class="card-header">
                    <span>可开票订单</span>
                </div>
            </template>
            <el-table :data="billableOrders" v-loading="loadingOrders" stripe>
                <el-table-column prop="order_no" label="订单号" min-width="140">
                    <template #default="{ row }">
                        <el-link type="primary" :underline="'never'" @click="$router.push(`/portal/orders/${row.id}`)">
                            {{ row.order_no || `#${row.id}` }}
                        </el-link>
                    </template>
                </el-table-column>
                <el-table-column label="商品" min-width="140">
                    <template #default="{ row }">{{ row.product?.name || row.description || '-' }}</template>
                </el-table-column>
                <el-table-column label="金额" width="100">
                    <template #default="{ row }">¥{{ row.total_amount || row.amount || 0 }}</template>
                </el-table-column>
                <el-table-column label="支付时间" width="150">
                    <template #default="{ row }">{{ formatDate(row.paid_at) }}</template>
                </el-table-column>
                <el-table-column label="操作" width="120" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            type="primary"
                            size="small"
                            :disabled="!titles.length"
                            :title="!titles.length ? '请先添加发票抬头' : ''"
                            @click="openGenerateDialog(row)"
                        >
                            申请开票
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>
            <el-empty v-if="billableOrders.length === 0 && !loadingOrders" description="暂无待开票订单" :image-size="60" />
        </el-card>

        <!-- 发票记录 -->
        <el-card shadow="never">
            <template #header>
                <div class="card-header">
                    <span>发票记录</span>
                </div>
            </template>
            <el-table :data="invoices" v-loading="loadingInvoices" stripe>
                <el-table-column prop="invoice_no" label="发票号" min-width="140">
                    <template #default="{ row }">{{ row.invoice_no || `#${row.id}` }}</template>
                </el-table-column>
                <el-table-column label="金额" width="100">
                    <template #default="{ row }">¥{{ row.amount || 0 }}</template>
                </el-table-column>
                <el-table-column label="状态" width="90">
                    <template #default="{ row }">
                        <el-tag :type="invoiceStatusType(row.status)" size="small">
                            {{ invoiceStatusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="开票日期" width="150">
                    <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
                </el-table-column>
                <el-table-column label="操作" width="180" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link size="small" @click="handlePreview(row)">
                            预览
                        </el-button>
                        <el-button link size="small" @click="handleResend(row)">
                            重发
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>
            <el-empty v-if="invoices.length === 0 && !loadingInvoices" description="暂无发票记录" :image-size="60" />

            <div class="pagination-wrap" v-if="totalInvoices > 0">
                <el-pagination
                    v-model:current-page="page"
                    v-model:page-size="perPage"
                    :total="totalInvoices"
                    :page-sizes="[10, 20, 50]"
                    layout="total, sizes, prev, pager, next"
                    @current-change="fetchInvoices"
                    @size-change="fetchInvoices"
                />
            </div>
        </el-card>

        <!-- 发票抬头对话框 -->
        <el-dialog v-model="titleDialog.visible" :title="titleDialog.isEdit ? '编辑发票抬头' : '新增发票抬头'" width="520px">
            <el-form :model="titleDialog.form" label-width="100px">
                <el-form-item label="抬头名称" required>
                    <el-input v-model="titleDialog.form.title" placeholder="公司全称" />
                </el-form-item>
                <el-form-item label="税号">
                    <el-input v-model="titleDialog.form.tax_no" placeholder="统一社会信用代码" />
                </el-form-item>
                <el-form-item label="地址">
                    <el-input v-model="titleDialog.form.address" placeholder="注册地址" />
                </el-form-item>
                <el-form-item label="电话">
                    <el-input v-model="titleDialog.form.phone" placeholder="注册电话" />
                </el-form-item>
                <el-form-item label="开户行">
                    <el-input v-model="titleDialog.form.bank_name" placeholder="开户银行名称" />
                </el-form-item>
                <el-form-item label="银行账号">
                    <el-input v-model="titleDialog.form.bank_account" placeholder="银行账号" />
                </el-form-item>
                <el-form-item>
                    <el-checkbox v-model="titleDialog.form.is_default">设为默认抬头</el-checkbox>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="titleDialog.visible = false">取消</el-button>
                <el-button type="primary" :loading="titleDialog.loading" @click="confirmSaveTitle">
                    {{ titleDialog.isEdit ? '保存' : '添加' }}
                </el-button>
            </template>
        </el-dialog>

        <!-- 申请开票对话框 -->
        <el-dialog v-model="generateDialog.visible" title="申请开票" width="480px">
            <el-form label-position="top">
                <el-form-item label="选择发票抬头">
                    <el-select v-model="generateDialog.titleId" placeholder="请选择发票抬头" style="width:100%">
                        <el-option
                            v-for="t in titles"
                            :key="t.id"
                            :label="t.title + (t.tax_no ? ' (' + t.tax_no + ')' : '')"
                            :value="t.id"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item label="订单信息">
                    <el-tag type="info">
                        {{ generateDialog.order?.order_no || `#${generateDialog.order?.id}` }}
                        — ¥{{ generateDialog.order?.total_amount || generateDialog.order?.amount || 0 }}
                    </el-tag>
                </el-form-item>
                <el-alert type="info" :closable="false" show-icon>
                    <template #title>
                        开具发票后将发送到您的注册邮箱，并可在下方发票记录中查看和下载。
                    </template>
                </el-alert>
            </el-form>
            <template #footer>
                <el-button @click="generateDialog.visible = false">取消</el-button>
                <el-button type="primary" :loading="generateDialog.loading" @click="confirmGenerate">
                    确认开票
                </el-button>
            </template>
        </el-dialog>

        <!-- 发票预览对话框 -->
        <el-dialog v-model="previewDialog.visible" title="发票预览" width="800px" top="5vh">
            <div v-loading="previewDialog.loading" style="min-height:300px">
                <iframe
                    v-if="previewDialog.html"
                    :srcdoc="previewDialog.html"
                    style="width:100%;min-height:500px;border:none"
                />
            </div>
            <template #footer>
                <el-button @click="previewDialog.visible = false">关闭</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import invoiceApi from '@/api/portalInvoice';
import shopApi from '@/api/shop';
import orderApi from '@/api/order';

// ── 数据 ──
const loadingTitles = ref(false);
const loadingOrders = ref(false);
const loadingInvoices = ref(false);
const titles = ref([]);
const billableOrders = ref([]);
const invoices = ref([]);
const totalInvoices = ref(0);
const page = ref(1);
const perPage = ref(10);

const stats = reactive({
    total_invoices: 0,
    total_amount: '0.00',
    billable_orders: 0,
});

// ── 发票抬头对话框 ──
const titleDialog = reactive({
    visible: false,
    isEdit: false,
    editId: null,
    loading: false,
    form: {
        title: '',
        tax_no: '',
        address: '',
        phone: '',
        bank_name: '',
        bank_account: '',
        is_default: false,
    },
});

function resetTitleForm() {
    titleDialog.form = { title: '', tax_no: '', address: '', phone: '', bank_name: '', bank_account: '', is_default: false };
    titleDialog.isEdit = false;
    titleDialog.editId = null;
}

function openTitleDialog(item) {
    resetTitleForm();
    if (item) {
        titleDialog.isEdit = true;
        titleDialog.editId = item.id;
        titleDialog.form = {
            title: item.title || '',
            tax_no: item.tax_no || '',
            address: item.address || '',
            phone: item.phone || '',
            bank_name: item.bank_name || '',
            bank_account: item.bank_account || '',
            is_default: item.is_default || false,
        };
    }
    titleDialog.visible = true;
}

async function confirmSaveTitle() {
    if (!titleDialog.form.title) {
        ElMessage.warning('请输入抬头名称');
        return;
    }
    titleDialog.loading = true;
    try {
        if (titleDialog.isEdit) {
            await invoiceApi.updateTitle(titleDialog.editId, titleDialog.form);
            ElMessage.success('发票抬头已更新');
        } else {
            await invoiceApi.createTitle(titleDialog.form);
            ElMessage.success('发票抬头已创建');
        }
        titleDialog.visible = false;
        fetchTitles();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '保存失败');
    } finally {
        titleDialog.loading = false;
    }
}

async function handleDeleteTitle(item) {
    try {
        await ElMessageBox.confirm(`确定要删除抬头「${item.title}」吗？`, '确认删除', {
            confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning',
        });
        await invoiceApi.deleteTitle(item.id);
        ElMessage.success('发票抬头已删除');
        fetchTitles();
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error(e.response?.data?.message || '删除失败');
        }
    }
}

// ── 申请开票对话框 ──
const generateDialog = reactive({
    visible: false,
    order: null,
    titleId: null,
    loading: false,
});

function openGenerateDialog(order) {
    generateDialog.order = order;
    // 默认选择默认抬头
    const defaultTitle = titles.value.find(t => t.is_default);
    generateDialog.titleId = defaultTitle?.id || (titles.value[0]?.id || null);
    generateDialog.visible = true;
}

async function confirmGenerate() {
    if (!generateDialog.titleId) {
        ElMessage.warning('请选择发票抬头');
        return;
    }
    generateDialog.loading = true;
    try {
        const { data: res } = await invoiceApi.generate(generateDialog.order.id, generateDialog.titleId);
        ElMessage.success('发票开具成功');
        generateDialog.visible = false;
        fetchInvoices();
        fetchBillableOrders();
        fetchStats();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '开票失败');
    } finally {
        generateDialog.loading = false;
    }
}

// ── 发票预览 ──
const previewDialog = reactive({
    visible: false,
    html: '',
    loading: false,
});

async function handlePreview(invoice) {
    previewDialog.loading = true;
    previewDialog.visible = true;
    previewDialog.html = '';
    try {
        const res = await invoiceApi.preview(invoice.id);
        previewDialog.html = res.data;
    } catch (e) {
        previewDialog.html = '<p style="color:red;padding:20px">无法加载发票预览</p>';
    } finally {
        previewDialog.loading = false;
    }
}

// ── 重发邮件 ──
async function handleResend(invoice) {
    try {
        await invoiceApi.resend(invoice.id);
        ElMessage.success('发票邮件已重新发送');
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '重发失败');
    }
}

// ── 数据加载 ──
async function fetchTitles() {
    loadingTitles.value = true;
    try {
        const { data: res } = await invoiceApi.titles();
        titles.value = res.data || [];
    } catch { /* ignore */ }
    finally { loadingTitles.value = false; }
}

async function fetchBillableOrders() {
    loadingOrders.value = true;
    try {
        const { data: res } = await orderApi.list({ status: 'paid', invoiced: false, per_page: 50 });
        billableOrders.value = res.data?.data || res.data || [];
    } catch { /* ignore */ }
    finally { loadingOrders.value = false; }
}

async function fetchInvoices() {
    loadingInvoices.value = true;
    try {
        const { data: res } = await invoiceApi.list({ page: page.value, per_page: perPage.value, sort: '-created_at' });
        invoices.value = res.data?.data || res.data || [];
        totalInvoices.value = res.meta?.total || res.data?.total || invoices.value.length;
    } catch { /* ignore */ }
    finally { loadingInvoices.value = false; }
}

async function fetchStats() {
    try {
        const { data: res } = await invoiceApi.stats();
        const s = res.data || {};
        stats.total_invoices = s.total_invoices || s.total || 0;
        stats.total_amount = s.total_amount || s.amount || '0.00';
        stats.billable_orders = s.billable_orders || s.pending_orders || 0;
    } catch { /* ignore */ }
}

// ── 工具 ──
const INVOICE_STATUS_MAP = {
    pending: { type: 'warning', label: '待处理' },
    processing: { type: 'info', label: '开票中' },
    completed: { type: 'success', label: '已开票' },
    failed: { type: 'danger', label: '开票失败' },
    cancelled: { type: 'info', label: '已取消' },
};

function invoiceStatusType(status) { return INVOICE_STATUS_MAP[status]?.type || 'info'; }
function invoiceStatusLabel(status) { return INVOICE_STATUS_MAP[status]?.label || status; }

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

onMounted(() => {
    fetchTitles();
    fetchBillableOrders();
    fetchInvoices();
    fetchStats();
});
</script>

<style scoped>
.portal-invoices { padding: 20px; }
.page-header { margin-bottom: 20px; }
.page-header h2 { margin: 0; font-size: 20px; }
.text-muted { color: #909399; font-size: 13px; margin: 4px 0 0; }
.mb-4 { margin-bottom: 16px; }

.mini-stat { text-align: center; padding: 8px 0; }
.mini-value { font-size: 28px; font-weight: 700; color: #303133; }
.mini-label { font-size: 14px; color: #909399; margin-top: 4px; }

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.empty-tip { padding: 10px 0; }

.title-list { display: flex; flex-direction: column; gap: 8px; }
.title-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border: 1px solid #ebeef5;
    border-radius: 6px;
    transition: border-color 0.2s;
}
.title-card.is-default {
    border-color: #409eff;
    background: #ecf5ff;
}
.title-card:hover { border-color: #c0c4cc; }
.title-main { flex: 1; min-width: 0; }
.title-name {
    font-size: 15px;
    font-weight: 600;
    color: #303133;
    margin-bottom: 4px;
}
.title-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 6px 16px;
    font-size: 12px;
    color: #909399;
}
.title-actions {
    display: flex;
    gap: 4px;
    flex-shrink: 0;
    margin-left: 12px;
}

.pagination-wrap {
    display: flex;
    justify-content: flex-end;
    padding: 16px 0 0;
}
</style>
