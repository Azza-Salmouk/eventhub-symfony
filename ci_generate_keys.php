<?php
/**
 * CI-only JWT key generator.
 * Reads JWT_PASSPHRASE from environment (set in ci.yml).
 * Does NOT read from .env files.
 * Used only in GitHub Actions — not needed locally (use generate_keys.php instead).
 */

$passphrase = getenv('JWT_PASSPHRASE');
if (!$passphrase) {
    fwrite(STDERR, "ERROR: JWT_PASSPHRASE env var is not set.\n");
    exit(1);
}

if (!is_dir('config/jwt')) {
    mkdir('config/jwt', 0755, true);
}

$config = [
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
];

$res = openssl_pkey_new($config);
if ($res === false) {
    fwrite(STDERR, "ERROR: openssl_pkey_new failed:\n");
    while ($e = openssl_error_string()) fwrite(STDERR, "  $e\n");
    exit(1);
}

$exported = openssl_pkey_export($res, $privKey, $passphrase);
if (!$exported || !$privKey) {
    fwrite(STDERR, "ERROR: openssl_pkey_export failed:\n");
    while ($e = openssl_error_string()) fwrite(STDERR, "  $e\n");
    exit(1);
}

file_put_contents('config/jwt/private.pem', $privKey);

$details = openssl_pkey_get_details($res);
file_put_contents('config/jwt/public.pem', $details['key']);

echo "JWT keys generated OK (passphrase from env)\n";
echo "private.pem: " . strlen($privKey) . " bytes\n";
echo "public.pem:  " . strlen($details['key']) . " bytes\n";
