<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('pos_inventories');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Dropped permanently - pos.melur.com integration removed
    }
};
