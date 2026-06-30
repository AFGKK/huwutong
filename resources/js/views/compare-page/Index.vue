<template>
    <div class="compare-page">
        <div class="page-header">
            <h2>竞品对比页管理</h2>
            <p class="text-muted">"互物通 vs Cryptlex / Localazy / Keygen / LicenseSpring" 功能对比矩阵</p>
        </div>

        <!-- 竞品列表 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="4" v-for="(comp, key) in competitors" :key="key">
                <el-card shadow="never" :body-style="{ padding: '12px' }">
                    <div class="comp-card">
                        <div class="comp-name">{{ comp.name }}</div>
                        <div class="comp-desc">{{ comp.description }}</div>
                        <el-link :href="comp.website" target="_blank" type="primary" size="small">访问官网</el-link>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 对比矩阵 -->
        <el-card shadow="never">
            <template #header>
                <div class="card-header">
                    <span>功能对比矩阵</span>
                    <el-tag type="success" size="small">✅ = 支持 &nbsp; ❌ = 不支持</el-tag>
                </div>
            </template>
            <el-table :data="matrix" stripe>
                <el-table-column prop="label" label="功能维度" width="160" fixed />
                <el-table-column label="互物通" width="120" align="center">
                    <template #default="{ row }">
                        <span v-if="row.huwutong?.supported" style="color:#67c23a;font-size:18px">✅</span>
                        <span v-else style="color:#c0c4cc;font-size:18px">❌</span>
                        <div class="cell-detail">{{ row.huwutong?.display }}</div>
                    </template>
                </el-table-column>
                <el-table-column v-for="(comp, key) in competitors" :key="key" :label="comp.name" width="120" align="center">
                    <template #default="{ row }">
                        <span v-if="row[key]?.supported" style="color:#67c23a;font-size:18px">✅</span>
                        <span v-else style="color:#c0c4cc;font-size:18px">❌</span>
                        <div class="cell-detail">{{ row[key]?.display }}</div>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- 优势摘要 -->
        <el-card shadow="never" class="mt-4">
            <template #header>
                <span>互物通优势摘要</span>
            </template>
            <el-row :gutter="16">
                <el-col :span="8" v-for="adv in advantages" :key="adv.feature" class="mb-2">
                    <el-alert :title="adv.feature" :description="adv.description" type="success" show-icon :closable="false" />
                </el-col>
            </el-row>
            <el-empty v-if="advantages.length === 0" description="暂无优势数据" />
        </el-card>

        <!-- SEO 预览 -->
        <el-card shadow="never" class="mt-4">
            <template #header><span>SEO 预览</span></template>
            <div class="seo-preview">
                <div class="seo-title">{{ seo?.title }}</div>
                <div class="seo-url">{{ locationOrigin }}/compare</div>
                <div class="seo-desc">{{ seo?.description }}</div>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { getComparison, getAdvantages } from '@/api/comparePage';

const competitors = ref({});
const matrix = ref([]);
const advantages = ref([]);
const seo = ref({});

const loadData = async () => {
    try {
        const [compRes, advRes] = await Promise.all([getComparison(), getAdvantages()]);
        if (compRes.data.success) {
            competitors.value = compRes.data.data.competitors || {};
            matrix.value = compRes.data.data.matrix || [];
            seo.value = compRes.data.data.seo || {};
        }
        if (advRes.data.success) {
            advantages.value = advRes.data.data.advantages || [];
        }
    } catch { /* ignore */ }
};

const locationOrigin = window.location.origin;

onMounted(() => loadData());
</script>

<style scoped>
.page-header { margin-bottom: 16px; }
.page-header h2 { margin: 0; }
.text-muted { color: #909399; font-size: 13px; margin: 4px 0 0 0; }
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.mb-2 { margin-bottom: 8px; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.comp-card { text-align: center; }
.comp-name { font-weight: 600; font-size: 15px; }
.comp-desc { font-size: 12px; color: #909399; margin: 4px 0; }
.cell-detail { font-size: 11px; color: #909399; margin-top: 2px; }
.seo-preview { background: #f5f7fa; border-radius: 4px; padding: 12px; border: 1px solid #ebeef5; }
.seo-title { color: #1a0dab; font-size: 18px; cursor: pointer; }
.seo-url { color: #006621; font-size: 14px; }
.seo-desc { color: #545454; font-size: 13px; margin-top: 4px; }
</style>
