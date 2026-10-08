<?php

namespace App\Enums;

enum TicketStatus: string
{
    case NEW = 'new';
    case IN_PROGRESS = 'in_progress';
    case RESOLVED = 'resolved';
    case REOPENED = 'reopened';

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::NEW         => [self::IN_PROGRESS, self::RESOLVED],
            self::IN_PROGRESS => [self::RESOLVED, self::NEW],
            self::RESOLVED    => [self::REOPENED],
            self::REOPENED    => [self::IN_PROGRESS, self::RESOLVED],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), strict: true);
    }
}
