<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Inquiry;
use App\Models\User;
use App\Notifications\InquirySlaReminder;
use Illuminate\Console\Command;

class SendSlaReminders extends Command
{
    protected $signature = 'sla:remind';

    protected $description = 'Send one in-app reminder for each inquiry that has become overdue for triage or investigation';

    public function handle(): int
    {
        $triage = $this->remindTriage();
        $investigation = $this->remindInvestigation();

        $this->info("Sent {$triage} triage and {$investigation} investigation reminder(s).");

        return self::SUCCESS;
    }

    private function remindTriage(): int
    {
        $inquiries = Inquiry::triageOverdue()
            ->where(fn ($q) => $q->whereNull('sla_reminded_stage')->orWhere('sla_reminded_stage', '!=', InquirySlaReminder::STAGE_TRIAGE))
            ->get();

        if ($inquiries->isEmpty()) {
            return 0;
        }

        $staff = User::where('role', UserRole::McmcStaff)->get()
            ->filter(fn (User $user) => $user->wantsNotification('slaReminder'));

        foreach ($inquiries as $inquiry) {
            $staff->each(fn (User $user) => $user->notify(new InquirySlaReminder($inquiry, InquirySlaReminder::STAGE_TRIAGE)));
            $this->markReminded($inquiry, InquirySlaReminder::STAGE_TRIAGE);
        }

        return $inquiries->count();
    }

    private function remindInvestigation(): int
    {
        $inquiries = Inquiry::investigationOverdue()
            ->where(fn ($q) => $q->whereNull('sla_reminded_stage')->orWhere('sla_reminded_stage', '!=', InquirySlaReminder::STAGE_INVESTIGATION))
            ->get();

        foreach ($inquiries as $inquiry) {
            User::where('role', UserRole::AgencyStaff)
                ->where('agency_id', $inquiry->agency_id)
                ->get()
                ->filter(fn (User $user) => $user->wantsNotification('slaReminder'))
                ->each(fn (User $user) => $user->notify(new InquirySlaReminder($inquiry, InquirySlaReminder::STAGE_INVESTIGATION)));

            $this->markReminded($inquiry, InquirySlaReminder::STAGE_INVESTIGATION);
        }

        return $inquiries->count();
    }

    private function markReminded(Inquiry $inquiry, string $stage): void
    {
        $inquiry->forceFill(['sla_reminded_stage' => $stage, 'sla_reminded_at' => now()])->saveQuietly();
    }
}
