<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;

class JwtKeyGenerateCommand extends Command
{
    protected $signature = 'jwt:keygen {--force : Proceed even if JWT_PRIVATE_KEY is already set}';

    protected $description = 'Generate an RSA 2048-bit keypair for RS256 JWT signing. Prints PEM output for .env.';

    public function handle(): int
    {
        $existing = (string) config('services.jwt.private_key', '');
        if ($existing !== '' && ! $this->option('force')) {
            $this->error('JWT_PRIVATE_KEY is already configured. Use --force to proceed anyway.');

            return self::FAILURE;
        }

        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($resource === false) {
            throw new RuntimeException('openssl_pkey_new() failed: '.openssl_error_string());
        }

        if (! openssl_pkey_export($resource, $privatePem)) {
            throw new RuntimeException('openssl_pkey_export() failed: '.openssl_error_string());
        }

        $details = openssl_pkey_get_details($resource);
        if ($details === false || ! isset($details['key'])) {
            throw new RuntimeException('openssl_pkey_get_details() failed: '.openssl_error_string());
        }
        $publicPem = $details['key'];

        $this->line('');
        $this->info('=== JWT_PRIVATE_KEY (keep secret, .env or GitHub Secret) ===');
        $this->line($privatePem);
        $this->info('=== JWT_PUBLIC_KEY (mount on MayringCoder as JWT_PUBLIC_KEY_PATH) ===');
        $this->line($publicPem);
        $this->line('');
        $this->warn('Copy both values into .env (use quoted multi-line format) and the corresponding GitHub Secrets.');
        $this->warn('Do NOT commit either key to git.');

        return self::SUCCESS;
    }
}
