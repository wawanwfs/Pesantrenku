<?php

namespace App\Console\Commands;

use App\Support\DomainRelations;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DomainIntegrityPreflight extends Command
{
    protected $signature = 'pesantenku:domain-preflight {--json : Emit machine-readable JSON}';

    protected $description = 'Detect orphan, cross-tenant, and duplicate domain data before integrity constraints are installed';

    public function handle(): int
    {
        $issues = [];

        foreach (DomainRelations::all() as [$table, $column, $parent, $constraint]) {
            if (! Schema::hasTable($table) || ! Schema::hasTable($parent) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $parentAlias = 'integrity_parent';
            $orphans = DB::table($table)
                ->leftJoin("{$parent} as {$parentAlias}", "{$table}.{$column}", '=', "{$parentAlias}.id")
                ->whereNotNull("{$table}.{$column}")
                ->whereNull("{$parentAlias}.id")
                ->count();

            if ($orphans > 0) {
                $issues[] = [
                    'type' => 'orphan',
                    'table' => $table,
                    'column' => $column,
                    'constraint' => $constraint,
                    'count' => $orphans,
                ];
            }

            if ($column !== 'pesantren_id'
                && Schema::hasColumn($table, 'pesantren_id')
                && Schema::hasColumn($parent, 'pesantren_id')) {
                $crossTenant = DB::table($table)
                    ->join("{$parent} as {$parentAlias}", "{$table}.{$column}", '=', "{$parentAlias}.id")
                    ->whereNotNull("{$table}.pesantren_id")
                    ->whereNotNull("{$parentAlias}.pesantren_id")
                    ->whereColumn("{$table}.pesantren_id", '!=', "{$parentAlias}.pesantren_id")
                    ->count();

                if ($crossTenant > 0) {
                    $issues[] = [
                        'type' => 'cross_tenant',
                        'table' => $table,
                        'column' => $column,
                        'constraint' => $constraint,
                        'count' => $crossTenant,
                    ];
                }
            }
        }

        foreach ([
            ['attendances', ['pesantren_id', 'student_id', 'date', 'session']],
            ['dorm_attendances', ['pesantren_id', 'student_id', 'date', 'activity']],
        ] as [$table, $columns]) {
            if (! Schema::hasTable($table) || collect($columns)->contains(fn (string $column) => ! Schema::hasColumn($table, $column))) {
                continue;
            }

            $duplicates = DB::table($table)
                ->select($columns)
                ->selectRaw('COUNT(*) as aggregate')
                ->groupBy($columns)
                ->havingRaw('COUNT(*) > 1')
                ->get()
                ->sum(fn (object $row) => max(0, (int) $row->aggregate - 1));

            if ($duplicates > 0) {
                $issues[] = [
                    'type' => 'duplicate',
                    'table' => $table,
                    'columns' => $columns,
                    'count' => $duplicates,
                ];
            }
        }

        $result = [
            'status' => $issues === [] ? 'passed' : 'failed',
            'checked_at' => now()->toIso8601String(),
            'issues' => $issues,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } elseif ($issues === []) {
            $this->info('Domain integrity preflight passed. Foreign keys and unique constraints can be installed.');
        } else {
            $this->error('Domain integrity preflight failed. Clean the rows below before running migrations.');
            $this->table(
                ['Type', 'Table', 'Column(s)', 'Count'],
                collect($issues)->map(fn (array $issue) => [
                    $issue['type'],
                    $issue['table'],
                    $issue['column'] ?? implode(', ', $issue['columns'] ?? []),
                    $issue['count'],
                ])->all()
            );
        }

        return $issues === [] ? self::SUCCESS : self::FAILURE;
    }
}
