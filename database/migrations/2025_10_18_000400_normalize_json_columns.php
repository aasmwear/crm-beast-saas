<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Normalize clients.fronter, clients.closer to JSONB in Postgres
        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='clients' AND column_name='fronter') THEN
        BEGIN
            ALTER TABLE clients
            ALTER COLUMN fronter TYPE jsonb USING
                CASE
                    WHEN pg_typeof(fronter)::text IN ('json','jsonb') THEN fronter::jsonb
                    ELSE to_jsonb(fronter)
                END;
        EXCEPTION WHEN others THEN
            NULL;
        END;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='clients' AND column_name='closer') THEN
        BEGIN
            ALTER TABLE clients
            ALTER COLUMN closer TYPE jsonb USING
                CASE
                    WHEN pg_typeof(closer)::text IN ('json','jsonb') THEN closer::jsonb
                    ELSE to_jsonb(closer)
                END;
        EXCEPTION WHEN others THEN
            NULL;
        END;
    END IF;
END
$$;
SQL
        );

        // Normalize tasks.assignees to JSONB if tasks table exists
        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='tasks' AND column_name='assignees') THEN
        BEGIN
            ALTER TABLE tasks
            ALTER COLUMN assignees TYPE jsonb USING
                CASE
                    WHEN pg_typeof(assignees)::text IN ('json','jsonb') THEN assignees::jsonb
                    ELSE to_jsonb(assignees)
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
