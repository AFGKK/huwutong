<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import api from '../../api/promotions.js'

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
    ElMessage.success(`优惠码 ${code} 已复制`)
}

function fmtDate(d) { return d ? new Date(d).toLocaleDateString('zh-CN') : '-' }

const typeLabels = {
    flash_sale: '限时秒杀', bulk_discount: '批量折扣', bundle: '捆绑销售',
    x_for_y: '买X送Y', free_gift: '赠送礼品', tiered: '阶梯优惠',
}

onMounted(() => loadData())
</script>

<template>
    <div class="portal-promotions">
        <div class="page-header">
            <h2>优惠与促销</h2>
            <p class="text-gray-400 text-sm">查看当前可用的促销活动和优惠券</p>
        </div>

        <el-card shadow="never" v-loading="loading">
            <el-tabs v-model="activeTab">
                <el-tab-pane :label="`促销活动 (${promotions.length})`" name="promotions">
                    <el-empty v-if="!promotions.length" description="暂无可用促销活动" />
                    <el-row :gutter="16" v-else>
                        <el-col :span="8" v-for="p in promotions" :key="p.id" class="mb-4">
                            <el-card shadow="hover" class="promo-card">
                                <div class="promo-type-badge">{{ typeLabels[p.type] || p.type }}</div>
                                <div class="promo-name">{{ p.name }}</div>
                                <div class="promo-discount" v-if="p.discount_type === 'percentage'">{{ p.discount_value }}% OFF</div>
                                <div class="promo-discount" v-else-if="p.discount_type === 'fixed_amount'">¥{{ p.discount_value }} 减免</div>
                                <div class="promo-discount" v-else>免费</div>
                                <div class="promo-desc text-sm text-gray-400 mt-2">{{ p.description || '' }}</div>
                                <div class="promo-period text-xs text-gray-400 mt-2">{{ fmtDate(p.starts_at) }} ~ {{ p.ends_at ? fmtDate(p.ends_at) : '不限' }}</div>
                                <div class="promo-extra text-xs text-gray-400" v-if="p.min_order_amount">最低消费: ¥{{ p.min_order_amount }}</div>
                            </el-card>
                        </el-col>
                    </el-row>
                </el-tab-pane>

                <el-tab-pane :label="`我的优惠券 (${coupons.length})`" name="coupons">
                    <el-empty v-if="!coupons.length" description="暂无可用优惠券" />
                    <el-row :gutter="16" v-else>
                        <el-col :span="8" v-for="c in coupons" :key="c.id" class="mb-4">
                            <el-card shadow="hover" class="coupon-card">
                                <div class="coupon-code">{{ c.code }}</div>
                                <div class="coupon-name">{{ c.name }}</div>
                                <div class="coupon-value" v-if="c.type === 'percentage'">{{ c.value }}% 折扣</div>
                                <div class="coupon-value" v-else-if="c.type === 'fixed_amount'">¥{{ c.value }} 减免</div>
                                <div class="coupon-value" v-else>免费试用</div>
                                <div class="coupon-expiry text-xs text-gray-400 mt-2">到期: {{ c.expires_at ? fmtDate(c.expires_at) : '不限' }}</div>
                                <el-button size="small" class="mt-2" @click="copyCode(c.code)">复制优惠码</el-button>
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
.promo-type-badge { position: absolute; top: 12px; right: 12px; font-size: 11px; background: #409eff; color: #fff; padding: 2px 8px; border-radius: 4px; }
.promo-name, .coupon-code { font-size: 16px; font-weight: 600; margin-bottom: 8px; }
.promo-discount, .coupon-value { font-size: 24px; font-weight: 700; color: #f56c6c; }
.coupon-code { font-family: monospace; letter-spacing: 1px; color: #409eff; }
</style>
