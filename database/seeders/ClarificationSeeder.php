<?php

namespace Database\Seeders;

use App\Enums\InquiryCategory;
use App\Enums\InquiryStatus;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\ClarificationConsult;
use App\Models\ClarificationMessage;
use App\Models\ClarificationThread;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Database\Seeder;

class ClarificationSeeder extends Seeder
{
    public function run(): void
    {
        $mcmcStaff = User::where('role', UserRole::McmcStaff)->first();
        $agencyStaff = User::where('role', UserRole::AgencyStaff)->where('agency_id', Agency::where('code', 'BNM')->value('id'))->first();
        $spr = Agency::where('code', 'SPR')->first();

        $scamInquiry = Inquiry::where('category', InquiryCategory::FinancialScams)->where('status', InquiryStatus::UnderInvestigation)->whereNull('jurisdiction_accepted_at')->first();
        $electionInquiry = Inquiry::where('category', InquiryCategory::ElectoralPolitical)->where('status', InquiryStatus::UnderInvestigation)->first();

        if ($scamInquiry) {
            $thread = ClarificationThread::create([
                'inquiry_id' => $scamInquiry->id,
                'opened_by' => $mcmcStaff->id,
                'subject' => 'Need confirmation on handout registration link',
                'status' => 'open',
            ]);

            ClarificationMessage::create([
                'clarification_thread_id' => $thread->id,
                'user_id' => $mcmcStaff->id,
                'message' => 'Can BNM confirm whether this registration link is officially affiliated with any government cash aid program?',
            ]);

            ClarificationMessage::create([
                'clarification_thread_id' => $thread->id,
                'user_id' => $agencyStaff->id,
                'message' => 'We are checking with our fraud unit and will revert with an official statement shortly.',
            ]);

            ClarificationConsult::create([
                'clarification_thread_id' => $thread->id,
                'requested_by' => $mcmcStaff->id,
                'consulted_agency_id' => $spr->id,
                'question' => 'Is there any overlap between this scam link and election-period cash aid rumours SPR has flagged recently?',
                'status' => 'pending',
            ]);
        }

        if ($electionInquiry) {
            $thread = ClarificationThread::create([
                'inquiry_id' => $electionInquiry->id,
                'opened_by' => $mcmcStaff->id,
                'subject' => 'Verifying election postponement claim',
                'status' => 'open',
            ]);

            ClarificationMessage::create([
                'clarification_thread_id' => $thread->id,
                'user_id' => $mcmcStaff->id,
                'message' => 'Requesting an official statement from SPR on whether any postponement has been announced.',
            ]);
        }
    }
}
