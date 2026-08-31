<?php
// 欢迎页面 — 直接跳转到 landing 页面
// 实际首页由 PublicPageController::landingPage 渲染
header('Location: /landing');
exit;
