<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Menyimpan arah hadap robot (derajat) di setiap titik jejak, supaya letak halangan di peta tepat. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('log_sensor', 'arah')) {
            Schema::table('log_sensor', function (Blueprint $t) {
                $t->float('arah')->nullable()->after('pos_y');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('log_sensor', 'arah')) {
            Schema::table('log_sensor', function (Blueprint $t) {
                $t->dropColumn('arah');
            });
        }
    }
};