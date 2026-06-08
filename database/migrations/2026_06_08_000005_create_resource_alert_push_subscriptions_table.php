<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_alert_push_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->char('endpoint_hash', 64)->unique();
            $table->text('subscription');
            $table->string('user_agent', 500)->nullable();
            $table->unsignedSmallInteger('failure_count')->default(0);
            $table->timestamp('last_success_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_alert_push_subscriptions');
    }
};
