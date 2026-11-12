<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Mengubah perintah.created_at menjadi presisi milidetik (3 digit pecahan detik). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('perintah', function (Blueprint $t) {
            $t->timestamp('created_at', 3)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('perintah', function (Blueprint $t) {
            $t->timestamp('created_at')->nullable()->change();
        });
    }
};