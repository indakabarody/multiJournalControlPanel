<?php
/**
 * Copyright (c) 2026 Indaka Barody
 * Multi-Journal Control Panel
 * Developed for Open Journal Systems (OJS) 3.3.x.
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class MultiJournalControlPanelSchemaMigration extends Migration
{
    /**
     * Run the database migrations to install or upgrade all 15 mjcp_* tables.
     * Does not modify any core OJS tables.
     *
     * @return void
     */
    public function up()
    {
        // 1. mjcp_article_data
        if (!Capsule::schema()->hasTable('mjcp_article_data')) {
            Capsule::schema()->create('mjcp_article_data', function (Blueprint $table) {
                $table->bigInteger('article_data_id')->autoIncrement();
                $table->bigInteger('submission_id');
                $table->bigInteger('context_id');
                $table->string('journal_path', 64);
                $table->string('journal_name', 255);
                $table->text('article_title')->nullable();
                $table->text('author_names')->nullable();
                $table->string('correspondent_email', 255)->nullable();
                $table->smallInteger('stage_id')->default(1);
                $table->string('pipeline_bucket', 32)->default('submission');
                $table->smallInteger('submission_status')->default(1);
                $table->tinyInteger('is_accepted')->default(0);
                $table->tinyInteger('is_declined')->default(0);
                $table->tinyInteger('is_published')->default(0);
                $table->smallInteger('current_round')->default(0);
                $table->smallInteger('round_status')->nullable();
                $table->date('stage_entry_date')->nullable();
                $table->datetime('date_submitted')->nullable();
                $table->date('date_accepted')->nullable();
                $table->date('date_published')->nullable();
                $table->bigInteger('target_issue_id')->nullable();
                $table->string('target_issue_label', 255)->nullable();
                $table->date('target_issue_date')->nullable();
                $table->integer('elapsed_working_days')->default(0);
                $table->integer('limit_working_days')->default(0);
                $table->integer('overdue_working_days')->default(0);
                $table->integer('remaining_days')->default(0);
                $table->string('bottleneck_status', 32)->default('normal');
                $table->integer('urgency_score')->default(0);
                $table->string('loa_status', 32)->default('missing');
                $table->tinyInteger('copyediting_done')->default(0);
                $table->tinyInteger('is_ready_to_publish')->default(0);
                $table->string('doi', 255)->nullable();
                $table->string('publication_url', 512)->nullable();
                $table->datetime('last_synced_at')->nullable();

                $table->unique(['submission_id'], 'mjcp_article_data_sub_uq');
                $table->index(['context_id'], 'mjcp_article_data_ctx_idx');
                $table->index(['context_id', 'pipeline_bucket'], 'mjcp_article_data_bucket_idx');
                $table->index(['bottleneck_status', 'urgency_score'], 'mjcp_article_data_btl_idx');
                $table->index(['context_id', 'target_issue_id'], 'mjcp_article_data_issue_idx');
            });
        }

        // 2. mjcp_payments
        if (!Capsule::schema()->hasTable('mjcp_payments')) {
            Capsule::schema()->create('mjcp_payments', function (Blueprint $table) {
                $table->bigInteger('payment_id')->autoIncrement();
                $table->bigInteger('submission_id');
                $table->bigInteger('context_id');
                $table->string('payment_status', 16)->default('unpaid');
                $table->bigInteger('amount')->default(0);
                $table->text('notes')->nullable();
                $table->string('proof_file_path', 512)->nullable();
                $table->string('proof_original_name', 255)->nullable();
                $table->string('proof_mime_type', 128)->nullable();
                $table->integer('proof_file_size')->nullable();
                $table->bigInteger('updated_by')->nullable();
                $table->datetime('updated_at')->nullable();
                $table->datetime('created_at')->nullable();

                $table->unique(['submission_id'], 'mjcp_payments_sub_uq');
                $table->index(['context_id', 'payment_status'], 'mjcp_payments_ctx_status_idx');
            });
        }

        // 3. mjcp_payment_logs
        if (!Capsule::schema()->hasTable('mjcp_payment_logs')) {
            Capsule::schema()->create('mjcp_payment_logs', function (Blueprint $table) {
                $table->bigInteger('payment_log_id')->autoIncrement();
                $table->bigInteger('payment_id')->nullable();
                $table->bigInteger('submission_id');
                $table->bigInteger('context_id');
                $table->string('old_status', 16)->nullable();
                $table->string('new_status', 16)->nullable();
                $table->bigInteger('old_amount')->nullable();
                $table->bigInteger('new_amount')->nullable();
                $table->text('old_notes')->nullable();
                $table->text('new_notes')->nullable();
                $table->string('old_proof_file_path', 512)->nullable();
                $table->string('new_proof_file_path', 512)->nullable();
                $table->bigInteger('changed_by')->nullable();
                $table->string('changed_by_username', 128)->nullable();
                $table->datetime('changed_at');

                $table->index(['submission_id'], 'mjcp_payment_logs_sub_idx');
                $table->index(['context_id'], 'mjcp_payment_logs_ctx_idx');
            });
        }

        // 4. mjcp_author_forms
        if (!Capsule::schema()->hasTable('mjcp_author_forms')) {
            Capsule::schema()->create('mjcp_author_forms', function (Blueprint $table) {
                $table->bigInteger('author_form_id')->autoIncrement();
                $table->bigInteger('submission_id');
                $table->bigInteger('context_id');
                $table->string('full_name_authors', 512);
                $table->string('affiliation', 512);
                $table->string('correspondent_email', 255);
                $table->string('whatsapp_number', 64);
                $table->string('transfer_proof_path', 512)->nullable();
                $table->string('transfer_proof_original_name', 255)->nullable();
                $table->string('transfer_proof_mime', 128)->nullable();
                $table->integer('transfer_proof_size')->nullable();
                $table->text('testimonial')->nullable();
                $table->tinyInteger('testimonial_consent')->default(1);
                $table->string('ip_address_hash', 128)->nullable();
                $table->string('submitted_locale', 16)->nullable();
                $table->datetime('submitted_at');
                $table->datetime('updated_at')->nullable();

                $table->unique(['submission_id'], 'mjcp_author_forms_sub_uq');
                $table->index(['context_id'], 'mjcp_author_forms_ctx_idx');
            });
        }

        // 5. mjcp_author_tokens
        if (!Capsule::schema()->hasTable('mjcp_author_tokens')) {
            Capsule::schema()->create('mjcp_author_tokens', function (Blueprint $table) {
                $table->bigInteger('token_id')->autoIncrement();
                $table->bigInteger('submission_id');
                $table->bigInteger('context_id');
                $table->string('token_hash', 64);
                $table->string('token_display', 128)->nullable();
                $table->tinyInteger('is_active')->default(1);
                $table->datetime('expires_at')->nullable();
                $table->bigInteger('created_by')->nullable();
                $table->datetime('created_at');
                $table->datetime('revoked_at')->nullable();

                $table->unique(['token_hash'], 'mjcp_author_tokens_hash_uq');
                $table->index(['submission_id', 'is_active'], 'mjcp_author_tokens_sub_idx');
            });
        }

        // 6. mjcp_loa_files
        if (!Capsule::schema()->hasTable('mjcp_loa_files')) {
            Capsule::schema()->create('mjcp_loa_files', function (Blueprint $table) {
                $table->bigInteger('loa_id')->autoIncrement();
                $table->bigInteger('submission_id');
                $table->bigInteger('context_id');
                $table->string('loa_number', 128);
                $table->string('file_path', 512);
                $table->string('original_filename', 255);
                $table->string('mime_type', 128);
                $table->integer('file_size');
                $table->tinyInteger('is_current')->default(1);
                $table->bigInteger('uploaded_by')->nullable();
                $table->string('uploaded_by_username', 128)->nullable();
                $table->datetime('uploaded_at');

                $table->index(['submission_id', 'is_current'], 'mjcp_loa_files_sub_cur_idx');
                $table->index(['loa_number'], 'mjcp_loa_files_num_idx');
            });
        }

        // 7. mjcp_issue_quotas
        if (!Capsule::schema()->hasTable('mjcp_issue_quotas')) {
            Capsule::schema()->create('mjcp_issue_quotas', function (Blueprint $table) {
                $table->bigInteger('quota_id')->autoIncrement();
                $table->bigInteger('context_id');
                $table->bigInteger('issue_id')->default(0);
                $table->integer('quota')->default(10);
                $table->date('target_date')->nullable();
                $table->bigInteger('updated_by')->nullable();
                $table->datetime('updated_at')->nullable();

                $table->unique(['context_id', 'issue_id'], 'mjcp_issue_quotas_ctx_iss_uq');
            });
        }

        // 8. mjcp_stage_thresholds
        if (!Capsule::schema()->hasTable('mjcp_stage_thresholds')) {
            Capsule::schema()->create('mjcp_stage_thresholds', function (Blueprint $table) {
                $table->bigInteger('threshold_id')->autoIncrement();
                $table->bigInteger('context_id')->default(0);
                $table->integer('round1_limit_days')->default(21);
                $table->integer('round2_limit_days')->default(7);
                $table->integer('round1_warning_days')->default(3);
                $table->integer('round2_warning_days')->default(2);
                $table->integer('copyediting_warning_days')->default(10);
                $table->bigInteger('updated_by')->nullable();
                $table->datetime('updated_at')->nullable();

                $table->unique(['context_id'], 'mjcp_stage_thresholds_ctx_uq');
            });
        }

        // 9. mjcp_holidays
        if (!Capsule::schema()->hasTable('mjcp_holidays')) {
            Capsule::schema()->create('mjcp_holidays', function (Blueprint $table) {
                $table->bigInteger('holiday_id')->autoIncrement();
                $table->bigInteger('context_id')->default(0);
                $table->date('holiday_date');
                $table->string('description', 255);
                $table->bigInteger('created_by')->nullable();
                $table->datetime('created_at')->nullable();

                $table->unique(['context_id', 'holiday_date'], 'mjcp_holidays_ctx_date_uq');
            });
        }

        // 10. mjcp_access_users
        if (!Capsule::schema()->hasTable('mjcp_access_users')) {
            Capsule::schema()->create('mjcp_access_users', function (Blueprint $table) {
                $table->bigInteger('access_id')->autoIncrement();
                $table->bigInteger('user_id')->nullable();
                $table->string('username', 128);
                $table->string('access_type', 32)->default('payment_access');
                $table->tinyInteger('is_active')->default(1);
                $table->bigInteger('created_by')->nullable();
                $table->datetime('created_at')->nullable();

                $table->unique(['username', 'access_type'], 'mjcp_access_users_uname_uq');
            });
        }

        // 11. mjcp_roles
        if (!Capsule::schema()->hasTable('mjcp_roles')) {
            Capsule::schema()->create('mjcp_roles', function (Blueprint $table) {
                $table->bigInteger('role_id')->autoIncrement();
                $table->string('role_key', 64);
                $table->string('role_name', 128);
                $table->string('description', 255)->nullable();
                $table->datetime('created_at')->nullable();

                $table->unique(['role_key'], 'mjcp_roles_key_uq');
            });
        }

        // 12. mjcp_user_roles
        if (!Capsule::schema()->hasTable('mjcp_user_roles')) {
            Capsule::schema()->create('mjcp_user_roles', function (Blueprint $table) {
                $table->bigInteger('user_role_id')->autoIncrement();
                $table->bigInteger('user_id')->nullable();
                $table->string('username', 128);
                $table->string('role_key', 64);
                $table->bigInteger('context_id')->default(0);
                $table->bigInteger('assigned_by')->nullable();
                $table->datetime('assigned_at')->nullable();

                $table->unique(['username', 'role_key', 'context_id'], 'mjcp_user_roles_u_r_c_uq');
                $table->index(['user_id'], 'mjcp_user_roles_uid_idx');
            });
        }

        // 13. mjcp_audit_logs
        if (!Capsule::schema()->hasTable('mjcp_audit_logs')) {
            Capsule::schema()->create('mjcp_audit_logs', function (Blueprint $table) {
                $table->bigInteger('audit_id')->autoIncrement();
                $table->bigInteger('context_id')->default(0);
                $table->bigInteger('submission_id')->nullable();
                $table->bigInteger('user_id')->nullable();
                $table->string('username', 128)->nullable();
                $table->string('action', 128);
                $table->string('entity_type', 64)->nullable();
                $table->text('old_value')->nullable();
                $table->text('new_value')->nullable();
                $table->string('ip_address_hash', 128)->nullable();
                $table->datetime('created_at');

                $table->index(['context_id', 'created_at'], 'mjcp_audit_logs_ctx_idx');
                $table->index(['submission_id'], 'mjcp_audit_logs_sub_idx');
            });
        }

        // 14. mjcp_sync_logs
        if (!Capsule::schema()->hasTable('mjcp_sync_logs')) {
            Capsule::schema()->create('mjcp_sync_logs', function (Blueprint $table) {
                $table->bigInteger('sync_id')->autoIncrement();
                $table->bigInteger('context_id')->default(0);
                $table->string('sync_status', 32)->default('connected');
                $table->string('sync_mode', 32)->default('scheduled');
                $table->integer('articles_processed')->default(0);
                $table->integer('active_count')->default(0);
                $table->text('error_message')->nullable();
                $table->bigInteger('triggered_by')->nullable();
                $table->datetime('started_at');
                $table->datetime('finished_at')->nullable();

                $table->index(['context_id', 'started_at'], 'mjcp_sync_logs_ctx_idx');
            });
        }

        // 15. mjcp_testimonials
        if (!Capsule::schema()->hasTable('mjcp_testimonials')) {
            Capsule::schema()->create('mjcp_testimonials', function (Blueprint $table) {
                $table->bigInteger('testimonial_id')->autoIncrement();
                $table->bigInteger('submission_id');
                $table->bigInteger('context_id');
                $table->string('author_name', 255);
                $table->string('institution', 255);
                $table->string('journal_name', 255);
                $table->text('testimonial_text');
                $table->tinyInteger('consent_given')->default(1);
                $table->tinyInteger('consent_withdrawn')->default(0);
                $table->datetime('withdrawn_at')->nullable();
                $table->bigInteger('withdrawn_by')->nullable();
                $table->datetime('submitted_at');

                $table->unique(['submission_id'], 'mjcp_testimonials_sub_uq');
                $table->index(['context_id', 'consent_given', 'consent_withdrawn'], 'mjcp_testimonials_ctx_consent_idx');
            });
        }

        $this->seedDefaultRolesAndThresholds();
    }

    /**
     * Seed default plugin roles (Director, Journal Manager, Finance Admin, Technical Admin)
     * and default stage thresholds (21 days Round 1, 7 days Round 2+, H-10 Copyediting).
     *
     * @return void
     */
    public function seedDefaultRolesAndThresholds()
    {
        $now = date('Y-m-d H:i:s');
        $defaultRoles = [
            [
                'role_key' => 'director',
                'role_name' => 'Director',
                'description' => 'Dashboard, pipeline, bottleneck, issue health, performance, and report export (no payment access)',
            ],
            [
                'role_key' => 'journal_manager',
                'role_name' => 'Journal Manager',
                'description' => 'Dashboard, pipeline, LOA upload, author links, and read-only payment view (if registered)',
            ],
            [
                'role_key' => 'finance_admin',
                'role_name' => 'Finance Admin',
                'description' => 'Payment verification, amount, notes, proof upload, bulk import, and payment reports (if registered)',
            ],
            [
                'role_key' => 'technical_admin',
                'role_name' => 'Technical Admin',
                'description' => 'System configuration, thresholds, holidays, quotas, role assignments, payment access list, and sync',
            ],
        ];

        foreach ($defaultRoles as $r) {
            $exists = Capsule::table('mjcp_roles')->where('role_key', '=', $r['role_key'])->exists();
            if (!$exists) {
                Capsule::table('mjcp_roles')->insert([
                    'role_key' => $r['role_key'],
                    'role_name' => $r['role_name'],
                    'description' => $r['description'],
                    'created_at' => $now,
                ]);
            }
        }

        $thresholdExists = Capsule::table('mjcp_stage_thresholds')->where('context_id', '=', 0)->exists();
        if (!$thresholdExists) {
            Capsule::table('mjcp_stage_thresholds')->insert([
                'context_id' => 0,
                'round1_limit_days' => 21,
                'round2_limit_days' => 7,
                'round1_warning_days' => 3,
                'round2_warning_days' => 2,
                'copyediting_warning_days' => 10,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations ONLY when explicitly requested with confirmation.
     * Uninstalling or disabling the plugin does NOT automatically drop plugin data.
     *
     * @param bool $confirmedDrop
     * @return void
     */
    public function down($confirmedDrop = false)
    {
        if (!$confirmedDrop) {
            return;
        }
        Capsule::schema()->dropIfExists('mjcp_testimonials');
        Capsule::schema()->dropIfExists('mjcp_sync_logs');
        Capsule::schema()->dropIfExists('mjcp_audit_logs');
        Capsule::schema()->dropIfExists('mjcp_user_roles');
        Capsule::schema()->dropIfExists('mjcp_roles');
        Capsule::schema()->dropIfExists('mjcp_access_users');
        Capsule::schema()->dropIfExists('mjcp_holidays');
        Capsule::schema()->dropIfExists('mjcp_stage_thresholds');
        Capsule::schema()->dropIfExists('mjcp_issue_quotas');
        Capsule::schema()->dropIfExists('mjcp_loa_files');
        Capsule::schema()->dropIfExists('mjcp_author_tokens');
        Capsule::schema()->dropIfExists('mjcp_author_forms');
        Capsule::schema()->dropIfExists('mjcp_payment_logs');
        Capsule::schema()->dropIfExists('mjcp_payments');
        Capsule::schema()->dropIfExists('mjcp_article_data');
    }
}
