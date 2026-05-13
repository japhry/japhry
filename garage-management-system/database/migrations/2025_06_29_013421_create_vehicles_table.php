<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade'); // Vehicle must belong to a customer

            $table->string('make');
            $table->string('model');
            $table->string('year', 4)->nullable();
            $table->string('vin')->unique()->nullable(); // Vehicle Identification Number, should be unique
            $table->string('registration_number')->unique(); // License plate, should be unique
            $table->string('color')->nullable();
            $table->text('notes')->nullable(); // Any additional notes about the vehicle

            // Future: Consider engine_type, mileage, etc.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
