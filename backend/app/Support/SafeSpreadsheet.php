<?php

namespace App\Support;

final class SafeSpreadsheet
{
    public static function csvLine(array $values): string
    {
        return collect($values)
            ->map(fn (mixed $value): string => self::csvCell($value))
            ->implode(',');
    }

    public static function csvCell(mixed $value): string
    {
        $value = self::plainText($value);
        if (preg_match('/^\s*[=+\-@]/u', $value) === 1) {
            $value = "'".$value;
        }

        return '"'.str_replace('"', '""', $value).'"';
    }

    public static function xmlHeader(string $worksheet = 'Data'): string
    {
        $worksheet = self::xml($worksheet);

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<?mso-application progid="Excel.Sheet"?>'."\n"
            .'<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" '
            .'xmlns:o="urn:schemas-microsoft-com:office:office" '
            .'xmlns:x="urn:schemas-microsoft-com:office:excel" '
            .'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'."\n"
            ."<Worksheet ss:Name=\"{$worksheet}\"><Table>\n";
    }

    public static function xmlRow(array $values): string
    {
        $cells = collect($values)
            ->map(fn (mixed $value): string => '<Cell><Data ss:Type="String">'.self::xml(self::plainText($value)).'</Data></Cell>')
            ->implode('');

        return "<Row>{$cells}</Row>\n";
    }

    public static function xmlFooter(): string
    {
        return "</Table></Worksheet></Workbook>\n";
    }

    public static function plainText(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        $text = str_replace(["\r\n", "\r", "\n", "\t"], ' ', (string) ($value ?? ''));

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }
}
