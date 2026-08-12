<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claims', function (Blueprint $table) {
            $table->id();
            $table->string('claimant_email')->nullable();
            $table->string('persona_inquiry_id')->nullable()->index(); // set once the Inquiry is created
            $table->string('verification_status')->default('pending'); // pending, verified, needs_resubmission, failed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claims');
    }
};
