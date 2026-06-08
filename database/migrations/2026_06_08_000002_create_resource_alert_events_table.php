<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_alert_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rule_id')->constrained('resource_alert_rules')->cascadeOnDelete();
            $table->unsignedInteger('server_id')->nullable();
            $table->unsignedInteger('node_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('metric');
            $table->string('severity');
            $table->string('status')->default('open');
            $table->decimal('value', 12, 4)->nullable();
            $table->decimal('threshold', 12, 4)->nullable();
            $table->text('message')->nullable();
            $table->timestamp('triggered_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('last_notified_at')->nullable();
            $table->unsignedInteger('notification_count')->default(0);
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index('rule_id');
            $table->index('status');
            $table->index('server_id');
            $table->index('node_id');
            $table->index('triggered_at');
            $table->index('resolved_at');
            $table->index(['rule_id', 'status', 'server_id', 'node_id'], 'resource_alert_events_open_lookup');

            $table->foreign('server_id')->references('id')->on('servers')->nullOnDelete();
            $table->foreign('node_id')->references('id')->on('nodes')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_alert_events');
    }
};
