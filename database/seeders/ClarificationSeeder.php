<?php

namespace Database\Seeders;

use App\Enums\ClarificationPriority;
use App\Enums\ClarificationTopic;
use App\Enums\InquiryCategory;
use App\Enums\InquiryStatus;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Database\Seeder;

class ClarificationSeeder extends Seeder
{
    public function run(): void
    {
        $mcmcStaff = User::where('role', UserRole::McmcStaff)->first();
        $bnmStaff = User::where('role', UserRole::AgencyStaff)->where('agency_id', Agency::where('code', 'BNM')->value('id'))->first();
        $sprStaff = User::where('role', UserRole::AgencyStaff)->where('agency_id', Agency::where('code', 'SPR')->value('id'))->first();
        $moh = Agency::where('code', 'MOH')->first();

        // Scenario 1: BNM asks MCMC a question, MCMC replies, and MCMC then
        // consults MOH for a health-related angle on the same case.
        $scamInquiry = Inquiry::where('category', InquiryCategory::FinancialScams)
            ->where('status', InquiryStatus::UnderInvestigation)
            ->whereNull('jurisdiction_accepted_at')
            ->first();

        if ($scamInquiry && $bnmStaff) {
            $thread = \App\Models\ClarificationThread::open(
                inquiry: $scamInquiry,
                openedBy: $bnmStaff,
                topic: ClarificationTopic::SourceVerification,
                priority: ClarificationPriority::Urgent,
                text: 'Can MCMC confirm whether this registration link is officially affiliated with any government cash aid program?',
            );

            $thread->reply($mcmcStaff, 'We are checking with the relevant ministry and will revert with an official statement shortly.');

            if ($moh) {
                $thread->inviteConsult(
                    mcmcUser: $mcmcStaff,
                    agency: $moh,
                    question: 'Is there any overlap between this scam link and health subsidy rumours MOH has flagged recently?',
                );
            }
        }

        // Scenario 2: SPR asks MCMC a question that is still awaiting a
        // response (status stays "open").
        $electionInquiry = Inquiry::where('category', InquiryCategory::ElectoralPolitical)
            ->where('status', InquiryStatus::UnderInvestigation)
            ->first();

        if ($electionInquiry && $sprStaff) {
            \App\Models\ClarificationThread::open(
                inquiry: $electionInquiry,
                openedBy: $sprStaff,
                topic: ClarificationTopic::SubmitterFollowUp,
                priority: ClarificationPriority::Normal,
                text: 'Requesting the submitter\'s original source link to help trace where this postponement claim originated.',
            );
        }
    }
}
