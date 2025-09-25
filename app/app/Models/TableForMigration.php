<?php

namespace App\Models;

use App\Enums\TableSourceEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static Builder<static>|TableForMigration query()
 */
class TableForMigration extends Model
{
    protected $table = 'table_for_migrations';

    protected $casts = [
        'source' => TableSourceEnum::class,
        'json_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $fillable = [
        'source',
        'json_data',
        'unique_id',
    ];
}