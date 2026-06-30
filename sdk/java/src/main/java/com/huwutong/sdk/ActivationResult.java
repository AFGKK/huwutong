package com.huwutong.sdk;

/**
 * License 激活结果
 */
public class ActivationResult {
    private boolean success;
    private String licenseKey;
    private String expiresAt;
    private java.util.List<String> features;
    private String message;

    public boolean isSuccess() { return success; }
    public void setSuccess(boolean success) { this.success = success; }
    public String getLicenseKey() { return licenseKey; }
    public void setLicenseKey(String licenseKey) { this.licenseKey = licenseKey; }
    public String getExpiresAt() { return expiresAt; }
    public void setExpiresAt(String expiresAt) { this.expiresAt = expiresAt; }
    public java.util.List<String> getFeatures() { return features; }
    public void setFeatures(java.util.List<String> features) { this.features = features; }
    public String getMessage() { return message; }
    public void setMessage(String message) { this.message = message; }
}
