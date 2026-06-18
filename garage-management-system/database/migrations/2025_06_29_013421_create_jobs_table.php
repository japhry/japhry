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
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_card_number')->unique(); // Unique identifier for the job card

            $table->unsignedBigInteger('branch_id');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');

            $table->unsignedBigInteger('vehicle_id');
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->onDelete('cascade');

            $table->unsignedBigInteger('customer_id'); // Denormalized for easier access, though vehicle has customer_id
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');

            $table->unsignedBigInteger('created_by_user_id')->nullable(); // User who created the job
            $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');

            $table->unsignedBigInteger('assigned_to_user_id')->nullable(); // Mechanic/technician assigned
            $table->foreign('assigned_to_user_id')->references('id')->on('users')->onDelete('set null');

            $table->string('status')->default('pending'); // e.g., pending, in_progress, completed, invoiced, paid
            $table->text('description')->nullable(); // Initial problem description or job scope
            $table->dateTime('date_opened')->useCurrent();
            $table->dateTime('date_closed')->nullable();
            $table->dateTime('estimated_completion_date')->nullable();
            $table->dateTime('actual_completion_date')->nullable();

            // Basic costings - more detailed costs will be in job_items/services table
            $table->decimal('estimated_cost', 10, 2)->nullable();
            $table->decimal('actual_cost', 10, 2)->nullable();
            $table->decimal('total_amount', 10, 2)->nullable(); // Final amount including tax, discounts
            $table->decimal('amount_paid', 10, 2)->default(0.00);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
