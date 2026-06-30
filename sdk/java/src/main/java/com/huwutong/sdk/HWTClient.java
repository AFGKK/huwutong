package com.huwutong.sdk;

import com.fasterxml.jackson.core.type.TypeReference;
import com.fasterxml.jackson.databind.ObjectMapper;

import java.io.IOException;
import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.time.Duration;
import java.util.*;

/**
 * HWT License Java 客户端 (M2-20)
 * <p>
 * 基于统一错误码标准 M2-34，提供 License 激活/验证/设备绑定功能。
 * <p>
 * 用法:
 * <pre>{@code
 * HWTClient client = new HWTClient("your_api_key");
 *
 * // 激活 License
 * ActivationResult result = client.activate("LICENSE-KEY", Map.of("machine_id", "abc123"));
 * System.out.println("激活成功: " + result.getExpiresAt());
 *
 * // 验证 License
 * ValidationResult vr = client.validate("LICENSE-KEY", Map.of("machine_id", "abc123"));
 * if (vr.isValid()) {
 *     System.out.println("License 有效");
 * }
 * }</pre>
 */
public class HWTClient {

    public static final String VERSION = "1.0.0";
    private static final int DEFAULT_TIMEOUT = 10;

    private final String apiKey;
    private final String host;
    private final HttpClient httpClient;
    private final ObjectMapper objectMapper;

    /**
     * 创建客户端
     *
     * @param apiKey API Key
     */
    public HWTClient(String apiKey) {
        this(apiKey, "https://api.huwutong.com");
    }

    /**
     * 创建客户端
     *
     * @param apiKey API Key
     * @param host   API 主机地址
     */
    public HWTClient(String apiKey, String host) {
        this.apiKey = apiKey;
        this.host = host.endsWith("/") ? host.substring(0, host.length() - 1) : host;
        this.httpClient = HttpClient.newBuilder()
                .connectTimeout(Duration.ofSeconds(DEFAULT_TIMEOUT))
                .build();
        this.objectMapper = new ObjectMapper();
    }

    /**
     * 激活 License
     *
     * @param licenseKey License Key
     * @param machineInfo 设备信息
     * @return 激活结果
     */
    public ActivationResult activate(String licenseKey, Map<String, Object> machineInfo) {
        Map<String, Object> payload = buildPayload(licenseKey, machineInfo);
        Map<String, Object> data = post("/api/license/activate", payload);

        ActivationResult result = new ActivationResult();
        result.setSuccess(true);
        result.setLicenseKey(licenseKey);
        result.setExpiresAt(getString(data, "expires_at"));
        result.setFeatures(getList(data, "features"));
        result.setMessage(getString(data, "message"));
        return result;
    }

    /**
     * 验证 License
     *
     * @param licenseKey License Key
     * @param machineInfo 设备信息
     * @return 验证结果
     */
    public ValidationResult validate(String licenseKey, Map<String, Object> machineInfo) {
        Map<String, Object> payload = buildPayload(licenseKey, machineInfo);
        Map<String, Object> data = post("/api/license/validate", payload);

        ValidationResult result = new ValidationResult();
        result.setValid(getBoolean(data, "valid"));
        result.setLicenseKey(licenseKey);
        result.setStatus(getString(data, "status"));
        result.setExpiresAt(getString(data, "expires_at"));
        result.setMachineId(getString(data, "machine_id"));
        result.setFingerprintHash(getString(data, "fingerprint_hash"));
        result.setFeatures(getList(data, "features"));
        result.setMessage(getString(data, "message"));
        return result;
    }

    /**
     * 解除激活
     *
     * @param licenseKey License Key
     * @param machineInfo 设备信息
     */
    public void deactivate(String licenseKey, Map<String, Object> machineInfo) {
        Map<String, Object> payload = buildPayload(licenseKey, machineInfo);
        post("/api/license/deactivate", payload);
    }

    /**
     * 检查 Feature Flag
     *
     * @param licenseKey License Key
     * @param featureKey Feature Key
     * @return 是否启用
     */
    public boolean checkFeature(String licenseKey, String featureKey) {
        Map<String, Object> payload = new HashMap<>();
        payload.put("license_key", licenseKey);
        payload.put("feature_key", featureKey);

        Map<String, Object> data = post("/api/license/check-feature", payload);
        return getBoolean(data, "enabled");
    }

    // ─── 内部方法 ───

