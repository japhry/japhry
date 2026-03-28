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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable()->unique(); // Made phone unique for easier lookup, can be relaxed if needed
            $table->string('email')->nullable()->unique(); // Made email unique
            $table->text('address')->nullable();
            // Future: Consider adding branch_id if customers are branch-specific,
            // or a pivot table if customers can interact with multiple branches.
            // For now, keeping it global.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
