<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import api from '../../api/promotions.js'

const { t, locale } = useI18n()

const activeTab = ref('promotions')
const loading = ref(false)
const promotions = ref([])
const coupons = ref([])

async function loadData() {
    loading.value = true
    try {
        const [promoRes, coupRes] = await Promise.all([
            api.activePromotions(),
            api.customerCoupons(),
        ])
        promotions.value = promoRes.data.data?.promotions || promoRes.data.data || []
        coupons.value = coupRes.data.data || []
    } catch (e) {
        console.error('Failed to load promotions:', e)
    } finally { loading.value = false }
}

function copyCode(code) {
    navigator.clipboard.writeText(code)
    ElMessage.success(t('portal.coupon_copied', { code }))
}

function fmtDate(d) {
    if (!d) return '-'
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN'
    return new Date(d).toLocaleDateString(loc)
}

const typeLabels = computed(() => ({
    flash_sale: t('portal.promo_flash'),
    bulk_discount: t('portal.promo_bulk'),
    bundle: t('portal.promo_bundle'),
    x_for_y: t('portal.promo_x_for_y'),
    free_gift: t('portal.promo_gift'),
    tiered: t('portal.promo_tiered'),
}))

onMounted(() => loadData())
</script>

<template>
    <div class="portal-promotions">
        <div class="page-header">
            <h2>{{ $t('portal.promo_title') }}</h2>
            <p class="text-gray-400 text-sm">{{ $t('portal.promo_subtitle') }}</p>
        </div>

        <el-card shadow="never" v-loading="loading">
            <el-tabs v-model="activeTab">
                <el-tab-pane :label="$t('portal.promotions_tab', { n: promotions.length })" name="promotions">
                    <el-empty v-if="!promotions.length" :description="$t('portal.no_promotions')" />
                    <el-row :gutter="16" v-else>
                        <el-col :span="8" v-for="p in promotions" :key="p.id" class="mb-4">
                            <el-card shadow="hover" class="promo-card">
                                <div class="promo-type-badge">{{ typeLabels[p.type] || p.type }}</div>
                                <div class="promo-name">{{ p.name }}</div>
                                <div class="promo-discount" v-if="p.discount_type === 'percentage'">{{ p.discount_value }}% OFF</div>
                                <div class="promo-discount" v-else-if="p.discount_type === 'fixed_amount'">{{ $t('portal.fixed_off', { n: p.discount_value }) }}</div>
                                <div class="promo-discount" v-else>{{ $t('portal.free') }}</div>
                                <div class="promo-desc text-sm text-gray-400 mt-2">{{ p.description || '' }}</div>
                                <div class="promo-period text-xs text-gray-400 mt-2">{{ fmtDate(p.starts_at) }} ~ {{ p.ends_at ? fmtDate(p.ends_at) : $t('portal.unlimited') }}</div>
                                <div class="promo-extra text-xs text-gray-400" v-if="p.min_order_amount">{{ $t('portal.min_spend', { n: p.min_order_amount }) }}</div>
                            </el-card>
                        </el-col>
                    </el-row>
                </el-tab-pane>

                <el-tab-pane :label="$t('portal.coupons_tab', { n: coupons.length })" name="coupons">
                    <el-empty v-if="!coupons.length" :description="$t('portal.no_coupons')" />
                    <el-row :gutter="16" v-else>
                        <el-col :span="8" v-for="c in coupons" :key="c.id" class="mb-4">
                            <el-card shadow="hover" class="coupon-card">
                                <div class="coupon-code">{{ c.code }}</div>
                                <div class="coupon-name">{{ c.name }}</div>
                                <div class="coupon-value" v-if="c.type === 'percentage'">{{ $t('portal.percent_off', { n: c.value }) }}</div>
                                <div class="coupon-value" v-else-if="c.type === 'fixed_amount'">{{ $t('portal.fixed_off', { n: c.value }) }}</div>
                                <div class="coupon-value" v-else>{{ $t('portal.free_trial') }}</div>
                                <div class="coupon-expiry text-xs text-gray-400 mt-2">{{ $t('portal.expires_label', { date: c.expires_at ? fmtDate(c.expires_at) : $t('portal.unlimited') }) }}</div>
                                <el-button size="small" class="mt-2" @click="copyCode(c.code)">{{ $t('portal.copy_coupon') }}</el-button>
                            </el-card>
                        </el-col>
                    </el-row>
                </el-tab-pane>
            </el-tabs>
        </el-card>
    </div>
</template>

<style scoped>
.page-header { margin-bottom: 20px; }
.page-header h2 { margin: 0 0 4px; font-size: 20px; }
.promo-card, .coupon-card { position: relative; min-height: 140px; }
.promo-type-badge { position: absolute; top: 12px; right: 12px; font-size: 11px; background: #0f172a; color: #fff; padding: 2px 8px; border-radius: 4px; }
.promo-name, .coupon-code { font-size: 16px; font-weight: 600; margin-bottom: 8px; }
.promo-discount, .coupon-value { font-size: 24px; font-weight: 700; color: #f56c6c; }
.coupon-code { font-family: monospace; letter-spacing: 1px; color: #0f172a; }
</style>
