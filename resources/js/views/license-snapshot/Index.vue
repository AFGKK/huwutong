<template>
    <div class="snapshot-container">
        <el-page-header :content="'License 快照与回滚'" @back="$router.push('/admin/dashboard')" />

        <el-alert title="License 每次变更前自动创建快照，支持查看历史快照和回滚到指定版本。快照保留 30 天。" type="info" show-icon :closable="false" class="alert-info" />

        <!-- 统计 -->
        <el-row :gutter="20" class="stat-cards">
            <el-col :span="6"><el-card shadow="hover"><div class="stat-value">{{ dash.total }}</div><div class="stat-label">快照总计</div></el-card></el-col>
            <el-col :span="6"><el-card shadow="hover"><div class="stat-value text-primary">{{ dash.today }}</div><div class="stat-label">今日创建</div></el-card></el-col>
            <el-col :span="6"><el-card shadow="hover"><div class="stat-value text-warning">{{ dash.last_30d }}</div><div class="stat-label">近30天</div></el-card></el-col>
            <el-col :span="6"><el-card shadow="hover"><div class="stat-value">{{ actions }}</div><div class="stat-label">操作类型数</div></el-card></el-col>
        </el-row>

        <el-card>
            <template #header>
                <el-space>
                    <span>快照列表</span>
                    <el-select v-model="actionFilter" placeholder="操作类型" clearable size="small" style="width:140px" @change="loadList">
                        <el-option label="全部" value="" />
                        <el-option label="升级" value="upgrade" />
                        <el-option label="降级" value="downgrade" />
                        <el-option label="转移" value="transfer" />
                        <el-option label="改席位" value="seat_change" />
                        <el-option label="改类型" value="type_change" />
                        <el-option label="管理员编辑" value="admin_edit" />
                        <el-option label="回滚" value="rollback" />
                        <el-option label="手动" value="manual" />
                    </el-select>
                </el-space>
            </template>
            <el-table :data="snapshots" stripe v-loading="loading">
                <el-table-column prop="created_at" label="时间" width="160" />
                <el-table-column label="操作" width="120">
                    <template #default="{ row }">
                        <el-tag size="small">{{ actionLabel(row.action) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="License" width="180">
                    <template #default="{ row }">{{ row.license?.license_key || row.license_id }}</template>
                </el-table-column>
                <el-table-column label="状态变更" width="160">
                    <template #default="{ row }">{{ row.status_before || '-' }} → {{ row.status_after || '-' }}</template>
                </el-table-column>
                <el-table-column label="变更说明" min-width="200">
                    <template #default="{ row }">
                        <template v-if="row.diff?.note">{{ row.diff.note }}</template>
                        <template v-else-if="row.diff">
                            <el-tag v-for="(v, k) in row.diff" :key="k" size="small" style="margin-right:4px">{{ k }}: {{ v?.from }}→{{ v?.to }}</el-tag>
                        </template>
                        <span v-else>-</span>
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="120" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="viewDetail(row)">详情</el-button>
                        <el-popconfirm title="确认回滚到此快照？" @confirm="handleRollback(row)">
                            <template #reference><el-button size="small" type="warning">回滚</el-button></template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>
            <el-pagination v-if="total > perPage" v-model:current-page="page" :page-size="perPage" :total="total" layout="prev, pager, next" @current-change="loadList" class="pagination" />
        </el-card>

        <!-- 详情弹窗 -->
        <el-dialog v-model="detailVisible" title="快照详情" width="800px">
            <el-descriptions :column="2" border size="small" v-if="detail">
                <el-descriptions-item label="快照ID">{{ detail.id }}</el-descriptions-item>
                <el-descriptions-item label="操作">{{ actionLabel(detail.action) }}</el-descriptions-item>
                <el-descriptions-item label="License ID">{{ detail.license_id }}</el-descriptions-item>
                <el-descriptions-item label="创建时间">{{ detail.created_at }}</el-descriptions-item>
                <el-descriptions-item label="变更前状态">{{ detail.status_before || '-' }}</el-descriptions-item>
                <el-descriptions-item label="变更后状态">{{ detail.status_after || '-' }}</el-descriptions-item>
            </el-descriptions>
            <h4>快照数据</h4>
            <pre class="json-view">{{ JSON.stringify(detail?.license_data, null, 2) }}</pre>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import licenseSnapshot from '@/api/licenseSnapshot';

const loading = ref(false);
const snapshots = ref([]);
const detailVisible = ref(false);
const detail = ref(null);
const actionFilter = ref('');
const page = ref(1);
const perPage = ref(20);
const total = ref(0);
const dash = reactive({ total: 0, today: 0, last_30d: 0, by_action: {} });
const actions = ref(0);

function actionLabel(a) {
    const map = { upgrade: '升级', downgrade: '降级', transfer: '转移', seat_change: '改席位', type_change: '改类型', admin_edit: '编辑', rollback: '回滚', manual: '手动' };
    return map[a] || a;
}

async function loadDash() {
    try { const res = await licenseSnapshot.dashboard(); Object.assign(dash, res.data.data); actions.value = Object.keys(dash.by_action).length; } catch {}
}
async function loadList(p) {
    loading.value = true;
    try {
        const params = { page: p || page.value, per_page: perPage.value };
        if (actionFilter.value) params.action = actionFilter.value;
        const res = await licenseSnapshot.list(params);
        snapshots.value = res.data.data.items || [];
        total.value = res.data.data.total;
        page.value = res.data.data.page;
    } catch {} finally { loading.value = false; }
}
async function viewDetail(row) {
    try { const res = await licenseSnapshot.show(row.id); detail.value = res.data.data; detailVisible.value = true; } catch {}
}
async function handleRollback(row) {
    try { await licenseSnapshot.rollback(row.id); ElMessage.success('已回滚'); loadList(); } catch {}
}

onMounted(() => { loadDash(); loadList(); });
</script>

<style scoped>
.snapshot-container { padding: 20px; }
.alert-info { margin: 16px 0; }
.stat-cards { margin-bottom: 16px; }
.stat-cards .el-card { text-align: center; }
.stat-value { font-size: 28px; font-weight: bold; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.text-primary { color: #409eff; }
.text-warning { color: #e6a23c; }
.pagination { margin-top: 16px; text-align: center; }
.json-view { background: #f5f7fa; padding: 12px; border-radius: 4px; max-height: 400px; overflow: auto; font-size: 12px; }
</style>
