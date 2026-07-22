<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_usage_alerts_announcement_reads', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('announcement_id');
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'announcement_id'], 'rua_announcement_reads_user_announcement_unique');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_usage_alerts_announcement_reads');
    }
};
