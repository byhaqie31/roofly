<?php
// backend/app/Support/AdminPermissions.php
namespace App\Support;

/**
 * The fixed admin permission list (spec § 5). Single source for the seeder,
 * the `can:` middleware keys in routes/api.php, and GET /api/admin/permissions.
 * Never hard-code a key string elsewhere — reference the constants.
 */
final class AdminPermissions
{
    public const DASHBOARD_VIEW    = 'dashboard.view';
    public const OWNERS_VIEW       = 'owners.view';
    public const TENANTS_VIEW      = 'tenants.view';
    public const OWNERS_WARN       = 'owners.warn';
    public const OWNERS_SUSPEND    = 'owners.suspend';
    public const OWNERS_PLAN       = 'owners.plan';
    public const SUPPORT_MANAGE    = 'support.manage';
    public const BROADCAST_SEND    = 'broadcast.send';
    public const SETTINGS_CHANNELS = 'settings.channels';
    public const SETTINGS_FLAGS    = 'settings.flags';
    public const ADMINS_MANAGE     = 'admins.manage';
    public const AUDIT_VIEW        = 'audit.view';
    public const USERS_DELETE      = 'users.delete';

    public const GUARD = 'web';

    /** key => ['preset' => in Operations preset]. Order is the display order. */
    public const ALL = [
        self::DASHBOARD_VIEW    => ['preset' => true],
        self::OWNERS_VIEW       => ['preset' => true],
        self::TENANTS_VIEW      => ['preset' => true],
        self::OWNERS_WARN       => ['preset' => true],
        self::OWNERS_SUSPEND    => ['preset' => true],
        self::OWNERS_PLAN       => ['preset' => false],
        self::SUPPORT_MANAGE    => ['preset' => true],
        self::BROADCAST_SEND    => ['preset' => true],
        self::SETTINGS_CHANNELS => ['preset' => false],
        self::SETTINGS_FLAGS    => ['preset' => false],
        self::ADMINS_MANAGE     => ['preset' => false],
        self::AUDIT_VIEW        => ['preset' => false],
        self::USERS_DELETE      => ['preset' => false],
    ];

    /** @return string[] */
    public static function keys(): array
    {
        return array_keys(self::ALL);
    }

    /** @return string[] */
    public static function operationsPreset(): array
    {
        return array_keys(array_filter(self::ALL, fn ($p) => $p['preset']));
    }

    /** @return array<int, array{key: string, preset: bool}> */
    public static function catalogue(): array
    {
        return array_map(
            fn ($key, $meta) => ['key' => $key, 'preset' => $meta['preset']],
            array_keys(self::ALL),
            self::ALL,
        );
    }
}
