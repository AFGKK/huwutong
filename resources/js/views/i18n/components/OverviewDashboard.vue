<template>
  <div class="overview-dashboard">
    <div v-loading="loading">
      <div v-if="dashboard">
        <h3 class="text-base font-semibold text-gray-800 mb-4">{{ t('i18n_overview.lang_progress') }}</h3>
        <el-table :data="dashboard.per_language || []" stripe size="small" class="mb-6">
          <el-table-column :label="t('i18n_overview.cols.language')" min-width="120">
            <template #default="{ row }">
              <span>{{ row.name }}</span>
              <span v-if="row.native_name" class="text-gray-400 ml-1">({{ row.native_name }})</span>
            </template>
          </el-table-column>
          <el-table-column :label="t('i18n_overview.cols.code')" prop="locale" width="80" />
          <el-table-column :label="t('i18n_overview.cols.total')" prop="total" width="80" align="center" />
          <el-table-column :label="t('i18n_overview.cols.published')" prop="published" width="80" align="center" />
          <el-table-column :label="t('i18n_overview.cols.missing')" width="80" align="center">
            <template #default="{ row }">
              <span :class="row.missing > 0 ? 'text-orange-500 font-medium' : 'text-green-500'">
                {{ row.missing }}
              </span>
            </template>
          </el-table-column>
          <el-table-column :label="t('i18n_overview.cols.progress')" min-width="180">
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

        <h3 class="text-base font-semibold text-gray-800 mb-4">{{ t('i18n_overview.overall_stats') }}</h3>
        <el-row :gutter="16">
          <el-col :span="6">
            <el-statistic :title="t('i18n_overview.stats.total')" :value="dashboard.stats.total_translations" />
          </el-col>
          <el-col :span="6">
            <el-statistic :title="t('i18n_overview.stats.published')" :value="dashboard.stats.total_published" />
          </el-col>
          <el-col :span="6">
            <el-statistic :title="t('i18n_overview.stats.missing')" :value="dashboard.stats.total_missing" />
          </el-col>
          <el-col :span="6">
            <el-statistic :title="t('i18n_overview.stats.ai')" :value="dashboard.stats.total_auto_translated" />
          </el-col>
        </el-row>

        <h3 class="text-base font-semibold text-gray-800 mt-6 mb-4">{{ t('i18n_overview.namespaces') }}</h3>
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
        <p v-else class="text-sm text-gray-400">{{ t('i18n_overview.no_namespaces') }}</p>
      </div>
      <el-empty v-else :description="t('i18n_overview.empty')" :image-size="120" />
    </div>
  </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

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
