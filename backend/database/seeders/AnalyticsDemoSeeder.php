<?php

namespace Database\Seeders;

use App\Models\AnalyticsEvent;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;

/**
 * 90 days of deterministic marketing-site analytics so the admin page has a
 * story: ~15–40 views/day, a quarter of visitors try the demo, 40 waitlist
 * leads, 8 of which registered (as real, property-less owner users).
 * Idempotent: wipes and regenerates its own rows each run.
 */
class AnalyticsDemoSeeder extends Seeder
{
    private const NS = '00000000-0000-4000-8000-00000000a000';

    private const PATHS = ['/', '/', '/', '/coming-soon', '/demo', '/demo', '/auth/register'];

    private const REFERRERS = [null, null, null, 'google.com', 'facebook.com', 'lowyat.net', 'instagram.com'];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('AnalyticsDemoSeeder skipped: refuses to wipe analytics data in production.');

            return;
        }

        mt_srand(2026);
        AnalyticsEvent::query()->delete();
        Lead::query()->delete();
        User::where('email', 'like', 'lead%@example.com')->forceDelete();

        $leadsMade = 0;
        $converted = 0;
        $vidSeq = 0;
        for ($day = 89; $day >= 0; $day--) {
            $date = now()->subDays($day)->startOfDay();
            $visitors = mt_rand(6, 16);
            for ($v = 0; $v < $visitors; $v++) {
                $vid = Uuid::uuid5(self::NS, 'v'.(++$vidSeq))->toString();
                $ref = self::REFERRERS[mt_rand(0, count(self::REFERRERS) - 1)];
                $t = $date->copy()->addMinutes(mt_rand(480, 1380));
                $views = mt_rand(1, 4);
                for ($i = 0; $i < $views; $i++) {
                    $this->event($vid, 'page_view', $t->copy()->addMinutes($i * 2), ['path' => self::PATHS[mt_rand(0, count(self::PATHS) - 1)], 'referrer' => $i === 0 ? $ref : null]);
                }
                if (mt_rand(1, 100) <= 25) {
                    $this->event($vid, 'demo_enter', $t->copy()->addMinutes(10), ['props' => ['role' => mt_rand(0, 1) ? 'owner' : 'tenant']]);
                    if (mt_rand(1, 100) <= 20) {
                        $this->event($vid, 'demo_feedback_click', $t->copy()->addMinutes(15));
                    }
                }
                if ($leadsMade < 40 && mt_rand(1, 100) <= 5) {
                    $n = ++$leadsMade;
                    $email = sprintf('lead%02d@example.com', $n);
                    $this->event($vid, 'waitlist_signup', $t->copy()->addMinutes(20), ['props' => ['email' => $email]]);
                    $lead = Lead::create(['email' => $email, 'visitor_id' => $vid, 'source' => 'waitlist', 'first_seen_at' => $t, 'last_seen_at' => $t]);
                    if ($converted < 8 && $n % 5 === 0) {
                        $converted++;
                        // onboarded_at + purposes keep these seeded owners off the
                        // onboarding wall on a fresh migrate:fresh --seed (the Task 1
                        // migration only back-fills rows that pre-date it). They're
                        // property-less, so the getting-started checklist staying
                        // visible (checklist_dismissed_at left null) is desirable —
                        // it's exactly the persona onboarding is meant to guide.
                        $owner = User::create(['name' => "Lead {$n}", 'email' => $email, 'role' => 'owner', 'password' => Hash::make('password'), 'plan_tier' => 'free', 'purposes' => ['rental'], 'onboarded_at' => $t->copy()->addDays(2)]);
                        $owner->forceFill(['created_at' => $t->copy()->addDays(2)])->saveQuietly();
                        $this->event($vid, 'register', $t->copy()->addDays(2), ['path' => '/auth/register', 'props' => ['email' => $email, 'userId' => $owner->id]]);
                        $lead->update(['converted_user_id' => $owner->id, 'last_seen_at' => $t->copy()->addDays(2)]);
                    }
                }
            }
        }
    }

    private function event(string $vid, string $name, $at, array $extra = []): void
    {
        AnalyticsEvent::create(array_merge([
            'visitor_id' => $vid, 'event' => $name, 'path' => null, 'referrer' => null, 'utm' => null, 'props' => null,
            'ip_hash' => hash('sha256', $vid), 'user_agent' => 'seed', 'created_at' => $at,
        ], $extra));
    }
}
