using System.Text.Json.Serialization;

namespace HuwutongSdk
{
    public class ActivationResult
    {
        [JsonPropertyName("success")]
        public bool Success { get; set; }

        [JsonPropertyName("data")]
        public ActivationData? Data { get; set; }

        [JsonPropertyName("message")]
        public string? Message { get; set; }

        [JsonPropertyName("error")]
        public ApiError? Error { get; set; }
    }

    public class ActivationData
    {
        [JsonPropertyName("license_key")]
        public string? LicenseKey { get; set; }

        [JsonPropertyName("expires_at")]
        public string? ExpiresAt { get; set; }

        [JsonPropertyName("device_id")]
        public string? DeviceId { get; set; }

        [JsonPropertyName("features")]
        public Dictionary<string, bool>? Features { get; set; }
    }

    public class ValidationResult
    {
        [JsonPropertyName("success")]
        public bool Success { get; set; }

        [JsonPropertyName("data")]
        public ValidationData? Data { get; set; }

        [JsonPropertyName("error")]
        public ApiError? Error { get; set; }

        public bool IsValid => Success && Data?.IsValid == true;
    }

    public class ValidationData
    {
        [JsonPropertyName("is_valid")]
        public bool IsValid { get; set; }

        [JsonPropertyName("license_key")]
        public string? LicenseKey { get; set; }

        [JsonPropertyName("status")]
        public string? Status { get; set; }

        [JsonPropertyName("expires_at")]
        public string? ExpiresAt { get; set; }

        [JsonPropertyName("days_remaining")]
        public int DaysRemaining { get; set; }

        [JsonPropertyName("features")]
        public Dictionary<string, bool>? Features { get; set; }
    }

    public class ApiError
    {
        [JsonPropertyName("code")]
        public string? Code { get; set; }

        [JsonPropertyName("message")]
        public string? Message { get; set; }
    }

    public class HwtResponse<T>
    {
        [JsonPropertyName("success")]
        public bool Success { get; set; }

        [JsonPropertyName("data")]
        public T? Data { get; set; }

        [JsonPropertyName("message")]
        public string? Message { get; set; }

        [JsonPropertyName("error")]
        public ApiError? Error { get; set; }
    }
}
