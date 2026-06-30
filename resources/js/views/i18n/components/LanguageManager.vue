<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-base font-semibold text-gray-800">支持的语言</h3>
      <el-button type="primary" size="small" @click="$emit('edit', null)">
        <el-icon class="mr-1"><Plus /></el-icon>
        添加语言
      </el-button>
    </div>

    <el-table :data="languages" v-loading="loading" stripe style="width: 100%">
      <el-table-column label="语言代码" prop="locale" width="120" />
      <el-table-column label="名称" min-width="150">
        <template #default="{ row }">
          <span class="mr-2">{{ row.flag || '' }}</span>
          <span>{{ row.name }}</span>
          <span v-if="row.native_name" class="text-gray-400 ml-1">({{ row.native_name }})</span>
        </template>
      </el-table-column>
      <el-table-column label="方向" width="80" align="center">
        <template #default="{ row }">
          <el-tag :type="row.is_rtl ? 'warning' : 'info'" size="small">
            {{ row.is_rtl ? 'RTL' : 'LTR' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="状态" width="80" align="center">
        <template #default="{ row }">
          <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">
            {{ row.is_active ? '启用' : '停用' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="默认" width="60" align="center">
        <template #default="{ row }">
          <el-tag v-if="row.is_default" type="primary" size="small">默认</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="排序" prop="sort_order" width="60" align="center" />
      <el-table-column label="操作" width="160" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" size="small" @click="$emit('edit', row)">编辑</el-button>
          <el-button
            link
            type="danger"
            size="small"
            :disabled="row.is_default"
            @click="$emit('delete', row.id)"
          >
            删除
          </el-button>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>

<script setup>
import { Plus } from '@element-plus/icons-vue';

defineProps({
  languages: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

defineEmits(['edit', 'delete', 'refresh']);
</script>
