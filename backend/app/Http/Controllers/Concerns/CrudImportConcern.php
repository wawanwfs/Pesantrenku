<?php

namespace App\Http\Controllers\Concerns;

use App\Support\SignedImportRow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

trait CrudImportConcern
{
    private function applyRequestFilters(Request $request, $query, string $table): void
    {
        if ($request->filled('status') && $this->hasColumn($table, 'status')) {
            $query->where("{$table}.status", $request->query('status'));
        }

        if ($request->filled('student_id') && $this->hasColumn($table, 'student_id')) {
            $query->where("{$table}.student_id", (int) $request->query('student_id'));
        }

        if ($request->filled('class_id')) {
            if ($this->hasColumn($table, 'class_id')) {
                $query->where("{$table}.class_id", (int) $request->query('class_id'));
            } elseif ($this->hasColumn($table, 'student_id')) {
                $query->whereExists(function ($subQuery) use ($table, $request) {
                    $subQuery->selectRaw('1')
                        ->from('students')
                        ->whereColumn('students.id', "{$table}.student_id")
                        ->where('students.class_id', (int) $request->query('class_id'));
                });
            }
        }

        $dateColumn = collect(['date', 'paid_at', 'due_date', 'start_at', 'noted_at', 'published_at', 'completed_at', 'created_at'])
            ->first(fn ($column) => $this->hasColumn($table, $column));

        if ($dateColumn && $request->filled('date_from')) {
            $query->whereDate("{$table}.{$dateColumn}", '>=', $request->query('date_from'));
        }

        if ($dateColumn && $request->filled('date_to')) {
            $query->whereDate("{$table}.{$dateColumn}", '<=', $request->query('date_to'));
        }
    }

    private function applySearchFilter(Request $request, $query, string $table): void
    {
        if (! ($search = $request->query('search'))) {
            return;
        }

        $columns = collect($this->columns($table))->filter(fn ($column) => in_array($column, ['name', 'title', 'nis', 'email', 'invoice_number', 'number', 'status', 'reference']));
        $query->where(function ($q) use ($columns, $search) {
            foreach ($columns as $column) {
                $q->orWhere($column, 'like', "%{$search}%");
            }
        });
    }

    private function importableColumns(Request $request, string $table): array
    {
        return $this->importableColumnsFromSchema($request, $this->columns($table), $table);
    }

    private function storeImportRows(string $uuid, array $rows): string
    {
        $path = "imports/tmp/{$uuid}.jsonl";
        $handle = tmpfile();
        abort_unless($handle, 500, 'File temporary import tidak dapat dibuat.');

        foreach ($rows as $row) {
            fwrite($handle, SignedImportRow::encode($row)."\n");
        }
        rewind($handle);
        $stored = $this->operationStorage->put($path, $handle);
        fclose($handle);
        abort_unless($stored, 500, 'File temporary import tidak dapat disimpan.');

        return $path;
    }

    private function queuedActorContext(Request $request, ?int $tenantId): array
    {
        $user = $request->user();
        abort_unless($user, 401);

        if ($this->tenantContext->isSuperAdmin($request)) {
            return [
                'version' => 1,
                'user_id' => (int) $user->id,
                'role' => 'super_admin',
                'membership_id' => null,
                'tenant_role_id' => null,
            ];
        }

        abort_unless($tenantId, 422, 'Operasi antrean tenant memerlukan tenant aktif.');
        $membership = DB::table('pesantren_user')
            ->join('roles', 'pesantren_user.role_id', '=', 'roles.id')
            ->where('pesantren_user.pesantren_id', $tenantId)
            ->where('pesantren_user.user_id', $user->id)
            ->where('pesantren_user.status', 'active')
            ->orderBy('pesantren_user.id')
            ->select('pesantren_user.id', 'pesantren_user.tenant_role_id', 'roles.name as role')
            ->first();
        abort_unless($membership, 403, 'Membership actor operasi antrean tidak ditemukan.');

        return [
            'version' => 1,
            'user_id' => (int) $user->id,
            'role' => (string) $membership->role,
            'membership_id' => (int) $membership->id,
            'tenant_role_id' => $membership->tenant_role_id ? (int) $membership->tenant_role_id : null,
        ];
    }

