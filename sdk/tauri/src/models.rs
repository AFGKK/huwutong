use serde::{Deserialize, Serialize};

#[derive(Debug, Serialize, Deserialize)]
pub struct ActivationResult {
    pub success: bool,
    pub data: Option<ActivationData>,
    pub message: Option<String>,
    pub error: Option<ApiError>,
}

#[derive(Debug, Serialize, Deserialize)]
pub struct ActivationData {
    pub license_key: Option<String>,
    pub expires_at: Option<String>,
    pub device_id: Option<String>,
    pub features: Option<std::collections::HashMap<String, bool>>,
}

#[derive(Debug, Serialize, Deserialize)]
pub struct ValidationResult {
    pub success: bool,
    pub data: Option<ValidationData>,
    pub error: Option<ApiError>,
}

impl ValidationResult {
    pub fn is_valid(&self) -> bool {
        self.success && self.data.as_ref().map_or(false, |d| d.is_valid)
    }
}

#[derive(Debug, Serialize, Deserialize)]
pub struct ValidationData {
    pub is_valid: bool,
    pub license_key: Option<String>,
    pub status: Option<String>,
    pub expires_at: Option<String>,
    pub days_remaining: Option<i32>,
    pub features: Option<std::collections::HashMap<String, bool>>,
}

#[derive(Debug, Serialize, Deserialize)]
pub struct ApiError {
    pub code: Option<String>,
    pub message: Option<String>,
}

#[derive(Debug, Serialize, Deserialize)]
pub struct HwtResponse<T> {
    pub success: bool,
    pub data: Option<T>,
    pub message: Option<String>,
    pub error: Option<ApiError>,
}

// 请求负载
#[derive(Debug, Serialize)]
pub(crate) struct ActivatePayload {
    pub license_key: String,
    pub device_info: std::collections::HashMap<String, String>,
    pub timestamp: i64,
}

#[derive(Debug, Serialize)]
pub(crate) struct ValidatePayload {
    pub license_key: String,
    pub device_info: std::collections::HashMap<String, String>,
    pub timestamp: i64,
}

#[derive(Debug, Serialize)]
pub(crate) struct DeactivatePayload {
    pub license_key: String,
    pub device_info: std::collections::HashMap<String, String>,
    pub timestamp: i64,
}

#[derive(Debug, Serialize)]
pub(crate) struct FeatureCheckPayload {
    pub license_key: String,
    pub feature: String,
    pub timestamp: i64,
}

#[derive(Debug, Serialize)]
pub(crate) struct OfflineGeneratePayload {
    pub license_key: String,
    pub timestamp: i64,
}

#[derive(Debug, Serialize)]
pub(crate) struct OfflineVerifyPayload {
    pub license_data: String,
    pub timestamp: i64,
}
