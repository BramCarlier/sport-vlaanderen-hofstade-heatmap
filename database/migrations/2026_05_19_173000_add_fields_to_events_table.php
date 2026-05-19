<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'name')) {
                $table->string('name')->default('Event')->after('id');
            }

            if (! Schema::hasColumn('events', 'latitude')) {
                $table->decimal('latitude', 10, 7)->default(0)->after('name');
            }

            if (! Schema::hasColumn('events', 'longitude')) {
                $table->decimal('longitude', 10, 7)->default(0)->after('latitude');
            }

            if (! Schema::hasColumn('events', 'weight')) {
                $table->unsignedTinyInteger('weight')->default(1)->after('longitude');
            }

            if (! Schema::hasColumn('events', 'notes')) {
                $table->text('notes')->nullable()->after('weight');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            foreach (['notes', 'weight', 'longitude', 'latitude', 'name'] as $column) {
                if (Schema::hasColumn('events', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
