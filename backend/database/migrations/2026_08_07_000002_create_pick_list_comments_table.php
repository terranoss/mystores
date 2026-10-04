<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pick_list_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->date('pick_date');
            $table->string('item_key', 191);
            $table->string('item_id')->nullable();
            $table->string('model_id')->nullable();
            $table->string('item_name')->nullable();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(
                ['workspace_id', 'kind', 'pick_date', 'item_key'],
                'pick_list_comments_scope_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pick_list_comments');
    }
};
