<?php

namespace App\Enums;

/**
 * Источник данных
 */
enum TableSourceEnum: int
{
    case REQUEST = 1;
    case CONTACTS = 2;
    case ANSWER = 3;
    case COMMENTS = 4;
    case DEPARTMENTS = 5;
    case CUSTOM_FIELDS = 6;
    case CUSTOM_FIELD_OPTIONS = 7;
}
