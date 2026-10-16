<?php

namespace App\Policies;

/** Promotions follow the sales permissions: sales.view to see them, sales.manage to create or change them. */
class PromotionPolicy extends PermissionPolicy
{
    protected function area(): string
    {
        return 'sales';
    }
}
