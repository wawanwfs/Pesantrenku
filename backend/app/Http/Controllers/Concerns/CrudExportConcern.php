<?php

namespace App\Http\Controllers\Concerns;

use App\Support\SafeSpreadsheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

trait CrudExportConcern
{
    private function formatRowsForExport($rows, array $columns): array
    {
        $maps = $this->relationMaps($rows, $columns);

        return $rows->map(function ($row) use ($columns, $maps) {
            $item = [];
            foreach ($columns as $column) {
                $value = $row->{$column} ?? null;
                $item[$column] = isset($maps[$column]) && $value
                    ? ($maps[$column]->get($value) ?? "#{$value}")
                    : $value;
            }

            return $item;
        })->all();
    }

    private function enrichRow(string $table, ?object $row): ?object
    {
        if (! $row) {
            return $row;
        }

        $columns = array_keys((array) $row);
        $maps = $this->relationMaps(collect([$row]), $columns);
        $data = (array) $this->redactRow($table, $row);

        foreach ($maps as $column => $map) {
            $value = $row->{$column} ?? null;
            if (! $value) {
                continue;
            }

            $label = $map->get($value);
            if ($label) {
                $data[preg_replace('/_id$/', '', $column).'_name'] = $label;
            }
        }

        return (object) $data;
    }

    private function relationMaps($rows, array $columns): array
    {
        $maps = [];
        foreach ($this->relationTables() as $column => [$table, $labelColumn]) {
            if (! in_array($column, $columns, true) || ! $this->hasTable($table)) {
                continue;
            }

            $ids = $rows->pluck($column)->filter()->unique()->values();
            if ($ids->isEmpty()) {
                continue;
            }

            $maps[$column] = DB::table($table)->whereIn('id', $ids)->pluck($labelColumn, 'id');
        }

        return $maps;
    }

    private function relationTables(): array
    {
        return [
            'academic_year_id' => ['academic_years', 'name'],
            'class_id' => ['classes', 'name'],
            'clinic_id' => ['clinics', 'name'],
            'doctor_id' => ['doctors', 'name'],
            'expense_category_id' => ['expense_categories', 'name'],
            'guardian_id' => ['guardians', 'name'],
            'invoice_id' => ['invoices', 'invoice_number'],
            'medical_record_id' => ['medical_records', 'complaint'],
            'medicine_id' => ['medicines', 'name'],
            'payment_method_id' => ['payment_methods', 'name'],
            'pesantren_id' => ['pesantrens', 'name'],
            'plan_id' => ['plans', 'name'],
            'program_id' => ['programs', 'name'],
            'role_id' => ['roles', 'label'],
            'room_id' => ['rooms', 'name'],
            'student_id' => ['students', 'name'],
            'subject_id' => ['subjects', 'name'],
            'teacher_id' => ['teachers', 'name'],
            'tenant_role_id' => ['tenant_roles', 'label'],
            'user_id' => ['users', 'name'],
        ];
    }

    private function pdfTemplate(Request $request, string $table): array
    {
        $key = match ($table) {
            'payments' => 'pdf.receipt_template',
            'letters', 'sick_letters' => 'pdf.letter_template',
            default => 'pdf.report_template',
        };

        $tenantId = $this->tenantContext->activePesantrenId($request);
        $setting = DB::table('system_settings')
            ->where('key', $key)
            ->when($tenantId, fn ($query) => $query->where('pesantren_id', $tenantId), fn ($query) => $query->whereNull('pesantren_id'))
            ->value('value');

        $decoded = json_decode((string) $setting, true);
        if (is_array($decoded)) {
            return $decoded + ['title' => 'Pesantenku', 'header' => 'Laporan Pesantenku', 'footer' => 'Dokumen dibuat otomatis oleh Pesantenku.'];
        }

        return [
            'title' => 'Pesantenku',
            'header' => $table === 'payments' ? 'Kwitansi Pembayaran Pesantenku' : 'Laporan Pesantenku',
            'footer' => 'Dokumen dibuat otomatis oleh Pesantenku.',
        ];
    }

