<template>
  <div class="overview-dashboard">
    <div v-loading="loading">
      <div v-if="dashboard">
        <!-- Per-language progress -->
        <h3 class="text-base font-semibold text-gray-800 mb-4">各语言翻译进度</h3>
        <el-table :data="dashboard.per_language || []" stripe size="small" class="mb-6">
          <el-table-column label="语言" min-width="120">
            <template #default="{ row }">
              <span>{{ row.name }}</span>
              <span v-if="row.native_name" class="text-gray-400 ml-1">({{ row.native_name }})</span>
            </template>
          </el-table-column>
          <el-table-column label="代码" prop="locale" width="80" />
          <el-table-column label="总条目" prop="total" width="80" align="center" />
          <el-table-column label="已发布" prop="published" width="80" align="center" />
          <el-table-column label="缺失" width="80" align="center">
            <template #default="{ row }">
              <span :class="row.missing > 0 ? 'text-orange-500 font-medium' : 'text-green-500'">
                {{ row.missing }}
              </span>
            </template>
          </el-table-column>
          <el-table-column label="进度" min-width="180">
            <template #default="{ row }">
              <div class="flex items-center gap-2">
                <el-progress
                  :percentage="Math.round(row.progress)"
                  :stroke-width="12"
                  :status="row.progress >= 100 ? 'success' : undefined"
                  :text-inside="true"
                  class="flex-1"
                />
                <span class="text-xs text-gray-500 w-10 text-right">{{ Math.round(row.progress) }}%</span>
              </div>
            </template>
          </el-table-column>
        </el-table>

        <!-- Stats cards -->
        <h3 class="text-base font-semibold text-gray-800 mb-4">总体统计</h3>
        <el-row :gutter="16">
          <el-col :span="6">
            <el-statistic title="翻译总数" :value="dashboard.stats.total_translations" />
          </el-col>
          <el-col :span="6">
            <el-statistic title="已发布" :value="dashboard.stats.total_published" />
          </el-col>
          <el-col :span="6">
            <el-statistic title="缺失翻译" :value="dashboard.stats.total_missing" />
          </el-col>
          <el-col :span="6">
            <el-statistic title="AI 翻译" :value="dashboard.stats.total_auto_translated" />
          </el-col>
        </el-row>

        <!-- Namespace breakdown -->
        <h3 class="text-base font-semibold text-gray-800 mt-6 mb-4">命名空间</h3>
        <div v-if="dashboard.namespaces?.length" class="flex flex-wrap gap-2">
          <el-tag
            v-for="ns in dashboard.namespaces"
            :key="ns.id"
            class="namespace-tag"
          >
            {{ ns.label || ns.namespace }}
            <span class="ml-1 text-gray-400">({{ ns.key_count }})</span>
          </el-tag>
        </div>
        <p v-else class="text-sm text-gray-400">暂无命名空间，请先扫描语言文件。</p>
      </div>
      <el-empty v-else description="暂无数据" :image-size="120" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  dashboard: { type: Object, default: null },
  loading: { type: Boolean, default: false },
});
</script>

<style scoped>
.namespace-tag {
  cursor: default;
  transition: all 0.2s;
}
.namespace-tag:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}
</style>
