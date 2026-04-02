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
function smtp_send(string $to, string $subject, string $body, bool $isHtml = false): bool
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
    $msg .= "Content-Type: " . ($isHtml ? "text/html" : "text/plain") . "; charset=UTF-8\r\n";
    $msg .= "Content-Transfer-Encoding: quoted-printable\r\n";
    $msg .= "\r\n";
    // quoted-printable wraps long lines safely for SMTP transport limits
    $encodedBody = quoted_printable_encode($body);
    $msg .= preg_replace('/(^|\r\n)\./', '$1..', $encodedBody); // dot-stuffing RFC 5321
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
$palletsDisplay = $pallets > 0 ? $pallets : '—';
$messageDisplay = $message ?: '—';

$body1 = <<<HTML
<!DOCTYPE html>
<html lang="ro">
<head><meta charset="UTF-8"><title>Cerere ofertă PalletStorage</title></head>
<body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fb;padding:32px 16px;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;max-width:600px;width:100%;box-shadow:0 4px 24px rgba(0,0,0,.09);">

      <!-- Header -->
      <tr>
        <td style="background:#102f60;padding:28px 32px;text-align:center;">
          <p style="margin:0 0 6px;color:#F5A623;font-size:11px;font-weight:700;letter-spacing:3px;text-transform:uppercase;">palletstorage.ro</p>
          <p style="margin:0;color:#ffffff;font-size:20px;font-weight:700;">&#128238; Cerere nouă de ofertă</p>
        </td>
      </tr>

      <!-- Body -->
      <tr>
        <td style="padding:32px 32px 8px;">
          <p style="margin:0 0 24px;color:#6B7280;font-size:14px;line-height:1.6;">
            Un nou client a completat formularul de contact pe <strong style="color:#102f60;">palletstorage.ro</strong>. Datele de contact sunt mai jos.
          </p>
          <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #E5E7EB;border-radius:8px;overflow:hidden;font-size:14px;">
            <tr style="background:#F9FAFB;">
              <td style="padding:12px 16px;font-weight:700;color:#102f60;width:38%;border-bottom:1px solid #E5E7EB;">Nume / Companie</td>
              <td style="padding:12px 16px;color:#111827;border-bottom:1px solid #E5E7EB;">{$name}</td>
            </tr>
            <tr>
              <td style="padding:12px 16px;font-weight:700;color:#102f60;border-bottom:1px solid #E5E7EB;">Telefon</td>
              <td style="padding:12px 16px;color:#111827;border-bottom:1px solid #E5E7EB;">{$phone}</td>
            </tr>
            <tr style="background:#F9FAFB;">
              <td style="padding:12px 16px;font-weight:700;color:#102f60;border-bottom:1px solid #E5E7EB;">Email</td>
              <td style="padding:12px 16px;color:#111827;border-bottom:1px solid #E5E7EB;">{$email}</td>
            </tr>
            <tr>
              <td style="padding:12px 16px;font-weight:700;color:#102f60;border-bottom:1px solid #E5E7EB;">Paleți estimați</td>
              <td style="padding:12px 16px;color:#111827;border-bottom:1px solid #E5E7EB;">{$palletsDisplay}</td>
            </tr>
            <tr style="background:#F9FAFB;">
              <td style="padding:12px 16px;font-weight:700;color:#102f60;">Mesaj</td>
              <td style="padding:12px 16px;color:#111827;">{$messageDisplay}</td>
            </tr>
          </table>
        </td>
      </tr>

      <!-- Footer -->
      <tr>
        <td style="padding:24px 32px 32px;text-align:center;border-top:1px solid #f0f0f0;margin-top:24px;">
          <p style="margin:16px 0 0;color:#9CA3AF;font-size:12px;">PalletStorage.ro · Bd. Basarabia 256, Sector 3, București · 0730 238 240</p>
        </td>
      </tr>

    </table>
  </td></tr>
</table>
</body>
</html>
HTML;

// ── Email confirmare client ───────────────────────────────────
$palletsRow = $pallets > 0
    ? "<tr><td style=\"padding:12px 16px;font-weight:700;color:#102f60;border-bottom:1px solid #E5E7EB;\">Paleți estimați</td><td style=\"padding:12px 16px;color:#111827;border-bottom:1px solid #E5E7EB;\">{$pallets}</td></tr>"
    : '';
