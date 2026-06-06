<template>
    <div class="sandbox-page">
        <div class="page-header">
            <div class="header-left">
                <h2>开发者免费 Sandbox</h2>
                <span class="header-subtitle">零成本测试环境，永久免费</span>
            </div>
            <div class="header-right">
                <el-button v-if="sandboxActive" type="danger" :loading="resetting" @click="handleReset">
                    <el-icon><Refresh /></el-icon> 重置沙箱
                </el-button>
                <el-button v-else type="primary" :loading="creating" @click="handleCreate">
                    <el-icon><CirclePlus /></el-icon> 创建沙箱环境
                </el-button>
            </div>
        </div>

        <!-- 未创建沙箱 -->
        <div v-if="!sandboxActive && !loadingStatus" class="welcome-section">
            <el-result icon="success" title="开发者免费 Sandbox" sub-title="注册即送永久免费开发环境，零成本测试集成">
                <template #extra>
                    <el-button type="primary" size="large" :loading="creating" @click="handleCreate">
                        <el-icon><CirclePlus /></el-icon> 立即创建沙箱
                    </el-button>
                </template>
            </el-result>

            <el-row :gutter="24" class="features-row">
                <el-col :span="8" v-for="feature in features" :key="feature.title">
                    <el-card shadow="never" class="feature-card">
                        <el-icon :size="32" :color="feature.color">
                            <component :is="feature.icon" />
                        </el-icon>
                        <h4>{{ feature.title }}</h4>
                        <p>{{ feature.desc }}</p>
                    </el-card>
                </el-col>
            </el-row>
        </div>

        <!-- 沙箱状态 -->
        <template v-if="sandboxActive">
            <el-row :gutter="16" class="info-row">
                <el-col :span="6" v-for="stat in sandboxStats" :key="stat.label">
                    <el-card shadow="never" class="stat-card" :body-style="{ padding: '16px' }">
                        <div class="stat-value" :style="{ color: stat.color }">{{ stat.value }}</div>
                        <div class="stat-label">{{ stat.label }}</div>
                        <div v-if="stat.sub" class="stat-sub">{{ stat.sub }}</div>
                    </el-card>
                </el-col>
            </el-row>

            <!-- Sandbox License 列表 -->
            <el-card shadow="never" class="table-card">
                <template #header>
                    <div class="card-header">
                        <span>Sandbox License</span>
                        <el-tag type="success" effect="plain" size="small">
                            限速 {{ sandboxInfo?.rate_limit || '60/min' }}
                        </el-tag>
                    </div>
                </template>
                <el-table :data="sandboxLicenses" v-loading="loadingLicenses" stripe>
                    <el-table-column label="License Key" min-width="220" prop="license_key">
                        <template #default="{ row }">
                            <code class="license-key">{{ row.license_key }}</code>
                            <el-button text size="small" type="primary" @click="copyKey(row.license_key)">
                                <el-icon><CopyDocument /></el-icon>
                            </el-button>
                        </template>
                    </el-table-column>
                    <el-table-column label="产品" width="150" prop="product_name" />
                    <el-table-column label="类型" width="100" prop="type">
                        <template #default="{ row }">
                            <el-tag size="small" effect="plain" type="warning">Sandbox</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="状态" width="100" prop="status">
                        <template #default="{ row }">
                            <el-tag :type="row.status === 'active' ? 'success' : 'danger'" size="small">
                                {{ row.status === 'active' ? '活跃' : row.status }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="设备绑定" width="100" prop="device_count" align="center" />
                    <el-table-column label="过期时间" width="170" prop="expires_at">
                        <template #default="{ row }">
                            {{ formatDate(row.expires_at) || '永久' }}
                        </template>
                    </el-table-column>
                </el-table>
            </el-card>

            <!-- 快速集成 -->
            <el-card shadow="never" class="quickstart-card">
                <template #header>
                    <span>快速集成</span>
                </template>
                <el-alert title="使用下方 License Key 和 API 地址即可开始集成测试" type="info" show-icon :closable="false" />
                <div class="quickstart-content">
                    <el-descriptions :column="2" border size="small" class="mt-3">
                        <el-descriptions-item label="API 地址">
                            <code>{{ apiBaseUrl }}</code>
                            <el-button text size="small" type="primary" @click="copyText(apiBaseUrl)">复制</el-button>
                        </el-descriptions-item>
                        <el-descriptions-item label="限速策略">
                            {{ sandboxInfo?.rate_limit || '60 次/分钟' }}
                        </el-descriptions-item>
                        <el-descriptions-item label="示例 License">
                            <code>{{ firstLicenseKey }}</code>
                            <el-button text size="small" type="primary" @click="copyText(firstLicenseKey)">复制</el-button>
                        </el-descriptions-item>
                        <el-descriptions-item label="数据隔离">
                            与生产环境完全隔离
                        </el-descriptions-item>
                    </el-descriptions>
                    <el-button type="primary" @click="$router.push('/wizard')" class="mt-3">
                        <el-icon><MagicStick /></el-icon> 使用集成向导
                    </el-button>
                </div>
            </el-card>
        </template>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { CirclePlus, Refresh, CopyDocument, Key, Connection, Monitor, WarnTriangleFilled } from '@element-plus/icons-vue';
import sandboxApi from '@/api/sandbox';

const creating = ref(false);
const resetting = ref(false);
const loadingStatus = ref(true);
const loadingLicenses = ref(false);
const sandboxActive = ref(false);
const sandboxInfo = reactive({});
const sandboxLicenses = ref([]);

const features = [
    { title: '1 个沙箱产品', desc: '包含完整功能模块：激活/验证/设备绑定/离线验证', icon: Key, color: '#409EFF' },
    { title: '5 个测试 License', desc: '每个 License 最多绑定 3 台设备，永久有效', icon: Connection, color: '#67C23A' },
    { title: 'API 限速 60/min', desc: '满足开发测试需求，防止滥用', icon: Monitor, color: '#E6A23C' },
    { title: '完全数据隔离', desc: '沙箱数据与生产环境完全隔离，一键重置', icon: WarnTriangleFilled, color: '#F56C6C' },
    { title: '永久免费', desc: '注册即开通，无需付费，无过期时间', icon: Key, color: '#909399' },
    { title: '一键重置', desc: '随时清除设备绑定和激活记录，重新测试', icon: Refresh, color: '#409EFF' },
];

const apiBaseUrl = window.location.origin || 'https://api.huwutong.com';

const sandboxStats = computed(() => {
    return [
        { label: 'License 总数', value: sandboxInfo.licenses_created || 0, color: '#409EFF' },
        { label: '活跃 License', value: sandboxInfo.licenses_active || 0, color: '#67C23A', sub: '可正常使用' },
        { label: '已绑定设备', value: sandboxInfo.devices_bound || 0, color: '#E6A23C', sub: '最多 15 台(5x3)' },
        { label: '可用额度', value: sandboxInfo.remaining_licenses || 0, color: '#F56C6C', sub: '还可创建的 License' },
    ];
});

const firstLicenseKey = computed(() => {
    return sandboxLicenses.value[0]?.license_key || 'SANDBOX-1-XXXXXXXX';
});

function formatDate(dateStr) {
    if (!dateStr) return null;
    return new Date(dateStr).toLocaleString('zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit',
    });
}

