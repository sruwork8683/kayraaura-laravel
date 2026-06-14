<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the email unique index only if it still exists
        // (it may have been dropped by a previous partial migration run).
        $emailIndexExists = collect(DB::select("SHOW INDEX FROM users WHERE Key_name = 'users_email_unique'"))->isNotEmpty();

        if ($emailIndexExists) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_email_unique');
            });
        }

        // Deduplicate phone numbers before adding the unique constraint.
        // Keep the newest user (highest id) for each phone; null out older duplicates.
        DB::statement("
            UPDATE users u
            JOIN (
                SELECT phone, MAX(id) AS keep_id
                FROM users
                WHERE phone IS NOT NULL AND phone != ''
                GROUP BY phone
                HAVING COUNT(*) > 1
            ) dupes ON u.phone = dupes.phone AND u.id != dupes.keep_id
            SET u.phone = NULL
        ");

        // Add the unique index only if it doesn't already exist.
        $phoneIndexExists = collect(DB::select("SHOW INDEX FROM users WHERE Key_name = 'users_phone_unique'"))->isNotEmpty();

        if (!$phoneIndexExists) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('phone');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_phone_unique');
            $table->unique('email');
        });
    }
};
