using System;
using System.Collections.Generic;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Threading.Tasks;

namespace HuwutongSdk
{
    /// <summary>
    /// HWT License Client — C# SDK (M2-21)
    ///
    /// 基于统一错误码标准 M2-34
    /// 支持: activate / validate / deactivate / checkFeature / getOfflineLicense / verifyOffline
    /// </summary>
    public class HwtClient
    {
        private readonly string _apiKey;
        private readonly string _host;
        private readonly string _secretKey;
        private readonly HttpClient _http;
        private readonly JsonSerializerOptions _jsonOptions;

        /// <summary>
        /// 创建 HWT 客户端
        /// </summary>
        /// <param name="apiKey">API Key</param>
        /// <param name="host">API 地址，默认 https://api.huwutong.com</param>
        /// <param name="secretKey">HMAC 签名密钥（可选）</param>
        /// <param name="timeoutSeconds">超时秒数，默认 30</param>
        public HwtClient(string apiKey, string host = "https://api.huwutong.com", string? secretKey = null, int timeoutSeconds = 30)
        {
            _apiKey = apiKey ?? throw new ArgumentNullException(nameof(apiKey));
            _host = host?.TrimEnd('/') ?? throw new ArgumentNullException(nameof(host));
            _secretKey = secretKey ?? "";
            _http = new HttpClient { Timeout = TimeSpan.FromSeconds(timeoutSeconds) };
            _jsonOptions = new JsonSerializerOptions
            {
                PropertyNamingPolicy = JsonNamingPolicy.CamelCase
            };
        }

        #region Core API

        /// <summary>激活 License</summary>
        public async Task<ActivationResult> Activate(string licenseKey, Dictionary<string, string> deviceInfo)
        {
            var payload = new Dictionary<string, object>
            {
                ["license_key"] = licenseKey,
                ["device_info"] = deviceInfo,
                ["timestamp"] = DateTimeOffset.UtcNow.ToUnixTimeSeconds()
            };
            return await PostAsync<ActivationResult>("/api/license/activate", payload);
        }

        /// <summary>验证 License</summary>
        public async Task<ValidationResult> Validate(string licenseKey, Dictionary<string, string> deviceInfo)
        {
            var payload = new Dictionary<string, object>
            {
                ["license_key"] = licenseKey,
                ["device_info"] = deviceInfo,
                ["timestamp"] = DateTimeOffset.UtcNow.ToUnixTimeSeconds()
            };
            return await PostAsync<ValidationResult>("/api/license/validate", payload);
        }

        /// <summary>停用 License</summary>
        public async Task<HwtResponse<object>> Deactivate(string licenseKey, Dictionary<string, string> deviceInfo)
        {
            var payload = new Dictionary<string, object>
            {
                ["license_key"] = licenseKey,
                ["device_info"] = deviceInfo,
                ["timestamp"] = DateTimeOffset.UtcNow.ToUnixTimeSeconds()
            };
            return await PostAsync<HwtResponse<object>>("/api/license/deactivate", payload);
        }

        /// <summary>检查 Feature Flag</summary>
        public async Task<HwtResponse<FeatureCheckResult>> CheckFeature(string licenseKey, string featureKey)
        {
            var payload = new Dictionary<string, object>
            {
                ["license_key"] = licenseKey,
                ["feature"] = featureKey,
                ["timestamp"] = DateTimeOffset.UtcNow.ToUnixTimeSeconds()
            };
            return await PostAsync<HwtResponse<FeatureCheckResult>>("/api/license/check-feature", payload);
        }

        /// <summary>获取离线 License 文件</summary>
        public async Task<HwtResponse<OfflineLicenseData>> GetOfflineLicense(string licenseKey)
        {
            var payload = new Dictionary<string, object>
            {
                ["license_key"] = licenseKey,
                ["timestamp"] = DateTimeOffset.UtcNow.ToUnixTimeSeconds()
            };
            return await PostAsync<HwtResponse<OfflineLicenseData>>("/api/offline/generate", payload);
        }

        /// <summary>验证离线 License</summary>
        public async Task<HwtResponse<VerifyOfflineData>> VerifyOffline(string licenseData)
        {
            var payload = new Dictionary<string, object>
            {
                ["license_data"] = licenseData,
                ["timestamp"] = DateTimeOffset.UtcNow.ToUnixTimeSeconds()
            };
            return await PostAsync<HwtResponse<VerifyOfflineData>>("/api/offline/verify", payload);
        }

        #endregion

        #region Private

        private async Task<T> PostAsync<T>(string path, object payload)
        {
            var url = $"{_host}{path}";
            var json = JsonSerializer.Serialize(payload, _jsonOptions);
            var content = new StringContent(json, Encoding.UTF8, "application/json");

            var request = new HttpRequestMessage(HttpMethod.Post, url)
            {
                Content = content
            };
            request.Headers.Authorization = new AuthenticationHeaderValue("Bearer", _apiKey);
            request.Headers.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));

            if (!string.IsNullOrEmpty(_secretKey))
            {
                var nonce = Guid.NewGuid().ToString("N");
                var timestamp = DateTimeOffset.UtcNow.ToUnixTimeSeconds().ToString();
                var signature = ComputeHmac(json, nonce, timestamp);
                request.Headers.Add("X-Nonce", nonce);
                request.Headers.Add("X-Timestamp", timestamp);
                request.Headers.Add("X-Signature", signature);
            }

            try
            {
                var resp = await _http.SendAsync(request);
                var body = await resp.Content.ReadAsStringAsync();
                var result = JsonSerializer.Deserialize<ApiEnvelope<T>>(body, _jsonOptions);

                if (result?.Error != null)
                {
                    throw new HwtApiException(
                        result.Error.Code ?? "UNKNOWN",
                        result.Error.Message ?? "未知错误",
                        (int)resp.StatusCode
                    );
                }

                return result!.Data ?? throw new HwtApiException("NULL_DATA", "响应数据为空", (int)resp.StatusCode);
            }
            catch (HwtApiException) { throw; }
            catch (TaskCanceledException ex)
            {
                throw new HwtNetworkException("请求超时", ex);
            }
            catch (Exception ex)
            {
                throw new HwtNetworkException(ex.Message, ex);
            }
        }

        private string ComputeHmac(string json, string nonce, string timestamp)
        {
            var data = $"{json}{nonce}{timestamp}";
            var keyBytes = Encoding.UTF8.GetBytes(_secretKey);
            var dataBytes = Encoding.UTF8.GetBytes(data);
            var hash = HMACSHA256.HashData(keyBytes, dataBytes);
            return Convert.ToHexString(hash).ToLower();
        }

        #endregion
    }

    // 内部 API 信封
    internal class ApiEnvelope<T>
    {
        public bool Success { get; set; }
        public T? Data { get; set; }
        public ApiError? Error { get; set; }
    }

    public class FeatureCheckResult
    {
        public bool Enabled { get; set; }
        public string? Feature { get; set; }
    }

    public class OfflineLicenseData
    {
        public string? LicenseData { get; set; }
        public string? ExpiresAt { get; set; }
    }

    public class VerifyOfflineData
    {
        public bool IsValid { get; set; }
        public string? LicenseKey { get; set; }
        public string? Status { get; set; }
    }
}