    private function pdfResponse(string $title, array $lines, string $filename)
    {
        $printable = [];
        foreach ($lines as $line) {
            $text = SafeSpreadsheet::plainText($line);
            foreach (explode("\n", wordwrap($text, 95, "\n", true)) as $wrapped) {
                $printable[] = $wrapped;
            }
        }

        $pages = array_chunk($printable, 48);
        if ($pages === []) {
            $pages = [[]];
        }

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $fontId = 3;
        $objects[$fontId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $kids = [];
        $nextId = 4;

        foreach ($pages as $pageNumber => $pageLines) {
            $pageId = $nextId++;
            $contentId = $nextId++;
            $kids[] = "{$pageId} 0 R";
            $content = "BT\n/F1 12 Tf\n50 805 Td\n(".$this->pdfEscape($this->pdfText($title)).") Tj\n0 -20 Td\n/F1 9 Tf\n";
            foreach ($pageLines as $line) {
                $content .= '('.$this->pdfEscape($this->pdfText($line)).") Tj\n0 -15 Td\n";
            }
            $content .= "0 -5 Td\n/F1 8 Tf\n(Halaman ".($pageNumber + 1).' / '.count($pages).") Tj\nET";
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 {$fontId} 0 R >> >> /Contents {$contentId} 0 R >>";
            $objects[$contentId] = '<< /Length '.strlen($content)." >>\nstream\n{$content}\nendstream";
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xref = strlen($pdf);
        $maxId = max(array_keys($objects));
        $pdf .= "xref\n0 ".($maxId + 1)."\n0000000000 65535 f \n";
        for ($id = 1; $id <= $maxId; $id++) {
            $pdf .= isset($offsets[$id])
                ? str_pad((string) $offsets[$id], 10, '0', STR_PAD_LEFT)." 00000 n \n"
                : "0000000000 00000 f \n";
        }
        $pdf .= "trailer\n<< /Size ".($maxId + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename={$filename}",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function pdfText(string $value): string
    {
        $converted = function_exists('iconv') ? iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value) : $value;

        return is_string($converted) ? $converted : $value;
    }

    private function pdfEscape(string $value): string
    {
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $value);
    }

    private function applyProtectedSettingScope(Request $request, $query, string $table): void
    {
        if ($table !== 'system_settings' || $request->user()?->hasRole('super_admin')) {
            return;
        }

        foreach (self::PROTECTED_SETTING_PREFIXES as $prefix) {
            $query->where('system_settings.key', 'not like', $prefix.'%');
        }
    }

    private function assertSystemSettingPayloadAllowed(Request $request, string $table, array $data, ?object $current = null): void
    {
        if ($table !== 'system_settings' || $request->user()?->hasRole('super_admin')) {
            return;
        }

        $key = (string) ($data['key'] ?? $current?->key ?? '');
        abort_if($this->isProtectedSettingKey($key), 403, 'Pengaturan internal hanya dapat dikelola super admin.');
    }

    private function assertImportRowsAllowed(Request $request, string $table, array $rows, array $schema, array $mapping): void
    {
        if ($table !== 'system_settings' || $request->user()?->hasRole('super_admin')) {
            return;
        }

        foreach ($rows as $row) {
            $data = $this->mappedImportRow($row, $schema, $mapping);
            if ($this->isProtectedSettingKey((string) ($data['key'] ?? ''))) {
                abort(403, 'Import pengaturan internal hanya dapat dilakukan super admin.');
            }
        }
    }

    private function isProtectedSettingKey(string $key): bool
    {
        return collect(self::PROTECTED_SETTING_PREFIXES)->contains(
            fn (string $prefix) => str_starts_with($key, $prefix)
        );
    }

    private function redactRow(string $table, ?object $row): ?object
    {
        if (! $row || $table !== 'users') {
            return $row;
        }

        return (object) collect((array) $row)->except(self::USER_SENSITIVE_COLUMNS)->all();
    }
}
