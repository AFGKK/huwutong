<template>
    <div class="tax-page">
        <div class="page-header">
            <div class="header-left">
                <h2>税务自动计算引擎</h2>
                <span class="header-subtitle">VAT / GST / Sales Tax / EU OSS/IOSS / 免税证书管理</span>
            </div>
        </div>

        <el-alert
            title="支持 50+ 国家税率自动计算，覆盖 EU VAT、亚太 GST、北美 Sales Tax，含 EU 反向征收/B2B 规则"
            type="success" show-icon :closable="false" class="alert-bar"
        />

        <el-tabs v-model="activeTab">
            <!-- 税率管理 -->
            <el-tab-pane label="税率管理" name="rates">
                <el-row :gutter="16" class="stats-row">
                    <el-col :span="6" v-for="s in statCards" :key="s.label">
                        <el-card shadow="never" class="stat-card">
                            <div class="stat-value" :style="{ color: s.color }">{{ s.value }}</div>
                            <div class="stat-label">{{ s.label }}</div>
                        </el-card>
                    </el-col>
                </el-row>

                <el-card shadow="never" class="filter-card">
                    <div class="filter-bar">
                        <el-input v-model="rateSearch" size="small" placeholder="搜索国家代码/名称" clearable style="width: 220px;" @clear="loadRates" @keyup.enter="loadRates" />
                        <el-select v-model="rateFilterCountry" size="small" placeholder="国家" filterable clearable style="width: 130px; margin-left: 8px;" @change="loadRates">
                            <el-option v-for="c in countryOptions" :key="c.country_code" :label="c.country_code + ' - ' + c.name" :value="c.country_code" />
                        </el-select>
                        <el-select v-model="rateFilterActive" size="small" style="width: 100px; margin-left: 8px;" @change="loadRates">
                            <el-option label="全部" value="" />
                            <el-option label="活跃" :value="true" />
                            <el-option label="停用" :value="false" />
                        </el-select>
                    </div>
                </el-card>

                <el-table :data="rateList" v-loading="loadingRates" stripe size="small" max-height="500">
                    <el-table-column label="国家" width="70" prop="country_code" align="center" />
                    <el-table-column label="州/省" width="70" prop="region_code" align="center" />
                    <el-table-column label="税种" width="100" prop="name" />
                    <el-table-column label="类型" width="80" prop="type" />
                    <el-table-column label="税率" width="90" align="right">
                        <template #default="{ row }">{{ row.rate_percent }}%</template>
                    </el-table-column>
                    <el-table-column label="EU" width="50" align="center">
                        <template #default="{ row }">
                            <el-tag v-if="row.is_eu" size="small" type="primary">EU</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="状态" width="70">
                        <template #default="{ row }">
                            <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                                {{ row.is_active ? '活跃' : '停用' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="生效" width="100" prop="effective_from" />
                    <el-table-column label="失效" width="100" prop="effective_until" />
                    <el-table-column label="描述" min-width="160" prop="description" />
                    <el-table-column label="操作" width="120" fixed="right">
                        <template #default="{ row }">
                            <el-button text size="small" type="primary" @click="openRateEdit(row)">编辑</el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <div class="pagination-wrap" v-if="ratePagination.total > ratePagination.per_page">
                    <el-pagination
                        v-model:current-page="ratePagination.current_page"
                        :page-size="ratePagination.per_page"
                        :total="ratePagination.total"
                        layout="prev, pager, next"
                        small
                        @current-change="loadRates"
                    />
                </div>
            </el-tab-pane>

            <!-- 税额计算器 -->
            <el-tab-pane label="税额计算器" name="calculator">
                <el-card shadow="never" class="calc-card">
                    <el-form label-position="top" size="small" :model="calcForm">
                        <el-row :gutter="16">
                            <el-col :span="8">
                                <el-form-item label="金额">
                                    <el-input-number v-model="calcForm.amount" :min="0" :precision="2" style="width: 100%;" />
                                </el-form-item>
                            </el-col>
                            <el-col :span="8">
                                <el-form-item label="国家">
                                    <el-select v-model="calcForm.country_code" filterable style="width: 100%;" @change="onCalcCountryChange">
                                        <el-option v-for="c in countryOptions" :key="c.country_code" :label="`${c.country_code} - ${c.name} (${c.rate_percent || (c.rate*100).toFixed(1)}%)`" :value="c.country_code" />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                            <el-col :span="8">
                                <el-form-item label="州/省">
                                    <el-select v-model="calcForm.region_code" filterable clearable style="width: 100%;" :disabled="regionOptions.length === 0">
                                        <el-option v-for="r in regionOptions" :key="r.region_code" :label="`${r.region_code} - ${(r.rate*100).toFixed(1)}%`" :value="r.region_code" />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                        </el-row>
                        <el-row :gutter="16">
                            <el-col :span="8">
                                <el-form-item label="B2B 交易">
                                    <el-switch v-model="calcForm.is_b2b" />
                                </el-form-item>
                            </el-col>
                            <el-col :span="8">
                                <el-form-item label="卖家所在国家">
                                    <el-input v-model="calcForm.seller_country" maxlength="2" placeholder="如 CN" />
                                </el-form-item>
                            </el-col>
                            <el-col :span="8" style="display: flex; align-items: flex-end;">
                                <el-button type="primary" :loading="calculating" @click="handleCalculate" style="margin-bottom: 2px;">
                                    计算税额
                                </el-button>
                            </el-col>
                        </el-row>
                    </el-form>

                    <!-- 计算结果 -->
                    <el-divider />
                    <div v-if="calcResult" class="calc-result">
                        <el-row :gutter="24">
                            <el-col :span="6">
                                <div class="result-item">
                                    <span class="result-label">应税金额</span>
                                    <span class="result-value">${{ calcResult.taxable_amount.toFixed(2) }}</span>
                                </div>
                            </el-col>
                            <el-col :span="6">
                                <div class="result-item">
                                    <span class="result-label">税种</span>
                                    <span class="result-value">{{ calcResult.type_label }}</span>
                                </div>
                            </el-col>
                            <el-col :span="6">
                                <div class="result-item highlight">
                                    <span class="result-label">税额</span>
                                    <span class="result-value">${{ calcResult.tax_amount.toFixed(2) }}</span>
                                </div>
                            </el-col>
                            <el-col :span="6">
                                <div class="result-item">
                                    <span class="result-label">含税总额</span>
                                    <span class="result-value">${{ calcResult.total.toFixed(2) }}</span>
                                </div>
                            </el-col>
                        </el-row>
                        <el-row :gutter="24" class="mt-2">
                            <el-col :span="6">
                                <span class="result-sub">税率: {{ calcResult.tax_percent }}%</span>
                            </el-col>
                            <el-col :span="6">
                                <el-tag v-if="calcResult.exempt_reason === 'exempt'" type="success" size="small">免税</el-tag>
                                <el-tag v-else-if="calcResult.exempt_reason === 'reverse_charge'" type="warning" size="small">反向征收</el-tag>
                                <el-tag v-else-if="calcResult.exempt_reason === 'ioss_threshold'" type="info" size="small">IOSS 阈值内</el-tag>
                            </el-col>
                            <el-col :span="6">
                                <el-tag v-if="calcResult.reporting_code" type="primary" size="small">{{ calcResult.reporting_code }}</el-tag>
                            </el-col>
                        </el-row>
                    </div>
                </el-card>
            </el-tab-pane>

            <!-- 免税证书 -->
            <el-tab-pane label="免税证书" name="certificates">
                <div class="section-header">
                    <el-button type="primary" size="small" @click="showCertDialog = true">
                        <el-icon><Plus /></el-icon> 新增证书
                    </el-button>
                </div>
                <el-table :data="certList" v-loading="loadingCerts" stripe size="small">
                    <el-table-column label="证书编号" min-width="160" prop="certificate_number" />
                    <el-table-column label="类型" width="130" prop="certificate_type" />
                    <el-table-column label="国家" width="70" prop="issuing_country" align="center" />
                    <el-table-column label="客户" min-width="140" prop="customer_name" />
                    <el-table-column label="有效期" width="200">
                        <template #default="{ row }">{{ row.valid_from }} ~ {{ row.valid_until }}</template>
                    </el-table-column>
                    <el-table-column label="状态" width="90">
                        <template #default="{ row }">
                            <el-tag :type="row.status === 'approved' ? 'success' : row.status === 'rejected' ? 'danger' : 'warning'" size="small">
                                {{ row.status === 'approved' ? '已批准' : row.status === 'rejected' ? '已拒绝' : '待审批' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="有效" width="60" align="center">
                        <template #default="{ row }">
                            <el-tag :type="row.is_valid ? 'success' : 'info'" size="small">{{ row.is_valid ? '是' : '否' }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" width="160" fixed="right">
                        <template #default="{ row }">
                            <el-button v-if="row.status === 'pending'" text size="small" type="success" @click="handleCertApprove(row, 'approved')">批准</el-button>
                            <el-button v-if="row.status === 'pending'" text size="small" type="danger" @click="handleCertApprove(row, 'rejected')">拒绝</el-button>
                            <el-button text size="small" type="danger" @click="handleCertDelete(row)">删除</el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </el-tab-pane>

            <!-- 税率编辑对话框 -->
            <el-dialog v-model="rateEditVisible" title="编辑税率" width="460px">
                <el-form :model="rateEditForm" label-position="top" size="small">
                    <el-row :gutter="16">
                        <el-col :span="12">
                            <el-form-item label="税率百分比">
                                <el-input-number v-model="rateEditForm.rate_percent" :min="0" :max="100" :precision="2" style="width: 100%;" />
                            </el-form-item>
                        </el-col>
                        <el-col :span="12">
                            <el-form-item label="状态">
                                <el-switch v-model="rateEditForm.is_active" />
                            </el-form-item>
                        </el-col>
                    </el-row>
                    <el-form-item label="生效日期">
                        <el-date-picker v-model="rateEditForm.effective_from" type="date" style="width: 100%;" />
                    </el-form-item>
                    <el-form-item label="失效日期">
                        <el-date-picker v-model="rateEditForm.effective_until" type="date" style="width: 100%;" />
                    </el-form-item>
                    <el-form-item label="描述">
                        <el-input v-model="rateEditForm.description" type="textarea" :rows="2" />
                    </el-form-item>
                </el-form>
                <template #footer>
                    <el-button @click="rateEditVisible = false">取消</el-button>
                    <el-button type="primary" :loading="savingRate" @click="handleSaveRate">保存</el-button>
                </template>
            </el-dialog>

            <!-- 新建证书对话框 -->
            <el-dialog v-model="showCertDialog" title="新增免税证书" width="460px">
                <el-form :model="certForm" label-position="top" size="small">
                    <el-form-item label="证书类型">
                        <el-select v-model="certForm.certificate_type" style="width: 100%;">
                            <el-option label="VAT 免税" value="vat_exempt" />
                            <el-option label="Sales Tax 免税" value="sales_tax_exempt" />
                            <el-option label="经销商证书" value="reseller" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="证书编号">
                        <el-input v-model="certForm.certificate_number" />
                    </el-form-item>
                    <el-form-item label="颁发国家">
                        <el-select v-model="certForm.issuing_country" filterable style="width: 100%;">
                            <el-option v-for="c in countryOptions" :key="c.country_code" :label="`${c.country_code} - ${c.name}`" :value="c.country_code" />
                        </el-select>
                    </el-form-item>
                    <el-row :gutter="16">
                        <el-col :span="12">
                            <el-form-item label="生效日期">
                                <el-date-picker v-model="certForm.valid_from" type="date" style="width: 100%;" />
                            </el-form-item>
                        </el-col>
                        <el-col :span="12">
                            <el-form-item label="失效日期">
                                <el-date-picker v-model="certForm.valid_until" type="date" style="width: 100%;" />
                            </el-form-item>
                        </el-col>
                    </el-row>
                    <el-form-item label="免税理由">
                        <el-input v-model="certForm.reason" type="textarea" :rows="2" />
                    </el-form-item>
                </el-form>
                <template #footer>
                    <el-button @click="showCertDialog = false">取消</el-button>
                    <el-button type="primary" :loading="savingCert" @click="handleCreateCert">提交</el-button>
                </template>
            </el-dialog>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import taxApi from '@/api/tax';

const activeTab = ref('rates');

// Stats
const stats = reactive({ total_rates: 0, active_rates: 0, eu_countries: 0, pending_certificates: 0 });
const statCards = computed(() => [
    { label: '总税率数', value: stats.total_rates, color: '#409EFF' },
    { label: '活跃税率', value: stats.active_rates, color: '#67C23A' },
    { label: 'EU 国家', value: stats.eu_countries, color: '#E6A23C' },
    { label: '待审批证书', value: stats.pending_certificates, color: '#F56C6C' },
]);

// Rates
const loadingRates = ref(false);
const rateList = ref([]);
const rateSearch = ref('');
const rateFilterCountry = ref('');
const rateFilterActive = ref('');
const ratePagination = reactive({ current_page: 1, per_page: 50, total: 0 });
const rateEditVisible = ref(false);
const savingRate = ref(false);
const rateEditForm = reactive({
    id: null, rate_percent: 0, is_active: true,
    effective_from: null, effective_until: null, description: '',
});

// Country options
const countryOptions = ref([]);

// Calculator
const calculating = ref(false);
const calcForm = reactive({
    amount: 100, country_code: 'US', region_code: '',
    is_b2b: false, seller_country: '',
});
const regionOptions = ref([]);
const calcResult = ref(null);

// Certificates
const loadingCerts = ref(false);
const certList = ref([]);
const showCertDialog = ref(false);
const savingCert = ref(false);
const certForm = reactive({
    certificate_type: 'vat_exempt', certificate_number: '',
    issuing_country: '', valid_from: '', valid_until: '', reason: '',
});

async function loadStats() {
    try {
        const { data: res } = await taxApi.stats();
        if (res.success) Object.assign(stats, res.data);
    } catch { /* ignore */ }
}

async function loadCountries() {
    try {
        const { data: res } = await taxApi.countries();
        if (res.success) countryOptions.value = res.data || [];
    } catch { /* ignore */ }
}

async function loadRates() {
    loadingRates.value = true;
    try {
        const params = { page: ratePagination.current_page, per_page: ratePagination.per_page };
        if (rateSearch.value) params.search = rateSearch.value;
        if (rateFilterCountry.value) params.country_code = rateFilterCountry.value;
        if (rateFilterActive.value !== '') params.is_active = rateFilterActive.value;
        const { data: res } = await taxApi.rates(params);
        if (res.success) {
            rateList.value = res.data?.data || [];
            ratePagination.current_page = res.data?.current_page || 1;
            ratePagination.total = res.data?.total || 0;
        }
    } finally {
        loadingRates.value = false;
    }
}

function openRateEdit(rate) {
    Object.assign(rateEditForm, {
        id: rate.id,
        rate_percent: rate.rate_percent,
        is_active: rate.is_active,
        effective_from: rate.effective_from || null,
        effective_until: rate.effective_until || null,
        description: rate.description || '',
    });
    rateEditVisible.value = true;
}

async function handleSaveRate() {
    savingRate.value = true;
    try {
        const { data: res } = await taxApi.updateRate(rateEditForm.id, {
            rate: rateEditForm.rate_percent / 100,
            is_active: rateEditForm.is_active,
            effective_from: rateEditForm.effective_from || null,
            effective_until: rateEditForm.effective_until || null,
            description: rateEditForm.description,
        });
        if (res.success) {
            ElMessage.success('税率已更新');
            rateEditVisible.value = false;
            await loadRates();
        }
    } catch {
        ElMessage.error('更新失败');
    } finally {
        savingRate.value = false;
    }
}

async function loadRegions(countryCode) {
    if (!countryCode) { regionOptions.value = []; return; }
    try {
        const { data: res } = await taxApi.regionTaxes(countryCode);
        if (res.success) regionOptions.value = res.data || [];
    } catch {
        regionOptions.value = [];
    }
}

function onCalcCountryChange(code) {
    calcForm.region_code = '';
    calcResult.value = null;
    loadRegions(code);
}

async function handleCalculate() {
    calculating.value = true;
    calcResult.value = null;
    try {
        const { data: res } = await taxApi.calculate(
            calcForm.amount,
            calcForm.country_code,
            {
                region_code: calcForm.region_code || undefined,
                is_b2b: calcForm.is_b2b,
                seller_country: calcForm.seller_country || undefined,
            }
        );
        if (res.success) calcResult.value = res.data;
    } catch {
        ElMessage.error('计算失败');
    } finally {
        calculating.value = false;
    }
}

async function loadCerts() {
    loadingCerts.value = true;
    try {
        const { data: res } = await taxApi.certificates({ per_page: 100 });
        if (res.success) certList.value = res.data?.data || [];
    } finally {
        loadingCerts.value = false;
    }
}

async function handleCreateCert() {
    savingCert.value = true;
    try {
        const { data: res } = await taxApi.storeCertificate({
            certificate_type: certForm.certificate_type,
            certificate_number: certForm.certificate_number,
            issuing_country: certForm.issuing_country,
            valid_from: certForm.valid_from,
            valid_until: certForm.valid_until,
            reason: certForm.reason,
        });
        if (res.success) {
            ElMessage.success('证书已提交');
            showCertDialog.value = false;
            await loadCerts();
            await loadStats();
        }
    } catch {
        ElMessage.error('提交失败');
    } finally {
        savingCert.value = false;
    }
}

async function handleCertApprove(cert, status) {
    try {
        const tips = status === 'approved' ? '批准此免税证书？' : '拒绝此免税证书？';
        await ElMessageBox.confirm(tips, '确认操作');
        const { data: res } = await taxApi.approveCertificate(cert.id, status);
        if (res.success) {
            ElMessage.success(res.message || '操作成功');
            await loadCerts();
            await loadStats();
        }
    } catch (e) {
        if (e !== 'cancel') ElMessage.error('操作失败');
    }
}

async function handleCertDelete(cert) {
    try {
        await ElMessageBox.confirm('确定删除此证书？', '确认删除');
        const { data: res } = await taxApi.deleteCertificate(cert.id);
        if (res.success) {
            ElMessage.success('已删除');
            await loadCerts();
        }
    } catch (e) {
        if (e !== 'cancel') ElMessage.error('删除失败');
    }
}

onMounted(() => {
    loadStats();
    loadCountries();
    loadRates();
    loadCerts();
});
</script>

<style scoped>
.tax-page { padding: 20px; }
.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}
.header-left h2 { margin: 0; font-size: 20px; }
.header-subtitle {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-left: 12px;
}
.alert-bar { margin-bottom: 16px; }

.stats-row { margin-bottom: 16px; }
.stat-card { text-align: center; }
.stat-value { font-size: 22px; font-weight: 700; }
.stat-label { font-size: 13px; color: var(--el-text-color-secondary); margin-top: 4px; }

.filter-card { margin-bottom: 16px; }
.filter-bar { display: flex; align-items: center; }

.pagination-wrap {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
}

/* Calculator */
.calc-card { margin-bottom: 16px; }
.calc-result {
    background: var(--el-fill-color-lighter);
    border-radius: 8px;
    padding: 16px;
}
.result-item {
    text-align: center;
}
.result-label {
    display: block;
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-bottom: 4px;
}
.result-value {
    display: block;
    font-size: 22px;
    font-weight: 700;
    color: var(--el-text-color-primary);
}
.result-item.highlight .result-value { color: var(--el-color-primary); }
.result-sub {
    font-size: 12px;
    color: var(--el-text-color-secondary);
}
.mt-2 { margin-top: 8px; }

/* Certificates */
.section-header {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 12px;
}
</style>
