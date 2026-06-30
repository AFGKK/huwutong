package com.huwutong.sdk;

/**
 * License 验证结果
 */
public class ValidationResult {
    private boolean valid;
    private String licenseKey;
    private String status;
    private String expiresAt;
    private String machineId;
    private String fingerprintHash;
    private java.util.List<String> features;
    private String message;

    public boolean isValid() { return valid; }
    public void setValid(boolean valid) { this.valid = valid; }
    public String getLicenseKey() { return licenseKey; }
    public void setLicenseKey(String licenseKey) { this.licenseKey = licenseKey; }
    public String getStatus() { return status; }
    public void setStatus(String status) { this.status = status; }
    public String getExpiresAt() { return expiresAt; }
    public void setExpiresAt(String expiresAt) { this.expiresAt = expiresAt; }
    public String getMachineId() { return machineId; }
    public void setMachineId(String machineId) { this.machineId = machineId; }
    public String getFingerprintHash() { return fingerprintHash; }
    public void setFingerprintHash(String fingerprintHash) { this.fingerprintHash = fingerprintHash; }
    public java.util.List<String> getFeatures() { return features; }
    public void setFeatures(java.util.List<String> features) { this.features = features; }
    public String getMessage() { return message; }
    public void setMessage(String message) { this.message = message; }
}
