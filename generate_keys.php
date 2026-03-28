<?php
/**
 * Generates RSA 2048 keypair for LexikJWT.
 * Passphrase is read from JWT_PASSPHRASE env var (or .env file).
 * Run: php generate_keys.php
 */

// Load .env manually if needed
if (file_exists(__DIR__ . '/.env')) {
    foreach (file(__DIR__ . '/.env') as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (preg_match('/^JWT_PASSPHRASE=(.+)$/', $line, $m)) {
            putenv("JWT_PASSPHRASE={$m[1]}");
        }
    }
}

$passphrase = getenv('JWT_PASSPHRASE') ?: 'd5a19149ecf743fabe272bb9aaf957f500aa2b8b29978543010b0f8a7f8efebb';
echo "Using passphrase: $passphrase\n";

// Find openssl.cnf
$cnfCandidates = [
    'openssl_minimal.cnf',
    'C:/Program Files/Common Files/SSL/openssl.cnf',
    'C:/php/extras/ssl/openssl.cnf',
    '/etc/ssl/openssl.cnf',
    '/usr/lib/ssl/openssl.cnf',
];
$cnf = null;
foreach ($cnfCandidates as $c) {
    if (file_exists($c)) { $cnf = realpath($c); break; }
}
if ($cnf) {
    putenv("OPENSSL_CONF=$cnf");
    echo "Using OPENSSL_CONF: $cnf\n";
}

$config = [
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
];
if ($cnf) $config['config'] = $cnf;

$res = openssl_pkey_new($config);
if ($res === false) {
    echo "FAILED openssl_pkey_new:\n";
    while ($e = openssl_error_string()) echo "  $e\n";
    exit(1);
}

$exportConfig = $cnf ? ['config' => $cnf] : [];
$exported = openssl_pkey_export($res, $privKey, $passphrase, $exportConfig ?: null);
if (!$exported || !$privKey) {
    echo "FAILED openssl_pkey_export:\n";
    while ($e = openssl_error_string()) echo "  $e\n";
    exit(1);
}

if (!is_dir('config/jwt')) mkdir('config/jwt', 0755, true);
file_put_contents('config/jwt/private.pem', $privKey);

$details = openssl_pkey_get_details($res);
file_put_contents('config/jwt/public.pem', $details['key']);

echo "Keys generated OK\n";
echo "private.pem: " . strlen($privKey) . " bytes\n";
echo "public.pem:  " . strlen($details['key']) . " bytes\n";
echo "Passphrase used: $passphrase\n";
