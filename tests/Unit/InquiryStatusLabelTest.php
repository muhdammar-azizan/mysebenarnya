<?php

namespace Tests\Unit;

use App\Enums\InquiryStatus;
use PHPUnit\Framework\TestCase;

class InquiryStatusLabelTest extends TestCase
{
    public function test_verified_true_labels_as_verified_as_true(): void
    {
        $this->assertSame('Verified as True', InquiryStatus::VerifiedTrue->label());
    }

    public function test_identified_fake_labels_as_identified_as_fake(): void
    {
        $this->assertSame('Identified as Fake', InquiryStatus::IdentifiedFake->label());
    }

    public function test_other_statuses_label_as_their_own_value(): void
    {
        $this->assertSame('Submitted', InquiryStatus::Submitted->label());
        $this->assertSame('Under Investigation', InquiryStatus::UnderInvestigation->label());
        $this->assertSame('Rejected', InquiryStatus::Rejected->label());
        $this->assertSame('Discarded', InquiryStatus::Discarded->label());
    }

    public function test_stored_backing_values_are_unchanged(): void
    {
        $this->assertSame('Verified True', InquiryStatus::VerifiedTrue->value);
        $this->assertSame('Identified Fake', InquiryStatus::IdentifiedFake->value);
    }
}
