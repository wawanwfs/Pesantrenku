<?php

use App\Support\DomainRelations;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DomainRelations::all() as [$tableName, $column, $referencedTable]) {
            $this->assertNoOrphans($tableName, $column, $referencedTable);
        }
        $this->assertNoDuplicateKey(
            'attendances',
            ['pesantren_id', 'student_id', 'date', 'session'],
            'uq_attendance_tenant_student_date_session',
        );
        $this->assertNoDuplicateKey(
            'dorm_attendances',
            ['pesantren_id', 'student_id', 'date', 'activity'],
            'uq_dorm_attendance_tenant_student_date_activity',
        );

        foreach (DomainRelations::all() as [$tableName, $column, $referencedTable, $constraint, $deleteAction]) {
            if (! Schema::hasTable($tableName)
                || ! Schema::hasTable($referencedTable)
                || ! Schema::hasColumn($tableName, $column)
                || $this->hasForeignKey($tableName, $column, $referencedTable, $constraint)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($column, $referencedTable, $constraint, $deleteAction) {
                $foreign = $table->foreign($column, $constraint)->references('id')->on($referencedTable);
                $foreign->{$deleteAction}();
            });
        }

        $this->addUniqueIfMissing(
            'attendances',
            ['pesantren_id', 'student_id', 'date', 'session'],
            'uq_attendance_tenant_student_date_session',
        );
        $this->addUniqueIfMissing(
            'dorm_attendances',
            ['pesantren_id', 'student_id', 'date', 'activity'],
            'uq_dorm_attendance_tenant_student_date_activity',
        );
    }

    public function down(): void
    {
        $this->dropUniqueIfPresent('dorm_attendances', 'uq_dorm_attendance_tenant_student_date_activity');
        $this->dropUniqueIfPresent('attendances', 'uq_attendance_tenant_student_date_session');

        foreach (array_reverse(DomainRelations::all()) as [$tableName, $column, $referencedTable, $constraint]) {
            if (Schema::hasTable($tableName) && $this->hasForeignKey($tableName, $column, $referencedTable, $constraint)) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropForeign($constraint));
            }
        }
    }

    private function assertNoOrphans(string $table, string $column, string $referencedTable): void
    {
        if (! Schema::hasTable($table)
            || ! Schema::hasTable($referencedTable)
            || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $orphan = DB::table("{$table} as child")
            ->leftJoin("{$referencedTable} as parent", 'parent.id', '=', "child.{$column}")
            ->whereNotNull("child.{$column}")
            ->whereNull('parent.id')
            ->select('child.id', "child.{$column}")
            ->first();

        if ($orphan) {
            throw new RuntimeException(
                "Foreign-key preflight gagal: {$table} #{$orphan->id} memiliki {$column}={$orphan->{$column}} yang tidak ditemukan pada {$referencedTable}."
            );
        }
    }

    /** @param list<string> $columns */
    private function assertNoDuplicateKey(string $table, array $columns, string $index): void
    {
        if (! Schema::hasTable($table)
            || collect($columns)->contains(fn (string $column) => ! Schema::hasColumn($table, $column))
            || $this->hasIndex($table, $index)) {
            return;
        }

        $query = DB::table($table)->select($columns)->selectRaw('COUNT(*) as aggregate');
        foreach ($columns as $column) {
            $query->whereNotNull($column);
        }
        $duplicate = $query->groupBy($columns)->havingRaw('COUNT(*) > 1')->first();
        if ($duplicate) {
            $values = collect($columns)
                ->map(fn (string $column) => "{$column}=".(string) $duplicate->{$column})
                ->implode(', ');
            throw new RuntimeException(
                "Unique-index preflight gagal untuk {$index} pada {$table}: kombinasi {$values} muncul lebih dari sekali."
            );
        }
    }

    private function hasForeignKey(string $table, string $column, string $referencedTable, string $constraint): bool
    {
        return collect(Schema::getForeignKeys($table))->contains(function (array $foreign) use ($column, $referencedTable, $constraint) {
            return ($foreign['name'] ?? null) === $constraint
                || (($foreign['columns'] ?? []) === [$column] && ($foreign['foreign_table'] ?? null) === $referencedTable);
        });
    }

    /** @param list<string> $columns */
    private function addUniqueIfMissing(string $tableName, array $columns, string $name): void
    {
        if (! Schema::hasTable($tableName)
            || collect($columns)->contains(fn (string $column) => ! Schema::hasColumn($tableName, $column))
            || $this->hasIndex($tableName, $name)) {
            return;
        }

        Schema::table($tableName, fn (Blueprint $table) => $table->unique($columns, $name));
    }

    private function dropUniqueIfPresent(string $tableName, string $name): void
    {
        if (Schema::hasTable($tableName) && $this->hasIndex($tableName, $name)) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropUnique($name));
        }
    }

    private function hasIndex(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn (array $index) => ($index['name'] ?? null) === $name);
    }
};
