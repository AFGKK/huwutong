package com.huwutong.sdk;

/**
 * HWT API 异常 (M2-34 标准)
 */
public class HWTApiException extends RuntimeException {
    private final String code;
    private final int statusCode;

    public HWTApiException(String code, String message, int statusCode) {
        super("[" + code + "] " + message);
        this.code = code;
        this.statusCode = statusCode;
    }

    public String getCode() { return code; }
    public int getStatusCode() { return statusCode; }
}
