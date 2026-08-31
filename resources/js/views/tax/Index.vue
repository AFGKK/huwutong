<template>
    <div class="tax-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('tax_page.title') }}</h2>
                <span class="header-subtitle">{{ t('tax_page.subtitle') }}</span>
            </div>
        </div>

        <el-alert
            :title="t('tax_page.alert')"
            type="success" show-icon :closable="false" class="alert-bar"
        />

        <el-tabs v-model="activeTab">
            <!-- 税率管理 -->
            <el-tab-pane :label="t('tax_page.tabs.rates')" name="rates">
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
                        <el-input v-model="rateSearch" size="small" :placeholder="t('tax_page.filters.search_ph')" clearable style="width: 220px;" @clear="loadRates" @keyup.enter="loadRates" />
                        <el-select v-model="rateFilterCountry" size="small" :placeholder="t('tax_compliance_page.filters.country')" filterable clearable style="width: 130px; margin-left: 8px;" @change="loadRates">
                            <el-option v-for="c in countryOptions" :key="c.country_code" :label="c.country_code + ' - ' + c.name" :value="c.country_code" />
                        </el-select>
                        <el-select v-model="rateFilterActive" size="small" style="width: 100px; margin-left: 8px;" @change="loadRates">
                            <el-option :label="t('tax_compliance_page.filters.all')" value="" />
                            <el-option :label="t('tax_compliance_page.filters.active')" :value="true" />
                            <el-option :label="t('tax_compliance_page.filters.inactive')" :value="false" />
                        </el-select>
                    </div>
                </el-card>

                <el-table :data="rateList" v-loading="loadingRates" stripe size="small" max-height="500">
                    <el-table-column :label="t('tax_compliance_page.cols.country')" width="70" prop="country_code" align="center" />
                    <el-table-column :label="t('tax_page.cols.region')" width="70" prop="region_code" align="center" />
                    <el-table-column :label="t('tax_page.cols.tax_name')" width="100" prop="name" />
                    <el-table-column :label="t('tax_compliance_page.cols.type')" width="80" prop="type" />
                    <el-table-column :label="t('tax_page.cols.rate')" width="90" align="right">
                        <template #default="{ row }">{{ row.rate_percent }}%</template>
                    </el-table-column>
                    <el-table-column :label="t('tax_page.cols.eu')" width="50" align="center">
                        <template #default="{ row }">
                            <el-tag v-if="row.is_eu" size="small" type="primary">EU</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('tax_compliance_page.cols.status')" width="70">
                        <template #default="{ row }">
                            <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                                {{ row.is_active ? t('tax_compliance_page.filters.active') : t('tax_compliance_page.filters.inactive') }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('tax_page.cols.effective_from')" width="100" prop="effective_from" />
                    <el-table-column :label="t('tax_page.cols.effective_until')" width="100" prop="effective_until" />
                    <el-table-column :label="t('tax_compliance_page.cols.description')" min-width="160" prop="description" />
                    <el-table-column :label="t('tax_compliance_page.cols.actions')" width="120" fixed="right">
                        <template #default="{ row }">
                            <el-button text size="small" type="primary" @click="openRateEdit(row)">{{ t('actions.edit') }}</el-button>
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
            <el-tab-pane :label="t('tax_page.tabs.calculator')" name="calculator">
                <el-card shadow="never" class="calc-card">
                    <el-form label-position="top" size="small" :model="calcForm">
                        <el-row :gutter="16">
                            <el-col :span="8">
                                <el-form-item :label="t('tax_page.calc.amount')">
                                    <el-input-number v-model="calcForm.amount" :min="0" :precision="2" style="width: 100%;" />
                                </el-form-item>
                            </el-col>
                            <el-col :span="8">
                                <el-form-item :label="t('tax_compliance_page.filters.country')">
                                    <el-select v-model="calcForm.country_code" filterable style="width: 100%;" @change="onCalcCountryChange">
                                        <el-option v-for="c in countryOptions" :key="c.country_code" :label="`${c.country_code} - ${c.name} (${c.rate_percent || (c.rate*100).toFixed(1)}%)`" :value="c.country_code" />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                            <el-col :span="8">
                                <el-form-item :label="t('tax_page.calc.region')">
                                    <el-select v-model="calcForm.region_code" filterable clearable style="width: 100%;" :disabled="regionOptions.length === 0">
                                        <el-option v-for="r in regionOptions" :key="r.region_code" :label="`${r.region_code} - ${(r.rate*100).toFixed(1)}%`" :value="r.region_code" />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                        </el-row>
                        <el-row :gutter="16">
                            <el-col :span="8">
                                <el-form-item :label="t('tax_page.calc.b2b')">
                                    <el-switch v-model="calcForm.is_b2b" />
                                </el-form-item>
                            </el-col>
                            <el-col :span="8">
                                <el-form-item :label="t('tax_page.calc.seller_country')">
                                    <el-input v-model="calcForm.seller_country" maxlength="2" :placeholder="t('tax_page.calc.seller_country_ph')" />
                                </el-form-item>
                            </el-col>
                            <el-col :span="8" style="display: flex; align-items: flex-end;">
                                <el-button type="primary" :loading="calculating" @click="handleCalculate" style="margin-bottom: 2px;">
                                    {{ t('tax_page.calc.calculate') }}
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
                                    <span class="result-label">{{ t('tax_page.calc.taxable_amount') }}</span>
                                    <span class="result-value">${{ calcResult.taxable_amount.toFixed(2) }}</span>
                                </div>
                            </el-col>
                            <el-col :span="6">
                                <div class="result-item">
                                    <span class="result-label">{{ t('tax_page.calc.tax_type') }}</span>
                                    <span class="result-value">{{ calcResult.type_label }}</span>
                                </div>
                            </el-col>
                            <el-col :span="6">
                                <div class="result-item highlight">
                                    <span class="result-label">{{ t('tax_page.calc.tax_amount') }}</span>
                                    <span class="result-value">${{ calcResult.tax_amount.toFixed(2) }}</span>
                                </div>
                            </el-col>
                            <el-col :span="6">
                                <div class="result-item">
                                    <span class="result-label">{{ t('tax_page.calc.total') }}</span>
                                    <span class="result-value">${{ calcResult.total.toFixed(2) }}</span>
                                </div>
                            </el-col>
                        </el-row>
                        <el-row :gutter="24" class="mt-2">
                            <el-col :span="6">
                                <span class="result-sub">{{ t('tax_page.calc.rate_sub', { rate: calcResult.tax_percent }) }}</span>
                            </el-col>
                            <el-col :span="6">
                                <el-tag v-if="calcResult.exempt_reason === 'exempt'" type="success" size="small">{{ t('tax_page.calc.exempt') }}</el-tag>
                                <el-tag v-else-if="calcResult.exempt_reason === 'reverse_charge'" type="warning" size="small">{{ t('tax_page.calc.reverse_charge') }}</el-tag>
                                <el-tag v-else-if="calcResult.exempt_reason === 'ioss_threshold'" type="info" size="small">{{ t('tax_page.calc.ioss_threshold') }}</el-tag>
                            </el-col>
                            <el-col :span="6">
                                <el-tag v-if="calcResult.reporting_code" type="primary" size="small">{{ calcResult.reporting_code }}</el-tag>
                            </el-col>
                        </el-row>
                    </div>
                </el-card>
            </el-tab-pane>

            <!-- 免税证书 -->
            <el-tab-pane :label="t('tax_page.tabs.certificates')" name="certificates">
                <div class="section-header">
                    <el-button type="primary" size="small" @click="showCertDialog = true">
                        <el-icon><Plus /></el-icon> {{ t('tax_page.buttons.add_certificate') }}
                    </el-button>
                </div>
                <el-table :data="certList" v-loading="loadingCerts" stripe size="small">
                    <el-table-column :label="t('tax_page.cols.certificate_number')" min-width="160" prop="certificate_number" />
                    <el-table-column :label="t('tax_compliance_page.cols.type')" width="130" prop="certificate_type" />
                    <el-table-column :label="t('tax_compliance_page.cols.country')" width="70" prop="issuing_country" align="center" />
                    <el-table-column :label="t('tax_page.cols.customer')" min-width="140" prop="customer_name" />
                    <el-table-column :label="t('tax_page.cols.validity')" width="200">
                        <template #default="{ row }">{{ row.valid_from }} ~ {{ row.valid_until }}</template>
                    </el-table-column>
                    <el-table-column :label="t('tax_compliance_page.cols.status')" width="90">
                        <template #default="{ row }">
                            <el-tag :type="row.status === 'approved' ? 'success' : row.status === 'rejected' ? 'danger' : 'warning'" size="small">
                                {{ certStatusLabels[row.status] || row.status }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('tax_page.cols.valid')" width="60" align="center">
                        <template #default="{ row }">
                            <el-tag :type="row.is_valid ? 'success' : 'info'" size="small">{{ row.is_valid ? t('tax_page.yes') : t('tax_page.no') }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('tax_compliance_page.cols.actions')" width="160" fixed="right">
                        <template #default="{ row }">
                            <el-button v-if="row.status === 'pending'" text size="small" type="success" @click="handleCertApprove(row, 'approved')">{{ t('actions.approve') }}</el-button>
                            <el-button v-if="row.status === 'pending'" text size="small" type="danger" @click="handleCertApprove(row, 'rejected')">{{ t('actions.reject') }}</el-button>
                            <el-button text size="small" type="danger" @click="handleCertDelete(row)">{{ t('actions.delete') }}</el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </el-tab-pane>

            <!-- 税率编辑对话框 -->
            <el-dialog v-model="rateEditVisible" :title="t('tax_page.dialogs.edit_rate')" width="460px">
                <el-form :model="rateEditForm" label-position="top" size="small">
                    <el-row :gutter="16">
                        <el-col :span="12">
                            <el-form-item :label="t('tax_page.forms.rate_percent')">
                                <el-input-number v-model="rateEditForm.rate_percent" :min="0" :max="100" :precision="2" style="width: 100%;" />
                            </el-form-item>
                        </el-col>
                        <el-col :span="12">
                            <el-form-item :label="t('tax_compliance_page.cols.status')">
                                <el-switch v-model="rateEditForm.is_active" />
                            </el-form-item>
                        </el-col>
                    </el-row>
                    <el-form-item :label="t('tax_page.forms.effective_from')">
                        <el-date-picker v-model="rateEditForm.effective_from" type="date" style="width: 100%;" />
                    </el-form-item>
                    <el-form-item :label="t('tax_page.forms.effective_until')">
                        <el-date-picker v-model="rateEditForm.effective_until" type="date" style="width: 100%;" />
                    </el-form-item>
                    <el-form-item :label="t('tax_compliance_page.cols.description')">
                        <el-input v-model="rateEditForm.description" type="textarea" :rows="2" />
                    </el-form-item>
                </el-form>
                <template #footer>
                    <el-button @click="rateEditVisible = false">{{ t('actions.cancel') }}</el-button>
                    <el-button type="primary" :loading="savingRate" @click="handleSaveRate">{{ t('actions.save') }}</el-button>
                </template>
            </el-dialog>

            <!-- 新建证书对话框 -->
            <el-dialog v-model="showCertDialog" :title="t('tax_page.dialogs.add_certificate')" width="460px">
                <el-form :model="certForm" label-position="top" size="small">
                    <el-form-item :label="t('tax_page.forms.certificate_type')">
                        <el-select v-model="certForm.certificate_type" style="width: 100%;">
                            <el-option v-for="(label, key) in certTypeLabels" :key="key" :label="label" :value="key" />
                        </el-select>
                    </el-form-item>
                    <el-form-item :label="t('tax_page.forms.certificate_number')">
                        <el-input v-model="certForm.certificate_number" />
                    </el-form-item>
                    <el-form-item :label="t('tax_page.forms.issuing_country')">
                        <el-select v-model="certForm.issuing_country" filterable style="width: 100%;">
                            <el-option v-for="c in countryOptions" :key="c.country_code" :label="`${c.country_code} - ${c.name}`" :value="c.country_code" />
                        </el-select>
                    </el-form-item>
                    <el-row :gutter="16">
                        <el-col :span="12">
                            <el-form-item :label="t('tax_page.forms.effective_from')">
                                <el-date-picker v-model="certForm.valid_from" type="date" style="width: 100%;" />
                            </el-form-item>
                        </el-col>
                        <el-col :span="12">
                            <el-form-item :label="t('tax_page.forms.effective_until')">
                                <el-date-picker v-model="certForm.valid_until" type="date" style="width: 100%;" />
                            </el-form-item>
                        </el-col>
                    </el-row>
                    <el-form-item :label="t('tax_page.forms.reason')">
                        <el-input v-model="certForm.reason" type="textarea" :rows="2" />
                    </el-form-item>
                </el-form>
                <template #footer>
                    <el-button @click="showCertDialog = false">{{ t('actions.cancel') }}</el-button>
                    <el-button type="primary" :loading="savingCert" @click="handleCreateCert">{{ t('actions.submit') }}</el-button>
                </template>
            </el-dialog>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import taxApi from '@/api/tax';

const { t } = useI18n();

const activeTab = ref('rates');

// Stats
const stats = reactive({ total_rates: 0, active_rates: 0, eu_countries: 0, pending_certificates: 0 });
const statCards = computed(() => [
    { label: t('tax_page.stats.total_rates'), value: stats.total_rates, color: '#0f172a' },
    { label: t('tax_page.stats.active_rates'), value: stats.active_rates, color: '#67C23A' },
    { label: t('tax_page.stats.eu_countries'), value: stats.eu_countries, color: '#E6A23C' },
    { label: t('tax_page.stats.pending_certificates'), value: stats.pending_certificates, color: '#F56C6C' },
]);

const certStatusLabels = computed(() => ({
    approved: t('tax_page.status.approved'),
    rejected: t('tax_page.status.rejected'),
    pending: t('tax_page.status.pending'),
}));

const certTypeLabels = computed(() => ({
    vat_exempt: t('tax_page.cert_types.vat_exempt'),
    sales_tax_exempt: t('tax_page.cert_types.sales_tax_exempt'),
    reseller: t('tax_page.cert_types.reseller'),
}));

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
            ElMessage.success(t('tax_page.messages.rate_updated'));
            rateEditVisible.value = false;
            await loadRates();
        }
    } catch {
        ElMessage.error(t('messages.failed'));
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
        ElMessage.error(t('tax_page.messages.calculate_failed'));
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
            ElMessage.success(t('tax_page.messages.cert_submitted'));
            showCertDialog.value = false;
            await loadCerts();
            await loadStats();
        }
    } catch {
        ElMessage.error(t('messages.failed'));
    } finally {
        savingCert.value = false;
    }
}

async function handleCertApprove(cert, status) {
    try {
        const tips = status === 'approved'
            ? t('tax_page.messages.approve_confirm')
            : t('tax_page.messages.reject_confirm');
        await ElMessageBox.confirm(tips, t('tax_page.messages.confirm_action_title'));
        const { data: res } = await taxApi.approveCertificate(cert.id, status);
        if (res.success) {
            ElMessage.success(res.message || t('messages.success'));
            await loadCerts();
            await loadStats();
        }
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('messages.failed'));
    }
}

async function handleCertDelete(cert) {
    try {
        await ElMessageBox.confirm(
            t('tax_page.messages.delete_cert_confirm'),
            t('tax_page.messages.confirm_delete_title'),
        );
        const { data: res } = await taxApi.deleteCertificate(cert.id);
        if (res.success) {
            ElMessage.success(t('tax_page.messages.deleted'));
            await loadCerts();
        }
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('messages.failed'));
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
