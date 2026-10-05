<?php

namespace App\Policies;

class CustomerPolicy extends PermissionPolicy
{
    protected function area(): string
    {
        return 'customers';
    }
}
