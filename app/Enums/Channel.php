<?php

declare(strict_types=1);

namespace App\Enums;

enum Channel: string
{
    case SMS = 'sms';
    case EMAIL = 'email';
}
