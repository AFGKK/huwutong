// Package huwutong HWT License Go SDK (M2-19)
//
// 基于统一错误码标准 M2-34，提供 License 激活/验证/设备绑定功能。
//
// Usage:
//
//	client := huwutong.NewClient("your_api_key", "https://api.huwutong.com")
//	result, err := client.Activate("LICENSE-KEY", machineInfo)
//	if err != nil {
//	    var apiErr *huwutong.ApiError
//	    if errors.As(err, &apiErr) {
//	        log.Printf("API Error [%s]: %s", apiErr.Code, apiErr.Message)
//	    }
//	}
package huwutong

import (
	"bytes"
	"crypto/hmac"
	"crypto/md5"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"sort"
	"strings"
	"time"
)

const (
	// Version SDK 版本
	Version = "1.0.0"
	// DefaultTimeout 默认 HTTP 超时
	DefaultTimeout = 10 * time.Second
)

// ApiError API 错误 (M2-34 标准)
type ApiError struct {
	Code       string            `json:"code"`
	Message    string            `json:"message"`
	StatusCode int               `json:"status_code"`
	Details    map[string]interface{} `json:"details,omitempty"`
}

func (e *ApiError) Error() string {
	return fmt.Sprintf("[%s] %s", e.Code, e.Message)
}

// NetworkError 网络异常
type NetworkError struct {
	Err error
}

func (e *NetworkError) Error() string {
	return fmt.Sprintf("network error: %v", e.Err)
}

// ActivationResult 激活结果
type ActivationResult struct {
	Success    bool     `json:"success"`
	LicenseKey string   `json:"license_key"`
	ExpiresAt  string   `json:"expires_at,omitempty"`
	Features   []string `json:"features,omitempty"`
	Message    string   `json:"message,omitempty"`
}

// ValidationResult 验证结果
type ValidationResult struct {
	IsValid        bool     `json:"is_valid"`
	LicenseKey     string   `json:"license_key"`
	Status         string   `json:"status,omitempty"`
	ExpiresAt      string   `json:"expires_at,omitempty"`
	MachineID      string   `json:"machine_id,omitempty"`
	FingerprintHash string  `json:"fingerprint_hash,omitempty"`
	Features       []string `json:"features,omitempty"`
	Message        string   `json:"message,omitempty"`
}

// Client HWT License 客户端
type Client struct {
	apiKey    string
	host      string
	timeout   time.Duration
	userAgent string
	httpClient *http.Client
}

// NewClient 创建客户端
func NewClient(apiKey, host string) *Client {
	if host == "" {
		host = "https://api.huwutong.com"
	}
	return &Client{
		apiKey:    apiKey,
		host:      strings.TrimRight(host, "/"),
		timeout:   DefaultTimeout,
		userAgent: fmt.Sprintf("HWT-SDK-Go/%s", Version),
		httpClient: &http.Client{Timeout: DefaultTimeout},
	}
}

// Activate 激活 License
func (c *Client) Activate(licenseKey string, machineInfo map[string]interface{}) (*ActivationResult, error) {
	payload := map[string]interface{}{
		"license_key": licenseKey,
		"machine_info": machineInfo,
		"timestamp":   time.Now().Unix(),
		"nonce":       generateNonce(),
	}
	payload["signature"] = sign(payload, c.apiKey)

	var result ActivationResult
	data, err := c.post("/api/license/activate", payload)
	if err != nil {
		return nil, err
	}

	result.Success = true
	result.LicenseKey = licenseKey
	if expiresAt, ok := data["expires_at"].(string); ok {
		result.ExpiresAt = expiresAt
	}
	if features, ok := data["features"].([]interface{}); ok {
		for _, f := range features {
			if s, ok := f.(string); ok {
				result.Features = append(result.Features, s)
			}
		}
	}
	result.Message = getString(data, "message")
	return &result, nil
}

// Validate 验证 License
func (c *Client) Validate(licenseKey string, machineInfo map[string]interface{}) (*ValidationResult, error) {
	payload := map[string]interface{}{
		"license_key": licenseKey,
		"machine_info": machineInfo,
		"timestamp":   time.Now().Unix(),
		"nonce":       generateNonce(),
	}
	payload["signature"] = sign(payload, c.apiKey)

	data, err := c.post("/api/license/validate", payload)
	if err != nil {
		return nil, err
	}

	result := &ValidationResult{
		IsValid:    getBool(data, "valid"),
		LicenseKey: licenseKey,
		Status:     getString(data, "status"),
		ExpiresAt:  getString(data, "expires_at"),
		Message:    getString(data, "message"),
	}

	if id, ok := data["machine_id"].(string); ok {
		result.MachineID = id
	}
	if fh, ok := data["fingerprint_hash"].(string); ok {
		result.FingerprintHash = fh
	}
	if features, ok := data["features"].([]interface{}); ok {
		for _, f := range features {
			if s, ok := f.(string); ok {
				result.Features = append(result.Features, s)
			}
		}
	}
	return result, nil
}

