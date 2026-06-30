use crate::error::{HwtApiError, HwtNetworkError};
use crate::models::*;
use hmac::{Hmac, Mac};
use rand::Rng;
use reqwest::blocking::Client;
use sha2::Sha256;
use std::collections::HashMap;
use std::time::{SystemTime, UNIX_EPOCH};

type HmacSha256 = Hmac<Sha256>;

/// HWT License Client — Tauri/Rust SDK (M2-21)
pub struct HwtClient {
    api_key: String,
    host: String,
    secret_key: Option<String>,
    client: Client,
}

impl HwtClient {
    /// 创建 HWT 客户端
    pub fn new(api_key: impl Into<String>, host: Option<String>, secret_key: Option<String>) -> Self {
        Self {
            api_key: api_key.into(),
            host: host.unwrap_or_else(|| "https://api.huwutong.com".to_string()),
            secret_key,
            client: Client::builder()
                .timeout(std::time::Duration::from_secs(30))
                .build()
                .expect("Failed to create HTTP client"),
        }
    }

    // ── Public API ──

    /// 激活 License
    pub fn activate(
        &self,
        license_key: &str,
        device_info: HashMap<String, String>,
    ) -> Result<ActivationResult, Box<dyn std::error::Error>> {
        let payload = ActivatePayload {
            license_key: license_key.to_string(),
            device_info,
            timestamp: now_ts(),
        };
        self.post::<ActivationResult, ActivatePayload>("/api/license/activate", &payload)
    }

    /// 验证 License
    pub fn validate(
        &self,
        license_key: &str,
        device_info: HashMap<String, String>,
    ) -> Result<ValidationResult, Box<dyn std::error::Error>> {
        let payload = ValidatePayload {
            license_key: license_key.to_string(),
            device_info,
            timestamp: now_ts(),
        };
        self.post::<ValidationResult, ValidatePayload>("/api/license/validate", &payload)
    }

    /// 停用 License
    pub fn deactivate(
        &self,
        license_key: &str,
        device_info: HashMap<String, String>,
    ) -> Result<HwtResponse<serde_json::Value>, Box<dyn std::error::Error>> {
        let payload = DeactivatePayload {
            license_key: license_key.to_string(),
            device_info,
            timestamp: now_ts(),
        };
        self.post::<HwtResponse<serde_json::Value>, DeactivatePayload>("/api/license/deactivate", &payload)
    }

    /// 检查 Feature Flag
    pub fn check_feature(
        &self,
        license_key: &str,
        feature: &str,
    ) -> Result<HwtResponse<serde_json::Value>, Box<dyn std::error::Error>> {
        let payload = FeatureCheckPayload {
            license_key: license_key.to_string(),
            feature: feature.to_string(),
            timestamp: now_ts(),
        };
        self.post::<HwtResponse<serde_json::Value>, FeatureCheckPayload>(
            "/api/license/check-feature",
            &payload,
        )
    }

    /// 获取离线 License
    pub fn get_offline_license(
        &self,
        license_key: &str,
    ) -> Result<HwtResponse<serde_json::Value>, Box<dyn std::error::Error>> {
        let payload = OfflineGeneratePayload {
            license_key: license_key.to_string(),
            timestamp: now_ts(),
        };
        self.post::<HwtResponse<serde_json::Value>, OfflineGeneratePayload>(
            "/api/offline/generate",
            &payload,
        )
    }

    /// 验证离线 License
    pub fn verify_offline(
        &self,
        license_data: &str,
    ) -> Result<HwtResponse<serde_json::Value>, Box<dyn std::error::Error>> {
        let payload = OfflineVerifyPayload {
            license_data: license_data.to_string(),
            timestamp: now_ts(),
        };
        self.post::<HwtResponse<serde_json::Value>, OfflineVerifyPayload>(
            "/api/offline/verify",
            &payload,
        )
    }

    // ── Internal ──

    fn post<T, P>(&self, path: &str, payload: &P) -> Result<T, Box<dyn std::error::Error>>
    where
        T: serde::de::DeserializeOwned,
        P: serde::Serialize,
    {
        let url = format!("{}{}", self.host, path);
        let body = serde_json::to_string(payload)?;

        let mut headers = reqwest::header::HeaderMap::new();
        headers.insert(
            "Content-Type",
            "application/json".parse().unwrap(),
        );
        headers.insert(
            "Authorization",
            format!("Bearer {}", self.api_key).parse().unwrap(),
        );

        if let Some(ref secret) = self.secret_key {
            let nonce = generate_nonce();
            let timestamp = now_ts().to_string();
            let signature = compute_hmac(secret, &body, &nonce, &timestamp);

            headers.insert("X-Nonce", nonce.parse().unwrap());
            headers.insert("X-Timestamp", timestamp.parse().unwrap());
            headers.insert("X-Signature", signature.parse().unwrap());
        }

        let response = self
            .client
            .post(&url)
            .headers(headers)
            .body(body)
            .send()
            .map_err(|e| Box::new(HwtNetworkError(e.to_string())) as Box<dyn std::error::Error>)?;

        let status = response.status();
        let json: serde_json::Value = response
            .json()
            .map_err(|e| Box::new(HwtNetworkError(e.to_string())) as Box<dyn std::error::Error>)?;

        // 检查错误
        if let Some(error) = json.get("error") {
            if !error.is_null() {
                let code = error["code"].as_str().unwrap_or("UNKNOWN").to_string();
                let message = error["message"].as_str().unwrap_or("Unknown error").to_string();
                return Err(Box::new(HwtApiError::new(code, message, status.as_u16())));
            }
        }

        let result: T = serde_json::from_value(json)
            .map_err(|e| Box::new(HwtNetworkError(e.to_string())) as Box<dyn std::error::Error>)?;
        Ok(result)
    }
}

fn now_ts() -> i64 {
    SystemTime::now()
        .duration_since(UNIX_EPOCH)
        .unwrap()
        .as_secs() as i64
}

fn generate_nonce() -> String {
    let mut rng = rand::thread_rng();
    (0..32).map(|_| format!("{:02x}", rng.gen::<u8>())).collect()
}

fn compute_hmac(secret: &str, body: &str, nonce: &str, timestamp: &str) -> String {
    let data = format!("{}{}{}", body, nonce, timestamp);
    let mut mac = HmacSha256::new_from_slice(secret.as_bytes())
        .expect("HMAC key should be valid");
    mac.update(data.as_bytes());
    hex::encode(mac.finalize().into_bytes())
}
