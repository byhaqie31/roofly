<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as Notifier;
use Throwable;

/**
 * Emails every active super admin (role admin, is_super_admin, not disabled).
 * Used for the "something happened" heads-ups: a new waitlist enquiry, a new
 * owner sign-up. Never throws — the visitor's request must not fail because
 * the admin alert couldn't be queued.
 */
final class SuperAdminAlerts
{
    public static function send(Notification $notification): void
    {
        try {
            $admins = User::where('role', UserRole::ADMIN)
                ->where('is_super_admin', true)
                ->whereNull('disabled_at')
                ->get();

            if ($admins->isNotEmpty()) {
                Notifier::send($admins, $notification);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
