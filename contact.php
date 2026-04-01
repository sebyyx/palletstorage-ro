<?php
// ══════════════════════════════════════════════════════════════
//  PalletStorage.ro – contact.php
//  Trimite email prin SMTP autentificat (fără librării externe)
// ══════════════════════════════════════════════════════════════

// ── Credențiale SMTP ──────────────────────────────────────────
define('SMTP_HOST', 'mail.palletstorage.ro');
define('SMTP_PORT', 465);          // 465 = SSL  |  587 = STARTTLS
define('SMTP_USER', 'contact@palletstorage.ro');
define('SMTP_PASS', '_R#kg=-9BT^%BJth');
define('MAIL_FROM', 'contact@palletstorage.ro');
define('MAIL_TO',   'contact@palletstorage.ro');

// ── Client SMTP minimal (fără dependențe externe) ─────────────
/**
 * Trimite un email simplu text prin SMTP cu AUTH LOGIN.
 * Suportă SSL direct (port 465) și STARTTLS (port 587).
 */
function smtp_send(string $to, string $subject, string $body): bool
{
    $ctx = stream_context_create([
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ],
    ]);

    $port = SMTP_PORT;
    $wrapper = ($port === 465) ? 'ssl' : 'tcp';

    $socket = @stream_socket_client(
        "{$wrapper}://" . SMTP_HOST . ":{$port}",
        $errno, $errstr, 15,
        STREAM_CLIENT_CONNECT, $ctx
    );

    if (!$socket) {
        error_log("SMTP connect error: {$errstr} ({$errno})");
        return false;
    }

    stream_set_timeout($socket, 15);

    $read = static function () use ($socket): string {
        $buf = '';
        while ($line = fgets($socket, 512)) {
            $buf .= $line;
            if (isset($line[3]) && $line[3] === ' ') break; // fim multi-linie
        }
        return $buf;
    };
    $cmd = static function (string $line) use ($socket): void {
        fwrite($socket, $line . "\r\n");
    };

    $read(); // greeting

    $cmd('EHLO palletstorage.ro');
    $ehlo = $read();

    // STARTTLS pentru port 587
    if ($port === 587) {
        if (str_contains($ehlo, 'STARTTLS')) {
            $cmd('STARTTLS');
            $read();
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT, $ctx);
            $cmd('EHLO palletstorage.ro');
            $read();
        }
    }

    // Autentificare
    $cmd('AUTH LOGIN');
    $read();
    $cmd(base64_encode(SMTP_USER));
    $read();
    $cmd(base64_encode(SMTP_PASS));
    $auth = $read();

    if (!str_starts_with($auth, '235')) {
        error_log("SMTP AUTH failed: {$auth}");
        fclose($socket);
        return false;
    }

    $cmd('MAIL FROM:<' . MAIL_FROM . '>');
    $read();
    $cmd("RCPT TO:<{$to}>");
    $read();
    $cmd('DATA');
    $read();

    $date    = date('r');
    $subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    $msg  = "Date: {$date}\r\n";
    $msg .= "From: PalletStorage.ro <" . MAIL_FROM . ">\r\n";
    $msg .= "To: {$to}\r\n";
    $msg .= "Subject: {$subject}\r\n";
    $msg .= "MIME-Version: 1.0\r\n";
    $msg .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $msg .= "Content-Transfer-Encoding: 8bit\r\n";
    $msg .= "\r\n";
    $msg .= str_replace("\n.", "\n..", $body); // dot-stuffing RFC 5321
    $msg .= "\r\n.";

    $cmd($msg);
    $resp = $read();

    $cmd('QUIT');
    fclose($socket);

    return str_starts_with($resp, '250');
}

// ═════════════════════════════════════════════════════════════
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Permite și http în faza de test; în producție lasă doar https
$allowed = ['https://palletstorage.ro', 'https://www.palletstorage.ro'];
$origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && !in_array($origin, $allowed, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

// ── Sanitizare ────────────────────────────────────────────────
function clean(string $val): string
{
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

$name    = clean($_POST['name']    ?? '');
$phone   = clean($_POST['phone']   ?? '');
$email   = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$pallets = max(0, intval($_POST['pallets'] ?? 0));
$message = clean($_POST['message'] ?? '');

if (!$name || !$phone || !$email) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Câmpuri obligatorii lipsă sau invalide.']);
    exit;
}

// ── Email notificare internă ──────────────────────────────────
$sep   = str_repeat('-', 50);
$body1 = "Cerere nouă de ofertă – palletstorage.ro\n{$sep}\n";
$body1 .= "Nume / Companie : {$name}\n";
$body1 .= "Telefon         : {$phone}\n";
$body1 .= "Email           : {$email}\n";
$body1 .= "Paleți estimați : " . ($pallets > 0 ? $pallets : '—') . "\n";
$body1 .= "Mesaj           : " . ($message ?: '—') . "\n";
$body1 .= $sep . "\n";

// ── Email confirmare client ───────────────────────────────────
$body2  = "Bună ziua, {$name},\n\n";
$body2 .= "Mulțumim că ne-ai contactat!\n";
$body2 .= "Am primit cererea ta și te vom contacta în cel mai scurt timp.\n\n";
$body2 .= "Detalii trimise:\n{$sep}\n";
$body2 .= "Telefon         : {$phone}\n";
$body2 .= "Paleți estimați : " . ($pallets > 0 ? $pallets : '—') . "\n";
if ($message) $body2 .= "Mesaj           : {$message}\n";
$body2 .= "{$sep}\n\n";
$body2 .= "Cu stimă,\nEchipa PalletStorage.ro\n";
$body2 .= "Tel: 0730 238 240  |  Bd. Basarabia 256, Sector 3, Bucuresti\n";

// ── Trimitere ─────────────────────────────────────────────────
$ok1 = smtp_send(MAIL_TO, "Cerere ofertă PalletStorage – {$name}", $body1);
$ok2 = smtp_send($email,  'Am primit cererea ta – PalletStorage.ro', $body2);

if ($ok1) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Eroare la trimiterea emailului. Te rugăm să ne suni direct.']);
}
