<?php

namespace App\Services;

use App\Models\BlockchainLicense;
use App\Models\License;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * M3-14 区块链 License / NFT License / Web3 钱包授权
 */
class BlockchainLicenseService
{
    /**
     * 验证钱包签名
     */
    public function verifyWalletSignature(string $wallet, string $signature, string $nonce): bool
    {
        $message = str_replace(
            ['{nonce}', '{timestamp}'],
            [$nonce, now()->timestamp],
            config('blockchain-license.wallet.signature_message')
        );

        // 实际项目应使用 web3.php 或 ethers.js 验证
        // hash -> recoverPublicKey -> compare address
        return !empty($signature) && strlen($signature) > 50;
    }

    /**
     * 创建钱包验证挑战
     */
    public function createChallenge(string $walletAddress): array
    {
        $nonce = Str::random(32);
        $message = str_replace(
            ['{nonce}', '{timestamp}'],
            [$nonce, now()->timestamp],
            config('blockchain-license.wallet.signature_message')
        );

        Cache::put("wallet_challenge:{$walletAddress}", $nonce, 300);

        return [
            'nonce' => $nonce,
            'message' => $message,
            'expires_in' => 300,
        ];
    }

    /**
     * 链上验证 NFT 持有
     */
    public function verifyNftOwnership(string $wallet, string $contract, string $tokenId): array
    {
        // 实际应通过 RPC 调用合约 balanceOf / ownerOf
        // 此处模拟验证逻辑
        $existing = BlockchainLicense::where('contract_address', $contract)
            ->where('token_id', $tokenId)
            ->first();

        if ($existing && $existing->owner_address !== $wallet) {
            return ['valid' => false, 'reason' => 'NFT已被转移'];
        }

        return [
            'valid' => true,
            'owner' => $wallet,
            'contract' => $contract,
            'token_id' => $tokenId,
        ];
    }

    /**
     * 绑定 License 到 NFT
     */
    public function bindLicenseToNft(License $license, array $nftData): BlockchainLicense
    {
        return BlockchainLicense::create([
            'tenant_id' => $license->tenant_id,
            'license_id' => $license->id,
            'chain' => $nftData['chain'],
            'contract_address' => $nftData['contract_address'],
            'token_id' => $nftData['token_id'],
            'token_uri' => $nftData['token_uri'] ?? null,
            'wallet_address' => $nftData['wallet_address'],
            'owner_address' => $nftData['wallet_address'],
            'transaction_hash' => $nftData['transaction_hash'] ?? null,
            'minted_at' => now(),
            'status' => 'active',
        ]);
    }

    /**
     * 获取区块链 License 仪表盘
     */
    public function getDashboard(int $tenantId): array
    {
        $total = BlockchainLicense::where('tenant_id', $tenantId)->count();
        $active = BlockchainLicense::where('tenant_id', $tenantId)->where('status', 'active')->count();

        $byChain = BlockchainLicense::where('tenant_id', $tenantId)
            ->selectRaw('chain, count(*) as count')
            ->groupBy('chain')
            ->pluck('count', 'chain')
            ->toArray();

        return [
            'total_nfts' => $total,
            'active_nfts' => $active,
            'by_chain' => $byChain,
            'enabled_chains' => collect(config('blockchain-license.chains'))
                ->where('enabled', true)
                ->keys()
                ->toArray(),
        ];
    }
}
