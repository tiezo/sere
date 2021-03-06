<?php

namespace App\Models\Postgres;

use App\Models\Contracts\SecretInterface;
use App\Models\Postgres\Traits\UuidPrimaryKey;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Secret model
 *
 * @property string uuid
 * @property string ciphertext
 * @property string ip
 * @property string attempts
 * @property Carbon expires_at
 * @property Carbon created_at
 * @property Carbon updated_at
 *
 * @package App\Models
 */
class Secret extends Model implements SecretInterface
{
    use UuidPrimaryKey;

    const CIPHER_METHOD = 'aes-256-cbc';

    const ATTEMPTS_MAX = 3;

    public $table = 'secrets';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    public $timestamps = true;

    protected $fillable = ['expires_at', 'ip'];

    protected $casts = [
        'expires_at' => 'timestamp'
    ];

    public function encrypt(string $text, string $password): void
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::CIPHER_METHOD));
        $encrypted = openssl_encrypt($text, self::CIPHER_METHOD, $password, 0, $iv);
        $this->ciphertext = $encrypted . ':' . base64_encode($iv);
    }

    /**
     * Returns false, if decryption failed
     */
    public function decrypt(string $password): string
    {
        list($encrypted, $iv_b64) = explode(':', $this->ciphertext);
        $iv = base64_decode($iv_b64);
        return openssl_decrypt($encrypted, self::CIPHER_METHOD, $password, 0, $iv);
    }

    public function increaseAttempts(): void
    {
        $this->attempts += 1;
    }

    /**
     * @return int
     */
    public function getAttemptsLeft(): int
    {
        $left = self::ATTEMPTS_MAX - $this->attempts;

        return max($left, 0);
    }

    /**
     * @return bool
     */
    public function needsDeletion(): bool
    {
        return $this->attempts >= self::ATTEMPTS_MAX || $this->expires_at < time();
    }
}
