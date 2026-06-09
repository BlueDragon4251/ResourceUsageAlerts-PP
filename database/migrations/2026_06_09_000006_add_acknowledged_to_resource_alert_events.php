<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('resource_alert_events', function (Blueprint $table): void {
            $table->timestamp('acknowledged_at')->nullable()->after('resolved_at');
        });

        // Update the status enum to include 'acknowledged'
        DB::statement("ALTER TABLE resource_alert_events MODIFY COLUMN status ENUM('open', 'acknowledged', 'resolved') NOT NULL DEFAULT 'open'");
    }

    public function down(): void
    {
        // Revert acknowledged events back to open
        DB::statement("UPDATE resource_alert_events SET status = 'open', acknowledged_at = NULL WHERE status = 'acknowledged'");
        DB::statement("ALTER TABLE resource_alert_events MODIFY COLUMN status ENUM('open', 'resolved') NOT NULL DEFAULT 'open'");
        Schema::table('resource_alert_events', function (Blueprint $table): void {
            $table->dropColumn('acknowledged_at');
        });
    }
};