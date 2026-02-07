<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('stripe_id')->nullable()->index();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four', 4)->nullable();
            $table->timestamp('trial_ends_at')->nullable();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('workspace_id')
                ->nullable()
                ->after('user_id')
                ->constrained('workspaces')
                ->nullOnDelete();
        });

        $users = DB::table('users')
            ->select([
                'id',
                'current_workspace_id',
                'stripe_id',
                'pm_type',
                'pm_last_four',
                'trial_ends_at',
            ])
            ->orderBy('id')
            ->get();

        foreach ($users as $user) {
            $workspaceId = $user->current_workspace_id;

            if ($workspaceId === null) {
                $workspaceId = DB::table('workspace_user')
                    ->where('user_id', $user->id)
                    ->value('workspace_id');
            }

            if ($workspaceId !== null) {
                DB::table('workspaces')
                    ->where('id', $workspaceId)
                    ->update([
                        'stripe_id' => $user->stripe_id,
                        'pm_type' => $user->pm_type,
                        'pm_last_four' => $user->pm_last_four,
                        'trial_ends_at' => $user->trial_ends_at,
                    ]);

                DB::table('subscriptions')
                    ->where('user_id', $user->id)
                    ->update(['workspace_id' => $workspaceId]);
            }
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->index(['workspace_id', 'stripe_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'stripe_status']);
            $table->dropConstrainedForeignId('workspace_id');
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropIndex(['stripe_id']);
            $table->dropColumn([
                'stripe_id',
                'pm_type',
                'pm_last_four',
                'trial_ends_at',
            ]);
        });
    }
};
