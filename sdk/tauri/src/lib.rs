/// HWT License SDK for Tauri/Rust (M2-21)
///
/// 基于统一错误码标准 M2-34
/// 支持: activate / validate / deactivate / checkFeature / getOfflineLicense / verifyOffline

pub mod error;
pub mod models;
pub mod client;

pub use client::HwtClient;
pub use error::{HwtApiError, HwtNetworkError};
pub use models::{ActivationResult, ActivationData, ValidationResult, ValidationData};
