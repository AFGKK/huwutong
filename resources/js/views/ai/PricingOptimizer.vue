<template>
  <div class="ai-feature-page">
    <el-page-header :content="'AI 智能定价建议'" @back="$router.push('/ai')" />
    <p class="text-muted" style="margin:8px 0 20px">基于需求弹性和销售数据分析最优定价、折扣策略和套餐组合</p>

    <el-button type="primary" :loading="loading" @click="loadData" style="margin-bottom:16px">
      <el-icon><Refresh /></el-icon> 获取定价建议
    </el-button>

    <el-card v-if="result" shadow="hover">
      <template #header>
        定价建议
        <el-tag size="small" style="margin-left:8px">{{ result.product_count }} 个产品</el-tag>
      </template>

      <el-table v-if="result.suggestions?.length" :data="result.suggestions" stripe size="small">
        <el-table-column prop="name || product" label="产品" min-width="140" />
        <el-table-column prop="current_price" label="当前价格" width="120" />
        <el-table-column prop="suggested_price" label="建议价格" width="120">
          <template #default="{ row }"><span style="color:#67c23a;font-weight:600">{{ row.suggested_price }}</span></template>
        </el-table-column>
        <el-table-column prop="reasoning || reason" label="理由" min-width="200" show-overflow-tooltip />
        <el-table-column prop="expected_impact" label="预期影响" min-width="160" />
      </el-table>
      <el-empty v-if="!result.suggestions?.length" description="暂无定价建议，需更多销售数据" />
    </el-card>

    <el-row :gutter="20" style="margin-top:20px" v-if="result">
      <el-col :span="12">
        <el-card v-if="result.discount_strategies?.length" shadow="hover">
          <template #header>折扣策略</template>
          <div v-for="(d, i) in result.discount_strategies" :key="i" class="strategy-item">
            <strong>{{ d.name }}</strong>
            <p>{{ d.description }}</p>
          </div>
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card v-if="result.insights?.length" shadow="hover">
          <template #header>洞察</template>
          <ul><li v-for="(item, i) in result.insights" :key="i">{{ item }}</li></ul>
        </el-card>
      </el-col>
    </el-row>

    <el-empty v-if="!loading && !result" description="点击「获取定价建议」开始分析" />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { Refresh } from '@element-plus/icons-vue'
import { getPricingSuggestions } from '@/api/aiIntelligence'

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
