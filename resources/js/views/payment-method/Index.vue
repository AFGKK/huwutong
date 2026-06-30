<template>
    <div>
        <el-breadcrumb separator="/" class="mb-4">
            <el-breadcrumb-item :to="{ path: '/admin/dashboard' }">首页</el-breadcrumb-item>
            <el-breadcrumb-item>计费管理</el-breadcrumb-item>
            <el-breadcrumb-item>支付方式管理</el-breadcrumb-item>
        </el-breadcrumb>

        <!-- 统计卡片 -->
        <el-row :gutter="20" class="mb-4">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-gray-800">{{ stats.total_methods ?? '-' }}</div>
                    <div class="text-sm text-gray-500 mt-1">支付方式总数</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-success">{{ stats.active_methods ?? '-' }}</div>
                    <div class="text-sm text-gray-500 mt-1">活跃方式</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-warning">{{ stats.expiring_soon ?? '-' }}</div>
                    <div class="text-sm text-gray-500 mt-1">即将过期</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-info">{{ stats.avg_per_customer ?? '-' }}</div>
                    <div class="text-sm text-gray-500 mt-1">平均/客户</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 卡片品牌分布 -->
        <el-card class="mb-4">
            <template #header>
                <span>卡片品牌分布</span>
            </template>
            <el-row :gutter="16">
                <el-col :span="6" v-for="(count, brand) in stats.brand_distribution ?? {}" :key="brand">
                    <div class="text-center p-3">
                        <div class="text-lg font-bold">{{ count }}</div>
                        <div class="text-sm text-gray-500">{{ brandLabel(brand) }}</div>
                    </div>
                </el-col>
                <el-col v-if="!stats.brand_distribution || Object.keys(stats.brand_distribution).length === 0">
                    <el-empty description="暂无数据" :image-size="60" />
                </el-col>
            </el-row>
        </el-card>

        <!-- 支付方式列表 -->
        <el-card>
            <template #header>
                <div class="flex items-center justify-between">
                    <span>支付方式列表</span>
                    <el-input
                        v-model="search"
                        placeholder="搜索客户 ID / 卡号末4位"
                        clearable
                        style="width: 260px"
                        @clear="fetchList"
                        @keyup.enter="fetchList"
                    />
                </div>
            </template>

            <el-table :data="list" v-loading="loading" stripe style="width:100%">
                <el-table-column prop="id" label="#" width="60" />
                <el-table-column prop="customer_id" label="客户 ID" width="90" />
                <el-table-column label="支付方式" width="160">
                    <template #default="{ row }">
                        <div class="flex items-center gap-2">
                            <el-icon :size="20" :color="brandColor(row.card_brand)">
                                <CreditCard />
                            </el-icon>
                            <span>{{ brandLabel(row.card_brand) }}</span>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="卡号" width="180">
                    <template #default="{ row }">
                        <span class="font-mono text-sm">**** **** **** {{ row.last_four }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="cardholder_name" label="持卡人" width="120" />
                <el-table-column label="有效期" width="100">
                    <template #default="{ row }">
                        <span>{{ row.expiry_month }}/{{ row.expiry_year }}</span>
                        <el-tag v-if="isExpiring(row)" type="warning" size="small" class="ml-1">即将过期</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="gateway" label="网关" width="80" />
                <el-table-column label="默认" width="60" align="center">
                    <template #default="{ row }">
                        <el-tag v-if="row.is_default" type="success" size="small">是</el-tag>
                        <span v-else class="text-gray-400">否</span>
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="70">
                    <template #default="{ row }">
                        <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">
                            {{ row.is_active ? '活跃' : '已删' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" label="创建时间" width="170" />
                <el-table-column label="操作" width="120" fixed="right">
                    <template #default="{ row }">
                        <el-button v-if="row.is_active" type="danger" size="small" @click="handleForceDelete(row)">强制删除</el-button>
                        <el-button v-else text size="small" disabled>已删除</el-button>
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
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { CreditCard } from '@element-plus/icons-vue';
import paymentMethodApi from '../../api/paymentMethod';

const stats = ref({});
const list = ref([]);
const loading = ref(false);
const search = ref('');
const currentPage = ref(1);
const perPage = ref(20);
const total = ref(0);

const brandLabels = {
    visa: 'Visa', mastercard: 'Mastercard', amex: 'American Express',
    discover: 'Discover', unionpay: '银联', jcb: 'JCB', diners: 'Diners Club',
};

const brandColors = {
    visa: '#1a1f71', mastercard: '#eb001b', amex: '#2e77bc',
    discover: '#ff6000', unionpay: '#e21836',
};

function brandLabel(brand) {
    return brandLabels[brand] || brand || '未知';
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
            `确定强制删除客户 #${row.customer_id} 的 ${brandLabel(row.card_brand)} 支付方式（末4位 ${row.last_four}）？`,
            '确认删除',
            { type: 'warning', confirmButtonText: '确认删除', cancelButtonText: '取消', confirmButtonClass: 'el-button--danger' }
        );
        await paymentMethodApi.adminForceDelete(row.id);
        ElMessage.success('已强制删除');
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
.text-info { color: #409eff; }
.font-mono { font-family: 'Courier New', Courier, monospace; }
</style>
