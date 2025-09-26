<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_for_migrations', function (Blueprint $table) {
            // Меняем тип json_data на LONGTEXT
            $table->longText('json_data')->change();
        });
    }

    public function down(): void
    {
        Schema::table('table_for_migrations', function (Blueprint $table) {
            // Откат назад в TEXT
            $table->text('json_data')->change();
        });
    }
};
