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
        DB::transaction(function (): void {
            // Ensure a default organization exists to bind legacy user roles
            $defaultOrganization = DB::table('organizations')->where('slug', 'default')->first();
            if (!$defaultOrganization) {
                $organizationId = DB::table('organizations')->insertGetId([
                    'slug' => 'default',
                    'name' => 'Default Organization',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $organizationId = $defaultOrganization->id;
            }

            // Copy users.role -> organization_role_bindings (user_id, organization_id, role)
            $users = DB::table('users')->select('id', 'role')->get();
            foreach ($users as $user) {
                if ($user->role === null) {
                    continue;
                }
                // Upsert to satisfy unique(user_id, organization_id)
                $existing = DB::table('organization_role_bindings')
                    ->where('user_id', $user->id)
                    ->where('organization_id', $organizationId)
                    ->first();

                if (!$existing) {
                    DB::table('organization_role_bindings')->insert([
                        'user_id' => $user->id,
                        'organization_id' => $organizationId,
                        'role' => $user->role,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Drop users.role
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'role')) {
                    $table->dropColumn('role');
                }
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate users.role and backfill one role per user from bindings
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role')) {
                $table->enum('role', ['owner', 'admin', 'editor'])->default('editor')->after('password');
            }
        });

        $bindings = DB::table('organization_role_bindings')
            ->select('user_id', 'role', 'organization_id')
            ->orderBy('organization_id')
            ->get();

        $userIdToRole = [];
        foreach ($bindings as $binding) {
            if (!array_key_exists($binding->user_id, $userIdToRole)) {
                $userIdToRole[$binding->user_id] = $binding->role;
            }
        }

        foreach ($userIdToRole as $userId => $role) {
            DB::table('users')->where('id', $userId)->update(['role' => $role]);
        }
    }
};


