<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MfaController;
use App\Http\Controllers\Api\SSOController;
use App\Http\Controllers\Api\LegalConsentController;
use App\Http\Controllers\Api\PasswordPolicyController;
use App\Http\Controllers\Api\AccountDeletionAdminController;
use App\Http\Controllers\Api\AppealController;
use App\Http\Controllers\Api\MerkleChainController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Controllers\Api\ImpersonateController;

// ─── 公开认证 ───

Route::post('/token/introspect', [TokenController::class, 'introspect']);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/phone/send-code', [AuthController::class, 'sendPhoneCode']);
Route::post('/phone/login', [AuthController::class, 'phoneLogin']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// OAuth login (public)
Route::post('/oauth/login', [AuthController::class, 'oauthLogin']);
Route::get('/oauth/available-providers', [AuthController::class, 'availableOauthProviders']);

// Magic Link (public)
Route::post('/auth/magic-link/send', [AuthController::class, 'sendMagicLink']);
Route::get('/auth/magic-link/verify', [AuthController::class, 'verifyMagicLink'])->name('magic-link.verify');

// WebAuthn / Passkey (public)
Route::post('/auth/webauthn/login/options', [AuthController::class, 'webauthnLoginOptions']);
Route::post('/auth/webauthn/login/verify', [AuthController::class, 'webauthnLoginVerify']);

// QR Scan login (public)
Route::post('/auth/qrcode/session', [AuthController::class, 'createQrSession']);
Route::get('/auth/qrcode/session/{sessionId}', [AuthController::class, 'pollQrSession']);
Route::post('/auth/qrcode/confirm', [AuthController::class, 'confirmQrSession']);

// Legal consents (public)
Route::get('/legal-consents', [AuthController::class, 'getLegalConsents']);

// MFA aware login (public)
Route::post('/mfa/login', [MfaController::class, 'mfaLogin']);
Route::post('/mfa/check-required', [MfaController::class, 'checkRequired']);

// SSO
Route::post('/sso/callback', [SSOController::class, 'callback'])->name('sso.login');

// ─── 受保护认证管理 ───

Route::middleware(['auth:sanctum', 'apm', 'tenant'])->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/token/refresh', [AuthController::class, 'refreshToken']);
    Route::post('/token/revoke', [TokenController::class, 'revokeCurrent']);
    Route::get('/tokens', [TokenController::class, 'myTokens']);

    // Email verification
    Route::post('/email/verify/send', [AuthController::class, 'sendEmailVerification']);
    Route::post('/email/verify', [AuthController::class, 'verifyEmail']);

    // Password management
    Route::post('/password/change', [AuthController::class, 'changePassword']);

    // WebAuthn / Passkey 管理（需认证）
    Route::post('/auth/webauthn/register/options', [AuthController::class, 'webauthnRegisterOptions']);
    Route::post('/auth/webauthn/register/verify', [AuthController::class, 'webauthnRegisterVerify']);
    Route::get('/auth/webauthn/credentials', [AuthController::class, 'webauthnCredentials']);
    Route::delete('/auth/webauthn/credentials/{id}', [AuthController::class, 'webauthnDeleteCredential']);

    Route::middleware('mask')->group(function () {
        // Session management
        Route::get('/sessions', [AuthController::class, 'sessions']);
        Route::delete('/sessions/{tokenId}', [AuthController::class, 'revokeSession'])->whereNumber('tokenId');

        // Admin Session 管理
        Route::get('/admin/sessions/dashboard', [AuthController::class, 'adminSessionDashboard']);
        Route::get('/admin/sessions', [AuthController::class, 'adminSessions']);
        Route::get('/admin/sessions/{tokenId}', [AuthController::class, 'adminSessionDetail'])->whereNumber('tokenId');
        Route::post('/admin/sessions/{tokenId}/terminate', [AuthController::class, 'adminTerminateSession'])->whereNumber('tokenId');
        Route::post('/admin/sessions/batch-terminate', [AuthController::class, 'adminBatchTerminate']);
        Route::post('/admin/sessions/terminate-user/{userId}', [AuthController::class, 'adminTerminateUserSessions'])->whereNumber('userId');

        // Device trust
        Route::post('/devices/trust', [AuthController::class, 'trustDevice']);
        Route::get('/devices/trusted', [AuthController::class, 'trustedDevices']);
        Route::delete('/devices/trusted/{deviceId}', [AuthController::class, 'removeTrustedDevice'])->whereNumber('deviceId');
        Route::delete('/devices/trusted', [AuthController::class, 'clearTrustedDevices']);
        Route::post('/devices/check', [AuthController::class, 'checkDevice']);

        // Password policy & account lock management
        Route::get('/password-policy/config', [PasswordPolicyController::class, 'getConfig']);
        Route::put('/password-policy/config', [PasswordPolicyController::class, 'updateConfig']);
        Route::get('/password-policy/locked-accounts', [PasswordPolicyController::class, 'lockedAccounts']);
        Route::post('/password-policy/unlock', [PasswordPolicyController::class, 'unlockAccount']);

        // Legal consent management (admin)
        Route::get('/legal-consents', [LegalConsentController::class, 'index']);
        Route::post('/legal-consents', [LegalConsentController::class, 'store']);
        Route::get('/legal-consents/{legalConsent}', [LegalConsentController::class, 'show'])->whereNumber('legalConsent');
        Route::put('/legal-consents/{legalConsent}', [LegalConsentController::class, 'update'])->whereNumber('legalConsent');
        Route::post('/legal-consents/{legalConsent}/publish', [LegalConsentController::class, 'publish'])->whereNumber('legalConsent');
        Route::get('/legal-consents/logs', [LegalConsentController::class, 'consentLogs']);

        // Invite codes (admin)
        Route::get('/invite-codes', [AuthController::class, 'inviteCodesList']);
        Route::post('/invite-codes/generate', [AuthController::class, 'generateInviteCodes']);
        Route::get('/invite-codes/stats', [AuthController::class, 'inviteCodeStats']);

        // Legal consent
        Route::post('/legal/consent', [AuthController::class, 'consentToLegal']);

        // Account deletion
        Route::post('/account/deletion', [AuthController::class, 'requestDeletion']);
        Route::post('/account/deletion/cancel', [AuthController::class, 'cancelDeletion']);
        Route::get('/account/deletion/status', [AuthController::class, 'deletionStatus']);

        // 账号申诉状态查询
        Route::get('/appeal/status', [AppealController::class, 'status']);

        // Account deletion admin
        Route::get('/account/deletions/pending', [AccountDeletionAdminController::class, 'pending']);
        Route::get('/account/deletions/history', [AccountDeletionAdminController::class, 'history']);
        Route::post('/account/deletions/approve', [AccountDeletionAdminController::class, 'approve']);
        Route::post('/account/deletions/reject', [AccountDeletionAdminController::class, 'reject']);
        Route::get('/account/deletions/stats', [AccountDeletionAdminController::class, 'stats']);

        // ── Merkle 审计链验证 ──
        Route::get('/merkle/stats', [MerkleChainController::class, 'stats']);
        Route::get('/merkle/verify', [MerkleChainController::class, 'verify']);
        Route::get('/merkle/verify/{logId}', [MerkleChainController::class, 'verify']);
        Route::post('/merkle/anchor', [MerkleChainController::class, 'anchor']);
        Route::get('/merkle/anchors', [MerkleChainController::class, 'anchors']);
        Route::post('/merkle/backfill', [MerkleChainController::class, 'backfill']);

        // OAuth binding
        Route::get('/oauth/providers', [AuthController::class, 'boundProviders']);
        Route::post('/oauth/bind', [AuthController::class, 'bindOAuth']);
        Route::delete('/oauth/unbind/{authProviderId}', [AuthController::class, 'unbindOAuth'])->whereNumber('authProviderId');

        // 头像上传
        Route::post('/avatar/upload', [AuthController::class, 'uploadAvatar']);
        Route::delete('/avatar', [AuthController::class, 'deleteAvatar']);

        // Login history
        Route::get('/login-history', [AuthController::class, 'loginHistory']);
    }); // end mask
}); // end auth:sanctum
