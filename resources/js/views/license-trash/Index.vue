<template>
    <div class="license-trash-container">
        <el-page-header :content="'License 回收站'" @back="$router.push('/admin/dashboard')" />

        <el-alert title="回收站中的 License 可在 30 天内恢复，超时将自动永久删除。支持批量恢复和手动清空。" type="warning" show-icon :closable="false" class="alert-info" />

        <!-- 统计 -->
        <el-row :gutter="20" class="stat-cards">
            <el-col :span="6"><el-card shadow="hover"><div class="stat-value">{{ stats.total }}</div><div class="stat-label">回收站总计</div></el-card></el-col>
            <el-col :span="6"><el-card shadow="hover"><div class="stat-value text-warning">{{ stats.today }}</div><div class="stat-label">今日删除</div></el-card></el-col>
            <el-col :span="6"><el-card shadow="hover"><div class="stat-value text-primary">{{ stats.last_7d }}</div><div class="stat-label">近7天</div></el-card></el-col>
            <el-col :span="6"><el-card shadow="hover"><div class="stat-value text-danger">{{ stats.expiring_soon }}</div><div class="stat-label">即将到期清理</div></el-card></el-col>
        </el-row>

        <el-card>
            <template #header>
                <el-space>
                    <span>已删除 License 列表</span>
                    <el-button size="small" type="primary" @click="handleBatchRestore">批量恢复</el-button>
                    <el-popconfirm title="确认清空所有回收站记录？此操作不可恢复！" @confirm="handleClear">
                        <template #reference><el-button size="small" type="danger">清空回收站</el-button></template>
                    </el-popconfirm>
                </el-space>
            </template>
            <el-table :data="trashed" stripe v-loading="loading" @selection-change="onSelectionChange">
                <el-table-column type="selection" width="40" />
                <el-table-column prop="license_key" label="License Key" width="200" />
                <el-table-column prop="product.name" label="产品" width="120" />
                <el-table-column prop="customer.name" label="客户" width="120" />
                <el-table-column prop="status" label="删除前状态" width="100">
                    <template #default="{ row }"><el-tag size="small">{{ row.status }}</el-tag></template>
                </el-table-column>
                <el-table-column prop="deleted_at" label="删除时间" width="160" />
                <el-table-column prop="deleted_by" label="删除人ID" width="90" />
                <el-table-column label="操作" width="160" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" type="primary" @click="handleRestore(row)">恢复</el-button>
                        <el-popconfirm title="确认永久删除？" @confirm="handleForceDelete(row)">
                            <template #reference><el-button size="small" type="danger">永久删除</el-button></template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>
            <el-pagination v-if="total > perPage" v-model:current-page="page" :page-size="perPage" :total="total" layout="prev, pager, next" @current-change="loadList" class="pagination" />
            <el-empty v-if="!loading && trashed.length === 0" description="回收站为空" :image-size="80" />
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import licenseTrash from '@/api/licenseTrash';

const loading = ref(false);
const trashed = ref([]);
const selected = ref([]);
const page = ref(1);
const perPage = ref(20);
const total = ref(0);
const stats = reactive({ total: 0, today: 0, last_7d: 0, last_30d: 0, expiring_soon: 0 });

async function loadStats() {
    try { const res = await licenseTrash.stats(); Object.assign(stats, res.data.data); } catch {}
}
async function loadList(p) {
    loading.value = true;
    try {
        const res = await licenseTrash.list({ page: p || page.value, per_page: perPage.value });
        trashed.value = res.data.data.items || [];
        total.value = res.data.data.total;
        page.value = res.data.data.page;
    } catch {} finally { loading.value = false; }
}
function onSelectionChange(val) { selected.value = val.map(v => v.id); }

async function handleRestore(row) {
    try { await licenseTrash.restore(row.id); ElMessage.success('已恢复'); loadList(); loadStats(); } catch {}
}
async function handleBatchRestore() {
    if (!selected.value.length) { ElMessage.warning('请先选择要恢复的 License'); return; }
    try { await licenseTrash.batchRestore(selected.value); ElMessage.success('批量恢复成功'); loadList(); loadStats(); selected.value = []; } catch {}
}
async function handleForceDelete(row) {
    try { await licenseTrash.forceDelete(row.id); ElMessage.success('已永久删除'); loadList(); loadStats(); } catch {}
}
async function handleClear() {
    try { await licenseTrash.clear(); ElMessage.success('回收站已清空'); loadList(); loadStats(); } catch {}
}

onMounted(() => { loadStats(); loadList(); });
</script>

<style scoped>
.license-trash-container { padding: 20px; }
.alert-info { margin: 16px 0; }
.stat-cards { margin-bottom: 16px; }
.stat-cards .el-card { text-align: center; }
.stat-value { font-size: 28px; font-weight: bold; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.text-warning { color: #e6a23c; }
.text-primary { color: #409eff; }
.text-danger { color: #f56c6c; }
.pagination { margin-top: 16px; text-align: center; }
</style>
