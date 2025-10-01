<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('table_for_migrations', function (Blueprint $table) {
            $table->dropColumn('unique_id');
        });
    }

    public function down(): void
    {
        Schema::table('table_for_migrations', function (Blueprint $table) {
            $table->char('unique_id', 36)->nullable();
        });
    }
};
