<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_privacy_settings') && ! Schema::hasColumn('user_privacy_settings', 'dm_policy')) {
            Schema::table('user_privacy_settings', function (Blueprint $table) {
                $table->string('dm_policy', 30)->default('followers_only')->after('allow_stranger_message');
            });

            DB::table('user_privacy_settings')->orderBy('id')->each(function ($row) {
                $policy = ($row->allow_stranger_message ?? false) ? 'everyone' : 'followers_only';
                DB::table('user_privacy_settings')->where('id', $row->id)->update(['dm_policy' => $policy]);
            });
        }

        if (Schema::hasTable('conversation_participants') && ! Schema::hasColumn('conversation_participants', 'request_status')) {
            Schema::table('conversation_participants', function (Blueprint $table) {
                $table->string('request_status', 20)->nullable()->after('is_hidden');
                $table->timestamp('request_responded_at')->nullable()->after('request_status');
            });
        }

        if (! Schema::hasTable('user_dm_mutes')) {
            Schema::create('user_dm_mutes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->timestamp('muted_until');
                $table->string('reason', 64)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_dm_mutes');

        if (Schema::hasTable('conversation_participants')) {
            Schema::table('conversation_participants', function (Blueprint $table) {
                if (Schema::hasColumn('conversation_participants', 'request_responded_at')) {
                    $table->dropColumn('request_responded_at');
                }
                if (Schema::hasColumn('conversation_participants', 'request_status')) {
                    $table->dropColumn('request_status');
                }
            });
        }

        if (Schema::hasTable('user_privacy_settings') && Schema::hasColumn('user_privacy_settings', 'dm_policy')) {
            Schema::table('user_privacy_settings', function (Blueprint $table) {
                $table->dropColumn('dm_policy');
            });
        }
    }
};
