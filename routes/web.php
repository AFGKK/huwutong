<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Vue 管理后台 SPA（所有 /admin/* 路由指向 admin Blade 模板）
Route::get('/admin/{path?}', function () {
    return view('admin');
})->where('path', '.*');

// 客户门户 SPA（所有 /portal/* 路由指向 admin Blade 模板）
Route::get('/portal/{path?}', function () {
    return view('admin');
})->where('path', '.*');
