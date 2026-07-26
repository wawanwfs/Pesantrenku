<?php

namespace App\Support;

final class SignedImportRow
{
    public static function encode(array $row): string
    {
        $payload = self::canonicalPayload($row);

        return json_encode([
            'data' => $row,
            'signature' => hash_hmac('sha256', $payload, self::key()),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }

    public static function decode(string $line): array
    {
        try {
            $envelope = json_decode(trim($line), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('Baris import antrean bukan JSON yang valid.', previous: $exception);
        }

        $row = $envelope['data'] ?? null;
        $signature = $envelope['signature'] ?? null;
        if (! is_array($row) || ! is_string($signature) || ! preg_match('/^[a-f0-9]{64}$/', $signature)) {
            throw new \RuntimeException('Envelope baris import antrean tidak valid.');
        }

        $expected = hash_hmac('sha256', self::canonicalPayload($row), self::key());
        if (! hash_equals($expected, $signature)) {
            throw new \RuntimeException('Integritas file import antrean gagal diverifikasi.');
        }

        return $row;
    }

    private static function canonicalPayload(array $row): string
    {
        return json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }

    private static function key(): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new \RuntimeException('APP_KEY wajib tersedia untuk menandatangani import antrean.');
        }

        return $key;
    }
}
