<template>
    <div class="blockchain-page">
        <div class="page-header">
            <h2>
                <el-icon style="vertical-align:middle;margin-right:8px"><Connection /></el-icon>
                区块链 / NFT License
            </h2>
            <div class="header-actions">
                <el-button @click="loadDashboard" :loading="loading">
                    <el-icon><Refresh /></el-icon> 刷新
                </el-button>
            </div>
        </div>

        <el-alert title="M3-14 区块链 License / NFT License / Web3 钱包授权 — 支持将 License 绑定到 NFT，验证钱包签名确权" type="info" show-icon :closable="false" class="mb-4" />

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.total }}</div>
                    <div class="stat-label">绑定总数</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value" style="color:#67c23a">{{ stats.active }}</div>
                    <div class="stat-label">活跃中</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value" style="color:#409eff">{{ stats.wallets }}</div>
                    <div class="stat-label">关联钱包</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value" style="color:#e6a23c">{{ stats.nfts }}</div>
                    <div class="stat-label">NFT 绑定</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 绑定列表 -->
        <el-card shadow="never">
            <template #header><span>区块链 License 列表</span></template>
            <el-table :data="licenses" v-loading="loading" stripe>
                <el-table-column prop="id" label="#" width="60" />
                <el-table-column label="License Key" min-width="200">
                    <template #default="{ row }">
                        <code>{{ row.license?.key || '-' }}</code>
                    </template>
                </el-table-column>
                <el-table-column label="钱包地址" min-width="200">
                    <template #default="{ row }">
                        <el-tag type="warning" effect="plain" size="small">
                            <code>{{ row.owner_address || '-' }}</code>
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="合约地址" min-width="200">
                    <template #default="{ row }">
                        <code>{{ row.contract_address || '-' }}</code>
                    </template>
                </el-table-column>
                <el-table-column label="Token ID" width="120">
                    <template #default="{ row }">
                        {{ row.token_id || '-' }}
                    </template>
                </el-table-column>
                <el-table-column label="区块链" width="100">
                    <template #default="{ row }">
                        <el-tag size="small">{{ row.chain || '-' }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" label="绑定时间" width="160" />
            </el-table>
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { Refresh, Connection } from '@element-plus/icons-vue';
import api from '@/api/blockchainLicense';

const loading = ref(false);
const licenses = ref([]);
const stats = reactive({ total: 0, active: 0, wallets: 0, nfts: 0 });

async function loadDashboard() {
    loading.value = true;
    try {
        const [dashboardRes, listRes] = await Promise.all([
            api.dashboard(),
            api.list({ per_page: 50 }),
        ]);
        const d = dashboardRes.data?.data || {};
        stats.total = d.total || 0;
        stats.active = d.active || 0;
        stats.wallets = d.wallets || 0;
        stats.nfts = d.nfts || 0;
        licenses.value = listRes.data?.data?.data || [];
    } catch {
        licenses.value = [];
    } finally {
        loading.value = false;
    }
}

onMounted(loadDashboard);
</script>

<style scoped>
.blockchain-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; font-size: 20px; }
.header-actions { display: flex; gap: 8px; }
.mb-4 { margin-bottom: 16px; }
.stat-card { text-align: center; cursor: default; }
.stat-value { font-size: 26px; font-weight: 700; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
</style>
