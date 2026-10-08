<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** One tracked event from a public/marketing page. Never exposes ip_hash/user_agent. */
class AnalyticsEvent extends Model
{
    use HasFactory, HasUuids;

    public const EVENTS = ['page_view', 'demo_enter', 'demo_feedback_click', 'waitlist_signup', 'register'];

    public $timestamps = false;

    protected $fillable = ['visitor_id', 'event', 'path', 'referrer', 'utm', 'props', 'ip_hash', 'user_agent', 'created_at'];

    protected function casts(): array
    {
        return ['utm' => 'array', 'props' => 'array', 'created_at' => 'datetime'];
    }
}