// Deactivate 解除激活
func (c *Client) Deactivate(licenseKey string, machineInfo map[string]interface{}) error {
	payload := map[string]interface{}{
		"license_key": licenseKey,
		"machine_info": machineInfo,
		"timestamp":   time.Now().Unix(),
		"nonce":       generateNonce(),
	}
	payload["signature"] = sign(payload, c.apiKey)
	_, err := c.post("/api/license/deactivate", payload)
	return err
}

// CheckFeature 检查 Feature Flag
func (c *Client) CheckFeature(licenseKey, featureKey string) (bool, error) {
	data, err := c.post("/api/license/check-feature", map[string]interface{}{
		"license_key": licenseKey,
		"feature_key": featureKey,
	})
	if err != nil {
		return false, err
	}
	return getBool(data, "enabled"), nil
}

// GetLicenseInfo 查询 License 信息
func (c *Client) GetLicenseInfo(licenseKey string) (map[string]interface{}, error) {
	return c.get(fmt.Sprintf("/api/license/info/%s", licenseKey))
}

// ─── 内部方法 ───

func (c *Client) post(path string, payload interface{}) (map[string]interface{}, error) {
	body, err := json.Marshal(payload)
	if err != nil {
		return nil, fmt.Errorf("marshal error: %w", err)
	}

	req, err := http.NewRequest("POST", c.host+path, bytes.NewReader(body))
	if err != nil {
		return nil, &NetworkError{Err: err}
	}

	req.Header.Set("Authorization", "Bearer "+c.apiKey)
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Accept", "application/json")
	req.Header.Set("User-Agent", c.userAgent)

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, &NetworkError{Err: err}
	}
	defer resp.Body.Close()

	return c.handleResponse(resp)
}

func (c *Client) get(path string) (map[string]interface{}, error) {
	req, err := http.NewRequest("GET", c.host+path, nil)
	if err != nil {
		return nil, &NetworkError{Err: err}
	}

	req.Header.Set("Authorization", "Bearer "+c.apiKey)
	req.Header.Set("Accept", "application/json")
	req.Header.Set("User-Agent", c.userAgent)

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return nil, &NetworkError{Err: err}
	}
	defer resp.Body.Close()

	return c.handleResponse(resp)
}

func (c *Client) handleResponse(resp *http.Response) (map[string]interface{}, error) {
	respBody, err := io.ReadAll(resp.Body)
	if err != nil {
		return nil, fmt.Errorf("read error: %w", err)
	}

	var data map[string]interface{}
	if err := json.Unmarshal(respBody, &data); err != nil {
		return nil, &ApiError{
			Code:       "SYS_PARSE_ERROR",
			Message:    "Invalid JSON response",
			StatusCode: resp.StatusCode,
		}
	}

	if resp.StatusCode >= 400 {
		errData, _ := data["error"].(map[string]interface{})
		code, _ := errData["code"].(string)
		msg, _ := errData["message"].(string)
		if code == "" {
			code = "SYS_ERROR"
			msg, _ = data["message"].(string)
		}
		return nil, &ApiError{
			Code:       code,
			Message:    msg,
			StatusCode: resp.StatusCode,
		}
	}

	// 标准响应格式
	if d, ok := data["data"].(map[string]interface{}); ok {
		return d, nil
	}
	return data, nil
}

func generateNonce() string {
	h := md5.New()
	h.Write([]byte(fmt.Sprintf("%d", time.Now().UnixNano())))
	return hex.EncodeToString(h.Sum(nil))[:16]
}

func sign(payload map[string]interface{}, apiKey string) string {
	keys := make([]string, 0, len(payload))
	for k := range payload {
		if k != "signature" {
			keys = append(keys, k)
		}
	}
	sort.Strings(keys)

	var msg strings.Builder
	for i, k := range keys {
		if i > 0 {
			msg.WriteString("&")
		}
		msg.WriteString(fmt.Sprintf("%s=%v", k, payload[k]))
	}

	mac := hmac.New(sha256.New, []byte(apiKey))
	mac.Write([]byte(msg.String()))
	return hex.EncodeToString(mac.Sum(nil))
}

func getString(data map[string]interface{}, key string) string {
	if v, ok := data[key].(string); ok {
		return v
	}
	return ""
}

func getBool(data map[string]interface{}, key string) bool {
	if v, ok := data[key].(bool); ok {
		return v
	}
	return false
}
