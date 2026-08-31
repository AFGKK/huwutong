use huwutong_sdk::HwtClient;
use serde_json::Value;
use std::collections::HashMap;

#[tauri::command]
fn lookup_license(api_base: String, license_key: String) -> Result<Value, String> {
    let base = api_base.trim_end_matches('/');
    let url = format!("{base}/api/license/public-lookup");

    let client = reqwest::blocking::Client::builder()
        .timeout(std::time::Duration::from_secs(15))
        .build()
        .map_err(|e| e.to_string())?;

    let response = client
        .post(&url)
        .json(&serde_json::json!({ "license_key": license_key.trim() }))
        .send()
        .map_err(|e| format!("请求失败: {e}"))?;

    response.json::<Value>().map_err(|e| e.to_string())
}

#[tauri::command]
fn validate_license(api_base: String, license_key: String, api_key: String) -> Result<bool, String> {
    let client = HwtClient::new(api_key, Some(api_base.trim_end_matches('/').to_string()), None);

    let mut device_info = HashMap::new();
    device_info.insert("machine_id".into(), "tauri-desktop".into());
    device_info.insert("platform".into(), std::env::consts::OS.into());

    client
        .validate(license_key.trim(), device_info)
        .map(|r| r.is_valid())
        .map_err(|e| e.to_string())
}

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .invoke_handler(tauri::generate_handler![lookup_license, validate_license])
        .run(tauri::generate_context!())
        .expect("error while running tauri application");
}