    private function importMapping(Request $request, mixed $mappingInput, array $schema, array $headers, ?string $table = null): array
    {
        if (is_string($mappingInput) && trim($mappingInput) !== '') {
            $decoded = json_decode($mappingInput, true);
            $mappingInput = is_array($decoded) ? $decoded : [];
        }

        $mappingInput = is_array($mappingInput) ? $mappingInput : [];
        $allowedColumns = $this->importableColumnsFromSchema($request, $schema, $table);
        $mapping = [];

        foreach ($headers as $header) {
            $requested = $mappingInput[$header] ?? $mappingInput[$this->normalizeImportHeader($header)] ?? null;
            if ($requested && in_array($requested, $allowedColumns, true)) {
                $mapping[$header] = $requested;

                continue;
            }

            $normalized = $this->normalizeImportHeader($header);
            $mapping[$header] = in_array($normalized, $allowedColumns, true) ? $normalized : '';
        }

        return $mapping;
    }

    private function importableColumnsFromSchema(Request $request, array $schema, ?string $table = null): array
    {
        $validatedColumns = array_keys($this->validationRules($request, (string) $table, true));

        return collect($schema)
            ->intersect($validatedColumns)
            ->reject(fn ($column) => in_array($column, ['id', 'pesantren_id', 'created_at', 'updated_at', 'deleted_at'], true))
            ->reject(fn ($column) => $table === 'users' && in_array($column, ['remember_token', 'mfa_secret'], true))
            ->values()
            ->all();
    }

    private function mappedImportRow(array $row, array $schema, array $mapping): array
    {
        $raw = [];
        foreach ($row as $header => $value) {
            $target = $mapping[$header] ?? null;
            if (! $target || ! in_array($target, $schema, true)) {
                continue;
            }

            $raw[$target] = $value === '' ? null : $value;
        }

        return $raw;
    }

    private function analyzeImportRows(Request $request, string $table, array $rows, array $schema, array $mapping): array
    {
        $valid = 0;
        $invalid = 0;
        $blank = 0;
        $errors = [];

        foreach ($rows as $rowNumber => $row) {
            $raw = $this->mappedImportRow($row, $schema, $mapping);
            if (! $raw) {
                $blank++;

                continue;
            }

            $validator = Validator::make($raw, $this->validationRules($request, $table, true), [], $this->validationAttributes());
            $scopeMessages = [];
            $data = [];

            if (! $validator->fails()) {
                $data = $this->tenantContext->withTenantPayload(
                    $request,
                    $table,
                    $this->preparePayload($request, $table, $validator->validated()),
                );

                try {
                    $this->assertPersonalPayload($request, $table, $data);
                    $this->assertOperationalPayload($request, $table, $data);
                } catch (\Throwable $exception) {
                    $scopeMessages[] = $exception->getMessage() ?: 'Data di luar scope akses role.';
                }
            }

            if ($validator->fails() || $scopeMessages) {
                $invalid++;
                if (count($errors) < 10) {
                    $errors[] = ['row' => $rowNumber, 'messages' => array_values(array_merge($validator->errors()->all(), $scopeMessages))];
                }

                continue;
            }

            $valid++;
        }

        return compact('valid', 'invalid', 'blank', 'errors');
    }

    private function validatedImportFile(Request $request): array
    {
        $file = $request->file('file');
        abort_unless($file && $file->isValid(), 422, 'File import tidak valid.');

        $extension = strtolower((string) $file->getClientOriginalExtension());
        abort_unless(in_array($extension, ['csv', 'txt', 'xlsx'], true), 422, 'Format import yang didukung hanya CSV, TXT, dan XLSX. Format XLS lama tidak didukung.');

        $path = $file->getRealPath();
        abort_unless(is_string($path) && is_readable($path), 422, 'File import tidak dapat dibaca.');

        $handle = fopen($path, 'rb');
        abort_unless($handle !== false, 422, 'File import tidak dapat dibuka.');
        $signature = fread($handle, 8) ?: '';
        fclose($handle);

        if ($extension === 'xlsx') {
            abort_unless(str_starts_with($signature, "PK\x03\x04"), 422, 'Isi file tidak sesuai format XLSX.');
        } else {
            abort_if(str_contains($signature, "\0"), 422, 'File teks import mengandung data biner.');
        }

        return [$extension, $path];
    }

