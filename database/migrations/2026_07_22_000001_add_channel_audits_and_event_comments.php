<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('resource_alert_channels') && ! Schema::hasColumn('resource_alert_channels', 'server_id')) {
            Schema::table('resource_alert_channels', function (Blueprint $table): void {
                $table->unsignedInteger('server_id')->nullable()->after('user_id')->index();
                $table->foreign('server_id')->references('id')->on('servers')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('resource_alert_channel_audits')) {
            Schema::create('resource_alert_channel_audits', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('channel_id')->nullable()->index();
                $table->unsignedInteger('actor_user_id')->nullable()->index();
                $table->unsignedInteger('server_id')->nullable()->index();
                $table->string('action', 32)->index();
                $table->string('channel_type', 64)->nullable();
                $table->json('changed_fields')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
                $table->foreign('server_id')->references('id')->on('servers')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('resource_alert_comments')) {
            Schema::create('resource_alert_comments', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('event_id')->index();
                $table->unsignedInteger('user_id')->nullable()->index();
                $table->text('body');
                $table->timestamps();

                $table->foreign('event_id')->references('id')->on('resource_alert_events')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_alert_comments');
        Schema::dropIfExists('resource_alert_channel_audits');

        if (Schema::hasTable('resource_alert_channels') && Schema::hasColumn('resource_alert_channels', 'server_id')) {
            Schema::table('resource_alert_channels', function (Blueprint $table): void {
                $table->dropForeign(['server_id']);
                $table->dropColumn('server_id');
            });
        }
    }
};
