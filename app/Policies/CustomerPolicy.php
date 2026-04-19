<?php

namespace App\Policies;

use App\Enums\UserRoleEnum;
use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role?->value, [
            UserRoleEnum::ADMIN->value,
            UserRoleEnum::VENDER->value,
            UserRoleEnum::STOCK_CONTROLLER->value,
            UserRoleEnum::WAREHOUSE_MANAGER->value,
        ], true);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->role?->value === UserRoleEnum::ADMIN->value;
    }
}
