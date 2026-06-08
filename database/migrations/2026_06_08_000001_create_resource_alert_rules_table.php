<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_alert_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('scope');
            $table->unsignedInteger('server_id')->nullable();
            $table->unsignedInteger('node_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('metric');
            $table->string('operator')->default('>=');
            $table->decimal('threshold', 12, 4)->nullable();
            $table->unsignedInteger('duration_minutes')->default(5);
            $table->unsignedInteger('cooldown_minutes')->default(30);
            $table->string('severity')->default('warning');
            $table->json('channels')->nullable();
            $table->boolean('enabled')->default(true)->index();
            $table->timestamp('last_checked_at')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('scope');
            $table->index('server_id');
            $table->index('node_id');
            $table->index('user_id');
            $table->index('metric');

            $table->foreign('server_id')->references('id')->on('servers')->nullOnDelete();
            $table->foreign('node_id')->references('id')->on('nodes')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_alert_rules');
    }
};
