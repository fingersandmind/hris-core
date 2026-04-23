<?php

namespace Jmal\Hris\Contracts;

interface AuthorizationResolverInterface
{
    /**
     * Check if the current user can perform the given ability.
     */
    public function can(string $ability): bool;
}
