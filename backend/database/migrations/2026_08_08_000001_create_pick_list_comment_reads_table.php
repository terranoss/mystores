<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pick_list_comment_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->date('pick_date');
            $table->string('item_key', 191);
            $table->timestamp('last_read_at');
            $table->timestamps();

            $table->unique(
                ['user_id', 'workspace_id', 'kind', 'pick_date', 'item_key'],
                'pick_list_comment_reads_scope_uidx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pick_list_comment_reads');
    }
};
