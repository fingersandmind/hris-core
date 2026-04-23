<?php

namespace Jmal\Hris\Support;

use Jmal\Hris\Contracts\AuthorizationResolverInterface;

class DefaultAuthorizationResolver implements AuthorizationResolverInterface
{
    public function can(string $ability): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        $attribute = config('hris.authorization.user_role_attribute', 'user_type');
        $role = $user->{$attribute};

        if ($role instanceof \BackedEnum) {
            $role = $role->value;
        }

        $allowedRoles = config("hris.authorization.roles.{$ability}", []);

        return in_array($role, $allowedRoles);
    }
}
