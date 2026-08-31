<template>
    <div class="blockchain-page">
        <div class="page-header">
            <h2>
                <el-icon style="vertical-align:middle;margin-right:8px"><Connection /></el-icon>
                {{ t('blockchain_license_page.title') }}
            </h2>
            <div class="header-actions">
                <el-button @click="loadDashboard" :loading="loading">
                    <el-icon><Refresh /></el-icon> {{ t('blockchain_license_page.refresh') }}
                </el-button>
            </div>
        </div>

        <el-alert :title="t('blockchain_license_page.info_alert')" type="info" show-icon :closable="false" class="mb-4" />

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6" v-for="item in statCards" :key="item.key">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value" :style="item.style">{{ stats[item.key] }}</div>
                    <div class="stat-label">{{ item.label }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 绑定列表 -->
        <el-card shadow="never">
            <template #header><span>{{ t('blockchain_license_page.list_title') }}</span></template>
            <el-table :data="licenses" v-loading="loading" stripe>
                <el-table-column prop="id" label="#" width="60" />
                <el-table-column :label="t('licenses_page.license_key')" min-width="200">
                    <template #default="{ row }">
                        <code>{{ row.license?.key || '-' }}</code>
                    </template>
                </el-table-column>
                <el-table-column :label="t('blockchain_license_page.col_wallet_address')" min-width="200">
                    <template #default="{ row }">
                        <el-tag type="warning" effect="plain" size="small">
                            <code>{{ row.owner_address || '-' }}</code>
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('blockchain_license_page.col_contract_address')" min-width="200">
                    <template #default="{ row }">
                        <code>{{ row.contract_address || '-' }}</code>
                    </template>
                </el-table-column>
                <el-table-column :label="t('blockchain_license_page.col_token_id')" width="120">
                    <template #default="{ row }">
                        {{ row.token_id || '-' }}
                    </template>
                </el-table-column>
                <el-table-column :label="t('blockchain_license_page.col_chain')" width="100">
                    <template #default="{ row }">
                        <el-tag size="small">{{ row.chain || '-' }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" :label="t('blockchain_license_page.col_bound_at')" width="160" />
            </el-table>
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { Refresh, Connection } from '@element-plus/icons-vue';
import api from '@/api/blockchainLicense';

const { t } = useI18n();

const statCardMeta = [
    { key: 'total', labelKey: 'blockchain_license_page.stats.total' },
    { key: 'active', labelKey: 'blockchain_license_page.stats.active', style: 'color:#67c23a' },
    { key: 'wallets', labelKey: 'blockchain_license_page.stats.wallets', style: 'color:#0f172a' },
    { key: 'nfts', labelKey: 'blockchain_license_page.stats.nfts', style: 'color:#e6a23c' },
];

const statCards = computed(() => statCardMeta.map((m) => ({
    key: m.key,
    label: t(m.labelKey),
    style: m.style || '',
})));

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
