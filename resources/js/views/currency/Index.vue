<template>
    <div class="currency-manager">
        <el-page-header :content="'多币种定价 ' + activeTabText" @back="router.push('/')" />

        <el-tabs v-model="activeTab" class="mt-4">
            <!-- ═══ 汇率管理 ═══ -->
            <el-tab-pane label="汇率管理" name="rates">
                <div class="flex justify-between mb-4">
                    <el-button type="primary" @click="openRateDialog">添加汇率</el-button>
                    <el-button @click="syncRates" :loading="loading">从 ECB 同步汇率</el-button>
                </div>

                <el-table :data="rates" v-loading="loading" stripe border>
                    <el-table-column prop="from_currency" label="源货币" width="100" />
                    <el-table-column prop="to_currency" label="目标货币" width="100" />
                    <el-table-column label="汇率">
                        <template #default="{ row }">
                            <span class="font-mono">{{ Number(row.rate).toFixed(8) }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="provider" label="来源" width="100" />
                    <el-table-column label="生效时间" width="180">
                        <template #default="{ row }">{{ row.effective_at ? new Date(row.effective_at).toLocaleString() : '-' }}</template>
                    </el-table-column>
                    <el-table-column label="过期时间" width="180">
                        <template #default="{ row }">{{ row.expires_at ? new Date(row.expires_at).toLocaleString() : '永不过期' }}</template>
                    </el-table-column>
                    <el-table-column label="操作" width="120" fixed="right">
                        <template #default="{ row }">
                            <el-button size="small" type="danger" @click="confirmDeleteRate(row.id)">删除</el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </el-tab-pane>

            <!-- ═══ 定价计划 ═══ -->
            <el-tab-pane label="定价计划" name="plans">
                <div class="flex justify-between mb-4">
                    <el-button type="primary" @click="openPlanDialog">创建定价计划</el-button>
                </div>

                <el-table :data="pricingPlans" v-loading="loading" stripe border>
                    <el-table-column prop="name" label="计划名称" min-width="150" />
                    <el-table-column prop="slug" label="标识" width="120" />
                    <el-table-column prop="billing_period" label="周期" width="100">
                        <template #default="{ row }">
                            <el-tag :type="row.billing_period === 'yearly' ? 'success' : row.billing_period === 'one_time' ? 'info' : ''">
                                {{ { monthly: '月度', yearly: '年度', one_time: '一次性' }[row.billing_period] || row.billing_period }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="多币种价格" min-width="300">
                        <template #default="{ row }">
                            <div class="flex flex-wrap gap-2">
                                <el-tag v-for="p in (row.prices || [])" :key="p.currency" type="warning" effect="plain" class="currency-price-tag">
                                    {{ p.currency }} {{ formatCurrency(p.price, p.currency) }}
                                </el-tag>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column prop="is_active" label="状态" width="80">
                        <template #default="{ row }">
                            <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">
                                {{ row.is_active ? '启用' : '停用' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" width="160" fixed="right">
                        <template #default="{ row }">
                            <el-button size="small" @click="editPlan(row)">编辑</el-button>
                            <el-button size="small" type="danger" @click="confirmDeletePlan(row.id)">删除</el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </el-tab-pane>

            <!-- ═══ 货币转换工具 ═══ -->
            <el-tab-pane label="货币转换" name="convert">
                <el-card class="convert-card">
                    <el-form :model="{ amount: convertAmount, from: convertFrom, to: convertTo }" label-width="100">
                        <el-form-item label="金额">
                            <el-input-number v-model="convertAmount" :min="0" :precision="2" style="width: 200px" />
                        </el-form-item>
                        <el-form-item label="源货币">
                            <el-select v-model="convertFrom" filterable style="width: 140px">
                                <el-option v-for="c in currencies" :key="c.code" :label="`${c.code} ${c.symbol}`" :value="c.code" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="目标货币">
                            <el-select v-model="convertTo" filterable style="width: 140px">
                                <el-option v-for="c in currencies" :key="c.code" :label="`${c.code} ${c.symbol}`" :value="c.code" />
                            </el-select>
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="doConvert">转换</el-button>
                        </el-form-item>
                    </el-form>

                    <div v-if="convertResult" class="convert-result mt-4 p-4 bg-gray-50 rounded">
                        <p class="text-lg">
                            {{ formatCurrency(convertResult.amount / (convertResult.rate || 1), convertResult.from) }}
                            <el-icon><ArrowRight /></el-icon>
                            <strong class="text-primary text-xl">{{ formatCurrency(convertResult.amount, convertResult.to) }}</strong>
                        </p>
                        <p class="text-sm text-gray-500 mt-1">
                            汇率: 1 {{ convertResult.from }} = {{ convertResult.rate?.toFixed(8) }} {{ convertResult.to }}
                        </p>
                        <p v-if="convertResult.error" class="text-red-500 mt-1">{{ convertResult.error }}</p>
                    </div>
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <!-- ─── 汇率对话框 ─── -->
        <el-dialog v-model="rateDialogVisible" title="设置汇率" width="500px">
            <el-form :model="rateForm" label-width="120">
                <el-form-item label="源货币" required>
                    <el-select v-model="rateForm.from_currency" filterable style="width: 200px">
                        <el-option v-for="c in currencies" :key="c.code" :label="`${c.code} - ${c.name}`" :value="c.code" />
                    </el-select>
                </el-form-item>
                <el-form-item label="目标货币" required>
                    <el-select v-model="rateForm.to_currency" filterable style="width: 200px">
                        <el-option v-for="c in currencies" :key="c.code" :label="`${c.code} - ${c.name}`" :value="c.code" />
                    </el-select>
                </el-form-item>
                <el-form-item label="汇率" required>
                    <el-input-number v-model="rateForm.rate" :min="0.00000001" :precision="8" :step="0.01" style="width: 240px" />
                </el-form-item>
                <el-form-item label="来源">
                    <el-select v-model="rateForm.provider" style="width: 200px">
                        <el-option label="手动" value="manual" />
                        <el-option label="ECB" value="ecb" />
                        <el-option label="Stripe" value="stripe" />
                        <el-option label="Alipay" value="alipay" />
                    </el-select>
                </el-form-item>
                <el-form-item label="生效时间">
                    <el-date-picker v-model="rateForm.effective_at" type="datetime" placeholder="立即生效" style="width: 100%" />
                </el-form-item>
                <el-form-item label="过期时间">
                    <el-date-picker v-model="rateForm.expires_at" type="datetime" placeholder="永不过期" style="width: 100%" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="rateDialogVisible = false">取消</el-button>
                <el-button type="primary" @click="submitRate">确定</el-button>
            </template>
        </el-dialog>

        <!-- ─── 定价计划对话框 ─── -->
        <el-dialog v-model="planDialogVisible" :title="isEditingPlan ? '编辑定价计划' : '创建定价计划'" width="700px">
            <el-form :model="planForm" label-width="120">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="名称" required>
                            <el-input v-model="planForm.name" placeholder="例如: 基础版" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="标识" required>
                            <el-input v-model="planForm.slug" placeholder="例如: basic" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item label="描述">
                    <el-input v-model="planForm.description" type="textarea" :rows="2" />
                </el-form-item>
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item label="计费周期" required>
                            <el-select v-model="planForm.billing_period">
                                <el-option label="月度" value="monthly" />
                                <el-option label="年度" value="yearly" />
                                <el-option label="一次性" value="one_time" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="排序">
                            <el-input-number v-model="planForm.sort_order" :min="0" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="状态">
                            <el-switch v-model="planForm.is_active" active-text="启用" inactive-text="停用" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-divider>多币种价格</el-divider>

                <div v-for="(priceRow, idx) in planForm.prices" :key="idx" class="price-row mb-3 p-3 bg-gray-50 rounded">
                    <el-row :gutter="12" align="middle">
                        <el-col :span="4">
                            <el-select v-model="priceRow.currency" filterable style="width: 100%">
                                <el-option v-for="c in currencies" :key="c.code" :label="c.code" :value="c.code" />
                            </el-select>
                        </el-col>
                        <el-col :span="6">
                            <el-input v-model="priceRow.price" type="number" step="0.01" min="0" placeholder="价格">
                                <template #prefix>{{ formatSymbol(priceRow.currency) }}</template>
                            </el-input>
                        </el-col>
                        <el-col :span="5">
                            <el-input v-model="priceRow.setup_fee" type="number" step="0.01" min="0" placeholder="设置费" />
                        </el-col>
                        <el-col :span="5">
                            <el-input v-model="priceRow.trial_price" type="number" step="0.01" min="0" placeholder="试用价" />
                        </el-col>
                        <el-col :span="4" class="text-right">
                            <el-button size="small" type="danger" :disabled="planForm.prices.length <= 1" @click="removePriceRow(idx)">删除</el-button>
                        </el-col>
                    </el-row>
                </div>

                <el-button size="small" @click="addPriceRow">+ 添加货币价格</el-button>
            </el-form>
            <template #footer>
                <el-button @click="planDialogVisible = false">取消</el-button>
                <el-button type="primary" @click="submitPlan">{{ isEditingPlan ? '更新' : '创建' }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script>
import { defineComponent, ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { ArrowRight } from '@element-plus/icons-vue';
import currencyApi from '@/api/currency';

export default defineComponent({
    name: 'CurrencyIndex',
    components: { ArrowRight },
    setup() {
        const router = useRouter();

        const activeTab = ref('rates');
        const loading = ref(false);
        const currencies = ref([]);
        const rates = ref([]);
        const pricingPlans = ref([]);

        const activeTabText = computed(() => {
            const map = { rates: '- 汇率管理', plans: '- 定价计划', convert: '- 货币转换' };
            return map[activeTab.value] || '';
        });

        const rateForm = ref({
            from_currency: 'USD',
            to_currency: 'CNY',
            rate: '',
            provider: 'manual',
            effective_at: new Date().toISOString().slice(0, 16),
            expires_at: '',
        });
        const rateDialogVisible = ref(false);
        const isEditingRate = ref(false);

        const planDialogVisible = ref(false);
        const isEditingPlan = ref(false);
        const editingPlanId = ref(null);
        const planForm = ref({
            product_id: '',
            slug: '',
            name: '',
            description: '',
            billing_period: 'monthly',
            is_active: true,
            sort_order: 0,
            prices: [{ currency: 'CNY', price: '', setup_fee: 0, trial_price: '' }],
        });

        const convertAmount = ref('');
        const convertFrom = ref('USD');
        const convertTo = ref('CNY');
        const convertResult = ref(null);

        async function fetchCurrencies() {
            try {
                const res = await currencyApi.getCurrencies();
                currencies.value = res.data.data || [];
            } catch (e) {
                console.error('Failed to fetch currencies', e);
            }
        }

        async function fetchRates() {
            loading.value = true;
            try {
                const res = await currencyApi.getRates();
                const grouped = res.data.data || {};
                rates.value = Object.values(grouped).flat();
            } catch (e) {
                console.error('Failed to fetch rates', e);
            } finally {
                loading.value = false;
            }
        }

        async function fetchPricingPlans() {
            loading.value = true;
            try {
                const res = await currencyApi.getPricingPlans();
                pricingPlans.value = res.data.data || [];
            } catch (e) {
                console.error('Failed to fetch pricing plans', e);
            } finally {
                loading.value = false;
            }
        }

        function openRateDialog() {
            isEditingRate.value = false;
            rateForm.value = {
                from_currency: 'USD',
                to_currency: 'CNY',
                rate: '',
                provider: 'manual',
                effective_at: new Date().toISOString().slice(0, 16),
                expires_at: '',
            };
            rateDialogVisible.value = true;
        }

        async function submitRate() {
            try {
                await currencyApi.setRate(rateForm.value);
                ElMessage.success('汇率设置成功');
                rateDialogVisible.value = false;
                await fetchRates();
            } catch (e) {
                const msg = e.response?.data?.errors
                    ? Object.values(e.response.data.errors).flat().join('; ')
                    : '设置汇率失败';
                ElMessage.error(msg);
            }
        }

        async function confirmDeleteRate(id) {
            try {
                await ElMessageBox.confirm('确定删除该汇率？', '确认', { type: 'warning' });
                await currencyApi.deleteRate(id);
                ElMessage.success('汇率已删除');
                await fetchRates();
            } catch (e) {
                if (e !== 'cancel') ElMessage.error('删除失败');
            }
        }

        async function syncRates() {
            try {
                const res = await currencyApi.syncRates('ecb');
                ElMessage.success(res.data.message || '汇率同步完成');
                await fetchRates();
            } catch (e) {
                ElMessage.error('汇率同步失败');
            }
        }

        async function doConvert() {
            if (!convertAmount.value || convertAmount.value <= 0) {
                ElMessage.warning('请输入金额');
                return;
            }
            try {
                const res = await currencyApi.convert(
                    convertAmount.value,
                    convertFrom.value,
                    convertTo.value
                );
                convertResult.value = res.data.data;
            } catch (e) {
                convertResult.value = null;
                ElMessage.error('转换失败');
            }
        }

        function openPlanDialog() {
            isEditingPlan.value = false;
            editingPlanId.value = null;
            planForm.value = {
                product_id: '',
                slug: '',
                name: '',
                description: '',
                billing_period: 'monthly',
                is_active: true,
                sort_order: 0,
                prices: [{ currency: 'CNY', price: '', setup_fee: 0, trial_price: '' }],
            };
            planDialogVisible.value = true;
        }

        function editPlan(plan) {
            isEditingPlan.value = true;
            editingPlanId.value = plan.id;
            planForm.value = {
                product_id: plan.product_id || '',
                slug: plan.slug,
                name: plan.name,
                description: plan.description || '',
                billing_period: plan.billing_period,
                is_active: plan.is_active,
                sort_order: plan.sort_order ?? 0,
                prices: plan.prices?.length
                    ? plan.prices.map(p => ({
                        currency: p.currency,
                        price: String(p.price),
                        setup_fee: p.setup_fee ?? 0,
                        trial_price: p.trial_price ? String(p.trial_price) : '',
                    }))
                    : [{ currency: 'CNY', price: '', setup_fee: 0, trial_price: '' }],
            };
            planDialogVisible.value = true;
        }

        function addPriceRow() {
            planForm.value.prices.push({
                currency: 'USD',
                price: '',
                setup_fee: 0,
                trial_price: '',
            });
        }

        function removePriceRow(index) {
            if (planForm.value.prices.length <= 1) {
                ElMessage.warning('至少需要一个价格');
                return;
            }
            planForm.value.prices.splice(index, 1);
        }

        async function submitPlan() {
            try {
                if (isEditingPlan.value) {
                    await currencyApi.updatePricingPlan(editingPlanId.value, planForm.value);
                    ElMessage.success('定价计划更新成功');
                } else {
                    await currencyApi.createPricingPlan(planForm.value);
                    ElMessage.success('定价计划创建成功');
                }
                planDialogVisible.value = false;
                await fetchPricingPlans();
            } catch (e) {
                const msg = e.response?.data?.errors
                    ? Object.values(e.response.data.errors).flat().join('; ')
                    : '操作失败';
                ElMessage.error(msg);
            }
        }

        async function confirmDeletePlan(id) {
            try {
                await ElMessageBox.confirm('确定删除该定价计划？', '确认', { type: 'warning' });
                await currencyApi.deletePricingPlan(id);
                ElMessage.success('定价计划已删除');
                await fetchPricingPlans();
            } catch (e) {
                if (e !== 'cancel') ElMessage.error('删除失败');
            }
        }

        function formatSymbol(currency) {
            const symbols = { CNY: '¥', USD: '$', EUR: '€', JPY: '¥', GBP: '£', HKD: 'HK$', SGD: 'S$', KRW: '₩' };
            return symbols[currency] || currency;
        }

        function formatCurrency(amount, currency) {
            const dec = ['JPY', 'KRW'].includes(currency) ? 0 : 2;
            return formatSymbol(currency) + Number(amount).toFixed(dec);
        }

        onMounted(() => {
            fetchCurrencies();
            fetchRates();
            fetchPricingPlans();
        });

        return {
            router, activeTab, activeTabText, loading, currencies, rates, pricingPlans,
            rateForm, rateDialogVisible, isEditingRate,
            planForm, planDialogVisible, isEditingPlan,
            convertAmount, convertFrom, convertTo, convertResult,
            openRateDialog, submitRate, confirmDeleteRate, syncRates,
            doConvert, formatSymbol, formatCurrency,
            openPlanDialog, editPlan, addPriceRow, removePriceRow,
            submitPlan, confirmDeletePlan,
        };
    },
});
</script>

<style scoped>
.currency-manager {
    padding: 20px;
}
.currency-price-tag {
    margin: 2px;
}
.convert-card {
    max-width: 600px;
}
.price-row {
    border: 1px solid #e5e7eb;
}
</style>
