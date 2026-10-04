<?php

namespace App\Policies;

class UnitPolicy extends PermissionPolicy
{
    protected function area(): string
    {
        return 'units';
    }
}
