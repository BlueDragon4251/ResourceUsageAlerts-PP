<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('resource_alert_rules') && ! Schema::hasColumn('resource_alert_rules', 'config')) {
            Schema::table('resource_alert_rules', function (Blueprint $table): void {
                $table->json('config')->nullable()->after('channels');
            });
        }

        if (! Schema::hasTable('resource_alert_maintenance_windows')) {
            Schema::create('resource_alert_maintenance_windows', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('scope')->default('global')->index();
                $table->unsignedInteger('server_id')->nullable()->index();
                $table->unsignedInteger('node_id')->nullable()->index();
                $table->unsignedInteger('user_id')->nullable()->index();
                $table->timestamp('starts_at');
                $table->timestamp('ends_at');
                $table->json('recurrence')->nullable();
                $table->boolean('enabled')->default(true)->index();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
                $table->foreign('node_id')->references('id')->on('nodes')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('resource_alert_notification_groups')) {
            Schema::create('resource_alert_notification_groups', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->unsignedInteger('owner_user_id')->nullable()->index();
                $table->json('channel_ids');
                $table->json('recipient_user_ids')->nullable();
                $table->boolean('shared')->default(false)->index();
                $table->timestamps();

                $table->foreign('owner_user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('resource_alert_delivery_attempts')) {
            Schema::create('resource_alert_delivery_attempts', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('event_id')->index();
                $table->unsignedBigInteger('channel_id')->nullable()->index();
                $table->string('channel_type', 64)->index();
                $table->string('status', 32)->index();
                $table->unsignedSmallInteger('response_status')->nullable();
                $table->string('failure_reason')->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->timestamp('attempted_at')->useCurrent()->index();

                $table->foreign('event_id')->references('id')->on('resource_alert_events')->cascadeOnDelete();
                $table->foreign('channel_id')->references('id')->on('resource_alert_channels')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('resource_alert_metric_tokens')) {
            Schema::create('resource_alert_metric_tokens', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('token_hash', 64)->unique();
                $table->unsignedInteger('server_id')->nullable()->index();
                $table->unsignedInteger('node_id')->nullable()->index();
                $table->json('allowed_metrics')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->boolean('enabled')->default(true)->index();
                $table->timestamps();

                $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
                $table->foreign('node_id')->references('id')->on('nodes')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('resource_alert_report_subscriptions')) {
            Schema::create('resource_alert_report_subscriptions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedInteger('user_id')->index();
                $table->string('frequency', 16)->default('weekly');
                $table->string('email');
                $table->json('filters')->nullable();
                $table->timestamp('last_sent_at')->nullable();
                $table->boolean('enabled')->default(true)->index();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_alert_report_subscriptions');
        Schema::dropIfExists('resource_alert_metric_tokens');
        Schema::dropIfExists('resource_alert_delivery_attempts');
        Schema::dropIfExists('resource_alert_notification_groups');
        Schema::dropIfExists('resource_alert_maintenance_windows');

        if (Schema::hasTable('resource_alert_rules') && Schema::hasColumn('resource_alert_rules', 'config')) {
            Schema::table('resource_alert_rules', fn (Blueprint $table) => $table->dropColumn('config'));
        }
    }
};
