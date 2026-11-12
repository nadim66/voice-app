<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('industry')->default('education');
            $table->string('timezone')->default('Asia/Dhaka');
            $table->string('status')->default('pilot');
            $table->json('settings')->nullable();
            $table->timestamps();
        });
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('viewer');
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id']);
        });
        Schema::create('phone_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('number');
            $table->string('trunk')->nullable();
            $table->string('direction')->default('both');
            $table->string('status')->default('unconfigured');
            $table->timestamps();
        });
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind');
            $table->string('secret_ref')->nullable();
            $table->json('config')->nullable();
            $table->string('status')->default('unconfigured');
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
        });
        Schema::create('knowledge_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('title');
            $table->longText('body');
            $table->string('source');
            $table->string('status')->default('draft');
            $table->date('effective_from')->nullable();
            $table->date('expires_at')->nullable();
            $table->unique(['tenant_id', 'code']);
            $table->timestamps();
        });
        Schema::create('reply_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->text('text');
            $table->json('fact_codes')->nullable();
            $table->string('action')->default('none');
            $table->string('status')->default('draft');
            $table->date('effective_from')->nullable();
            $table->date('expires_at')->nullable();
            $table->unique(['tenant_id', 'code']);
            $table->timestamps();
        });
        Schema::create('intent_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('reply_code');
            $table->json('examples');
            $table->json('keywords')->nullable();
            $table->json('negatives')->nullable();
            $table->unsignedSmallInteger('priority')->default(50);
            $table->unique(['tenant_id', 'name']);
            $table->timestamps();
        });
        Schema::create('brain_test_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('suite');
            $table->text('input')->nullable();
            $table->json('turns')->nullable();
            $table->string('expected_reply')->nullable();
            $table->string('expected_action')->nullable();
            $table->boolean('expected_end')->nullable();
            $table->unique(['tenant_id', 'name']);
            $table->timestamps();
        });
        Schema::create('brain_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status')->default('published');
            $table->json('snapshot');
            $table->json('test_results');
            $table->string('checksum', 64);
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->unique(['tenant_id', 'version']);
            $table->timestamps();
        });
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('phone_e164', 13);
            $table->string('name')->nullable();
            $table->unsignedTinyInteger('student_class')->nullable();
            $table->string('version')->nullable();
            $table->string('source')->nullable();
            $table->timestamp('lead_at')->nullable();
            $table->string('external_ref')->nullable();
            $table->string('experiment_group')->default('call');
            $table->json('attributes')->nullable();
            $table->unique(['tenant_id', 'phone_e164']);
            $table->timestamps();
        });
        Schema::create('consent_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();
            $table->string('channel')->default('call');
            $table->string('purpose')->default('service');
            $table->boolean('granted');
            $table->string('source');
            $table->string('text_version');
            $table->timestamp('occurred_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['tenant_id', 'contact_id', 'occurred_at']);
            $table->timestamps();
        });
        Schema::create('dnd_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('phone_e164', 13);
            $table->string('reason');
            $table->string('source')->default('manual');
            $table->unique(['tenant_id', 'phone_e164']);
            $table->timestamps();
        });
        Schema::create('contact_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });
        Schema::create('list_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->unique(['contact_list_id', 'contact_id']);
            $table->timestamps();
        });
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('goal')->default('registration');
            $table->string('mode')->default('hybrid');
            $table->string('purpose')->default('service');
            $table->foreignId('contact_list_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brain_release_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status')->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->unsignedInteger('retry_gap_minutes')->default(240);
            $table->unsignedInteger('daily_cap')->default(300);
            $table->unsignedSmallInteger('concurrency')->default(5);
            $table->decimal('budget_tk', 12, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('call_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('attempt_no')->default(1);
            $table->timestamp('next_attempt_at')->nullable();
            $table->string('status')->default('simulated');
            $table->string('reason')->nullable();
            $table->string('batch_id');
            $table->timestamps();
        });
        Schema::create('voice_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('call_uuid')->unique();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brain_release_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('direction')->default('test');
            $table->string('state')->default('greet');
            $table->string('result')->default('in_progress');
            $table->string('outcome')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_sec')->default(0);
            $table->decimal('cost_tk', 12, 4)->default(0);
            $table->json('context')->nullable();
            $table->boolean('is_simulation')->default(true);
            $table->timestamps();
        });
        Schema::create('call_turns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voice_call_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('turn_no');
            $table->string('speaker');
            $table->text('text');
            $table->string('intent')->nullable();
            $table->string('reply_code')->nullable();
            $table->string('action')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->unsignedInteger('latency_ms')->default(0);
            $table->json('evidence')->nullable();
            $table->unique(['voice_call_id', 'turn_no']);
            $table->timestamps();
        });
        Schema::create('call_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voice_call_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->json('data')->nullable();
            $table->timestamps();
        });
        Schema::create('call_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voice_call_id')->constrained()->cascadeOnDelete();
            $table->text('summary_bn');
            $table->unsignedTinyInteger('lead_score')->default(0);
            $table->string('sentiment')->default('unknown');
            $table->string('next_action')->nullable();
            $table->timestamps();
        });
        Schema::create('follow_up_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('voice_call_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('callback');
            $table->text('notes')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('open');
            $table->boolean('is_simulation')->default(true);
            $table->timestamps();
        });
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('voice_call_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel')->default('sms');
            $table->string('template_code')->default('SMS_REG_LINK');
            $table->text('body');
            $table->string('status')->default('dry_run');
            $table->string('provider_message_id')->nullable();
            $table->timestamps();
        });
        Schema::create('outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();
            $table->foreignId('voice_call_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('registered');
            $table->string('source');
            $table->timestamp('occurred_at');
            $table->string('external_ref')->nullable();
            $table->timestamps();
        });
        Schema::create('review_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voice_call_id')->nullable()->constrained()->nullOnDelete();
            $table->text('question');
            $table->string('issue');
            $table->text('suggested_fix')->nullable();
            $table->string('status')->default('open');
            $table->unsignedInteger('fixed_in_version')->nullable();
            $table->timestamps();
        });
        Schema::create('test_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('brain_version')->nullable();
            $table->string('suite');
            $table->unsignedInteger('total');
            $table->unsignedInteger('passed');
            $table->json('failed_cases')->nullable();
            $table->string('snapshot_checksum', 64);
            $table->timestamps();
        });
        Schema::create('usage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voice_call_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('telephony_sec')->default(0);
            $table->unsignedInteger('stt_sec')->default(0);
            $table->unsignedInteger('tts_chars')->default(0);
            $table->unsignedInteger('llm_input_tokens')->default(0);
            $table->unsignedInteger('llm_output_tokens')->default(0);
            $table->decimal('cost_tk', 12, 4)->default(0);
            $table->string('provider')->default('offline');
            $table->timestamps();
        });
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('target');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('usage_records');
        Schema::dropIfExists('test_runs');
        Schema::dropIfExists('review_items');
        Schema::dropIfExists('outcomes');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('follow_up_tasks');
        Schema::dropIfExists('call_summaries');
        Schema::dropIfExists('call_events');
        Schema::dropIfExists('call_turns');
        Schema::dropIfExists('voice_calls');
        Schema::dropIfExists('call_jobs');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('list_members');
        Schema::dropIfExists('contact_lists');
        Schema::dropIfExists('dnd_entries');
        Schema::dropIfExists('consent_events');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('brain_releases');
        Schema::dropIfExists('brain_test_cases');
        Schema::dropIfExists('intent_rules');
        Schema::dropIfExists('reply_cards');
        Schema::dropIfExists('knowledge_pages');
        Schema::dropIfExists('integrations');
        Schema::dropIfExists('phone_numbers');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('tenants');
    }
};