    private function rowsFromImportFile(string $path, string $extension): array
    {
        if ($extension === 'xlsx') {
            return $this->rowsFromXlsx($path);
        }

        $handle = fopen($path, 'rb');
        abort_unless($handle !== false, 422, 'File import tidak dapat dibaca.');

        try {
            $firstLine = fgets($handle);
            abort_unless($firstLine !== false, 422, 'File import kosong.');
            $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine) ?? $firstLine;
            $delimiter = $this->detectImportDelimiter($firstLine);
            rewind($handle);

            $headers = fgetcsv($handle, 0, $delimiter);
            abort_unless(is_array($headers), 422, 'Header import tidak dapat dibaca.');
            if (isset($headers[0])) {
                $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]) ?? (string) $headers[0];
            }
            $headers = array_map(fn ($header) => $this->normalizeImportHeader((string) $header), $headers);
            $this->assertSafeImportHeaders($headers);

            $rows = [];
            $lineNumber = 1;
            $maxRows = max(1, (int) config('pesantrenku.import.max_rows', 10000));
            while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
                $lineNumber++;
                if (count($values) === 1 && trim((string) ($values[0] ?? '')) === '') {
                    continue;
                }
                abort_if(count($rows) >= $maxRows, 422, "File import melebihi batas {$maxRows} baris data.");
                abort_if(count($values) > count($headers), 422, "Baris {$lineNumber} memiliki kolom lebih banyak daripada header.");

                $row = [];
                foreach ($headers as $index => $header) {
                    if ($header !== '') {
                        $row[$header] = $values[$index] ?? null;
                    }
                }
                $rows[$lineNumber] = $row;
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function rowsFromXlsx(string $path): array
    {
        abort_unless(class_exists(\ZipArchive::class) && function_exists('simplexml_load_string'), 422, 'Import XLSX membutuhkan ekstensi PHP zip dan simplexml.');

        $zip = new \ZipArchive;
        abort_unless($zip->open($path, \ZipArchive::RDONLY) === true, 422, 'File XLSX tidak dapat dibuka.');

        try {
            $limit = max(1048576, (int) config('pesantrenku.import.max_uncompressed_bytes', 52428800));
            abort_if($zip->numFiles > 1000, 422, 'Arsip XLSX memiliki terlalu banyak entry.');
            $totalUncompressed = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                abort_unless(is_array($stat), 422, 'Metadata entry XLSX tidak valid.');
                $name = str_replace('\\', '/', (string) ($stat['name'] ?? ''));
                abort_if(
                    $name === '' || str_starts_with($name, '/') || preg_match('#(^|/)\.\.(/|$)#', $name),
                    422,
                    'Arsip XLSX mengandung path yang tidak aman.'
                );
                abort_if(
                    str_contains(strtolower($name), 'vbaproject.bin') || str_starts_with(strtolower($name), 'xl/externallinks/'),
                    422,
                    'Macro dan external link tidak diizinkan pada import XLSX.'
                );
                $totalUncompressed += (int) ($stat['size'] ?? 0);
                abort_if($totalUncompressed > $limit, 422, 'Ukuran data XLSX setelah ekstraksi melewati batas keamanan.');
            }

            $sharedStrings = [];
            $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
            if (is_string($sharedXml) && $sharedXml !== '') {
                $shared = simplexml_load_string($sharedXml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
                abort_unless($shared !== false, 422, 'Shared strings XLSX tidak valid.');
                $namespace = $shared->getNamespaces(true)[''] ?? null;
                $root = $namespace ? $shared->children($namespace) : $shared;
                foreach ($root->si ?? [] as $item) {
                    $itemRoot = $namespace ? $item->children($namespace) : $item;
                    $text = isset($itemRoot->t) ? (string) $itemRoot->t : '';
                    foreach ($itemRoot->r ?? [] as $run) {
                        $runRoot = $namespace ? $run->children($namespace) : $run;
                        $text .= (string) ($runRoot->t ?? '');
                    }
                    $sharedStrings[] = $text;
                }
            }

            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            abort_unless(is_string($sheetXml) && $sheetXml !== '', 422, 'Sheet pertama XLSX tidak ditemukan.');
        } finally {
            $zip->close();
        }

        $sheet = simplexml_load_string($sheetXml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        abort_unless($sheet !== false, 422, 'XML worksheet XLSX tidak valid.');
        $namespace = $sheet->getNamespaces(true)[''] ?? null;
        $root = $namespace ? $sheet->children($namespace) : $sheet;
        $sheetData = $root->sheetData ?? null;
        abort_unless($sheetData !== null, 422, 'Worksheet XLSX tidak memiliki data.');

        $rawRows = [];
        $maxRows = max(1, (int) config('pesantrenku.import.max_rows', 10000)) + 1;
        $maxColumns = max(1, (int) config('pesantrenku.import.max_columns', 100));
        foreach ($sheetData->row ?? [] as $rowNode) {
            abort_if(count($rawRows) >= $maxRows, 422, 'Jumlah baris XLSX melewati batas keamanan.');
            $rowRoot = $namespace ? $rowNode->children($namespace) : $rowNode;
            $values = [];
            foreach ($rowRoot->c ?? [] as $cell) {
                $reference = (string) $cell['r'];
                $columnLetters = preg_replace('/[^A-Z]/', '', strtoupper($reference)) ?: '';
                $index = $this->excelColumnIndex($columnLetters);
                abort_if($index >= $maxColumns, 422, "XLSX melebihi batas {$maxColumns} kolom.");
                $cellRoot = $namespace ? $cell->children($namespace) : $cell;
                $type = (string) $cell['t'];
                if ($type === 'inlineStr') {
                    $inline = $cellRoot->is ?? null;
                    $inlineRoot = ($inline && $namespace) ? $inline->children($namespace) : $inline;
                    $value = (string) ($inlineRoot->t ?? '');
                } else {
                    $value = (string) ($cellRoot->v ?? '');
                    if ($type === 's') {
                        $value = $sharedStrings[(int) $value] ?? '';
                    }
                }
                $values[$index] = $value;
            }
            $rawRows[(int) ($rowNode['r'] ?: count($rawRows) + 1)] = $values;
        }

        abort_unless($rawRows !== [], 422, 'Worksheet XLSX kosong.');
        $headerLine = array_key_first($rawRows);
        $headerValues = $rawRows[$headerLine] ?? [];
        $maxHeaderIndex = $headerValues === [] ? -1 : max(array_keys($headerValues));
        $headers = [];
        for ($index = 0; $index <= $maxHeaderIndex; $index++) {
            $headers[] = $this->normalizeImportHeader((string) ($headerValues[$index] ?? ''));
        }
        $this->assertSafeImportHeaders($headers);

        $mapped = [];
        foreach ($rawRows as $lineNumber => $values) {
            if ($lineNumber === $headerLine) {
                continue;
            }
            if (collect($values)->every(fn ($value) => trim((string) $value) === '')) {
                continue;
            }
            $row = [];
            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $row[$header] = $values[$index] ?? null;
                }
            }
            $mapped[$lineNumber] = $row;
        }

        return $mapped;
    }

    private function detectImportDelimiter(string $line): string
    {
        $delimiters = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];
        arsort($delimiters);

        return (string) array_key_first($delimiters);
    }

    private function assertSafeImportHeaders(array $headers): void
    {
        $maxColumns = max(1, (int) config('pesantrenku.import.max_columns', 100));
        abort_if(count($headers) > $maxColumns, 422, "File import melebihi batas {$maxColumns} kolom.");
        $nonBlank = array_values(array_filter($headers, fn ($header) => $header !== ''));
        abort_unless($nonBlank !== [], 422, 'Header import wajib tersedia.');
        abort_if(count($nonBlank) !== count(array_unique($nonBlank)), 422, 'Header import tidak boleh duplikat setelah normalisasi.');
    }

    private function normalizeImportHeader(string $header): string
    {
        return trim(strtolower(str_replace([' ', '-'], '_', $header)));
    }

    private function excelColumnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }

    private function dateColumn(string $table): ?string
    {
        return collect(['date', 'paid_at', 'due_date', 'start_at', 'noted_at', 'published_at', 'completed_at', 'assigned_at', 'created_at'])
            ->first(fn ($column) => $this->hasColumn($table, $column));
    }

    private function changedFields(array $before, array $after, string $table): array
    {
        $changes = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $key) {
            if (in_array($key, ['updated_at'], true)) {
                continue;
            }

            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;
            if ($old !== $new) {
                $changes[] = [
                    'field' => $key,
                    'before' => $old,
                    'after' => $new,
                    'before_label' => $this->humanDiffValue($key, $old),
                    'after_label' => $this->humanDiffValue($key, $new),
                    'label' => Str::headline(str_replace('_', ' ', str_ends_with($key, '_id') ? Str::beforeLast($key, '_id') : $key)),
                    'table' => $table,
                ];
            }
        }

        return $changes;
    }

    private function humanDiffValue(string $field, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $relation = match ($field) {
            'student_id' => ['students', 'name'],
            'class_id' => ['classes', 'name'],
            'subject_id' => ['subjects', 'name'],
            'teacher_id' => ['teachers', 'name'],
            'guardian_id' => ['guardians', 'name'],
            'room_id' => ['rooms', 'name'],
            'user_id' => ['users', 'name'],
            'role_id' => ['roles', 'label'],
            'pesantren_id' => ['pesantrens', 'name'],
            'plan_id' => ['plans', 'name'],
            'invoice_id' => ['invoices', 'invoice_number'],
            'payment_method_id' => ['payment_methods', 'name'],
            'medicine_id' => ['medicines', 'name'],
            'medical_record_id' => ['medical_records', 'complaint'],
            default => null,
        };

        if (! $relation || ! is_numeric($value)) {
            return $value;
        }

        return DB::table($relation[0])->where('id', (int) $value)->value($relation[1]) ?? "#{$value}";
    }
}
