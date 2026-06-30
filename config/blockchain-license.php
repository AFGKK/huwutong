<?php

// M3-14 区块链 License / NFT License / Web3 钱包授权

return [
    'chains' => [
        'ethereum' => [
            'name' => 'Ethereum',
            'rpc_url' => env('ETH_RPC_URL', 'https://mainnet.infura.io/v3/'),
            'chain_id' => 1,
            'explorer_url' => 'https://etherscan.io',
            'enabled' => env('BLOCKCHAIN_ETH_ENABLED', false),
        ],
        'polygon' => [
            'name' => 'Polygon',
            'rpc_url' => env('POLYGON_RPC_URL', 'https://polygon-rpc.com'),
            'chain_id' => 137,
            'explorer_url' => 'https://polygonscan.com',
            'enabled' => env('BLOCKCHAIN_POLYGON_ENABLED', true),
        ],
        'bsc' => [
            'name' => 'BNB Smart Chain',
            'rpc_url' => env('BSC_RPC_URL', 'https://bsc-dataseed.binance.org'),
            'chain_id' => 56,
            'explorer_url' => 'https://bscscan.com',
            'enabled' => env('BLOCKCHAIN_BSC_ENABLED', true),
        ],
    ],

    'nft' => [
        'default_contract' => env('NFT_LICENSE_CONTRACT', ''),
        'abi_path' => storage_path('blockchain/nft-license.abi.json'),
        'token_uri_base' => env('NFT_TOKEN_URI_BASE', 'https://api.huwutong.com/v1/nft/'),
        'verification_gas_limit' => 100000,
    ],

    'wallet' => [
        'required_confirmations' => 12,
        'verification_timeout_seconds' => 300,
        'allowed_wallet_types' => ['metamask', 'walletconnect', 'trustwallet'],
        'signature_message' => 'HWT License Verification\nNonce: {nonce}\nTimestamp: {timestamp}',
    ],

    'license' => [
        'sync_interval_minutes' => 60,
        'max_nft_per_wallet' => 100,
        'auto_revoke_on_transfer' => true,
    ],
];
