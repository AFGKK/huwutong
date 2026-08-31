<template>
  <div class="ai-feature-page">
    <el-page-header :content="t('pricing_optimizer_page.title')" @back="$router.push('/ai')" />
    <p class="text-muted" style="margin:8px 0 20px">{{ t('pricing_optimizer_page.desc') }}</p>

    <el-button type="primary" :loading="loading" @click="loadData" style="margin-bottom:16px">
      <el-icon><Refresh /></el-icon> {{ t('pricing_optimizer_page.fetch') }}
    </el-button>

    <el-card v-if="result" shadow="hover">
      <template #header>
        {{ t('pricing_optimizer_page.suggestions') }}
        <el-tag size="small" style="margin-left:8px">{{ t('pricing_optimizer_page.product_count', { n: result.product_count }) }}</el-tag>
      </template>

      <el-table v-if="result.suggestions?.length" :data="result.suggestions" stripe size="small">
        <el-table-column prop="name || product" :label="t('pricing_optimizer_page.cols.product')" min-width="140" />
        <el-table-column prop="current_price" :label="t('pricing_optimizer_page.cols.current')" width="120" />
        <el-table-column prop="suggested_price" :label="t('pricing_optimizer_page.cols.suggested')" width="120">
          <template #default="{ row }"><span style="color:#67c23a;font-weight:600">{{ row.suggested_price }}</span></template>
        </el-table-column>
        <el-table-column prop="reasoning || reason" :label="t('pricing_optimizer_page.cols.reason')" min-width="200" show-overflow-tooltip />
        <el-table-column prop="expected_impact" :label="t('pricing_optimizer_page.cols.impact')" min-width="160" />
      </el-table>
      <el-empty v-if="!result.suggestions?.length" :description="t('pricing_optimizer_page.no_suggestions')" />
    </el-card>

    <el-row :gutter="20" style="margin-top:20px" v-if="result">
      <el-col :span="12">
        <el-card v-if="result.discount_strategies?.length" shadow="hover">
          <template #header>{{ t('pricing_optimizer_page.discount_strategies') }}</template>
          <div v-for="(d, i) in result.discount_strategies" :key="i" class="strategy-item">
            <strong>{{ d.name }}</strong>
            <p>{{ d.description }}</p>
          </div>
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card v-if="result.insights?.length" shadow="hover">
          <template #header>{{ t('pricing_optimizer_page.insights') }}</template>
          <ul><li v-for="(item, i) in result.insights" :key="i">{{ item }}</li></ul>
        </el-card>
      </el-col>
    </el-row>

    <el-empty v-if="!loading && !result" :description="t('pricing_optimizer_page.empty')" />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { Refresh } from '@element-plus/icons-vue'
import { getPricingSuggestions } from '@/api/aiIntelligence'

const { t } = useI18n()
const loading = ref(false)
const result = ref(null)

async function loadData() {
  loading.value = true
  try {
    const res = await getPricingSuggestions()
    result.value = res.data
  } catch (_) { /* ignore */ }
  finally { loading.value = false }
}
</script>
<style scoped>
.ai-feature-page { padding: 20px; }
.text-muted { color: #909399; font-size: 14px; }
.strategy-item { margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #f0f0f0; }
.strategy-item:last-child { border-bottom: none; }
.strategy-item p { margin: 4px 0 0; font-size: 13px; color: #909399; }
ul { padding-left: 20px; }
li { margin-bottom: 6px; }
</style>
