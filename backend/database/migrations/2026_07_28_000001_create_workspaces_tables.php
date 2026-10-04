<?php

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('workspace_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 20); // owner|member
            $table->timestamps();
            $table->unique(['workspace_id', 'user_id']);
        });

        Schema::create('workspace_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('email');
            $table->string('token', 64)->unique();
            $table->string('role', 20)->default('member');
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->index(['email', 'workspace_id']);
        });

        Schema::table('shopee_stores', function (Blueprint $table) {
            $table->foreignId('workspace_id')
                ->nullable()
                ->after('user_id')
                ->constrained('workspaces')
                ->nullOnDelete();
        });

        $users = User::query()->orderBy('id')->get();
        foreach ($users as $user) {
            $workspace = Workspace::create([
                'name' => $user->name ? ($user->name.' Workspace') : 'Workspace',
                'owner_user_id' => $user->id,
            ]);
            WorkspaceMember::create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'role' => 'owner',
            ]);
            DB::table('shopee_stores')
                ->where('user_id', $user->id)
                ->update(['workspace_id' => $workspace->id]);
        }

        Schema::table('shopee_stores', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'shop_id']);
        });

        Schema::table('shopee_stores', function (Blueprint $table) {
            $table->unique(['workspace_id', 'shop_id']);
        });
    }

    public function down(): void
    {
        Schema::table('shopee_stores', function (Blueprint $table) {
            $table->dropUnique(['workspace_id', 'shop_id']);
        });

        Schema::table('shopee_stores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workspace_id');
            $table->unique(['user_id', 'shop_id']);
        });

        Schema::dropIfExists('workspace_invites');
        Schema::dropIfExists('workspace_members');
        Schema::dropIfExists('workspaces');
    }
};
