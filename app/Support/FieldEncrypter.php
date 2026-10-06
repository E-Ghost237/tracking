<?php

namespace App\Support;

use Illuminate\Encryption\Encrypter;
use RuntimeException;

/**
 * Field-level AES-256-GCM encryption for secrets at rest (FR-91, FR-146).
 *
 * Uses a dedicated key (FIELD_ENCRYPTION_KEY) that is separate from APP_KEY and,
 * in production, injected from a secrets manager rather than stored with the database.
 */
class FieldEncrypter
{
    private ?Encrypter $encrypter = null;

    public function __construct(private readonly ?string $key) {}

    public function encrypt(string $plaintext): string
    {
        return $this->encrypter()->encryptString($plaintext);
    }

    public function decrypt(string $ciphertext): string
    {
        return $this->encrypter()->decryptString($ciphertext);
    }

    private function encrypter(): Encrypter
    {
        if ($this->encrypter !== null) {
            return $this->encrypter;
        }

        if (blank($this->key)) {
            throw new RuntimeException('FIELD_ENCRYPTION_KEY is not configured.');
        }

        $key = str_starts_with($this->key, 'base64:') ? base64_decode(substr($this->key, 7), true) : $this->key;

        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException('FIELD_ENCRYPTION_KEY must be 32 bytes (base64: prefixed).');
        }

        return $this->encrypter = new Encrypter($key, 'aes-256-gcm');
    }
}
