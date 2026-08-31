<?php

/**
 * 本地大模型部署配置 (M3-49)
 *
 * 数据不出企业内网的私有化大模型部署方案
 * 支持：Ollama / vLLM
 */
return [

    /*
    |--------------------------------------------------------------------------
    | 本地 LLM 启用
    |--------------------------------------------------------------------------
    */
    'enabled' => env('LOCAL_LLM_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | 默认 Provider（站点设置未配置时的兜底）
    |--------------------------------------------------------------------------
    | LOCAL_LLM_ENABLED=true 时默认 ollama，否则 deepseek
    */
    'default_provider' => env(
        'LLM_DEFAULT_PROVIDER',
        env('LOCAL_LLM_ENABLED', false) ? 'ollama' : 'deepseek'
    ),

    /*
    |--------------------------------------------------------------------------
    | Ollama 配置
    |--------------------------------------------------------------------------
    */
    'ollama' => [
        'api_base' => env('OLLAMA_API_BASE', 'http://127.0.0.1:11434'),
        'default_model' => env('OLLAMA_DEFAULT_MODEL', 'qwen2.5:7b'),
        'health_endpoint' => '/api/tags',
        'timeout' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | D-37 推荐模型（setup.sh / ollama:setup --pull）
    |--------------------------------------------------------------------------
    */
    'recommended_models' => [
        'qwen2.5:7b',
        'qwen2.5:1.5b',
        'nomic-embed-text',
    ],

    /*
    |--------------------------------------------------------------------------
    | vLLM 配置
    |--------------------------------------------------------------------------
    */
    'vllm' => [
        'api_base' => env('VLLM_API_BASE', 'http://localhost:8000'),
        'default_model' => env('VLLM_DEFAULT_MODEL', 'Qwen/Qwen2-7B-Instruct'),
        'health_endpoint' => '/v1/models',
        'timeout' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | 模型管理
    |--------------------------------------------------------------------------
    */
    'models' => [
        'auto_download' => env('LOCAL_LLM_AUTO_DOWNLOAD', false),
        'download_dir' => env('LOCAL_LLM_DOWNLOAD_DIR', '/data/models'),
        'allowed_sources' => [
            'ollama' => ['library'],
            'huggingface' => ['Qwen', 'deepseek-ai', 'mistralai', 'meta-llama'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | GPU 监控
    |--------------------------------------------------------------------------
    */
    'gpu_monitoring' => [
        'enabled' => env('LOCAL_LLM_GPU_MONITORING', true),
        'nvidia_smi_path' => env('NVIDIA_SMI_PATH', 'nvidia-smi'),
        'poll_interval_seconds' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | 硬件要求
    |--------------------------------------------------------------------------
    */
    'hardware_requirements' => [
        'minimum_ram_gb' => 16,
        'recommended_ram_gb' => 32,
        'minimum_disk_gb' => 50,
        'gpu_required' => false,
        'recommended_vram_gb' => 8,
    ],
];
