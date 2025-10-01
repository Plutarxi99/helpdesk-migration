<?php

namespace App\Enums;

/**
 * Статус отправки
 */
enum SendEnum: int
{
    case NOT_SEND = 0;
    case SEND = 1;
}
