<template>
    <div class="demo-booking-page">
        <div class="page-header">
            <h2>预约 Demo / 联系销售</h2>
            <p class="text-muted">管理销售线索 — 企业客户填写表单后自动创建CRM线索，分配销售跟进</p>
        </div>

        <el-row :gutter="16">
            <el-col :span="16">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>预约列表</span>
                            <div class="card-actions">
                                <el-select v-model="filterStatus" size="small" style="width:140px" @change="loadList">
                                    <el-option label="全部" value="" />
                                    <el-option v-for="(label, key) in statusLabels" :key="key" :label="label" :value="key" />
                                </el-select>
                            </div>
                        </div>
                    </template>
                    <el-table :data="list" v-loading="loading" stripe>
                        <el-table-column prop="company_name" label="公司" width="150" />
                        <el-table-column prop="contact_name" label="联系人" width="100" />
                        <el-table-column prop="email" label="邮箱" width="180" />
                        <el-table-column prop="phone" label="电话" width="120" />
                        <el-table-column prop="status" label="状态" width="100">
                            <template #default="{ row }">
                                <el-tag :type="statusTag(row.status)" size="small">{{ statusLabels[row.status] || row.status }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="created_at" label="提交时间" width="160" />
                        <el-table-column label="操作" width="140" fixed="right">
                            <template #default="{ row }">
                                <el-select v-model="row.status" size="small" @change="(val) => handleUpdateStatus(row.id, val)">
                                    <el-option v-for="(label, key) in statusLabels" :key="key" :label="label" :value="key" />
                                </el-select>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-if="!loading && list.length === 0" description="暂无预约记录" />
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="never" class="mb-4">
                    <template #header><span>统计概览</span></template>
                    <div class="stats-grid">
                        <div class="stat-item" v-for="(val, key) in stats" :key="key">
                            <div class="stat-value">{{ val }}</div>
                            <div class="stat-label">{{ statusLabels[key] || key }}</div>
                        </div>
                    </div>
                </el-card>
                <el-card shadow="never">
                    <template #header><span>Calendly 集成</span></template>
                    <p class="text-muted">Calendly 预约日历可用于官网嵌入</p>
                    <el-tag :type="calendly.enabled ? 'success' : 'danger'" size="small">
                        {{ calendly.enabled ? '已启用' : '未启用' }}
                    </el-tag>
                    <div v-if="calendly.link" class="mt-2">
                        <el-input v-model="calendly.link" readonly size="small">
                            <template #append>
                                <el-button @click="copyText(calendly.link)" size="small">复制</el-button>
                            </template>
                        </el-input>
                    </div>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import {
    getDemoBookingList,
    updateDemoBookingStatus,
    getDemoBookingStats,
    getCalendlyLink,
} from '@/api/demoBooking';

const list = ref([]);
const loading = ref(false);
const stats = ref({});
const calendly = ref({ enabled: false, link: '' });
const filterStatus = ref('');

const statusLabels = { pending: '待处理', contacted: '已联系', scheduled: '已预约', completed: '已完成', converted: '已转化', lost: '已流失' };
const statusTag = (s) => ({ pending: 'info', contacted: 'warning', scheduled: 'primary', completed: 'success', converted: 'success', lost: 'danger' }[s] || 'info');

const loadList = async () => {
    loading.value = true;
    try {
        const res = await getDemoBookingList({ status: filterStatus.value || undefined });
        if (res.data.success) list.value = res.data.data.data || [];
    } catch { list.value = []; }
    finally { loading.value = false; }
};

const loadStats = async () => {
    try {
        const res = await getDemoBookingStats();
        if (res.data.success) stats.value = res.data.data;
    } catch { /* ignore */ }
};

const loadCalendly = async () => {
    try {
        const res = await getCalendlyLink();
        if (res.data.success) calendly.value = res.data.data;
    } catch { /* ignore */ }
};

const handleUpdateStatus = async (id, status) => {
    try {
        const res = await updateDemoBookingStatus(id, status);
        if (res.data.success) ElMessage.success('状态已更新');
    } catch { ElMessage.error('更新失败'); }
};

const copyText = async (text) => {
    try {
        await navigator.clipboard.writeText(text);
        ElMessage.success('已复制');
    } catch { /* ignore */ }
};

onMounted(() => { loadList(); loadStats(); loadCalendly(); });
</script>

<style scoped>
.page-header { margin-bottom: 16px; }
.page-header h2 { margin: 0; }
.text-muted { color: #909399; font-size: 13px; margin: 4px 0 0 0; }
.mb-4 { margin-bottom: 16px; }
.mt-2 { margin-top: 8px; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.card-actions { display: flex; gap: 8px; }
.stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.stat-item { text-align: center; padding: 8px; }
.stat-value { font-size: 24px; font-weight: 700; color: #409eff; }
.stat-label { font-size: 12px; color: #909399; margin-top: 4px; }
</style>
