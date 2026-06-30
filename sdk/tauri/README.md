# Huwutong Tauri (Rust) SDK

M2-21: 基于统一错误码标准 M2-34

## 安装

```toml
# Cargo.toml
[dependencies]
huwutong-sdk = { path = "./sdk/tauri" }
```

## 快速开始

```rust
use huwutong_sdk::HwtClient;
use std::collections::HashMap;

fn main() -> Result<(), Box<dyn std::error::Error>> {
    let client = HwtClient::new(
        "your_api_key",
        Some("https://api.huwutong.com".to_string()),
        None,
    );

    // 激活
    let mut device_info = HashMap::new();
    device_info.insert("machine_id".to_string(), "unique-id".to_string());

    let activation = client.activate("LICENSE-KEY", device_info)?;
    println!("Activation: {}", activation.success);

    // 验证
    let mut device_info = HashMap::new();
    device_info.insert("machine_id".to_string(), "unique-id".to_string());

    let validation = client.validate("LICENSE-KEY", device_info)?;
    println!("Valid: {}", validation.is_valid());

    Ok(())
}
```

## Tauri 命令集成

```rust
// src-tauri/src/main.rs
use huwutong_sdk::HwtClient;

#[tauri::command]
async fn validate_license(key: String) -> Result<bool, String> {
    let client = HwtClient::new(
        std::env::var("HWT_API_KEY").unwrap(),
        None, None,
    );
    let mut info = std::collections::HashMap::new();
    info.insert("machine_id".into(), machine_uuid::get().to_string());

    let result = client.validate(&key, info)
        .map_err(|e| e.to_string())?;
    Ok(result.is_valid())
}
```
