export class HwtApiError extends Error {
  code: string;
  status: number;
  constructor(code: string, message: string, status?: number);
}

export class HwtNetworkError extends Error {
  constructor(message: string);
}

export interface HwtClientOptions {
  apiKey: string;
  host?: string;
  secretKey?: string;
  timeout?: number;
}

export interface ActivationResult {
  success: boolean;
  data?: {
    license_key: string;
    expires_at: string;
    device_id: string;
    features?: Record<string, boolean>;
  };
  message?: string;
}

export interface ValidationResult {
  success: boolean;
  isValid: boolean;
  data?: {
    is_valid: boolean;
    license_key: string;
    status: string;
    expires_at: string;
    days_remaining: number;
    features?: Record<string, boolean>;
  };
}

export class HwtClient {
  constructor(options: HwtClientOptions);
  activate(licenseKey: string, deviceInfo?: Record<string, string>): Promise<ActivationResult>;
  validate(licenseKey: string, deviceInfo?: Record<string, string>): Promise<ValidationResult>;
  deactivate(licenseKey: string, deviceInfo?: Record<string, string>): Promise<any>;
  checkFeature(licenseKey: string, featureKey: string): Promise<any>;
  getOfflineLicense(licenseKey: string): Promise<any>;
  verifyOffline(licenseData: string): Promise<any>;
}
