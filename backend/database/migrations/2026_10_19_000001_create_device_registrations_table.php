<?php

use App\Models\Workspace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->uuid('public_id')->nullable()->unique()->after('id');
        });

        // Backfill public_id for existing workspaces
        $workspaces = DB::table('workspaces')->whereNull('public_id')->get();
        foreach ($workspaces as $workspace) {
            DB::table('workspaces')
                ->where('id', $workspace->id)
                ->update(['public_id' => (string) Str::uuid()]);
        }

        Schema::create('device_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('player_id', 255)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_registrations');

        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('public_id');
        });
    }
};