    private Map<String, Object> buildPayload(String licenseKey, Map<String, Object> machineInfo) {
        Map<String, Object> payload = new HashMap<>();
        payload.put("license_key", licenseKey);
        payload.put("machine_info", machineInfo != null ? machineInfo : new HashMap<>());
        payload.put("timestamp", System.currentTimeMillis() / 1000);
        payload.put("nonce", generateNonce());
        payload.put("signature", sign(payload));
        return payload;
    }

    private Map<String, Object> post(String path, Map<String, Object> payload) {
        try {
            String json = objectMapper.writeValueAsString(payload);

            HttpRequest request = HttpRequest.newBuilder()
                    .uri(URI.create(host + path))
                    .header("Authorization", "Bearer " + apiKey)
                    .header("Content-Type", "application/json")
                    .header("Accept", "application/json")
                    .header("User-Agent", "HWT-SDK-Java/" + VERSION)
                    .timeout(Duration.ofSeconds(DEFAULT_TIMEOUT))
                    .POST(HttpRequest.BodyPublishers.ofString(json, StandardCharsets.UTF_8))
                    .build();

            HttpResponse<String> response = httpClient.send(request, HttpResponse.BodyHandlers.ofString());
            return handleResponse(response);
        } catch (IOException | InterruptedException e) {
            throw new HWTNetworkException("Network error: " + e.getMessage(), e);
        }
    }

    private Map<String, Object> handleResponse(HttpResponse<String> response) {
        try {
            Map<String, Object> data = objectMapper.readValue(response.body(),
                    new TypeReference<Map<String, Object>>() {});

            if (response.statusCode() >= 400) {
                Map<String, Object> errorData = (Map<String, Object>) data.getOrDefault("error", data);
                String code = getString(errorData, "code");
                String message = getString(errorData, "message");
                throw new HWTApiException(
                        code != null ? code : "SYS_ERROR",
                        message != null ? message : "Unknown error",
                        response.statusCode()
                );
            }

            // 标准响应格式
            Map<String, Object> d = (Map<String, Object>) data.get("data");
            return d != null ? d : data;
        } catch (HWTApiException e) {
            throw e;
        } catch (Exception e) {
            throw new HWTApiException("SYS_PARSE_ERROR", "Invalid JSON response", response.statusCode());
        }
    }

    private String generateNonce() {
        try {
            MessageDigest md = MessageDigest.getInstance("MD5");
            byte[] hash = md.digest(String.valueOf(System.nanoTime()).getBytes());
            StringBuilder hex = new StringBuilder();
            for (byte b : hash) {
                hex.append(String.format("%02x", b));
            }
            return hex.substring(0, 16);
        } catch (NoSuchAlgorithmException e) {
            return UUID.randomUUID().toString().substring(0, 16);
        }
    }

    private String sign(Map<String, Object> payload) {
        List<String> keys = new ArrayList<>(payload.keySet());
        keys.remove("signature");
        Collections.sort(keys);

        StringBuilder msg = new StringBuilder();
        for (int i = 0; i < keys.size(); i++) {
            if (i > 0) msg.append("&");
            msg.append(keys.get(i)).append("=").append(payload.get(keys.get(i)));
        }

        try {
            javax.crypto.Mac mac = javax.crypto.Mac.getInstance("HmacSHA256");
            javax.crypto.spec.SecretKeySpec keySpec = new javax.crypto.spec.SecretKeySpec(
                    apiKey.getBytes(StandardCharsets.UTF_8), "HmacSHA256");
            mac.init(keySpec);
            byte[] hash = mac.doFinal(msg.toString().getBytes(StandardCharsets.UTF_8));
            StringBuilder hex = new StringBuilder();
            for (byte b : hash) {
                hex.append(String.format("%02x", b));
            }
            return hex.toString();
        } catch (Exception e) {
            throw new RuntimeException("Sign error", e);
        }
    }

    private String getString(Map<String, Object> data, String key) {
        Object v = data.get(key);
        return v instanceof String ? (String) v : null;
    }

    private boolean getBoolean(Map<String, Object> data, String key) {
        Object v = data.get(key);
        return v instanceof Boolean && (Boolean) v;
    }

    private List<String> getList(Map<String, Object> data, String key) {
        Object v = data.get(key);
        if (v instanceof List) {
            List<String> result = new ArrayList<>();
            for (Object item : (List<?>) v) {
                if (item instanceof String) result.add((String) item);
            }
            return result;
        }
        return null;
    }
}
