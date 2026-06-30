<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('blockchain_licenses')) {
            return;
        }
        Schema::create('blockchain_licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('license_id')->nullable()->constrained()->nullOnDelete();
            $table->string('chain', 30)->comment('ethereum/polygon/bsc');
            $table->string('contract_address', 100)->comment('NFT合约地址');
            $table->string('token_id', 100)->comment('NFT Token ID');
            $table->string('token_uri', 500)->nullable()->comment('Token URI元数据');
            $table->string('wallet_address', 100)->comment('持有者钱包地址');
            $table->string('owner_address', 100)->nullable()->comment('当前owner地址(可能已转移)');
            $table->string('transaction_hash', 100)->nullable()->comment('铸造交易哈希');
            $table->timestamp('minted_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->string('status', 30)->default('active')->comment('active/transferred/burned/revoked');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['chain', 'contract_address', 'token_id']);
            $table->index(['wallet_address', 'status']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('wallet_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('wallet_address', 100);
            $table->string('wallet_type', 30)->default('metamask');
            $table->string('nonce', 64);
            $table->text('signed_message')->nullable();
            $table->string('signature', 200)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at');
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->index(['wallet_address', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_verifications');
        Schema::dropIfExists('blockchain_licenses');
    }
};
