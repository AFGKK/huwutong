<template>
  <el-dialog v-model="visible" title="🪙 积分交易记录" width="550px" :close-on-click-modal="false" top="10vh">
    <div v-if="loading" style="text-align:center;padding:30px">
      <el-icon class="is-loading" :size="24"><Loading /></el-icon>
    </div>
    <div v-else>
      <!-- 余额概览 -->
      <div class="pts-balance-card">
        <div class="pts-balance-item">
          <span class="pts-balance-label">当前余额</span>
          <span class="pts-balance-value">{{ balance }}</span>
        </div>
        <div class="pts-balance-item">
          <span class="pts-balance-label">累计获得</span>
          <span class="pts-balance-value pts-earned">{{ totalEarned }}</span>
        </div>
        <div class="pts-balance-item">
          <span class="pts-balance-label">累计消费</span>
          <span class="pts-balance-value pts-spent">{{ totalSpent }}</span>
        </div>
      </div>

      <!-- 交易列表 -->
      <div v-if="transactions.length" class="pts-tx-list">
        <div v-for="tx in transactions" :key="tx.id" class="pts-tx-item">
          <div class="pts-tx-left">
            <span class="pts-tx-icon">{{ tx.type === 'earn' ? '➕' : tx.type === 'spend' ? '➖' : '🔄' }}</span>
            <div class="pts-tx-info">
              <div class="pts-tx-desc">{{ tx.description || tx.type }}</div>
              <div class="pts-tx-time">{{ formatTime(tx.created_at) }}</div>
            </div>
          </div>
          <div class="pts-tx-right">
            <span :class="['pts-tx-amount', tx.type === 'earn' ? 'pts-earned' : 'pts-spent']">
              {{ tx.type === 'earn' ? '+' : '-' }}{{ tx.amount }}
            </span>
          </div>
        </div>
      </div>
      <el-empty v-else description="暂无交易记录" :image-size="50" />

      <!-- 分页 -->
      <div v-if="hasMore" style="text-align:center;margin-top:12px">
        <el-button size="small" text @click="loadMore">加载更多</el-button>
      </div>
    </div>
  </el-dialog>
</template>

<script setup>
import { ref, watch } from 'vue'
import { Loading } from '@element-plus/icons-vue'
import apiClient from '@/api/client'

const props = defineProps({
  modelValue: Boolean,
})
const emit = defineEmits(['update:modelValue'])

const visible = ref(props.modelValue)
const loading = ref(false)
const balance = ref(0)
const totalEarned = ref(0)
const totalSpent = ref(0)
const transactions = ref([])
const page = ref(1)
const hasMore = ref(true)

watch(() => props.modelValue, (v) => {
  visible.value = v
  if (v) { page.value = 1; transactions.value = []; hasMore.value = true; loadTransactions() }
})
watch(visible, (v) => emit('update:modelValue', v))

async function loadTransactions() {
  loading.value = true
  try {
    const [balRes, txRes] = await Promise.all([
      apiClient.get('/points/balance'),
      apiClient.get('/points/transactions', { params: { per_page: 20, page: page.value } }),
    ])
    const bal = balRes.data?.data || {}
    balance.value = bal.balance || 0
    totalEarned.value = bal.total_earned || 0
    totalSpent.value = bal.total_spent || 0

    const txData = txRes.data?.data || {}
    const list = txData.data || []
    transactions.value = page.value === 1 ? list : [...transactions.value, ...list]
    const meta = txData
    hasMore.value = list.length >= 20
  } catch { /* ignore */ }
  finally { loading.value = false }
}

function loadMore() {
  page.value++
  loadTransactions()
}

function formatTime(date) {
  if (!date) return ''
  return new Date(date).toLocaleString('zh-CN', {
    month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit'
  })
}
</script>

<style scoped>
.pts-balance-card {
  display: flex;
  gap: 12px;
  margin-bottom: 16px;
}
.pts-balance-item {
  flex: 1;
  background: #f9f9f9;
  border-radius: 8px;
  padding: 12px;
  text-align: center;
}
.pts-balance-label {
  display: block;
  font-size: 12px;
  color: #909399;
  margin-bottom: 4px;
}
.pts-balance-value {
  font-size: 22px;
  font-weight: 700;
  color: #303133;
}
.pts-earned { color: #67c23a; }
.pts-spent { color: #e6a23c; }
.pts-tx-list {
  max-height: 400px;
  overflow-y: auto;
}
.pts-tx-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 0;
  border-bottom: 1px solid #f5f5f5;
}
.pts-tx-left {
  display: flex;
  align-items: center;
  gap: 10px;
}
.pts-tx-icon { font-size: 18px; }
.pts-tx-desc { font-size: 13px; color: #303133; }
.pts-tx-time { font-size: 11px; color: #c0c4cc; margin-top: 2px; }
.pts-tx-amount { font-size: 15px; font-weight: 600; }
</style>
