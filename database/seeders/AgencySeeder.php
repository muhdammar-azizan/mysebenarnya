<?php

namespace Database\Seeders;

use App\Enums\InquiryCategory;
use App\Models\Agency;
use Illuminate\Database\Seeder;

class AgencySeeder extends Seeder
{
    public function run(): void
    {
        $agencies = [
            [
                'name' => 'Ministry of Health Malaysia',
                'code' => 'MOH',
                'specialization' => InquiryCategory::HealthMedical,
                'description' => 'Handles public health and medical misinformation verification.',
                'contact_email' => 'verification@moh.gov.my',
                'contact_phone' => '+603-8000-8000',
            ],
            [
                'name' => 'Bank Negara Malaysia',
                'code' => 'BNM',
                'specialization' => InquiryCategory::FinancialScams,
                'description' => 'Handles financial scam and monetary policy misinformation verification.',
                'contact_email' => 'verification@bnm.gov.my',
                'contact_phone' => '+603-2698-8044',
            ],
            [
                'name' => 'Election Commission of Malaysia',
                'code' => 'SPR',
                'specialization' => InquiryCategory::ElectoralPolitical,
                'description' => 'Handles election-related misinformation verification.',
                'contact_email' => 'verification@spr.gov.my',
                'contact_phone' => '+603-8892-7000',
            ],
            [
                'name' => 'Ministry of Home Affairs',
                'code' => 'KDN',
                'specialization' => InquiryCategory::DisasterEmergency,
                'description' => 'Handles public security and safety misinformation verification.',
                'contact_email' => 'verification@moha.gov.my',
                'contact_phone' => '+603-8886-8000',
            ],
            [
                'name' => 'Royal Malaysia Police',
                'code' => 'PDRM',
                'specialization' => InquiryCategory::CriminalFraud,
                'description' => 'Handles crime-related misinformation verification.',
                'contact_email' => 'verification@rmp.gov.my',
                'contact_phone' => '+603-2266-2222',
            ],
        ];

        foreach ($agencies as $agency) {
            Agency::create($agency);
        }
    }
}