async function loadStatus() {
    loadingStatus.value = true;
    try {
        const { data: res } = await sandboxApi.status();
        if (res.success) {
            Object.assign(sandboxInfo, res.data);
            sandboxActive.value = res.data.is_sandbox;
            if (res.data.is_sandbox) {
                await loadLicenses();
            }
        } else {
            sandboxActive.value = false;
        }
    } catch {
        sandboxActive.value = false;
    } finally {
        loadingStatus.value = false;
    }
}

async function loadLicenses() {
    loadingLicenses.value = true;
    try {
        const { data: res } = await sandboxApi.licenses();
        if (res.success) {
            sandboxLicenses.value = res.data || [];
        }
    } catch {
        sandboxLicenses.value = [];
    } finally {
        loadingLicenses.value = false;
    }
}

async function handleCreate() {
    creating.value = true;
    try {
        const { data: res } = await sandboxApi.create();
        if (res.success) {
            ElMessage.success(res.data?.message || '沙箱环境创建成功！');
            Object.assign(sandboxInfo, res.data?.sandbox_info || {});
            sandboxActive.value = true;
            await loadLicenses();
        }
    } catch {
        ElMessage.error('创建沙箱环境失败');
    } finally {
        creating.value = false;
    }
}

async function handleReset() {
    try {
        await ElMessageBox.confirm(
            '重置沙箱将清除所有设备绑定和激活记录，License 将恢复为初始状态。确定继续？',
            '确认重置',
            { confirmButtonText: '确定重置', cancelButtonText: '取消', type: 'warning' }
        );
        resetting.value = true;
        const { data: res } = await sandboxApi.reset();
        if (res.success) {
            ElMessage.success(res.message || '沙箱已重置');
            await loadLicenses();
        }
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error('重置失败');
        }
    } finally {
        resetting.value = false;
    }
}

function copyKey(key) {
    copyText(key);
}

function copyText(text) {
    navigator.clipboard.writeText(text).then(() => {
        ElMessage.success('已复制');
    });
}

onMounted(() => {
    loadStatus();
});
</script>

<style scoped>
.sandbox-page { padding: 20px; }

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

/* 欢迎区域 */
.welcome-section { margin-bottom: 32px; }
.features-row { margin-top: 32px; }
.feature-card {
    text-align: center;
    padding: 24px 16px;
    margin-bottom: 16px;
}
.feature-card h4 { margin: 12px 0 6px; font-size: 15px; }
.feature-card p { margin: 0; font-size: 13px; color: var(--el-text-color-secondary); }

/* 统计 */
.info-row { margin-bottom: 16px; }
.stat-card { text-align: center; }
.stat-value { font-size: 28px; font-weight: 700; line-height: 1.2; }
.stat-label { font-size: 13px; color: var(--el-text-color-secondary); margin-top: 4px; }
.stat-sub { font-size: 11px; color: var(--el-text-color-secondary); margin-top: 2px; }

/* 表格 */
.table-card { margin-bottom: 16px; }
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

/* 快速集成 */
.quickstart-card { margin-bottom: 16px; }
.quickstart-content { }
.mt-3 { margin-top: 12px; }
code {
    font-family: 'SF Mono', 'Fira Code', monospace;
    font-size: 12px;
}

:deep(.el-card__body) { padding: 16px; }
</style>
