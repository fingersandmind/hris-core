<?php

namespace Jmal\Hris\Support;

/**
 * The tenant the current process is acting for.
 *
 * The default resolver reads the scope from the session, which exists only
 * during a web request. Anything outside one — a queued job, a command — has to
 * say who it is acting for, and this is where it says it.
 */
class TenantContext
{
    protected static ?int $scopeId = null;

    /**
     * Whether queries with no tenant are allowed to run unfiltered.
     *
     * Off by default so that forgetting to set a tenant returns nothing rather
     * than everything.
     */
    protected static bool $unscoped = false;

    public static function set(?int $scopeId): void
    {
        static::$scopeId = $scopeId;
    }

    public static function get(): ?int
    {
        return static::$scopeId;
    }

    public static function forget(): void
    {
        static::$scopeId = null;
    }

    public static function unscopedAllowed(): bool
    {
        return static::$unscoped;
    }

    /**
     * Act as one tenant for the duration of the callback.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function runAs(int $scopeId, callable $callback): mixed
    {
        $previousScope = static::$scopeId;
        $previousUnscoped = static::$unscoped;

        static::$scopeId = $scopeId;
        static::$unscoped = false;

        try {
            return $callback();
        } finally {
            static::$scopeId = $previousScope;
            static::$unscoped = $previousUnscoped;
        }
    }

    /**
     * Deliberately cross every tenant — reporting, maintenance, seeding.
     *
     * Explicit by design: reading across tenants should be a decision someone
     * made, not something that happens because no tenant was resolved.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function runUnscoped(callable $callback): mixed
    {
        $previousScope = static::$scopeId;
        $previousUnscoped = static::$unscoped;

        static::$scopeId = null;
        static::$unscoped = true;

        try {
            return $callback();
        } finally {
            static::$scopeId = $previousScope;
            static::$unscoped = $previousUnscoped;
        }
    }
}
