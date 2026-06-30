<template>
    <div class="multi-currency-pricing">
        <el-page-header :content="'多币种商品定价 ' + activeTabText" @back="router.push('/')" />

        <el-tabs v-model="activeTab" class="mt-4">
            <!-- ═══ Tab 1: 概览 ═══ -->
            <el-tab-pane label="概览" name="overview">
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6">
                        <el-card shadow="hover">
                            <el-statistic title="SKU 总数" :value="dashboard.total_skus || 0" />
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="hover">
                            <el-statistic title="多币种 SKU" :value="dashboard.multi_currency_skus || 0" />
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="hover">
                            <el-statistic title="覆盖率" :value="coverageRate" suffix="%" />
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="hover">
                            <el-statistic title="涉及币种" :value="dashboard.currency_count || 0" />
                        </el-card>
                    </el-col>
                </el-row>

                <el-card v-loading="dashboardLoading">
                    <template #header>
                        <span>各币种覆盖情况</span>
                    </template>
                    <el-table :data="currencyCoverage" stripe border empty-text="暂无数据">
                        <el-table-column prop="currency" label="币种" width="100" />
                        <el-table-column prop="total" label="SKU 数量" width="120" />
                        <el-table-column label="占比" min-width="200">
                            <template #default="{ row }">
                                <div class="flex items-center gap-2">
                                    <el-progress :percentage="row.percentage || 0" :stroke-width="16" />
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column prop="last_updated" label="最近更新" min-width="160">
                            <template #default="{ row }">
                                {{ row.last_updated ? new Date(row.last_updated).toLocaleString() : '-' }}
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>

            <!-- ═══ Tab 2: SKU 定价管理 ═══ -->
            <el-tab-pane label="SKU 定价管理" name="skus">
                <div class="flex justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <el-input
                            v-model="skuFilter.product_id"
                            placeholder="搜索 Product ID"
                            clearable
                            style="width: 220px"
                            @keyup.enter="fetchSkus"
                        />
                        <el-button type="primary" @click="fetchSkus">搜索</el-button>
                        <el-button @click="resetSkuFilter">重置</el-button>
                    </div>
                </div>

                <el-table :data="skus" v-loading="skusLoading" stripe border>
                    <el-table-column prop="sku_code" label="SKU 编码" width="140" />
                    <el-table-column prop="product_name" label="商品名称" min-width="180" />
                    <el-table-column label="基础价格" width="120">
                        <template #default="{ row }">
                            <span class="font-mono">{{ row.base_price ? '¥' + Number(row.base_price).toFixed(2) : '-' }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column label="货币数" width="80" align="center">
                        <template #default="{ row }">
                            <el-tag>{{ row.currency_count || 0 }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="状态" width="100">
                        <template #default="{ row }">
                            <el-tag :type="row.is_enabled ? 'success' : 'info'" size="small">
                                {{ row.is_enabled ? '已启用' : '未启用' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" width="200" fixed="right">
                        <template #default="{ row }">
                            <el-button size="small" type="primary" @click="openPriceDialog(row)">编辑价格</el-button>
                            <el-button
                                size="small"
                                :type="row.is_enabled ? 'danger' : 'default'"
                                @click="toggleEnabled(row)"
                            >
                                {{ row.is_enabled ? '停用' : '启用' }}
                            </el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <div v-if="skusPagination.total > 0" class="flex justify-center mt-4">
                    <el-pagination
                        v-model:current-page="skusPagination.current_page"
                        :page-size="skusPagination.per_page"
                        :total="skusPagination.total"
                        layout="prev, pager, next, total"
                        @current-change="fetchSkus"
                    />
                </div>
            </el-tab-pane>

            <!-- ═══ Tab 3: 批量更新 ═══ -->
            <el-tab-pane label="批量更新" name="batch">
                <el-alert title="批量更新多币种价格" description="可粘贴 JSON 或上传文件来批量更新多个 SKU 的多币种价格。格式参考右侧示例。" type="info" show-icon :closable="false" class="mb-4" />

                <el-row :gutter="16">
                    <el-col :span="16">
                        <el-card>
                            <template #header>
                                <div class="flex justify-between items-center">
                                    <span>输入数据</span>
                                    <div>
                                        <el-upload
                                            accept=".json,.csv"
                                            :show-file-list="false"
                                            :before-upload="handleFileUpload"
                                            :auto-upload="false"
                                        >
                                            <el-button size="small">上传文件</el-button>
                                        </el-upload>
                                    </div>
                                </div>
                            </template>
                            <el-input
                                v-model="batchInput"
                                type="textarea"
                                :rows="12"
                                placeholder='[
    {
        "sku_code": "SKU001",
        "prices": {
            "USD": { "price": 29.99, "compare_at_price": 39.99 },
            "EUR": { "price": 24.99 }
        }
    }
]'
                                class="font-mono"
                            />
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card>
                            <template #header><span>格式说明</span></template>
                            <div class="text-sm text-gray-600">
                                <p class="mb-2">每项包含:</p>
                                <ul class="list-disc pl-4 space-y-1">
                                    <li><code>sku_code</code> - SKU 编码</li>
                                    <li><code>prices</code> - 货币价格对象，键为币种代码</li>
                                    <li>每项价格可包含 <code>price</code>（必填）和 <code>compare_at_price</code>（可选）</li>
                                </ul>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>

                <div class="mt-4 flex gap-2">
                    <el-button type="primary" @click="doBatchUpdate" :loading="batchLoading">提交更新</el-button>
                    <el-button @click="batchInput = ''">清空</el-button>
                </div>

                <el-card v-if="batchResult" class="mt-4">
                    <template #header><span>更新结果</span></template>
                    <el-alert
                        :title="batchResult.message"
                        :type="batchResult.success ? 'success' : 'warning'"
                        show-icon
                        :closable="false"
                    />
                    <pre class="mt-2 text-sm">{{ JSON.stringify(batchResult.data, null, 2) }}</pre>
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <!-- ─── 价格编辑对话框 ─── -->
        <el-dialog v-model="priceDialogVisible" title="编辑多币种价格" width="650px" :close-on-click-modal="false">
            <div v-if="selectedSku" class="mb-4 text-sm text-gray-500">
                SKU: <strong>{{ selectedSku.sku_code }}</strong>
                &nbsp;|&nbsp; 商品: <strong>{{ selectedSku.product_name }}</strong>
            </div>
            <el-form v-loading="priceFormLoading" label-width="140">
                <div v-for="(cp, currency) in priceForm" :key="currency" class="price-row mb-3 p-3 bg-gray-50 rounded">
                    <div class="flex items-center justify-between mb-2">
                        <strong class="text-sm">{{ currency }}</strong>
                        <el-tag v-if="cp.is_converted" size="small" type="warning" effect="plain">
                            基于 {{ cp.source_currency || '?' }} 汇率转换
                        </el-tag>
                    </div>
                    <el-row :gutter="12">
                        <el-col :span="12">
                            <el-form-item label="价格" required>
                                <el-input-number v-model="cp.price" :min="0" :precision="2" :step="0.01" style="width: 100%" />
                            </el-form-item>
                        </el-col>
                        <el-col :span="12">
                            <el-form-item label="对比价">
                                <el-input-number v-model="cp.compare_at_price" :min="0" :precision="2" :step="0.01" :placeholder="'无'" style="width: 100%" />
                            </el-form-item>
                        </el-col>
                    </el-row>
                </div>
                <el-empty v-if="Object.keys(priceForm).length === 0" description="暂无价格数据" />
            </el-form>
            <template #footer>
                <el-button @click="priceDialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="priceSaving" @click="saveSkuPrices">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script>
import { defineComponent, ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
    getMcpDashboard,
    getEnabledSkus,
    getSkuPrices,
    updateSkuPrices,
    batchUpdatePrices,
    disableMultiCurrency,
} from '@/api/multiCurrencyPricing';

export default defineComponent({
    name: 'MultiCurrencyPricingIndex',
    setup() {
        const router = useRouter();

        // ─── Tab 状态 ───
        const activeTab = ref('overview');
        const activeTabText = computed(() => {
            const map = { overview: '- 概览', skus: '- SKU 定价管理', batch: '- 批量更新' };
            return map[activeTab.value] || '';
        });

        // ─── 概览 ───
        const dashboard = ref({});
        const dashboardLoading = ref(false);

        const coverageRate = computed(() => {
            const total = dashboard.value.total_skus || 0;
            const mc = dashboard.value.multi_currency_skus || 0;
            return total ? Number(((mc / total) * 100).toFixed(1)) : 0;
        });

        const currencyCoverage = computed(() => {
            return (dashboard.value.currency_coverage || []).map((item) => ({
                ...item,
                percentage: item.percentage ? Math.round(item.percentage) : 0,
            }));
        });

        async function fetchDashboard() {
            dashboardLoading.value = true;
            try {
                const res = await getMcpDashboard();
                dashboard.value = res.data.data || {};
            } catch (e) {
                console.error('Failed to fetch dashboard', e);
            } finally {
                dashboardLoading.value = false;
            }
        }

        // ─── SKU 定价管理 ───
        const skus = ref([]);
        const skusLoading = ref(false);
        const skusPagination = ref({ current_page: 1, per_page: 15, total: 0 });
        const skuFilter = ref({ product_id: '' });

        async function fetchSkus() {
            skusLoading.value = true;
            try {
                const params = {
                    page: skusPagination.value.current_page,
                    per_page: skusPagination.value.per_page,
                    ...(skuFilter.value.product_id ? { product_id: skuFilter.value.product_id } : {}),
                };
                const res = await getEnabledSkus(params);
                const body = res.data.data || {};
                skus.value = body.data || [];
                skusPagination.value = {
                    current_page: body.current_page || 1,
                    per_page: body.per_page || 15,
                    total: body.total || 0,
                };
            } catch (e) {
                console.error('Failed to fetch SKUs', e);
            } finally {
                skusLoading.value = false;
            }
        }

        function resetSkuFilter() {
            skuFilter.value = { product_id: '' };
            skusPagination.value.current_page = 1;
            fetchSkus();
        }

        // ─── 价格编辑对话框 ───
        const priceDialogVisible = ref(false);
        const selectedSku = ref(null);
        const priceForm = ref({});
        const priceFormLoading = ref(false);
        const priceSaving = ref(false);

        async function openPriceDialog(row) {
            selectedSku.value = row;
            priceDialogVisible.value = true;
            priceFormLoading.value = true;
            try {
                const res = await getSkuPrices(row.id);
                const prices = res.data.data || {};
                priceForm.value = {};
                for (const [currency, data] of Object.entries(prices)) {
                    priceForm.value[currency] = {
                        price: data.price ?? 0,
                        compare_at_price: data.compare_at_price ?? null,
                        is_converted: !!data.is_converted,
                        source_currency: data.source_currency || null,
                    };
                }
            } catch (e) {
                console.error('Failed to fetch prices', e);
                ElMessage.error('获取价格失败');
            } finally {
                priceFormLoading.value = false;
            }
        }

        async function saveSkuPrices() {
            priceSaving.value = true;
            try {
                const prices = {};
                for (const [currency, data] of Object.entries(priceForm.value)) {
                    prices[currency] = {
                        price: data.price,
                        ...(data.compare_at_price != null ? { compare_at_price: data.compare_at_price } : {}),
                    };
                }
                await updateSkuPrices(selectedSku.value.id, prices);
                ElMessage.success('价格更新成功');
                priceDialogVisible.value = false;
                fetchSkus();
            } catch (e) {
                console.error('Failed to save prices', e);
                ElMessage.error('保存失败');
            } finally {
                priceSaving.value = false;
            }
        }

        async function toggleEnabled(row) {
            try {
                if (row.is_enabled) {
                    await ElMessageBox.confirm(`确定停用 SKU "${row.sku_code}" 的多币种功能？`, '确认操作');
                    await disableMultiCurrency(row.id);
                    ElMessage.success('已停用');
                } else {
                    ElMessage.info('请通过编辑价格来启用多币种功能');
                }
                fetchSkus();
            } catch (e) {
                if (e !== 'cancel') console.error(e);
            }
        }

        // ─── 批量更新 ───
        const batchInput = ref('');
        const batchLoading = ref(false);
        const batchResult = ref(null);

        function handleFileUpload(file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                batchInput.value = e.target.result;
            };
            reader.readAsText(file);
            return false;
        }

        async function doBatchUpdate() {
            if (!batchInput.value.trim()) {
                ElMessage.warning('请输入或上传要更新的数据');
                return;
            }
            let parsed;
            try {
                parsed = JSON.parse(batchInput.value);
                if (!Array.isArray(parsed)) throw new Error('必须是数组');
            } catch (e) {
                ElMessage.error('JSON 格式错误: ' + e.message);
                return;
            }
            batchLoading.value = true;
            batchResult.value = null;
            try {
                const res = await batchUpdatePrices(parsed);
                const body = res.data;
                batchResult.value = {
                    success: true,
                    message: body.message || '批量更新已完成',
                    data: body.data || {},
                };
                ElMessage.success('批量更新提交成功');
                batchInput.value = '';
            } catch (e) {
                const msg = e.response?.data?.message || e.message;
                batchResult.value = {
                    success: false,
                    message: '更新失败: ' + msg,
                    data: e.response?.data?.data || {},
                };
                ElMessage.error('批量更新失败');
            } finally {
                batchLoading.value = false;
            }
        }

        // ─── 生命周期 ───
        onMounted(() => {
            fetchDashboard();
        });

        return {
            router,
            activeTab,
            activeTabText,
            dashboard,
            dashboardLoading,
            coverageRate,
            currencyCoverage,
            skus,
            skusLoading,
            skusPagination,
            skuFilter,
            fetchSkus,
            resetSkuFilter,
            priceDialogVisible,
            selectedSku,
            priceForm,
            priceFormLoading,
            priceSaving,
            openPriceDialog,
            saveSkuPrices,
            toggleEnabled,
            batchInput,
            batchLoading,
            batchResult,
            handleFileUpload,
            doBatchUpdate,
        };
    },
});
</script>

<style scoped>
.multi-currency-pricing {
    padding: 16px;
}
.price-row + .price-row {
    margin-top: 8px;
}
.font-mono {
    font-family: 'Courier New', Courier, monospace;
}
</style>
