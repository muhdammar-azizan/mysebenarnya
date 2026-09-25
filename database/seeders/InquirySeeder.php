<?php

namespace Database\Seeders;

use App\Enums\InquiryCategory;
use App\Enums\InquiryStatus;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Inquiry;
use App\Models\InquiryActivityLog;
use App\Models\InquiryEvidence;
use App\Models\User;
use Illuminate\Database\Seeder;

class InquirySeeder extends Seeder
{
    public function run(): void
    {
        $publicUsers = User::where('role', UserRole::Public)->get();
        $mcmcStaff = User::where('role', UserRole::McmcStaff)->get();
        $moh = Agency::where('code', 'MOH')->first();
        $bnm = Agency::where('code', 'BNM')->first();
        $spr = Agency::where('code', 'SPR')->first();

        $inquiries = [
            [
                'title' => 'Viral claim: drinking bleach cures COVID-19',
                'description' => 'A viral WhatsApp message claims that drinking diluted bleach can cure COVID-19 infections within 24 hours.',
                'source_url' => 'https://example.com/viral-post-1',
                'category' => InquiryCategory::HealthMedical,
                'status' => InquiryStatus::IdentifiedFake,
                'agency_id' => $moh->id,
                'reviewed_by' => $mcmcStaff->first()->id,
                'reviewed_at' => now()->subDays(7),
                'jurisdiction_accepted_at' => now()->subDays(6),
                'resolution_notes' => 'Confirmed fake. MOH clarified there is no scientific evidence supporting this claim, and it poses serious health risks.',
                'resolved_at' => now()->subDays(5),
            ],
            [
                'title' => 'Facebook post: Bank Negara issuing new RM500 notes',
                'description' => 'A Facebook post claims Bank Negara Malaysia is issuing a new RM500 banknote design starting next month.',
                'source_url' => 'https://example.com/viral-post-2',
                'category' => InquiryCategory::FinancialScams,
                'status' => InquiryStatus::Rejected,
                'agency_id' => $bnm->id,
                'reviewed_by' => $mcmcStaff->first()->id,
                'reviewed_at' => now()->subDays(4),
                'resolution_notes' => 'BNM declined jurisdiction — no such note is planned and this does not concern a regulated financial product. Returned to MCMC for reassignment.',
                'resolved_at' => now()->subDays(3),
            ],
            [
                'title' => 'Telegram message: Election date postponed',
                'description' => 'A Telegram channel is spreading a message claiming the upcoming general election has been postponed indefinitely.',
                'source_url' => 'https://example.com/viral-post-3',
                'category' => InquiryCategory::ElectoralPolitical,
                'status' => InquiryStatus::UnderInvestigation,
                'agency_id' => $spr->id,
                'reviewed_by' => $mcmcStaff->last()->id,
                'reviewed_at' => now()->subDays(2),
                'jurisdiction_accepted_at' => now()->subDay(),
            ],
            [
                'title' => 'TikTok video: free government cash handout link',
                'description' => 'A TikTok video shares a link claiming to register for a one-time RM1,000 government handout, requesting bank login details.',
                'source_url' => 'https://example.com/viral-post-4',
                'category' => InquiryCategory::FinancialScams,
                'status' => InquiryStatus::UnderInvestigation,
                'agency_id' => $bnm->id,
                'reviewed_by' => $mcmcStaff->last()->id,
                'reviewed_at' => now()->subDay(),
                // jurisdiction_accepted_at intentionally left null: this one is still
                // "Awaiting Jurisdiction Review" (computed, see Inquiry::isAwaitingJurisdiction()).
            ],
            [
                'title' => 'News article: new vaccine mandate for all schools',
                'description' => 'An article circulating on X (Twitter) claims a new nationwide vaccine mandate is being enforced in all schools starting next semester.',
                'source_url' => 'https://example.com/viral-post-5',
                'category' => InquiryCategory::HealthMedical,
                'status' => InquiryStatus::VerifiedTrue,
                'agency_id' => $moh->id,
                'reviewed_by' => $mcmcStaff->first()->id,
                'reviewed_at' => now()->subDays(3),
                'jurisdiction_accepted_at' => now()->subDays(2),
                'resolution_notes' => 'MOH confirmed the mandate is genuine and part of the updated national immunisation schedule.',
                'resolved_at' => now()->subDay(),
            ],
            [
                'title' => 'Duplicate submission of RM500 note rumour',
                'description' => 'A near-identical report about the RM500 banknote rumour, submitted separately.',
                'source_url' => 'https://example.com/viral-post-2b',
                'category' => InquiryCategory::FinancialScams,
                'status' => InquiryStatus::Discarded,
                'reviewed_by' => $mcmcStaff->first()->id,
                'reviewed_at' => now()->subDays(2),
                'resolution_notes' => 'Discarded as a duplicate of an existing inquiry.',
                'resolved_at' => now()->subDays(2),
            ],
            [
                'title' => 'Instagram post: earthquake warning for Kuala Lumpur',
                'description' => 'An Instagram post warns of an imminent major earthquake in Kuala Lumpur, citing an anonymous geologist.',
                'source_url' => 'https://example.com/viral-post-6',
                'category' => InquiryCategory::DisasterEmergency,
                'status' => InquiryStatus::Submitted,
            ],
        ];

        foreach ($inquiries as $index => $data) {
            $submitter = $publicUsers[$index % $publicUsers->count()];

            $inquiry = Inquiry::create([
                'reference_no' => sprintf('INQ-2026-%05d', $index + 1),
                'submitted_by' => $submitter->id,
                'title' => $data['title'],
                'description' => $data['description'],
                'source_url' => $data['source_url'],
                'category' => $data['category'],
                'status' => $data['status'],
                'agency_id' => $data['agency_id'] ?? null,
                'reviewed_by' => $data['reviewed_by'] ?? null,
                'reviewed_at' => $data['reviewed_at'] ?? null,
                'jurisdiction_accepted_at' => $data['jurisdiction_accepted_at'] ?? null,
                'resolution_notes' => $data['resolution_notes'] ?? null,
                'resolved_at' => $data['resolved_at'] ?? null,
            ]);

            InquiryActivityLog::create([
                'inquiry_id' => $inquiry->id,
                'user_id' => $submitter->id,
                'action' => 'submitted',
                'from_status' => null,
                'to_status' => InquiryStatus::Submitted->value,
                'notes' => 'Inquiry submitted by public user.',
            ]);

            if ($inquiry->status !== InquiryStatus::Submitted) {
                InquiryActivityLog::create([
                    'inquiry_id' => $inquiry->id,
                    'user_id' => $inquiry->reviewed_by ?? $mcmcStaff->first()->id,
                    'action' => 'status_changed',
                    'from_status' => InquiryStatus::Submitted->value,
                    'to_status' => $inquiry->status->value,
                    'notes' => $data['resolution_notes'] ?? 'Status updated during triage.',
                ]);
            }

            if ($index < 2) {
                InquiryEvidence::create([
                    'inquiry_id' => $inquiry->id,
                    'uploaded_by' => $submitter->id,
                    'file_path' => 'evidence/screenshot-'.($index + 1).'.png',
                    'file_name' => 'screenshot-'.($index + 1).'.png',
                    'file_type' => 'image/png',
                    'file_size' => 245_000,
                    'description' => 'Screenshot of the viral post.',
                ]);
            }
        }
    }
}
