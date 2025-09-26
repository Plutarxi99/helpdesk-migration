<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_for_migrations', function (Blueprint $table) {
            $table->unique(
                ['id_table_for_migrations', 'source'],
                'uq_table_for_migrations_source'
            );
        });
    }

    public function down(): void
    {
        Schema::table('table_for_migrations', function (Blueprint $table) {
            $table->dropUnique('uq_table_for_migrations_source');
        });
    }
};
