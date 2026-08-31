<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-base font-semibold text-gray-800">{{ t('language_manager.title') }}</h3>
      <el-button type="primary" size="small" @click="$emit('edit', null)">
        <el-icon class="mr-1"><Plus /></el-icon>
        {{ t('language_manager.add') }}
      </el-button>
    </div>

    <el-table :data="languages" v-loading="loading" stripe style="width: 100%">
      <el-table-column :label="t('language_manager.cols.code')" prop="locale" width="120" />
      <el-table-column :label="t('language_manager.cols.name')" min-width="150">
        <template #default="{ row }">
          <span class="mr-2">{{ row.flag || '' }}</span>
          <span>{{ row.name }}</span>
          <span v-if="row.native_name" class="text-gray-400 ml-1">({{ row.native_name }})</span>
        </template>
      </el-table-column>
      <el-table-column :label="t('language_manager.cols.direction')" width="80" align="center">
        <template #default="{ row }">
          <el-tag :type="row.is_rtl ? 'warning' : 'info'" size="small">
            {{ row.is_rtl ? 'RTL' : 'LTR' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('language_manager.cols.status')" width="80" align="center">
        <template #default="{ row }">
          <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">
            {{ row.is_active ? t('language_manager.active') : t('language_manager.inactive') }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('language_manager.cols.default')" width="60" align="center">
        <template #default="{ row }">
          <el-tag v-if="row.is_default" type="primary" size="small">{{ t('language_manager.default') }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('language_manager.cols.sort')" prop="sort_order" width="60" align="center" />
      <el-table-column :label="t('language_manager.cols.actions')" width="160" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" size="small" @click="$emit('edit', row)">{{ t('actions.edit') }}</el-button>
          <el-button
            link
            type="danger"
            size="small"
            :disabled="row.is_default"
            @click="$emit('delete', row.id)"
          >
            {{ t('actions.delete') }}
          </el-button>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { Plus } from '@element-plus/icons-vue';

const { t } = useI18n();

defineProps({
  languages: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

defineEmits(['edit', 'delete', 'refresh']);
</script>
