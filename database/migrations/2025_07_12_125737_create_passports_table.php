<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('passports', function (Blueprint $table) {
            $table->id();
            $table->char('iso_code', 3);
            $table->string('passport_number', 20);
            $table->date('date_issued');
            $table->date('date_expiry');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['iso_code', 'passport_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('passports');
    }
};
