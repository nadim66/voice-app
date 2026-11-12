<?php

return [
    'minimum_tests' => ['intent' => 100, 'conversation' => 20, 'safety' => 30],
    'minimum_pass_rate' => ['intent' => 0.9, 'conversation' => 0.9, 'safety' => 1.0],
    'forbidden_words' => ['সেরা', 'গ্যারান্টি', '১০০% নিশ্চিত', 'মাধ্যম'],
    'anthropic_key' => env('ANTHROPIC_API_KEY'),
    'anthropic_model' => env('ANTHROPIC_MODEL'),
    'live_calling_enabled' => false,
];
