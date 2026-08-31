<template>
    <div class="portal-invoices">
        <div class="page-header">
            <div>
                <h2>{{ $t('portal.self_invoice_title') }}</h2>
                <p class="text-muted">{{ $t('portal.self_invoice_subtitle') }}</p>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="8">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value">{{ stats.total_invoices }}</div>
                        <div class="mini-label">{{ $t('portal.total_invoices') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value" style="color:#67c23a">¥{{ stats.total_amount }}</div>
                        <div class="mini-label">{{ $t('portal.invoiced_amount') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value" style="color:#0f172a">{{ stats.billable_orders }}</div>
                        <div class="mini-label">{{ $t('portal.billable_orders') }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 发票抬头管理 -->
        <el-card shadow="never" class="mb-4">
            <template #header>
                <div class="card-header">
                    <span>{{ $t('portal.invoice_titles') }}</span>
                    <el-button size="small" type="primary" @click="openTitleDialog()">+ {{ $t('portal.add_title') }}</el-button>
                </div>
            </template>
            <div v-if="titles.length === 0 && !loadingTitles" class="empty-tip">
                <el-empty :image-size="50" :description="$t('portal.no_titles')" />
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
                            <el-tag v-if="item.is_default" size="small" type="primary" style="margin-left:6px">{{ $t('portal.default_tag') }}</el-tag>
                        </div>
                        <div class="title-meta">
                            <span v-if="item.tax_no">{{ $t('portal.tax_no_label', { v: item.tax_no }) }}</span>
                            <span v-if="item.address">{{ $t('portal.address_label', { v: item.address }) }}</span>
                            <span v-if="item.phone">{{ $t('portal.phone_label', { v: item.phone }) }}</span>
                            <span v-if="item.bank_name && item.bank_account">{{ $t('portal.bank_label', { v: `${item.bank_name} (${item.bank_account})` }) }}</span>
                        </div>
                    </div>
                    <div class="title-actions">
                        <el-button link size="small" type="primary" @click="openTitleDialog(item)">{{ $t('portal.edit') }}</el-button>
                        <el-button link size="small" type="danger" @click="handleDeleteTitle(item)">{{ $t('actions.delete') }}</el-button>
                    </div>
                </el-card>
            </div>
        </el-card>

        <!-- 可开票订单 -->
        <el-card shadow="never" class="mb-4">
            <template #header>
                <div class="card-header">
                    <span>{{ $t('portal.billable_orders_section') }}</span>
                </div>
            </template>
            <el-table :data="billableOrders" v-loading="loadingOrders" stripe>
                <el-table-column prop="order_no" :label="$t('portal.order_no')" min-width="140">
                    <template #default="{ row }">
                        <el-link type="primary" :underline="'never'" @click="$router.push(`/portal/orders/${row.id}`)">
                            {{ row.order_no || `#${row.id}` }}
                        </el-link>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('portal.product_item')" min-width="140">
                    <template #default="{ row }">{{ row.product?.name || row.description || '-' }}</template>
                </el-table-column>
                <el-table-column :label="$t('portal.amount')" width="100">
                    <template #default="{ row }">¥{{ row.total_amount || row.amount || 0 }}</template>
                </el-table-column>
                <el-table-column :label="$t('portal.paid_at')" width="150">
                    <template #default="{ row }">{{ formatDate(row.paid_at) }}</template>
                </el-table-column>
                <el-table-column :label="$t('portal.actions')" width="120" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            type="primary"
                            size="small"
                            :disabled="!titles.length"
                            :title="!titles.length ? $t('portal.add_title_first') : ''"
                            @click="openGenerateDialog(row)"
                        >
                            {{ $t('portal.apply_invoice') }}
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>
            <el-empty v-if="billableOrders.length === 0 && !loadingOrders" :description="$t('portal.no_billable')" :image-size="60" />
        </el-card>

        <!-- 发票记录 -->
        <el-card shadow="never">
            <template #header>
                <div class="card-header">
                    <span>{{ $t('portal.invoice_records') }}</span>
                </div>
            </template>
            <el-table :data="invoices" v-loading="loadingInvoices" stripe>
                <el-table-column prop="invoice_no" :label="$t('portal.invoice_no')" min-width="140">
                    <template #default="{ row }">{{ row.invoice_no || `#${row.id}` }}</template>
                </el-table-column>
                <el-table-column :label="$t('portal.amount')" width="100">
                    <template #default="{ row }">¥{{ row.amount || 0 }}</template>
                </el-table-column>
                <el-table-column :label="$t('portal.status')" width="90">
                    <template #default="{ row }">
                        <el-tag :type="invoiceStatusType(row.status)" size="small">
                            {{ invoiceStatusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('portal.invoice_date')" width="150">
                    <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
                </el-table-column>
                <el-table-column :label="$t('portal.actions')" width="180" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link size="small" @click="handlePreview(row)">
                            {{ $t('portal.preview') }}
                        </el-button>
                        <el-button link size="small" @click="handleResend(row)">
                            {{ $t('portal.resend') }}
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>
            <el-empty v-if="invoices.length === 0 && !loadingInvoices" :description="$t('portal.no_invoice_records')" :image-size="60" />

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
        <el-dialog v-model="titleDialog.visible" :title="titleDialog.isEdit ? $t('portal.edit_title') : $t('portal.add_title_dialog')" width="520px">
            <el-form :model="titleDialog.form" label-width="100px">
                <el-form-item :label="$t('portal.title_name')" required>
                    <el-input v-model="titleDialog.form.title" :placeholder="$t('portal.company_name_ph')" />
                </el-form-item>
                <el-form-item :label="$t('portal.tax_no')">
                    <el-input v-model="titleDialog.form.tax_no" :placeholder="$t('portal.tax_no_ph')" />
                </el-form-item>
                <el-form-item :label="$t('portal.address')">
                    <el-input v-model="titleDialog.form.address" :placeholder="$t('portal.address_ph')" />
                </el-form-item>
                <el-form-item :label="$t('portal.phone')">
                    <el-input v-model="titleDialog.form.phone" :placeholder="$t('portal.phone_ph')" />
                </el-form-item>
                <el-form-item :label="$t('portal.bank_name')">
                    <el-input v-model="titleDialog.form.bank_name" :placeholder="$t('portal.bank_name_ph')" />
                </el-form-item>
                <el-form-item :label="$t('portal.bank_account')">
                    <el-input v-model="titleDialog.form.bank_account" :placeholder="$t('portal.bank_account_ph')" />
                </el-form-item>
                <el-form-item>
                    <el-checkbox v-model="titleDialog.form.is_default">{{ $t('portal.set_default_title') }}</el-checkbox>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="titleDialog.visible = false">{{ $t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="titleDialog.loading" @click="confirmSaveTitle">
                    {{ titleDialog.isEdit ? $t('actions.save') : $t('portal.add') }}
                </el-button>
            </template>
        </el-dialog>

        <!-- 申请开票对话框 -->
        <el-dialog v-model="generateDialog.visible" :title="$t('portal.apply_invoice')" width="480px">
            <el-form label-position="top">
                <el-form-item :label="$t('portal.select_title')">
                    <el-select v-model="generateDialog.titleId" :placeholder="$t('portal.select_title_ph')" style="width:100%">
                        <el-option
                            v-for="titleItem in titles"
                            :key="titleItem.id"
                            :label="titleItem.title + (titleItem.tax_no ? ' (' + titleItem.tax_no + ')' : '')"
                            :value="titleItem.id"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('portal.order_info')">
                    <el-tag type="info">
                        {{ generateDialog.order?.order_no || `#${generateDialog.order?.id}` }}
                        — ¥{{ generateDialog.order?.total_amount || generateDialog.order?.amount || 0 }}
                    </el-tag>
                </el-form-item>
                <el-alert type="info" :closable="false" show-icon>
                    <template #title>
                        {{ $t('portal.generate_hint') }}
                    </template>
                </el-alert>
            </el-form>
            <template #footer>
                <el-button @click="generateDialog.visible = false">{{ $t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="generateDialog.loading" @click="confirmGenerate">
                    {{ $t('portal.confirm_invoice') }}
                </el-button>
            </template>
        </el-dialog>

        <!-- 发票预览对话框 -->
        <el-dialog v-model="previewDialog.visible" :title="$t('portal.invoice_preview')" width="800px" top="5vh">
            <div v-loading="previewDialog.loading" style="min-height:300px">
                <iframe
                    v-if="previewDialog.html"
                    :srcdoc="previewDialog.html"
                    style="width:100%;min-height:500px;border:none"
                />
            </div>
            <template #footer>
                <el-button @click="previewDialog.visible = false">{{ $t('actions.close') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import invoiceApi from '@/api/portalInvoice';
import shopApi from '@/api/shop';
import orderApi from '@/api/order';

const { t, locale } = useI18n();

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
        ElMessage.warning(t('portal.title_name_required'));
        return;
    }
    titleDialog.loading = true;
    try {
        if (titleDialog.isEdit) {
            await invoiceApi.updateTitle(titleDialog.editId, titleDialog.form);
            ElMessage.success(t('portal.title_updated'));
        } else {
            await invoiceApi.createTitle(titleDialog.form);
            ElMessage.success(t('portal.title_created'));
        }
        titleDialog.visible = false;
        fetchTitles();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('portal.save_failed'));
    } finally {
        titleDialog.loading = false;
    }
}

async function handleDeleteTitle(item) {
    try {
        await ElMessageBox.confirm(
            t('portal.delete_title_confirm', { title: item.title }),
            t('portal.confirm_delete'),
            { confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel'), type: 'warning' },
        );
        await invoiceApi.deleteTitle(item.id);
        ElMessage.success(t('portal.title_deleted'));
        fetchTitles();
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error(e.response?.data?.message || t('portal.delete_failed_msg'));
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
    const defaultTitle = titles.value.find(item => item.is_default);
    generateDialog.titleId = defaultTitle?.id || (titles.value[0]?.id || null);
    generateDialog.visible = true;
}

async function confirmGenerate() {
    if (!generateDialog.titleId) {
        ElMessage.warning(t('portal.title_required'));
        return;
    }
    generateDialog.loading = true;
    try {
        await invoiceApi.generate(generateDialog.order.id, generateDialog.titleId);
        ElMessage.success(t('portal.invoice_generated'));
        generateDialog.visible = false;
        fetchInvoices();
        fetchBillableOrders();
        fetchStats();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('portal.invoice_generate_failed'));
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
        previewDialog.html = `<p style="color:red;padding:20px">${t('portal.preview_failed')}</p>`;
    } finally {
        previewDialog.loading = false;
    }
}

// ── 重发邮件 ──
async function handleResend(invoice) {
    try {
        await invoiceApi.resend(invoice.id);
        ElMessage.success(t('portal.resend_ok'));
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('portal.resend_failed'));
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
function invoiceStatusType(status) {
    const map = {
        pending: 'warning',
        processing: 'info',
        completed: 'success',
        failed: 'danger',
        cancelled: 'info',
    };
    return map[status] || 'info';
}

function invoiceStatusLabel(status) {
    const map = {
        pending: t('portal.ticket_open'),
        processing: t('portal.inv_processing'),
        completed: t('portal.inv_completed'),
        failed: t('portal.inv_gen_failed'),
        cancelled: t('portal.inv_cancelled'),
    };
    return map[status] || status;
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const dateLocale = locale.value === 'en' ? 'en-US' : 'zh-CN';
    return new Date(dateStr).toLocaleString(dateLocale, {
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
    border-color: #0f172a;
    background: #f1f5f9;
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
