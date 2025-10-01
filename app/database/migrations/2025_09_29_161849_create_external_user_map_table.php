<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create(
            'external_id_map',
            function (Blueprint $table) {
                $table->id();
                $table->string('external_id');
                $table->unsignedTinyInteger('source');
                $table->unsignedBigInteger('local_id');
                $table->timestamps();
                $table->unique(['external_id', 'source']);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('external_id_map');
    }
};
