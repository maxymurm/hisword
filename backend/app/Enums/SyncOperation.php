<?php

namespace App\Enums;

enum SyncOperation: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
}
