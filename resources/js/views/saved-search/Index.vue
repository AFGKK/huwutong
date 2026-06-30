<template>
    <div>
        <el-breadcrumb separator="/" class="mb-4">
            <el-breadcrumb-item :to="{ path: '/admin/dashboard' }">首页</el-breadcrumb-item>
            <el-breadcrumb-item>系统管理</el-breadcrumb-item>
            <el-breadcrumb-item>保存搜索管理</el-breadcrumb-item>
        </el-breadcrumb>

        <el-row :gutter="20" class="mb-4">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-gray-800">{{ stats.total ?? '-' }}</div>
                    <div class="text-sm text-gray-500 mt-1">总搜索数</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-primary">{{ stats.shared ?? '-' }}</div>
                    <div class="text-sm text-gray-500 mt-1">已分享</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-success">{{ stats.active_users ?? '-' }}</div>
                    <div class="text-sm text-gray-500 mt-1">使用用户</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-warning">{{ stats.most_used_page ?? '-' }}</div>
                    <div class="text-sm text-gray-500 mt-1">最常用页面</div>
                </el-card>
            </el-col>
        </el-row>

        <el-card>
            <template #header>
                <div class="flex items-center justify-between">
                    <span class="font-semibold">已保存的搜索</span>
                    <el-select v-model="pageFilter" placeholder="按页面筛选" clearable style="width:180px" @change="fetchList">
                        <el-option v-for="(label, key) in pages" :key="key" :label="label" :value="key" />
                    </el-select>
                </div>
            </template>

            <el-table :data="list" v-loading="loading" stripe>
                <el-table-column prop="name" label="名称" min-width="180">
                    <template #default="{ row }">
                        <div class="flex items-center gap-2">
                            <el-icon v-if="row.icon" :color="row.color || '#409eff'"><component :is="row.icon" /></el-icon>
                            <span class="font-medium">{{ row.name }}</span>
                            <el-tag v-if="row.is_shared" type="success" size="small">已分享</el-tag>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column prop="description" label="描述" min-width="160" show-overflow-tooltip />
                <el-table-column label="页面" width="120">
                    <template #default="{ row }">{{ pages[row.page] || row.page }}</template>
                </el-table-column>
                <el-table-column label="创建者" width="120">
                    <template #default="{ row }">{{ row.user?.name || row.user?.email || '#' + row.user_id }}</template>
                </el-table-column>
                <el-table-column prop="usage_count" label="使用次数" width="80" align="center" />
                <el-table-column prop="created_at" label="创建时间" width="160" />
                <el-table-column prop="last_used_at" label="最后使用" width="160">
                    <template #default="{ row }">{{ row.last_used_at || '-' }}</template>
                </el-table-column>
                <el-table-column label="操作" width="120" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" type="danger" @click="handleDelete(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="mt-4 flex justify-center" v-if="total > perPage">
                <el-pagination v-model:current-page="currentPage" :page-size="perPage" :total="total"
                    layout="prev, pager, next" @current-change="onPageChange" />
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import savedSearchApi from '../../api/savedSearch';

const list = ref([]);
const loading = ref(false);
const currentPage = ref(1);
const perPage = ref(20);
const total = ref(0);
const pageFilter = ref('');
const stats = ref({});

const pages = {
    licenses: 'License 管理',
    customers: '客户管理',
    products: '产品管理',
    devices: '设备管理',
    invoices: '发票管理',
    subscriptions: '订阅管理',
    tickets: '工单管理',
};

async function fetchStats() {
    try {
        const res = await savedSearchApi.list({ page: '', per_page: 1 });
        const data = res.data;
        stats.value = {
            total: data.total || 0,
            shared: list.value.filter(s => s.is_shared).length,
            active_users: new Set(list.value.map(s => s.user_id)).size,
            most_used_page: Object.entries(
                list.value.reduce((acc, s) => { acc[s.page] = (acc[s.page] || 0) + 1; return acc; }, {})
            ).sort((a, b) => b[1] - a[1])[0]?.[0] || '-',
        };
    } catch { /* ignore */ }
}

async function fetchList() {
    loading.value = true;
    try {
        const params = { page: currentPage.value, per_page: perPage.value };
        if (pageFilter.value) params.page = pageFilter.value;
        const res = await savedSearchApi.list(params);
        list.value = res.data.data || [];
        total.value = res.data.total || 0;
    } catch { list.value = []; }
    finally { loading.value = false; }
}

async function handleDelete(row) {
    try {
        await ElMessageBox.confirm(`确定删除保存的搜索 "${row.name}"？`, '确认', { type: 'warning' });
        await savedSearchApi.destroy(row.id);
        ElMessage.success('已删除');
        await fetchList();
        await fetchStats();
    } catch { /* cancelled */ }
}

function onPageChange(page) {
    currentPage.value = page;
    fetchList();
}

onMounted(() => {
    fetchList();
});
</script>

<style scoped>
.text-primary { color: #409eff; }
.text-success { color: #67c23a; }
.text-warning { color: #e6a23c; }
</style>
