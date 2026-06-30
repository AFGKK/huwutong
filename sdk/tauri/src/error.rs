use thiserror::Error;

/// HWT API 异常 (M2-34 标准)
#[derive(Error, Debug)]
#[error("[{code}] {message}")]
pub struct HwtApiError {
    pub code: String,
    pub message: String,
    pub http_status: u16,
}

impl HwtApiError {
    pub fn new(code: impl Into<String>, message: impl Into<String>, http_status: u16) -> Self {
        Self {
            code: code.into(),
            message: message.into(),
            http_status,
        }
    }
}

/// HWT 网络异常
#[derive(Error, Debug)]
#[error("Network error: {0}")]
pub struct HwtNetworkError(pub String);
