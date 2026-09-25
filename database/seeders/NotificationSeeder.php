<?php

namespace Database\Seeders;

use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use App\Notifications\InquiryStatusChanged;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $resolved = Inquiry::whereIn('status', [
            InquiryStatus::VerifiedTrue,
            InquiryStatus::IdentifiedFake,
            InquiryStatus::Rejected,
            InquiryStatus::Discarded,
        ])->with('submitter')->get();

        foreach ($resolved as $inquiry) {
            $inquiry->submitter->notify(new InquiryStatusChanged(
                inquiry: $inquiry,
                fromStatus: InquiryStatus::Submitted->value,
                toStatus: $inquiry->status->value,
            ));
        }
    }
}
