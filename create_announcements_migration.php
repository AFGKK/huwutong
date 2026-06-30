<?php
$file = __DIR__ . '/database/migrations/2026_06_18_000001_create_announcements_tables.php';
$code = '<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable(\'announcements\')) {
            Schema::create(\'announcements\', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger(\'conversation_id\');
                $table->unsignedBigInteger(\'sender_id\');
                $table->string(\'title\', 200);
                $table->text(\'content\');
                $table->timestamps();

                $table->index(\'conversation_id\');
                $table->foreign(\'conversation_id\')->references(\'id\')->on(\'user_conversations\')->cascadeOnDelete();
                $table->foreign(\'sender_id\')->references(\'id\')->on(\'users\')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable(\'announcement_reads\')) {
            Schema::create(\'announcement_reads\', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger(\'announcement_id\');
                $table->unsignedBigInteger(\'user_id\');
                $table->timestamp(\'read_at\')->nullable();

                $table->unique([\'announcement_id\', \'user_id\']);
                $table->foreign(\'announcement_id\')->references(\'id\')->on(\'announcements\')->cascadeOnDelete();
                $table->foreign(\'user_id\')->references(\'id\')->on(\'users\')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(\'announcement_reads\');
        Schema::dropIfExists(\'announcements\');
    }
};
';
file_put_contents($file, $code);
echo "Migration created: $file\n";

// Run migration
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$kernel->call('migrate', ['--force' => true]);
echo "Migration executed.\n";
