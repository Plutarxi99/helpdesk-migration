<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_for_migrations', function (Blueprint $table) {
            $table->text('error_message')->nullable()->after('is_send');
        });
    }

    public function down(): void
    {
        Schema::table('table_for_migrations', function (Blueprint $table) {
            $table->dropColumn('error_message');
        });
    }
};
