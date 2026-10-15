<?php

namespace App\Policies;

/** Quotations follow the sales permissions: sales.view to see them, sales.manage to write or convert them. */
class QuotationPolicy extends PermissionPolicy
{
    protected function area(): string
    {
        return 'sales';
    }
}
