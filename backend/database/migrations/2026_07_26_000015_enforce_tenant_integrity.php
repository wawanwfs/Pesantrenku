<?php

use App\Support\TenantIntegrityCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (TenantIntegrityCatalog::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'pesantren_id')) {
                continue;
            }
            $nullCount = DB::table($table)->whereNull('pesantren_id')->count();
            if ($nullCount > 0) {
                throw new RuntimeException("{$table} memiliki {$nullCount} row tanpa tenant; jalankan cleanup/domain-preflight sebelum migration.");
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->unsignedBigInteger('pesantren_id')->nullable(false)->change();
                $blueprint->unique(['pesantren_id', 'id'], $this->indexName($table));
            });
        }

        if (DB::getDriverName() === 'mysql') {
            $this->installMysqlTriggers();
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            foreach (TenantIntegrityCatalog::TABLES as $table) {
                DB::unprepared('DROP TRIGGER IF EXISTS `'.$this->triggerName($table, 'bi').'`');
                DB::unprepared('DROP TRIGGER IF EXISTS `'.$this->triggerName($table, 'bu').'`');
            }
        }

        foreach (array_reverse(TenantIntegrityCatalog::TABLES) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'pesantren_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropUnique($this->indexName($table));
                $blueprint->unsignedBigInteger('pesantren_id')->nullable()->change();
            });
        }
    }

    private function installMysqlTriggers(): void
    {
        $relations = collect(TenantIntegrityCatalog::tenantRelations())->groupBy('child');
        foreach (TenantIntegrityCatalog::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $checks = [];
            foreach ($relations->get($table, collect()) as $relation) {
                if (! Schema::hasColumn($table, $relation['column']) || ! Schema::hasTable($relation['parent'])) {
                    continue;
                }
                $column = $relation['column'];
                $parent = $relation['parent'];
                $checks[] = "IF NEW.`{$column}` IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `{$parent}` p WHERE p.`id` = NEW.`{$column}` AND p.`pesantren_id` = NEW.`pesantren_id`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'cross-tenant relation {$table}.{$column}'; END IF;";
            }
            $body = implode("\n", $checks);
            $insertTrigger = $this->triggerName($table, 'bi');
            $updateTrigger = $this->triggerName($table, 'bu');
            DB::unprepared("DROP TRIGGER IF EXISTS `{$insertTrigger}`");
            DB::unprepared("CREATE TRIGGER `{$insertTrigger}` BEFORE INSERT ON `{$table}` FOR EACH ROW BEGIN IF NEW.`pesantren_id` IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'tenant required for {$table}'; END IF; {$body} END");
            DB::unprepared("DROP TRIGGER IF EXISTS `{$updateTrigger}`");
            DB::unprepared("CREATE TRIGGER `{$updateTrigger}` BEFORE UPDATE ON `{$table}` FOR EACH ROW BEGIN IF NOT (NEW.`pesantren_id` <=> OLD.`pesantren_id`) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'tenant identity immutable for {$table}'; END IF; {$body} END");
        }
    }

    private function indexName(string $table): string
    {
        return 'tenant_id_pk_'.substr(hash('sha256', $table), 0, 12);
    }

    private function triggerName(string $table, string $suffix): string
    {
        return 'ti_'.substr(hash('sha256', $table), 0, 16).'_'.$suffix;
    }
};
