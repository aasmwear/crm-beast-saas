<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_field_values', function (Blueprint $table): void {
            $table->unsignedBigInteger('organization_id')->nullable()->after('custom_field_id');
        });

        DB::table('custom_field_values')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $orgId = DB::table('custom_fields')
                        ->where('id', $row->custom_field_id)
                        ->value('organization_id');

                    if ($orgId === null) {
                        throw new \RuntimeException(
                            'custom_field_values row ' . $row->id . ' references missing custom_field_id ' . $row->custom_field_id
                        );
                    }

                    DB::table('custom_field_values')
                        ->where('id', $row->id)
                        ->update(['organization_id' => $orgId]);
                }
            }, 'id');

        if (DB::table('custom_field_values')->whereNull('organization_id')->exists()) {
            throw new \RuntimeException('Backfill failed: custom_field_values.organization_id is still NULL for some rows.');
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE custom_field_values ALTER COLUMN organization_id SET NOT NULL');
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE custom_field_values MODIFY organization_id BIGINT UNSIGNED NOT NULL');
        } else {
            Schema::table('custom_field_values', function (Blueprint $table): void {
                $table->unsignedBigInteger('organization_id')->nullable(false)->change();
            });
        }

        Schema::table('custom_field_values', function (Blueprint $table): void {
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnDelete();
        });

        Schema::table('custom_field_values', function (Blueprint $table): void {
            $table->dropIndex(['entity_type', 'entity_id']);
        });

        Schema::table('custom_field_values', function (Blueprint $table): void {
            $table->index(['organization_id', 'entity_type', 'entity_id'], 'custom_field_values_org_entity_idx');
        });
    }

    public function down(): void
    {
        Schema::table('custom_field_values', function (Blueprint $table): void {
            $table->dropIndex('custom_field_values_org_entity_idx');
        });

        Schema::table('custom_field_values', function (Blueprint $table): void {
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::table('custom_field_values', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
        });

        Schema::table('custom_field_values', function (Blueprint $table): void {
            $table->dropColumn('organization_id');
        });
    }
};
