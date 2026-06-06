<template>
    <router-view />
</template>

<script setup>
// Admin 应用根组件—基于路由的 layout 切换
</script>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html, body, #admin-app {
    height: 100%;
    width: 100%;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

/* 滚动条美化 */
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-thumb { background: #c0c4cc; border-radius: 3px; }
::-webkit-scrollbar-track { background: transparent; }
</style>
