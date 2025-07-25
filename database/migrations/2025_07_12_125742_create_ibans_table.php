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
        Schema::create('ibans', function (Blueprint $table) {
            $table->id();
            $table->char('iban', 34);
            $table->string('bank_name', 100);
            $table->timestamp('created_at')->useCurrent();
            $table->unique('iban');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ibans');
    }
};
