<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Imports containing explicit IDs do not advance PostgreSQL sequences.
        // Block table writes while comparing counters, and never move one backwards.
        DB::unprepared(<<<'SQL'
DO $$
DECLARE
    item record;
    sequence_name text;
    maximum_id bigint;
    sequence_value bigint;
    sequence_called boolean;
BEGIN
    FOR item IN
        SELECT n.nspname AS schema_name, c.relname AS table_name, a.attname AS column_name,
            (SELECT dep.refobjid::regclass::text
             FROM pg_attrdef def JOIN pg_depend dep ON dep.objid = def.oid AND dep.classid = 'pg_attrdef'::regclass
             JOIN pg_class seq ON seq.oid = dep.refobjid AND seq.relkind = 'S'
             WHERE def.adrelid = c.oid AND def.adnum = a.attnum LIMIT 1) AS default_sequence
        FROM pg_class c
        JOIN pg_namespace n ON n.oid = c.relnamespace
        JOIN pg_attribute a ON a.attrelid = c.oid
        WHERE c.relkind = 'r' AND n.nspname = current_schema()
          AND a.attnum > 0 AND NOT a.attisdropped
        ORDER BY c.relname, a.attnum
    LOOP
        sequence_name := pg_get_serial_sequence(format('%I.%I', item.schema_name, item.table_name), item.column_name);
        -- Some imports preserve nextval defaults but lose sequence ownership.
        sequence_name := COALESCE(sequence_name, item.default_sequence);
        IF sequence_name IS NULL THEN CONTINUE; END IF;
        EXECUTE format('LOCK TABLE %I.%I IN SHARE ROW EXCLUSIVE MODE', item.schema_name, item.table_name);
        EXECUTE format('SELECT max(%I) FROM %I.%I', item.column_name, item.schema_name, item.table_name) INTO maximum_id;
        EXECUTE format('SELECT last_value, is_called FROM %s', sequence_name) INTO sequence_value, sequence_called;
        IF maximum_id IS NOT NULL AND (maximum_id > sequence_value OR (maximum_id = sequence_value AND NOT sequence_called)) THEN
            PERFORM setval(sequence_name::regclass, maximum_id, true);
        END IF;
    END LOOP;
END $$;
SQL);
    }

    public function down(): void
    {
        // Restoring stale counters would reintroduce duplicate primary keys.
    }
};
