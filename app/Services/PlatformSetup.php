<?php

namespace App\Services;

use App\Models\BrainTestCase;
use App\Models\IntentRule;
use App\Models\KnowledgePage;
use App\Models\ReplyCard;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

class PlatformSetup
{
    public function seed(): Tenant
    {
        return DB::transaction(function (): Tenant {
            $tenant = Tenant::firstOrCreate(['slug' => 'tenant_001_aitutor'], [
                'name' => 'AI Tutor', 'industry' => 'education', 'timezone' => 'Asia/Dhaka', 'status' => 'pilot',
                'settings' => ['calling_start' => '10:00', 'calling_end' => '20:00', 'daily_cap' => 300, 'max_concurrent_calls' => 5,
                    'open_questions' => "Confirm registration steps and mobile browser support.\nConfirm support team and callback hours.\nReview all draft answers and current app availability.",
                    'lexicon' => ['AI Tutor' => 'এআই টিউটর', 'onlineaitutor.com' => 'অনলাইন এআই টিউটর ডট কম', 'OTP' => 'ওটিপি']],
            ]);
            app(TenantContext::class)->run($tenant, function (): void {
                if (KnowledgePage::exists() || ReplyCard::exists()) {
                    return;
                }
                $source = json_decode(file_get_contents(resource_path('brain/aitutor.json')), true, flags: JSON_THROW_ON_ERROR);
                foreach (['knowledge' => KnowledgePage::class, 'replies' => ReplyCard::class, 'intents' => IntentRule::class, 'tests' => BrainTestCase::class] as $key => $model) {
                    foreach ($source[$key] as $data) {
                        $model::create($data);
                    }
                }
            });

            return $tenant;
        });
    }
}
