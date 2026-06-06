<template>
    <div class="staging-page">
        <div class="page-header">
            <div class="header-left">
                <h2>Staging 集成测试环境</h2>
                <span class="header-subtitle">与生产数据完全隔离，独立的测试沙箱环境</span>
            </div>
            <div class="header-right">
                <el-button v-if="stagingActive" type="danger" :loading="resetting" @click="handleReset">
                    <el-icon><Refresh /></el-icon> 重置环境
                </el-button>
                <el-button v-else type="primary" :loading="creating" @click="handleCreate">
                    <el-icon><CirclePlus /></el-icon> 申请 Staging 环境
                </el-button>
            </div>
        </div>

        <!-- 还没有 Staging 环境 -->
        <div v-if="!stagingActive && !loadingStatus" class="welcome-section">
            <el-result
                icon="success"
                title="Staging 集成测试环境"
                sub-title="客户集成测试专用沙箱环境，与生产数据完全隔离 + 可一键重置 + API 限速 + 独立域名"
            >
                <template #extra>
                    <el-button type="primary" size="large" :loading="creating" @click="handleCreate">
                        <el-icon><CirclePlus /></el-icon> 立即申请
                    </el-button>
                </template>
            </el-result>

            <el-row :gutter="24" class="features-row">
                <el-col :span="6" v-for="f in features" :key="f.title">
                    <el-card shadow="never" class="feature-card">
                        <el-icon :size="28" :color="f.color"><component :is="f.icon" /></el-icon>
                        <h4>{{ f.title }}</h4>
                        <p>{{ f.desc }}</p>
                    </el-card>
                </el-col>
            </el-row>
        </div>

        <!-- Staging 环境详情 -->
        <template v-if="stagingActive">
            <el-row :gutter="16" class="info-row">
                <el-col :span="6" v-for="stat in statsCards" :key="stat.label">
                    <el-card shadow="never" class="stat-card">
                        <div class="stat-value" :style="{ color: stat.color }">{{ stat.value }}</div>
                        <div class="stat-label">{{ stat.label }}</div>
                        <div v-if="stat.sub" class="stat-sub">{{ stat.sub }}</div>
                    </el-card>
                </el-col>
            </el-row>

            <!-- 环境信息 -->
            <el-card shadow="never" class="detail-card">
                <template #header>
                    <span>环境详情</span>
                    <el-tag
                        :type="envInfo.status === 'active' ? 'success' : 'danger'"
                        size="small"
                        effect="plain"
                        style="margin-left: 12px;"
                    >{{ envInfo.status === 'active' ? '活跃' : envInfo.status }}</el-tag>
                </template>
                <el-descriptions :column="2" border size="small">
                    <el-descriptions-item label="环境名称">
                        <template #default>
                            <el-input v-model="editForm.name" size="small" class="inline-input" @blur="handleUpdate" />
                        </template>
                    </el-descriptions-item>
                    <el-descriptions-item label="独立子域名">
                        <code>{{ envInfo.subdomain }}.staging.huwutong.com</code>
                        <el-button text size="small" type="primary" @click="copyText(envInfo.subdomain + '.staging.huwutong.com')">
                            <el-icon><CopyDocument /></el-icon>
                        </el-button>
                    </el-descriptions-item>
                    <el-descriptions-item label="API Base URL">
                        <code>{{ envInfo.api_base_url }}</code>
                        <el-button text size="small" type="primary" @click="copyText(envInfo.api_base_url)">
                            <el-icon><CopyDocument /></el-icon>
                        </el-button>
                    </el-descriptions-item>
                    <el-descriptions-item label="API 限速">
                        <el-input-number
                            v-model="editForm.rate_limit"
                            :min="30"
                            :max="600"
                            size="small"
                            style="width: 120px;"
                            @change="handleUpdate"
                        /> / min
                    </el-descriptions-item>
                    <el-descriptions-item label="创建时间">{{ envInfo.created_at }}</el-descriptions-item>
                    <el-descriptions-item label="过期时间">{{ envInfo.expires_at || '永久' }}</el-descriptions-item>
                    <el-descriptions-item label="上次重置">{{ envInfo.last_reset_at || '尚未重置' }}</el-descriptions-item>
                    <el-descriptions-item label="数据隔离">
                        <el-tag type="success" size="small">完全隔离</el-tag>
                    </el-descriptions-item>
                </el-descriptions>
            </el-card>

            <!-- License 列表 -->
            <el-card shadow="never" class="table-card">
                <template #header>
                    <div class="card-header">
                        <span>Staging License（{{ envInfo.licenses_total || 0 }} / {{ envInfo.license_limit }}）</span>
                    </div>
                </template>
                <el-table :data="stagingLicenses" v-loading="loadingLicenses" stripe>
                    <el-table-column label="License Key" min-width="240" prop="license_key">
                        <template #default="{ row }">
                            <code class="license-key">{{ row.license_key }}</code>
                            <el-button text size="small" type="primary" @click="copyText(row.license_key)">
                                <el-icon><CopyDocument /></el-icon>
                            </el-button>
                        </template>
                    </el-table-column>
                    <el-table-column label="产品" width="150" prop="product_name" />
                    <el-table-column label="状态" width="90" prop="status">
                        <template #default="{ row }">
                            <el-tag :type="row.status === 'active' ? 'success' : 'danger'" size="small">
                                {{ row.status === 'active' ? '活跃' : row.status }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="设备" width="120" align="center">
                        <template #default="{ row }">
                            {{ row.device_count }} / {{ row.max_devices }}
                        </template>
                    </el-table-column>
                    <el-table-column label="过期" width="170" prop="expires_at">
                        <template #default="{ row }">{{ formatDate(row.expires_at) || '永久' }}</template>
                    </el-table-column>
                </el-table>
            </el-card>

            <!-- 快速集成 -->
            <el-card shadow="never" class="quickstart-card">
                <template #header><span>快速集成</span></template>
                <el-alert title="使用 Staging 环境的独立域名和 License 进行集成测试" type="info" show-icon :closable="false" />
                <div class="quickstart-actions mt-3">
                    <el-descriptions :column="2" border size="small">
                        <el-descriptions-item label="API 地址">{{ envInfo.api_base_url }}</el-descriptions-item>
                        <el-descriptions-item label="限速策略">{{ envInfo.rate_limit }}</el-descriptions-item>
                        <el-descriptions-item label="示例 License">
                            <code>{{ firstKey }}</code>
                            <el-button text size="small" type="primary" @click="copyText(firstKey)">复制</el-button>
                        </el-descriptions-item>
                        <el-descriptions-item label="集成向导">
                            <el-button type="primary" size="small" @click="$router.push('/wizard')">
                                使用集成向导
                            </el-button>
                        </el-descriptions-item>
                    </el-descriptions>
                </div>
            </el-card>
        </template>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { CirclePlus, Refresh, CopyDocument, Key, Connection, Monitor, Switch } from '@element-plus/icons-vue';
import stagingApi from '@/api/staging';

const creating = ref(false);
const resetting = ref(false);
const loadingStatus = ref(true);
const loadingLicenses = ref(false);
const stagingActive = ref(false);
const envInfo = reactive({});
const stagingLicenses = ref([]);
const editForm = reactive({ name: '', rate_limit: 120 });

const features = [
    { title: '独立子域名', desc: '每个 Staging 环境拥有独立的子域名，与生产环境隔离', icon: Monitor, color: '#409EFF' },
    { title: '10 个测试 License', desc: '多达 10 个 License，每 License 绑定 5 台设备，覆盖各种测试场景', icon: Key, color: '#67C23A' },
    { title: 'API 限速 120/min', desc: '更高限速满足集成测试需求，可自定义调整', icon: Connection, color: '#E6A23C' },
    { title: '一键重置', desc: '随时清除设备绑定、激活记录，恢复全新状态', icon: Refresh, color: '#F56C6C' },
    { title: '数据完全隔离', desc: 'Staging 数据与生产库独立，不产生任何影响', icon: Switch, color: '#909399' },
    { title: '1 年有效期', desc: '长达一年的测试周期，续期可申请延长', icon: Key, color: '#409EFF' },
];

const statsCards = computed(() => [
    {
        label: 'License 总数',
        value: envInfo.licenses_total || 0,
        color: '#409EFF',
        sub: `上限 ${envInfo.license_limit}`,
    },
    {
        label: '活跃 License',
        value: envInfo.licenses_active || 0,
        color: '#67C23A',
        sub: '可正常使用',
    },
    {
        label: '已绑定设备',
        value: envInfo.devices_bound || 0,
        color: '#E6A23C',
        sub: '每 License 可用 5 台',
    },
    {
        label: '状态',
        value: envInfo.status === 'active' ? '运行中' : '已暂停',
        color: envInfo.status === 'active' ? '#67C23A' : '#F56C6C',
        sub: envInfo.api_base_url || '',
    },
]);

const firstKey = computed(() => stagingLicenses.value[0]?.license_key || 'STAGING-1-XXXXXXXX');

function formatDate(d) {
    if (!d) return null;
    return new Date(d).toLocaleString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit' });
}

async function loadStatus() {
    loadingStatus.value = true;
    try {
        const { data: res } = await stagingApi.index();
        if (res.success && res.data) {
            Object.assign(envInfo, res.data);
            editForm.name = res.data.name || '';
            editForm.rate_limit = res.data.rate_limit ? parseInt(res.data.rate_limit) : 120;
            stagingActive.value = true;
            await loadLicenses();
        } else {
            stagingActive.value = false;
        }
    } catch {
        stagingActive.value = false;
    } finally {
        loadingStatus.value = false;
    }
}

async function loadLicenses() {
    if (!envInfo.id) return;
    loadingLicenses.value = true;
    try {
        const { data: res } = await stagingApi.licenses(envInfo.id);
        if (res.success) stagingLicenses.value = res.data || [];
    } catch {
        stagingLicenses.value = [];
    } finally {
        loadingLicenses.value = false;
    }
}

async function handleCreate() {
    creating.value = true;
    try {
        const { data: res } = await stagingApi.create();
        if (res.success) {
            ElMessage.success(res.message || 'Staging 环境创建成功！');
            Object.assign(envInfo, res.data);
            editForm.name = res.data.name || '';
            editForm.rate_limit = res.data.rate_limit ? parseInt(res.data.rate_limit) : 120;
            stagingActive.value = true;
            await loadLicenses();
        }
    } catch {
        ElMessage.error('申请 Staging 环境失败');
    } finally {
        creating.value = false;
    }
}

async function handleReset() {
    if (!envInfo.id) return;
    try {
        await ElMessageBox.confirm(
            '重置将清除 Staging 环境下的所有设备绑定和激活记录，License 将恢复初始状态。确定继续？',
            '确认重置',
            { confirmButtonText: '确定重置', cancelButtonText: '取消', type: 'warning' }
        );
        resetting.value = true;
        const { data: res } = await stagingApi.reset(envInfo.id);
        if (res.success) {
            ElMessage.success(res.message || 'Staging 环境已重置');
            Object.assign(envInfo, res.data);
            await loadLicenses();
        }
    } catch (e) {
        if (e !== 'cancel') ElMessage.error('重置失败');
    } finally {
        resetting.value = false;
    }
}

async function handleUpdate() {
    if (!envInfo.id) return;
    try {
        const { data: res } = await stagingApi.update(envInfo.id, {
            name: editForm.name,
            rate_limit: editForm.rate_limit,
        });
        if (res.success) {
            Object.assign(envInfo, res.data);
        }
    } catch {
        ElMessage.error('更新失败');
    }
}

function copyText(text) {
    navigator.clipboard.writeText(text).then(() => ElMessage.success('已复制'));
}

onMounted(() => loadStatus());
</script>

<style scoped>
.staging-page { padding: 20px; }

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}
.header-left h2 { margin: 0; font-size: 20px; }
.header-subtitle {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-left: 12px;
}

.welcome-section { margin-bottom: 32px; }
.features-row { margin-top: 32px; }
.feature-card {
    text-align: center;
    padding: 24px 8px;
    margin-bottom: 16px;
}
.feature-card h4 { margin: 10px 0 6px; font-size: 14px; }
.feature-card p { margin: 0; font-size: 12px; color: var(--el-text-color-secondary); }

.info-row { margin-bottom: 16px; }
.stat-card { text-align: center; }
.stat-value { font-size: 26px; font-weight: 700; }
.stat-label { font-size: 13px; color: var(--el-text-color-secondary); margin-top: 4px; }
.stat-sub { font-size: 11px; color: var(--el-text-color-secondary); margin-top: 2px; }

.detail-card,
.table-card,
.quickstart-card {
    margin-bottom: 16px;
}
.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.license-key {
    font-family: 'SF Mono', 'Fira Code', monospace;
    font-size: 13px;
    font-weight: 600;
    letter-spacing: 0.5px;
}
.inline-input { width: 200px; }
.mt-3 { margin-top: 12px; }
code {
    font-family: 'SF Mono', 'Fira Code', monospace;
    font-size: 12px;
}
:deep(.el-card__body) { padding: 16px; }
</style>
