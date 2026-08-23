<?php
// backend/app/Services/AuditLogger.php
namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

/**
 * The one door for admin writes into the audit log (spec § 5). Stored in
 * Spatie ActivityLog with log_name = admin; the owner/tenant model logs
 * (log_name = default) are untouched.
 */
class AuditLogger
{
    public const LOG_NAME = 'admin';

    public const ADMIN_LOGIN               = 'admin.login';
    public const ADMIN_INVITE_SENT         = 'admin.invite_sent';
    public const ADMIN_INVITE_ACCEPTED     = 'admin.invite_accepted';
    public const ADMIN_PERMISSIONS_CHANGED = 'admin.permissions_changed';
    public const ADMIN_DISABLED            = 'admin.disabled';
    public const ADMIN_ENABLED             = 'admin.enabled';
    public const OWNER_WARNED              = 'owner.warned';
    public const OWNER_SUSPENDED           = 'owner.suspended';
    public const OWNER_UNSUSPENDED         = 'owner.unsuspended';
    public const TENANT_INVITE_RESENT      = 'tenant.invite_resent';
    public const ANALYTICS_EXPORTED        = 'analytics.exported';

    /** Every SP1 action, for validation of the audit filter. */
    public const ACTIONS = [
        self::ADMIN_LOGIN, self::ADMIN_INVITE_SENT, self::ADMIN_INVITE_ACCEPTED,
        self::ADMIN_PERMISSIONS_CHANGED, self::ADMIN_DISABLED, self::ADMIN_ENABLED,
        self::OWNER_WARNED, self::OWNER_SUSPENDED, self::OWNER_UNSUSPENDED,
        self::TENANT_INVITE_RESENT, self::ANALYTICS_EXPORTED,
    ];

    public function record(
        string $action,
        ?Model $subject = null,
        array $before = [],
        array $after = [],
        ?string $reason = null,
    ): Activity {
        $log = activity(self::LOG_NAME)
            ->event($action)
            ->withProperties([
                'before' => $before,
                'after'  => $after,
                'reason' => $reason,
                'ip'     => request()?->ip(),
            ]);

        if ($actor = auth()->user()) {
            $log->causedBy($actor);
        }
        if ($subject !== null) {
            $log->performedOn($subject);
        }

        return $log->log($action);
    }
}
