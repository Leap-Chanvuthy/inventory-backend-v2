<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['customers.read_all', 'customers.read_own']);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('customers.create');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->hasPermission('customers.update_all')
            || ($user->hasPermission('customers.update_own') && (int) ($customer->created_by ?? 0) === (int) $user->id);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->hasPermission('customers.delete_all')
            || ($user->hasPermission('customers.delete_own') && (int) ($customer->created_by ?? 0) === (int) $user->id);
    }
}
