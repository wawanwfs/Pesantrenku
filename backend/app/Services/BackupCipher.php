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
        $contents = json_encode($envelope, JSON_UNESCAPED_SLASHES);

        return [
            'contents' => $contents,
            'provider' => $provider,
            'key_id' => $keyId,
            'checksum' => hash('sha256', $contents),
        ];
    }

    public function decrypt(string $contents): string
    {
        $envelope = json_decode($contents, true);
        if (! is_array($envelope) || ! isset($envelope['ciphertext'], $envelope['encrypted_key'])) {
            return $contents;
        }

        $encryptedKey = base64_decode((string) $envelope['encrypted_key'], true);
        $dataKey = ($envelope['key_provider'] ?? 'local') === 'kms'
            ? $this->kmsDecryptKey($encryptedKey ?: '')
            : base64_decode(Crypt::decryptString($encryptedKey ?: ''), true);
        $plaintext = openssl_decrypt(
            base64_decode((string) $envelope['ciphertext'], true) ?: '',
            'aes-256-gcm',
            $dataKey ?: '',
            OPENSSL_RAW_DATA,
            base64_decode((string) $envelope['iv'], true) ?: '',
            base64_decode((string) $envelope['tag'], true) ?: '',
        );
        if ($plaintext === false) {
            throw new \RuntimeException('Backup decryption or authentication failed.');
        }

        return $plaintext;
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
