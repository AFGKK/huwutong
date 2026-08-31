<template>
    <div class="compare-page" v-loading="loading">
        <div class="page-header">
            <div>
                <h2>{{ t('compare_page.title') }}</h2>
                <p class="text-muted">
                    {{ t('compare_page.subtitle') }}
                    <el-tag size="small" class="ml-2" :type="source === 'site_setting' ? 'success' : 'info'">
                        {{ t('compare_page.source_prefix') }}{{ sourceLabelText }}
                    </el-tag>
                </p>
            </div>
            <div class="header-actions">
                <el-button @click="handleReset" :loading="saving">{{ t('compare_page.reset_from_default') }}</el-button>
                <el-button type="primary" @click="handleSave" :loading="saving">{{ t('actions.save') }}</el-button>
            </div>
        </div>

        <el-row :gutter="16" class="mb-4">
            <el-col :span="6" v-for="(comp, key) in editConfig.competitors" :key="key">
                <el-card shadow="never" :body-style="{ padding: '12px' }">
                    <div class="comp-edit">
                        <el-input v-model="comp.name" size="small" :placeholder="t('compare_page.ph.name')" class="mb-1" />
                        <el-input v-model="comp.description" size="small" type="textarea" :rows="2" :placeholder="t('compare_page.ph.description')" class="mb-1" />
                        <el-input v-model="comp.website" size="small" :placeholder="t('compare_page.ph.website')" />
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="never">
            <template #header>
                <div class="card-header">
                    <span>{{ t('compare_page.matrix.title') }}</span>
                    <el-tag type="success" size="small">{{ t('compare_page.matrix.hint') }}</el-tag>
                </div>
            </template>
            <el-table :data="matrixRows" stripe max-height="520">
                <el-table-column prop="label" :label="t('compare_page.cols.dimension')" width="160" fixed />
                <el-table-column :label="t('app_name')" min-width="140" align="center">
                    <template #default="{ row }">
                        <el-switch
                            v-if="row.type === 'boolean'"
                            v-model="editConfig.comparison_data[row.key].huwutong"
                        />
                        <el-input
                            v-else
                            v-model="editConfig.comparison_data[row.key].huwutong"
                            size="small"
                            type="textarea"
                            :rows="2"
                        />
                    </template>
                </el-table-column>
                <el-table-column
                    v-for="(comp, key) in editConfig.competitors"
                    :key="key"
                    :label="comp.name || key"
                    min-width="140"
                    align="center"
                >
                    <template #default="{ row }">
                        <el-switch
                            v-if="row.type === 'boolean'"
                            v-model="editConfig.comparison_data[row.key][key]"
                        />
                        <el-input
                            v-else
                            v-model="editConfig.comparison_data[row.key][key]"
                            size="small"
                            type="textarea"
                            :rows="2"
                        />
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <el-card shadow="never" class="mt-4">
            <template #header><span>{{ t('compare_page.seo.section') }}</span></template>
            <el-form label-width="90px">
                <el-form-item :label="t('compare_page.seo.title')">
                    <el-input v-model="editConfig.seo.title" />
                </el-form-item>
                <el-form-item :label="t('compare_page.seo.description')">
                    <el-input v-model="editConfig.seo.description" type="textarea" :rows="2" />
                </el-form-item>
                <el-form-item :label="t('compare_page.seo.keywords')">
                    <el-input v-model="editConfig.seo.keywords" />
                </el-form-item>
            </el-form>
            <div class="seo-preview">
                <div class="seo-title">{{ editConfig.seo?.title }}</div>
                <div class="seo-url">{{ locationOrigin }}/compare</div>
                <div class="seo-desc">{{ editConfig.seo?.description }}</div>
            </div>
        </el-card>

        <el-card shadow="never" class="mt-4">
            <template #header><span>{{ t('compare_page.advantages.title', { brand: t('app_name') }) }}</span></template>
            <el-row :gutter="16">
                <el-col :span="8" v-for="adv in advantages" :key="adv.feature" class="mb-2">
                    <el-alert :title="adv.feature" :description="adv.description" type="success" show-icon :closable="false" />
                </el-col>
            </el-row>
            <el-empty v-if="advantages.length === 0" :description="t('compare_page.advantages.empty')" />
        </el-card>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { getCompareConfig, updateCompareConfig, resetCompareConfig, getAdvantages } from '@/api/comparePage';

const { t } = useI18n();

