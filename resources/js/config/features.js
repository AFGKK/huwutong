/**
 * 前端功能开关默认值（构建期回退）
 *
 * 运行时以系统设置 mvp_mode_enabled 为准（经 /api/user 下发到 authStore）。
 * VITE_MVP_MODE 仅在尚未拉取用户信息时作为回退。
 */
export default {
  // 默认关闭：显示全部侧边栏；仅当显式 VITE_MVP_MODE=true 时构建期回退为开启
  mvpMode: import.meta.env.VITE_MVP_MODE === 'true',
}
