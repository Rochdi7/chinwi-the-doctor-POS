<?php

/**
 * Serve the app over HTTPS on the local network, so a phone can be used as
 * the barcode scanner while developing.
 *
 * Safari (and Chrome) only give a page the camera on a secure origin, and
 * `php artisan serve` speaks plain HTTP. This puts a TLS front in front of
 * it: the phone talks HTTPS to this script, this script talks HTTP to
 * artisan serve on localhost.
 *
 *   php artisan serve --host=127.0.0.1 --port=8000
 *   php deploy/serve-https.php                      (in a second terminal)
 *
 * Then browse to https://<your-lan-ip>:8443 on the phone and accept the
 * self-signed certificate once. Development only: never expose this.
 */

$httpsPort = (int) ($argv[1] ?? 8443);
$appHost = '127.0.0.1';
$appPort = (int) ($argv[2] ?? 8000);

$certDir = __DIR__.'/../storage/app/dev-cert';
$certFile = $certDir.'/local.pem';

if (! is_dir($certDir)) {
    mkdir($certDir, 0755, true);
}

// A certificate that covers this machine's LAN addresses. Regenerated when
// the address changes, so moving between networks does not break it.
$ips = localAddresses();
$fingerprint = implode(',', $ips);

if (! is_file($certFile) || trim(@file_get_contents($certDir.'/.hosts')) !== $fingerprint) {
    makeCertificate($certFile, $ips);
    file_put_contents($certDir.'/.hosts', $fingerprint);
    echo "Certificate created for: {$fingerprint}\n";
}

$context = stream_context_create([
    'ssl' => [
        'local_cert' => $certFile,
        'allow_self_signed' => true,
        'verify_peer' => false,
    ],
]);

// Accept plain TCP, then turn TLS on per connection: a non-blocking accept
// on a tls:// server cannot complete the handshake.
stream_context_set_option($context, 'ssl', 'verify_peer_name', false);

$server = @stream_socket_server(
    "tcp://0.0.0.0:{$httpsPort}",
    $errno,
    $error,
    STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
    $context
);

if (! $server) {
    fwrite(STDERR, "Cannot listen on port {$httpsPort}: {$error}\n");
    exit(1);
}

echo "\n  HTTPS ready. On the phone, open:\n";

foreach ($ips as $ip) {
    if ($ip !== '127.0.0.1') {
        echo "    https://{$ip}:{$httpsPort}/admin/scanner\n";
    }
}

echo "\n  Accept the certificate warning once (Advanced -> Continue).\n";
echo "  Forwarding to http://{$appHost}:{$appPort}\n\n";

/**
 * A browser opens several connections at once (page, css, Livewire polling),
 * and the phone adds more, so every pair is kept open together and driven by
 * one select loop rather than served one after another.
 *
 * @var array<int, array{0: resource, 1: resource}> $pairs
 */
$pairs = [];

stream_set_blocking($server, false);

while (true) {
    $read = [$server];

    foreach ($pairs as [$client, $upstream]) {
        $read[] = $client;
        $read[] = $upstream;
    }

    $write = $except = [];

    if (@stream_select($read, $write, $except, 0, 20000) === false) {
        continue;
    }

    foreach ($read as $ready) {
        if ($ready === $server) {
            accept($server, $appHost, $appPort, $pairs);

            continue;
        }

        forward($ready, $pairs);
    }
}

/**
 * @param  array<int, array{0: resource, 1: resource}>  $pairs
 */
function accept($server, string $appHost, int $appPort, array &$pairs): void
{
    $client = @stream_socket_accept($server, 0);

    if (! $client) {
        return;
    }

    // The handshake needs a blocking socket; the connection goes
    // non-blocking once TLS is up.
    stream_set_blocking($client, true);

    if (@stream_socket_enable_crypto($client, true, STREAM_CRYPTO_METHOD_TLS_SERVER) !== true) {
        @fclose($client);

        return;
    }

    $upstream = @stream_socket_client("tcp://{$appHost}:{$appPort}", $errno, $error, 5);

    if (! $upstream) {
        @fwrite($client, "HTTP/1.1 502 Bad Gateway\r\nContent-Length: 0\r\n\r\n");
        @fclose($client);

        return;
    }

    stream_set_blocking($client, false);
    stream_set_blocking($upstream, false);

    $pairs[] = [$client, $upstream];
}

/**
 * Move whatever arrived on one socket to its partner, and drop the pair once
 * either end hangs up.
 *
 * @param  array<int, array{0: resource, 1: resource}>  $pairs
 */
function forward($socket, array &$pairs): void
{
    foreach ($pairs as $i => [$client, $upstream]) {
        if ($socket !== $client && $socket !== $upstream) {
            continue;
        }

        $other = $socket === $client ? $upstream : $client;
        $data = @fread($socket, 65536);

        if ($data === false || ($data === '' && feof($socket))) {
            @fclose($client);
            @fclose($upstream);
            unset($pairs[$i]);

            return;
        }

        if ($data !== '') {
            @fwrite($other, $data);
        }

        return;
    }
}

/** @return array<int, string> */
function localAddresses(): array
{
    $ips = ['127.0.0.1'];

    // The address a phone on the same Wi-Fi will use.
    $socket = @stream_socket_client('udp://8.8.8.8:53', $errno, $error, 1);

    if ($socket) {
        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        $ip = strstr((string) $name, ':', true);

        if ($ip && $ip !== '127.0.0.1') {
            $ips[] = $ip;
        }
    }

    return array_values(array_unique($ips));
}

/** @param array<int, string> $ips */
function makeCertificate(string $path, array $ips): void
{
    $alt = ['DNS:localhost'];

    foreach ($ips as $ip) {
        $alt[] = 'IP:'.$ip;
    }

    $config = sys_get_temp_dir().'/dev-cert-'.getmypid().'.cnf';
    file_put_contents($config, implode("\n", [
        '[req]',
        'distinguished_name = dn',
        'x509_extensions = ext',
        'prompt = no',
        '[dn]',
        'CN = '.($ips[1] ?? 'localhost'),
        '[ext]',
        'subjectAltName = '.implode(',', $alt),
        'basicConstraints = CA:FALSE',
        'keyUsage = digitalSignature, keyEncipherment',
        'extendedKeyUsage = serverAuth',
        '',
    ]));

    $key = tempnam(sys_get_temp_dir(), 'key');
    $crt = tempnam(sys_get_temp_dir(), 'crt');

    exec(sprintf(
        'openssl req -x509 -newkey rsa:2048 -nodes -keyout %s -out %s -days 825 -config %s 2>&1',
        escapeshellarg($key),
        escapeshellarg($crt),
        escapeshellarg($config)
    ), $output, $status);

    if ($status !== 0) {
        fwrite(STDERR, "openssl failed:\n".implode("\n", $output)."\n");
        exit(1);
    }

    // PHP wants the key and the certificate in one file.
    file_put_contents($path, file_get_contents($crt).file_get_contents($key));

    @unlink($config);
    @unlink($key);
    @unlink($crt);
}
