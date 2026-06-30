<template>
  <el-dialog v-model="visible" title="🪙 打赏" width="420px" :close-on-click-modal="false" top="20vh">
    <div v-if="loading" style="text-align:center;padding:20px">
      <el-icon class="is-loading" :size="24"><Loading /></el-icon>
      <p style="color:#999;font-size:13px;margin-top:8px">加载中...</p>
    </div>
    <div v-else>
      <!-- 余额显示 -->
      <div class="tip-balance-bar">
        <span>💰 我的积分：<strong>{{ balance }}</strong></span>
        <el-button text size="small" type="primary" @click="$emit('viewTransactions')" style="font-size:12px">交易记录</el-button>
      </div>

      <!-- 预设金额 -->
      <div class="tip-amount-grid">
        <div v-for="amt in presetAmounts" :key="amt" class="tip-amount-btn"
          :class="{ selected: selectedAmount === amt }"
          @click="selectedAmount = amt">
          🪙 {{ amt }}
        </div>
        <div class="tip-amount-btn tip-custom-btn"
          :class="{ selected: !presetAmounts.includes(selectedAmount) && selectedAmount > 0 }"
          @click="isCustom = true">
          ✏️ 自定义
        </div>
      </div>

      <!-- 自定义输入 -->
      <div v-if="isCustom" style="margin-top:10px">
        <el-input v-model.number="customAmount" type="number" :min="1" :max="Math.min(99999, balance)" placeholder="输入积分数量..." size="small" @input="selectedAmount = customAmount" />
      </div>

      <!-- 附言 -->
      <div style="margin-top:12px">
        <el-input v-model="tipMessage" type="textarea" :rows="2" placeholder="给作者留个言（选填）..." maxlength="200" size="small" />
      </div>

      <!-- 提示 -->
      <div v-if="balance < 1" style="margin-top:10px;text-align:center;color:#e6a23c;font-size:13px">
        ⚠️ 积分不足，请联系管理员获取积分
      </div>

      <div class="tip-footer">
        <el-button @click="visible = false">取消</el-button>
        <el-button type="warning" :loading="submitting" :disabled="!selectedAmount || selectedAmount < 1 || balance < 1" @click="submitTip">
          🪙 打赏 {{ selectedAmount }} 积分
        </el-button>
      </div>
    </div>
  </el-dialog>
</template>

<script setup>
import { ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { Loading } from '@element-plus/icons-vue'
import apiClient from '@/api/client'

const props = defineProps({
  modelValue: Boolean,
  contentId: { type: Number, required: true },
  contentType: { type: String, required: true }, // oa_article | forum_post | blog_post
  receiverId: { type: Number, default: 0 },
})

const emit = defineEmits(['update:modelValue', 'tipped', 'viewTransactions'])

const visible = ref(props.modelValue)
const loading = ref(false)
const balance = ref(0)
const selectedAmount = ref(0)
const customAmount = ref(0)
const isCustom = ref(false)
const tipMessage = ref('')
const submitting = ref(false)
const presetAmounts = [1, 5, 10, 20, 50, 100]

watch(() => props.modelValue, (v) => {
  visible.value = v
  if (v) loadBalance()
})

watch(visible, (v) => emit('update:modelValue', v))

async function loadBalance() {
  loading.value = true
  try {
    const r = await apiClient.get('/points/balance')
    const data = r.data?.data || {}
    balance.value = data.balance || 0
  } catch { balance.value = 0 }
  finally { loading.value = false }
}

async function submitTip() {
  const amount = selectedAmount.value
  if (!amount || amount < 1) { ElMessage.warning('请选择积分数量'); return }
  if (balance.value < amount) { ElMessage.warning('积分不足'); return }

  submitting.value = true
  try {
    const r = await apiClient.post('/points/tip', {
      content_type: props.contentType,
      content_id: props.contentId,
      points: amount,
      message: tipMessage.value.trim() || null,
    })
    const data = r.data?.data || {}
    balance.value = data.balance || 0
    ElMessage.success(`🎉 打赏成功！已送出 ${amount} 积分`)
    selectedAmount.value = 0
    customAmount.value = 0
    tipMessage.value = ''
    isCustom.value = false
    emit('tipped', { points: amount, balance: data.balance })
    visible.value = false
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '打赏失败')
  } finally { submitting.value = false }
}
</script>

<style scoped>
.tip-balance-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 14px;
  background: #fff7e6;
  border-radius: 8px;
  margin-bottom: 14px;
  font-size: 14px;
}
.tip-balance-bar strong {
  color: #e6a23c;
  font-size: 18px;
}
.tip-amount-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
}
.tip-amount-btn {
  text-align: center;
  padding: 10px 4px;
  border: 1px solid #e4e7ed;
  border-radius: 8px;
  cursor: pointer;
  font-size: 14px;
  transition: all 0.15s;
  background: #fff;
}
.tip-amount-btn:hover {
  border-color: #e6a23c;
  color: #e6a23c;
}
.tip-amount-btn.selected {
  border-color: #e6a23c;
  background: #fff7e6;
  color: #e6a23c;
  font-weight: 600;
}
.tip-custom-btn {
  font-size: 13px;
  color: #909399;
}
.tip-footer {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 16px;
  padding-top: 12px;
  border-top: 1px solid #f0f0f0;
}
</style>
