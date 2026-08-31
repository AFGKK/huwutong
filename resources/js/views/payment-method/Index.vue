<template>
    <div>
        <el-breadcrumb separator="/" class="mb-4">
            <el-breadcrumb-item :to="{ path: '/admin/dashboard' }">{{ t('nav.home') }}</el-breadcrumb-item>
            <el-breadcrumb-item>{{ t('admin.group.billing') }}</el-breadcrumb-item>
            <el-breadcrumb-item>{{ t('admin.menu.payment_methods') }}</el-breadcrumb-item>
        </el-breadcrumb>

        <!-- 统计卡片 -->
        <el-row :gutter="20" class="mb-4">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-gray-800">{{ stats.total_methods ?? '-' }}</div>
                    <div class="text-sm text-gray-500 mt-1">{{ t('payment_method_page.stats.total_methods') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-success">{{ stats.active_methods ?? '-' }}</div>
                    <div class="text-sm text-gray-500 mt-1">{{ t('payment_method_page.stats.active_methods') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-warning">{{ stats.expiring_soon ?? '-' }}</div>
                    <div class="text-sm text-gray-500 mt-1">{{ t('payment_method_page.stats.expiring_soon') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-info">{{ stats.avg_per_customer ?? '-' }}</div>
                    <div class="text-sm text-gray-500 mt-1">{{ t('payment_method_page.stats.avg_per_customer') }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 卡片品牌分布 -->
        <el-card class="mb-4">
            <template #header>
                <span>{{ t('payment_method_page.brand_distribution') }}</span>
            </template>
            <el-row :gutter="16">
                <el-col :span="6" v-for="(count, brand) in stats.brand_distribution ?? {}" :key="brand">
                    <div class="text-center p-3">
                        <div class="text-lg font-bold">{{ count }}</div>
                        <div class="text-sm text-gray-500">{{ brandLabel(brand) }}</div>
                    </div>
                </el-col>
                <el-col v-if="!stats.brand_distribution || Object.keys(stats.brand_distribution).length === 0">
                    <el-empty :description="t('messages.no_data')" :image-size="60" />
                </el-col>
            </el-row>
        </el-card>

        <!-- 支付方式列表 -->
        <el-card>
            <template #header>
                <div class="flex items-center justify-between">
                    <span>{{ t('payment_method_page.list_title') }}</span>
                    <el-input
                        v-model="search"
                        :placeholder="t('payment_method_page.search_ph')"
                        clearable
                        style="width: 260px"
                        @clear="fetchList"
                        @keyup.enter="fetchList"
                    />
                </div>
            </template>

            <el-table :data="list" v-loading="loading" stripe style="width:100%">
                <el-table-column prop="id" label="#" width="60" />
                <el-table-column prop="customer_id" :label="t('payment_method_page.columns.customer_id')" width="90" />
                <el-table-column :label="t('payment_method_page.columns.payment_method')" width="160">
                    <template #default="{ row }">
                        <div class="flex items-center gap-2">
                            <el-icon :size="20" :color="brandColor(row.card_brand)">
                                <CreditCard />
                            </el-icon>
                            <span>{{ brandLabel(row.card_brand) }}</span>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column :label="t('payment_method_page.columns.card_number')" width="180">
                    <template #default="{ row }">
                        <span class="font-mono text-sm">**** **** **** {{ row.last_four }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="cardholder_name" :label="t('payment_method_page.columns.cardholder')" width="120" />
                <el-table-column :label="t('payment_method_page.columns.expiry')" width="100">
                    <template #default="{ row }">
                        <span>{{ row.expiry_month }}/{{ row.expiry_year }}</span>
                        <el-tag v-if="isExpiring(row)" type="warning" size="small" class="ml-1">{{ t('payment_method_page.expiring_tag') }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="gateway" :label="t('payment_method_page.columns.gateway')" width="80" />
                <el-table-column :label="t('payment_method_page.columns.default')" width="60" align="center">
                    <template #default="{ row }">
                        <el-tag v-if="row.is_default" type="success" size="small">{{ t('payment_method_page.yes') }}</el-tag>
                        <span v-else class="text-gray-400">{{ t('payment_method_page.no') }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="t('payment_method_page.columns.status')" width="70">
                    <template #default="{ row }">
                        <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">
                            {{ row.is_active ? t('payment_method_page.status.active') : t('payment_method_page.status.deleted') }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" :label="t('payment_method_page.columns.created_at')" width="170" />
                <el-table-column :label="t('payment_method_page.columns.actions')" width="120" fixed="right">
                    <template #default="{ row }">
                        <el-button v-if="row.is_active" type="danger" size="small" @click="handleForceDelete(row)">{{ t('payment_method_page.actions.force_delete') }}</el-button>
                        <el-button v-else text size="small" disabled>{{ t('payment_method_page.actions.deleted') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="mt-4 flex justify-center" v-if="total > perPage">
                <el-pagination
                    v-model:current-page="currentPage"
                    :page-size="perPage"
                    :total="total"
                    layout="prev, pager, next"
                    @current-change="onPageChange"
                />
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { CreditCard } from '@element-plus/icons-vue';
import paymentMethodApi from '../../api/paymentMethod';

const { t } = useI18n();

const stats = ref({});
const list = ref([]);
const loading = ref(false);
const search = ref('');
const currentPage = ref(1);
const perPage = ref(20);
const total = ref(0);

const brandColors = {
    visa: '#1a1f71', mastercard: '#eb001b', amex: '#2e77bc',
    discover: '#ff6000', unionpay: '#e21836',
};

function brandLabel(brand) {
    if (!brand) return t('payment_method_page.brands.unknown');
    const key = `payment_method_page.brands.${brand}`;
    const label = t(key);
    return label !== key ? label : brand;
}

function brandColor(brand) {
    return brandColors[brand] || '#909399';
}

function isExpiring(row) {
    if (!row.expiry_year || !row.expiry_month) return false;
    const expiry = new Date(row.expiry_year, row.expiry_month, 0);
    const soon = new Date();
    soon.setDate(soon.getDate() + 30);
    return expiry > new Date() && expiry <= soon;
}

async function fetchDashboard() {
    try {
        const res = await paymentMethodApi.adminDashboard();
        stats.value = res.data;
    } catch {
        // ignore
    }
}

async function fetchList() {
    loading.value = true;
    try {
        const res = await paymentMethodApi.adminIndex({
            page: currentPage.value,
            per_page: perPage.value,
            search: search.value || undefined,
        });
        list.value = res.data.data;
        total.value = res.data.total;
    } catch {
        list.value = [];
    } finally {
        loading.value = false;
    }
}

async function handleForceDelete(row) {
    try {
        await ElMessageBox.confirm(
            t('payment_method_page.dialog.force_delete_confirm', {
                customer_id: row.customer_id,
                brand: brandLabel(row.card_brand),
                last_four: row.last_four,
            }),
            t('payment_method_page.dialog.force_delete_title'),
            {
                type: 'warning',
                confirmButtonText: t('actions.delete'),
                cancelButtonText: t('actions.cancel'),
                confirmButtonClass: 'el-button--danger',
            }
        );
        await paymentMethodApi.adminForceDelete(row.id);
        ElMessage.success(t('payment_method_page.messages.force_deleted'));
        await fetchList();
        await fetchDashboard();
    } catch {
        // cancelled
    }
}

function onPageChange(page) {
    currentPage.value = page;
    fetchList();
}

onMounted(() => {
    fetchDashboard();
    fetchList();
});
</script>

<style scoped>
.text-success { color: #67c23a; }
.text-warning { color: #e6a23c; }
.text-info { color: #0f172a; }
.font-mono { font-family: 'Courier New', Courier, monospace; }
</style>
