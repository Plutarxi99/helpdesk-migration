<?php

namespace App\Enums;

enum TableSourceEnum:int
{
    case REQUEST = 1;
    case CONTACTS = 2;
    case ANSWER = 3;
    case COMMENTS = 4;
    case DEPARTMENTS = 5;
}
