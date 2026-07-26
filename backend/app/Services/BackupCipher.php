<?php

namespace App\Services;

use Aws\Kms\KmsClient;
use Illuminate\Support\Facades\Crypt;

class BackupCipher
{
    public function encrypt(string $plaintext): array
    {
        $provider = (string) config('backup-security.provider', 'local');
        $dataKey = random_bytes(32);
        [$encryptedKey, $keyId] = $provider === 'kms'
            ? $this->kmsEncryptKey($dataKey)
            : [Crypt::encryptString(base64_encode($dataKey)), 'app-key'];
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $dataKey, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ciphertext === false) {
            throw new \RuntimeException('Backup encryption failed.');
        }

        $envelope = [
            'version' => 1,
            'algorithm' => 'AES-256-GCM',
            'key_provider' => $provider,
            'key_id' => $keyId,
            'encrypted_key' => base64_encode($encryptedKey),
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'ciphertext' => base64_encode($ciphertext),
        ];
        $contents = json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return [
            'contents' => $contents,
            'provider' => $provider,
            'key_id' => $keyId,
            'checksum' => hash('sha256', $contents),
        ];
    }

    public function decrypt(
        string $contents,
        ?string $expectedProvider = null,
        ?string $expectedKeyId = null,
    ): string {
        try {
            $envelope = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('Envelope backup terenkripsi tidak valid.', previous: $exception);
        }

        $required = ['version', 'algorithm', 'key_provider', 'key_id', 'encrypted_key', 'iv', 'tag', 'ciphertext'];
        if (! is_array($envelope) || array_diff($required, array_keys($envelope)) !== []) {
            throw new \RuntimeException('Envelope backup terenkripsi tidak lengkap.');
        }
        if ((int) $envelope['version'] !== 1 || (string) $envelope['algorithm'] !== 'AES-256-GCM') {
            throw new \RuntimeException('Versi atau algoritma enkripsi backup tidak didukung.');
        }

        $provider = (string) $envelope['key_provider'];
        $keyId = (string) $envelope['key_id'];
        if (! in_array($provider, ['local', 'kms'], true) || $keyId === '') {
            throw new \RuntimeException('Metadata penyedia kunci backup tidak valid.');
        }
        if ($expectedProvider !== null && ! hash_equals($expectedProvider, $provider)) {
            throw new \RuntimeException('Penyedia kunci backup tidak cocok dengan metadata database.');
        }
        if ($expectedKeyId !== null && ! hash_equals($expectedKeyId, $keyId)) {
            throw new \RuntimeException('ID kunci backup tidak cocok dengan metadata database.');
        }

        $encryptedKey = $this->decodeBase64Field($envelope, 'encrypted_key');
        $iv = $this->decodeBase64Field($envelope, 'iv');
        $tag = $this->decodeBase64Field($envelope, 'tag');
        $ciphertext = $this->decodeBase64Field($envelope, 'ciphertext');
        if (strlen($iv) !== 12 || strlen($tag) !== 16 || $ciphertext === '') {
            throw new \RuntimeException('Parameter kriptografi backup tidak valid.');
        }

        try {
            $dataKey = $provider === 'kms'
                ? $this->kmsDecryptKey($encryptedKey)
                : base64_decode(Crypt::decryptString($encryptedKey), true);
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Kunci data backup tidak dapat didekripsi.', previous: $exception);
        }
        if (! is_string($dataKey) || strlen($dataKey) !== 32) {
            throw new \RuntimeException('Kunci data backup tidak valid.');
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $dataKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );
        if ($plaintext === false) {
            throw new \RuntimeException('Backup decryption or authentication failed.');
        }

        return $plaintext;
    }

    private function decodeBase64Field(array $envelope, string $field): string
    {
        if (! is_string($envelope[$field] ?? null) || $envelope[$field] === '') {
            throw new \RuntimeException("Field {$field} pada envelope backup tidak valid.");
        }

        $decoded = base64_decode($envelope[$field], true);
        if ($decoded === false) {
            throw new \RuntimeException("Field {$field} pada envelope backup bukan base64 yang valid.");
        }

        return $decoded;
    }

    private function kmsEncryptKey(string $dataKey): array
    {
        $keyId = (string) config('backup-security.kms_key_id');
        if ($keyId === '') {
            throw new \RuntimeException('AWS_KMS_BACKUP_KEY_ID is required for production backup encryption.');
        }
        $result = $this->kms()->encrypt(['KeyId' => $keyId, 'Plaintext' => $dataKey, 'EncryptionContext' => ['service' => 'pesantenku-backup']]);

        return [(string) $result->get('CiphertextBlob'), $keyId];
    }

    private function kmsDecryptKey(string $encryptedKey): string
    {
        $result = $this->kms()->decrypt(['CiphertextBlob' => $encryptedKey, 'EncryptionContext' => ['service' => 'pesantenku-backup']]);

        return (string) $result->get('Plaintext');
    }

    private function kms(): KmsClient
    {
        return new KmsClient(array_filter([
            'version' => 'latest',
            'region' => env('AWS_DEFAULT_REGION', 'ap-southeast-1'),
            'endpoint' => config('backup-security.kms_endpoint'),
        ]));
    }
}
