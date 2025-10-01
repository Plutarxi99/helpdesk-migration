<?php

namespace App\Models;

use App\Enums\TableSourceEnum;
use Illuminate\Database\Eloquent\Model;

/**
 * Класс нужен для маппинга из переносимой системы в новую
 *
 * @property int $external_id ID из родительской системы
 * @property TableSourceEnum $source Источник из какой таблицы будет перенос
 * @property User $local_user_id ID в новой системе
 */
class ExternalIdMap extends Model
{
    protected $table = 'external_id_map';

    protected $fillable = [
        'external_id',
        'source',
        'local_id',
    ];

    protected $casts = [
        'source' => TableSourceEnum::class,
    ];

    /**
     * Получить локальный ID по источнику и внешнему ID
     *
     * @param TableSourceEnum $source Источник переноса
     * @param int $external_id Внешний источник
     *
     * @return int|null
     */
    public static function getLocalId(TableSourceEnum $source, int $external_id): ?int
    {
        return self::query()
            ->where('source', $source->value)
            ->where('external_id', $external_id)
            ->value('local_id');
    }

    /**
     * Сохранить в бд
     *
     * @param TableSourceEnum $source Источник переноса
     * @param int $external_id Внешний ID
     * @param int $local_id локальный ID
     *
     * @return void
     */
    public static function saveMapping(TableSourceEnum $source, int $external_id, int $local_id): void
    {
        self::updateOrCreate(
            ['source' => $source->value, 'external_id' => $external_id],
            ['local_id' => $local_id]
        );
    }
}
