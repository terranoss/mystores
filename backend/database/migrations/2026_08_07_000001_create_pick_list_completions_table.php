<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pick_list_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->date('pick_date');
            $table->string('item_key', 191);
            $table->string('item_id')->nullable();
            $table->string('model_id')->nullable();
            $table->string('item_name');
            $table->string('photo_path');
            $table->foreignId('picked_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('picked_at');
            $table->timestamps();

            $table->unique(
                ['workspace_id', 'kind', 'pick_date', 'item_key'],
                'pick_list_completions_unique'
            );
            $table->index(['workspace_id', 'kind', 'pick_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pick_list_completions');
    }
};
