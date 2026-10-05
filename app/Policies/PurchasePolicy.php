<?php

namespace App\Policies;

class PurchasePolicy extends PermissionPolicy
{
    protected function area(): string
    {
        return 'purchases';
    }
}
