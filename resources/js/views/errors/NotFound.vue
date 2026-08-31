<template>
    <div class="not-found">
        <el-result icon="warning" title="404" :sub-title="t('not_found.subtitle')">
            <template #extra>
                <el-button type="primary" @click="$router.push('/dashboard')">{{ t('not_found.back_home') }}</el-button>
            </template>
        </el-result>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
const { t } = useI18n()
</script>

<style scoped>
.not-found {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 60vh;
}
</style>
