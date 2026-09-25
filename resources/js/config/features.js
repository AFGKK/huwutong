/**
 * 前端功能开关（与 config/features.php 对齐）
 * VITE_MVP_MODE=false 可恢复全量后台菜单
 */
export default {
  mvpMode: import.meta.env.VITE_MVP_MODE !== 'false',
}
