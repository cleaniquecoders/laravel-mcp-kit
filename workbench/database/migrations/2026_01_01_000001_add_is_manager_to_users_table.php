<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Testbench ships the default `users` table; we only add the `is_manager`
 * flag the demo gates read.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'is_manager')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_manager')->default(false);
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_manager');
        });
    }
};
