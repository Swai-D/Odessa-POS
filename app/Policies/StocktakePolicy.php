<?php

namespace App\Policies;

class StocktakePolicy extends PermissionPolicy
{
    protected function area(): string
    {
        return 'inventory';
    }
}
