<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('resource_alert_events')) {
            return;
        }

        if (!Schema::hasColumn('resource_alert_events', 'acknowledged_at')) {
            Schema::table('resource_alert_events', function (Blueprint $table): void {
                $table->timestamp('acknowledged_at')->nullable()->after('resolved_at');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('resource_alert_events')) {
            return;
        }

        if (Schema::hasColumn('resource_alert_events', 'acknowledged_at')) {
            Schema::table('resource_alert_events', function (Blueprint $table): void {
                $table->dropColumn('acknowledged_at');
            });
        }
    }
};
