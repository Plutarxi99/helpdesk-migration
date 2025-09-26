<?php

namespace App\Models;

use App\Enums\SendEnum;
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
        'is_send' => SendEnum::class,
        'json_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $fillable = [
        'source',
        'json_data',
        'id_table_for_migrations',
        'is_send',
        'error_message',
    ];

    /**
     * Получение не отправленных значений
     *
     * @param TableSourceEnum $source_enum Источник загрузки откуда и куда будет загружено
     * @param int|null        $from_id     ID откуда получить значения
     * @param int|null        $to_id       ID до какого получить значение
     *
     * @return array
     */
    public function getNotSend(TableSourceEnum $source_enum, ?int $from_id, ?int $to_id): array
    {
        return TableForMigration::query()
            ->where('source', $source_enum)
            ->where('is_send', SendEnum::NOT_SEND)
            ->when(! is_null($from_id), fn($q) => $q->where('id_table_for_migrations', '>=', $from_id))
            ->when(! is_null($to_id), fn($q) => $q->where('id_table_for_migrations', '<=', $to_id))
            ->get();
    }
}