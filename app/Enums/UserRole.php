<?php

namespace App\Enums;

enum UserRole: string
{
    case User = 'user';
    case Admin = 'admin';
    case SuperAdmin = 'super_admin';

    public function isAdmin(): bool
    {
        return $this === self::Admin || $this === self::SuperAdmin;
    }
}
