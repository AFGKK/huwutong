<template>
    <div class="portal-licenses">
        <div class="page-header">
            <h2>我的 License</h2>
            <div class="header-actions">
                <el-input
                    v-model="searchKey"
                    placeholder="搜索 License Key..."
                    clearable
                    style="width: 240px"
                    :prefix-icon="Search"
                    @clear="fetchLicenses"
                    @keyup.enter="fetchLicenses"
                />
                <el-button @click="fetchLicenses" :icon="Refresh">刷新</el-button>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="8">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value">{{ stats.total }}</div>
                        <div class="mini-label">全部</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value" style="color: #67c23a">{{ stats.active }}</div>
                        <div class="mini-label">活跃中</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value" style="color: #f56c6c">{{ stats.expired }}</div>
                        <div class="mini-label">已过期</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- License 列表 -->
        <el-card shadow="never">
            <el-table :data="licenses" v-loading="loading" stripe>
                <el-table-column label="License Key" min-width="200">
                    <template #default="{ row }">
                        <el-link type="primary" :underline="false" @click="$router.push(`/portal/licenses/${row.id}`)">
                            <code>{{ row.license_key }}</code>
                        </el-link>
                    </template>
                </el-table-column>
                <el-table-column prop="product?.name" label="产品" min-width="120">
                    <template #default="{ row }">{{ row.product?.name || '-' }}</template>
                </el-table-column>
                <el-table-column label="类型" width="100">
                    <template #default="{ row }">
                        <el-tag v-if="row.type === 'trial'" type="warning" size="small">试用</el-tag>
                        <el-tag v-else-if="row.type === 'enterprise'" type="success" size="small">企业版</el-tag>
                        <el-tag v-else-if="row.type === 'development'" size="small">开发版</el-tag>
                        <span v-else>标准</span>
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="90">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)" size="small" effect="dark">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="seats" label="座位数" width="70" align="center" />
                <el-table-column prop="max_devices" label="设备限制" width="80" align="center" />
                <el-table-column prop="expires_at" label="到期时间" width="160">
                    <template #default="{ row }">
                        <span v-if="row.expires_at" :class="{ 'expiring-text': isExpiring(row.expires_at) }">
                            {{ row.expires_at }}
                        </span>
                        <span v-else>永久</span>
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" label="创建时间" width="160" />
                <el-table-column label="操作" width="100" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link size="small" @click="$router.push(`/portal/licenses/${row.id}`)">
                            详情
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>

            <!-- 分页 -->
            <div class="pagination-wrap">
                <el-pagination
                    v-model:current-page="page"
                    v-model:page-size="perPage"
                    :total="total"
                    :page-sizes="[10, 20, 50]"
                    layout="total, sizes, prev, pager, next"
                    @current-change="fetchLicenses"
                    @size-change="fetchLicenses"
                />
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import licenseApi from '@/api/license';
import { ElMessage } from 'element-plus';
import { Search, Refresh } from '@element-plus/icons-vue';

const loading = ref(false);
const licenses = ref([]);
const total = ref(0);
const page = ref(1);
const perPage = ref(10);
const searchKey = ref('');

const stats = reactive({
    total: 0,
    active: 0,
    expired: 0,
});

const STATUS_MAP = {
    pending: { type: 'info', label: '待激活' },
    active: { type: 'success', label: '活跃' },
    suspended: { type: 'warning', label: '已暂停' },
    frozen: { type: 'warning', label: '已冻结' },
    expired: { type: 'info', label: '已过期' },
    revoked: { type: 'danger', label: '已吊销' },
    refunded: { type: 'danger', label: '已退款' },
    blacklisted: { type: 'danger', label: '黑名单' },
};

function statusType(status) { return STATUS_MAP[status]?.type || 'info'; }
function statusLabel(status) { return STATUS_MAP[status]?.label || status; }
function isExpiring(dateStr) {
    if (!dateStr) return false;
    const diff = new Date(dateStr) - new Date();
    return diff / (1000 * 60 * 60 * 24) <= 30 && diff >= 0;
}

async function fetchLicenses() {
    loading.value = true;
    try {
        const params = {
            page: page.value,
            per_page: perPage.value,
            sort: '-created_at',
        };
        if (searchKey.value) {
            params.search = searchKey.value;
        }
        const { data: res } = await licenseApi.list(params);
        licenses.value = res.data?.data || [];
        total.value = res.data?.total || 0;

        // 获取统计数据
        const { data: statsRes } = await licenseApi.stats();
        const s = statsRes.data || {};
        stats.total = s.total || 0;
        stats.active = s.active || 0;
        stats.expired = s.expired || 0;
    } catch {
        ElMessage.error('获取 License 列表失败');
    } finally {
        loading.value = false;
    }
}

onMounted(fetchLicenses);
</script>

<style scoped>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.page-header h2 { margin: 0; }

.header-actions {
    display: flex;
    gap: 8px;
}

.mb-4 { margin-bottom: 16px; }

.mini-stat {
    text-align: center;
    padding: 8px 0;
}

.mini-value {
    font-size: 28px;
    font-weight: 700;
    color: #303133;
}

.mini-label {
    font-size: 14px;
    color: #909399;
    margin-top: 4px;
}

.pagination-wrap {
    display: flex;
    justify-content: flex-end;
    padding: 16px 0 0;
}

.expiring-text { color: #e6a23c; font-weight: 500; }
</style>
