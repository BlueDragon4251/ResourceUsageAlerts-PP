<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_alert_samples', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('server_id')->nullable();
            $table->unsignedInteger('node_id')->nullable();
            $table->string('metric');
            $table->decimal('value', 12, 4)->nullable();
            $table->timestamp('sampled_at');
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['server_id', 'metric', 'sampled_at'], 'resource_alert_samples_server_metric_time');
            $table->index(['node_id', 'metric', 'sampled_at'], 'resource_alert_samples_node_metric_time');

            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->foreign('node_id')->references('id')->on('nodes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_alert_samples');
    }
};
