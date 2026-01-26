<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotently convert clients JSON-like columns to jsonb
        DB::statement(<<<'SQL'
DO $$
BEGIN
    -- clients.tags -> jsonb
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='clients' AND column_name='tags') THEN
        BEGIN
            ALTER TABLE clients
            ALTER COLUMN tags TYPE jsonb USING
                CASE
                    WHEN pg_typeof(tags)::text IN ('json','jsonb') THEN tags::jsonb
                    ELSE to_jsonb(tags)
                END;
        EXCEPTION WHEN others THEN
            NULL;
        END;
    END IF;

    -- clients.project_scope -> jsonb
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='clients' AND column_name='project_scope') THEN
        BEGIN
            ALTER TABLE clients
            ALTER COLUMN project_scope TYPE jsonb USING
                CASE
                    WHEN pg_typeof(project_scope)::text IN ('json','jsonb') THEN project_scope::jsonb
                    ELSE to_jsonb(project_scope)
                END;
        EXCEPTION WHEN others THEN
            NULL;
        END;
    END IF;
END
$$;
SQL
        );
    }

    public function down(): void
    {
        // no-op
    }
};
