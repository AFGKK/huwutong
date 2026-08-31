<template>
    <div class="webhook-logs">
        <div class="page-header">
            <h2>{{ t('webhook_logs_page.title') }}</h2>
            <el-button @click="loadData">{{ t('actions.refresh') }}</el-button>
        </div>

        <el-card shadow="never" class="mb-4">
            <el-form :inline="true" :model="filters" size="small">
                <el-form-item :label="t('webhook_logs_page.gateway')">
                    <el-select v-model="filters.gateway" clearable :placeholder="t('webhook_logs_page.all')" style="width:130px">
                        <el-option :label="t('webhook_logs_page.gateways.alipay')" value="alipay" />
                        <el-option :label="t('webhook_logs_page.gateways.wechat')" value="wechat" />
                        <el-option label="Stripe" value="stripe" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('webhook_logs_page.status')">
                    <el-select v-model="filters.status" clearable :placeholder="t('webhook_logs_page.all')" style="width:130px">
                        <el-option :label="t('webhook_logs_page.statuses.completed')" value="completed" />
                        <el-option :label="t('webhook_logs_page.statuses.processing')" value="processing" />
                        <el-option :label="t('webhook_logs_page.statuses.failed')" value="failed" />
                        <el-option :label="t('webhook_logs_page.statuses.received')" value="received" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('webhook_logs_page.event_type')">
                    <el-input v-model="filters.event_type" :placeholder="t('webhook_logs_page.event_placeholder')" clearable style="width:180px" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadData">{{ t('actions.search') }}</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <el-card shadow="never">
            <el-table :data="list" v-loading="loading" stripe>
                <el-table-column prop="gateway" :label="t('webhook_logs_page.cols.gateway')" width="100">
                    <template #default="{ row }">
                        <el-tag>{{ row.gateway }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="event_type" :label="t('webhook_logs_page.cols.event_type')" width="250" />
                <el-table-column prop="event_id" :label="t('webhook_logs_page.cols.event_id')" width="200" show-overflow-tooltip />
                <el-table-column prop="status" :label="t('webhook_logs_page.cols.status')" width="120">
                    <template #default="{ row }">
                        <el-tag v-if="row.status === 'completed'" type="success" size="small">{{ t('webhook_logs_page.statuses.completed') }}</el-tag>
                        <el-tag v-else-if="row.status === 'processing'" type="warning" size="small">{{ t('webhook_logs_page.statuses.processing') }}</el-tag>
                        <el-tag v-else-if="row.status === 'failed'" type="danger" size="small">{{ t('webhook_logs_page.statuses.failed') }}</el-tag>
                        <el-tag v-else type="info" size="small">{{ row.status }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="error_message" :label="t('webhook_logs_page.cols.error')" min-width="200" show-overflow-tooltip />
                <el-table-column prop="created_at" :label="t('webhook_logs_page.cols.time')" width="180" />
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
import { useI18n } from 'vue-i18n';
import { getWebhookLogs } from '@/api/payment';

const { t } = useI18n();
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
