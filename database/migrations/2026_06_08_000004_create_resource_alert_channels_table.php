<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_alert_channels', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('name');
            $table->string('type');
            $table->text('config')->nullable();
            $table->boolean('enabled')->default(true)->index();
            $table->timestamps();

            $table->index(['user_id', 'type']);

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_alert_channels');
    }
};
