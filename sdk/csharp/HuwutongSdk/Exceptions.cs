namespace HuwutongSdk
{
    /// <summary>
    /// HWT API 异常 (M2-34 标准)
    /// </summary>
    public class HwtApiException : Exception
    {
        public string ErrorCode { get; }
        public int HttpStatus { get; }

        public HwtApiException(string errorCode, string message, int httpStatus = 400)
            : base($"[{errorCode}] {message}")
        {
            ErrorCode = errorCode;
            HttpStatus = httpStatus;
        }
    }

    /// <summary>
    /// HWT 网络异常
    /// </summary>
    public class HwtNetworkException : Exception
    {
        public HwtNetworkException(string message, Exception inner)
            : base($"网络错误: {message}", inner) { }
    }
}