const loading = ref(false);
const saving = ref(false);
const source = ref('config');
const advantages = ref([]);
const editConfig = ref({
    competitors: {},
    dimensions: {},
    comparison_data: {},
    seo: { title: '', description: '', keywords: '' },
});

const sourceLabels = computed(() => ({
    site_setting: t('compare_page.source.site_setting'),
    config: t('compare_page.source.config'),
}));

const sourceLabelText = computed(() => sourceLabels.value[source.value] || source.value);

const matrixRows = computed(() => {
    const dims = editConfig.value.dimensions || {};
    return Object.keys(dims).map((key) => ({
        key,
        label: dims[key]?.label || key,
        type: dims[key]?.type || 'text',
    }));
});

const ensureComparisonCells = (cfg) => {
    const data = { ...(cfg.comparison_data || {}) };
    const dims = cfg.dimensions || {};
    const comps = Object.keys(cfg.competitors || {});
    Object.keys(dims).forEach((dimKey) => {
        if (!data[dimKey] || typeof data[dimKey] !== 'object') {
            data[dimKey] = {};
        }
        if (!('huwutong' in data[dimKey])) {
            data[dimKey].huwutong = dims[dimKey]?.type === 'boolean' ? false : '';
        }
        comps.forEach((ck) => {
            if (!(ck in data[dimKey])) {
                data[dimKey][ck] = dims[dimKey]?.type === 'boolean' ? false : '';
            }
        });
    });
    cfg.comparison_data = data;
    if (!cfg.seo) cfg.seo = { title: '', description: '', keywords: '' };
    return cfg;
};

const loadData = async () => {
    loading.value = true;
    try {
        const [cfgRes, advRes] = await Promise.all([getCompareConfig(), getAdvantages()]);
        const payload = cfgRes.data?.data || cfgRes.data || {};
        editConfig.value = ensureComparisonCells(JSON.parse(JSON.stringify(payload.config || {})));
        source.value = payload.source || 'config';
        if (advRes.data?.success) {
            advantages.value = advRes.data.data.advantages || [];
        }
    } catch (e) {
        ElMessage.error(t('compare_page.messages.load_failed'));
    } finally {
        loading.value = false;
    }
};

const handleSave = async () => {
    saving.value = true;
    try {
        const res = await updateCompareConfig(editConfig.value);
        const payload = res.data?.data || {};
        if (payload.config) {
            editConfig.value = ensureComparisonCells(JSON.parse(JSON.stringify(payload.config)));
        }
        source.value = 'site_setting';
        const advRes = await getAdvantages();
        if (advRes.data?.success) {
            advantages.value = advRes.data.data.advantages || [];
        }
        ElMessage.success(t('compare_page.messages.save_success'));
    } catch (e) {
        ElMessage.error(e?.response?.data?.message || t('messages.failed'));
    } finally {
        saving.value = false;
    }
};

const handleReset = async () => {
    try {
        await ElMessageBox.confirm(
            t('compare_page.reset_confirm.body'),
            t('compare_page.reset_confirm.title'),
            { type: 'warning' },
        );
    } catch {
        return;
    }
    saving.value = true;
    try {
        const res = await resetCompareConfig();
        const payload = res.data?.data || {};
        if (payload.config) {
            editConfig.value = ensureComparisonCells(JSON.parse(JSON.stringify(payload.config)));
        }
        source.value = 'site_setting';
        ElMessage.success(t('compare_page.messages.reset_success'));
        await loadData();
    } catch (e) {
        ElMessage.error(t('compare_page.messages.reset_failed'));
    } finally {
        saving.value = false;
    }
};

const locationOrigin = window.location.origin;

onMounted(() => loadData());
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 16px; }
.page-header h2 { margin: 0; }
.header-actions { display: flex; gap: 8px; flex-shrink: 0; }
.text-muted { color: #909399; font-size: 13px; margin: 4px 0 0 0; }
.ml-2 { margin-left: 8px; }
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.mb-2 { margin-bottom: 8px; }
.mb-1 { margin-bottom: 6px; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.comp-edit { text-align: left; }
.seo-preview { background: #f5f7fa; border-radius: 4px; padding: 12px; border: 1px solid #ebeef5; margin-top: 8px; }
.seo-title { color: #1a0dab; font-size: 18px; }
.seo-url { color: #006621; font-size: 14px; }
.seo-desc { color: #545454; font-size: 13px; margin-top: 4px; }
</style>
