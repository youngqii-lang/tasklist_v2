<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\CalendarEvent;

class CalendarEventPolicy
{
    /**
     * Admin and Managers add agendas for every division, a Supervisor for the division they lead; Staff and
     * Freelance only read the agenda. Which divisions the agenda may name is checked by SaveCalendarEventRequest.
     */
    public function create(Account $user): bool
    {
        return $user->is_active
            && ($user->isAdmin() || $user->isManager() || ($user->isSupervisor() && $user->division_id !== null));
    }

    public function update(Account $user, CalendarEvent $calendarEvent): bool
    {
        return $calendarEvent->managedBy($user);
    }

    public function delete(Account $user, CalendarEvent $calendarEvent): bool
    {
        return $calendarEvent->managedBy($user);
    }
}
