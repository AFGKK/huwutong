<template>
    <div class="webhook-logs">
        <div class="page-header">
            <h2>Webhook 日志</h2>
            <el-button @click="loadData">刷新</el-button>
        </div>

        <el-card shadow="never" class="mb-4">
            <el-form :inline="true" :model="filters" size="small">
                <el-form-item label="网关">
                    <el-select v-model="filters.gateway" clearable placeholder="全部" style="width:130px">
                        <el-option label="支付宝" value="alipay" />
                        <el-option label="微信支付" value="wechat" />
                        <el-option label="Stripe" value="stripe" />
                    </el-select>
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="filters.status" clearable placeholder="全部" style="width:130px">
                        <el-option label="已处理" value="completed" />
                        <el-option label="处理中" value="processing" />
                        <el-option label="失败" value="failed" />
                        <el-option label="已接收" value="received" />
                    </el-select>
                </el-form-item>
                <el-form-item label="事件类型">
                    <el-input v-model="filters.event_type" placeholder="搜索事件类型" clearable style="width:180px" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadData">查询</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <el-card shadow="never">
            <el-table :data="list" v-loading="loading" stripe>
                <el-table-column prop="gateway" label="网关" width="100">
                    <template #default="{ row }">
                        <el-tag>{{ row.gateway }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="event_type" label="事件类型" width="250" />
                <el-table-column prop="event_id" label="事件 ID" width="200" show-overflow-tooltip />
                <el-table-column prop="status" label="状态" width="120">
                    <template #default="{ row }">
                        <el-tag v-if="row.status === 'completed'" type="success" size="small">已处理</el-tag>
                        <el-tag v-else-if="row.status === 'processing'" type="warning" size="small">处理中</el-tag>
                        <el-tag v-else-if="row.status === 'failed'" type="danger" size="small">失败</el-tag>
                        <el-tag v-else type="info" size="small">{{ row.status }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="error_message" label="错误信息" min-width="200" show-overflow-tooltip />
                <el-table-column prop="created_at" label="时间" width="180" />
            </el-table>

            <div class="pagination-wrap" v-if="pagination.last_page > 1">
                <el-pagination
                    v-model:current-page="pagination.current_page"
                    :page-size="pagination.per_page"
                    :total="pagination.total"
                    layout="prev, pager, next, total"
                    @current-change="loadData"
                />
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { getWebhookLogs } from '@/api/payment';

const loading = ref(false);
const list = ref([]);
const pagination = ref({ current_page: 1, last_page: 1, per_page: 20, total: 0 });
const filters = ref({
    gateway: '',
    status: '',
    event_type: '',
});

const loadData = async () => {
    loading.value = true;
    try {
        const params = { ...filters.value, page: pagination.value.current_page, per_page: pagination.value.per_page };
        const res = await getWebhookLogs(params);
        if (res.data.success) {
            list.value = res.data.data.data || [];
            pagination.value = {
                current_page: res.data.data.current_page,
                last_page: res.data.data.last_page,
                per_page: res.data.data.per_page,
                total: res.data.data.total,
            };
        }
    } catch (e) { /* ignore */ }
    finally { loading.value = false; }
};

onMounted(() => loadData());
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; }
.pagination-wrap { margin-top: 16px; display: flex; justify-content: flex-end; }
</style>