$messageRow = $message
    ? "<tr style=\"background:#F9FAFB;\"><td style=\"padding:12px 16px;font-weight:700;color:#102f60;\">Mesaj</td><td style=\"padding:12px 16px;color:#111827;\">{$message}</td></tr>"
    : '';

$body2 = <<<HTML
<!DOCTYPE html>
<html lang="ro">
<head><meta charset="UTF-8"><title>Am primit cererea ta – PalletStorage.ro</title></head>
<body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fb;padding:32px 16px;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;max-width:600px;width:100%;box-shadow:0 4px 24px rgba(0,0,0,.09);">

      <!-- Header -->
      <tr>
        <td style="background:#102f60;padding:32px;text-align:center;">
          <p style="margin:0 0 6px;color:#F5A623;font-size:11px;font-weight:700;letter-spacing:3px;text-transform:uppercase;">palletstorage.ro</p>
          <p style="margin:0;color:#ffffff;font-size:22px;font-weight:700;">Am primit cererea ta! &#10024;</p>
        </td>
      </tr>

      <!-- Greeting -->
      <tr>
        <td style="padding:32px 32px 24px;">
          <p style="margin:0 0 12px;font-size:16px;color:#111827;">Bună ziua, <strong>{$name}</strong>,</p>
          <p style="margin:0 0 12px;font-size:14px;color:#6B7280;line-height:1.7;">
            Îți mulțumim că ne-ai contactat! Am primit cererea ta de ofertă și unul din specialiștii noștri te va suna în cel mai scurt timp pentru a discuta detaliile.
          </p>
          <p style="margin:0;font-size:14px;color:#6B7280;line-height:1.7;">
            Mai jos regăsești un sumar al cererii tale:
          </p>
        </td>
      </tr>

      <!-- Summary table -->
      <tr>
        <td style="padding:0 32px 24px;">
          <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #E5E7EB;border-radius:8px;overflow:hidden;font-size:14px;">
            <tr style="background:#F9FAFB;">
              <td style="padding:12px 16px;font-weight:700;color:#102f60;width:38%;border-bottom:1px solid #E5E7EB;">Telefon</td>
              <td style="padding:12px 16px;color:#111827;border-bottom:1px solid #E5E7EB;">{$phone}</td>
            </tr>
            {$palletsRow}
            {$messageRow}
          </table>
        </td>
      </tr>

      <!-- CTA -->
      <tr>
        <td style="padding:0 32px 32px;text-align:center;">
          <p style="margin:0 0 20px;font-size:14px;color:#6B7280;">Ai o urgență? Ne poți suna direct:</p>
          <a href="tel:+40730238240" style="display:inline-block;padding:14px 32px;background:#F5A623;color:#111827;font-weight:700;font-size:15px;border-radius:50px;text-decoration:none;">&#128222; 0730 238 240</a>
        </td>
      </tr>

      <!-- Divider + footer -->
      <tr>
        <td style="padding:20px 32px 32px;border-top:1px solid #F0F0F0;text-align:center;">
          <p style="margin:0 0 4px;font-size:13px;color:#111827;font-weight:600;">Echipa PalletStorage.ro</p>
          <p style="margin:0;font-size:12px;color:#9CA3AF;">Bd. Basarabia 256, Sector 3, București — incinta Faur</p>
          <p style="margin:4px 0 0;font-size:12px;color:#9CA3AF;">
            <a href="mailto:contact@palletstorage.ro" style="color:#9CA3AF;">contact@palletstorage.ro</a>
            &nbsp;·&nbsp;
            <a href="https://palletstorage.ro" style="color:#9CA3AF;">palletstorage.ro</a>
          </p>
        </td>
      </tr>

    </table>
  </td></tr>
</table>
</body>
</html>
HTML;

// ── Trimitere ─────────────────────────────────────────────────
$ok1 = smtp_send(MAIL_TO, "Cerere ofertă PalletStorage – {$name}", $body1, true);
$ok2 = smtp_send($email,  'Am primit cererea ta – PalletStorage.ro', $body2, true);

if ($ok1) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Eroare la trimiterea emailului. Te rugăm să ne suni direct.']);
}
