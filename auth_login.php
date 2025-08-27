<?php
// auth_login.php
// Selvstendig innloggingsside for SkipsWeb uten referanse til ekstern CSS.
// Oppfyller layoutkrav i CHANGE_REQUEST auth_login.md.

require_once __DIR__ . '/includes/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --------- Hjelpere ----------
if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

/**
 * Bygg trygt redirect-mål fra ?next=…
 * - Tillat KUN relative paths
 * - Fallback til '/' (evt. admin-dashboard for admin ved tom sti)
 */
function build_redirect_target(string $rawNext, bool $isAdmin, string $base): string {
    $path  = parse_url((string)$rawNext, PHP_URL_PATH) ?? '';
    $query = parse_url((string)$rawNext, PHP_URL_QUERY) ?? '';

    if ($path === '' || $path === '/') {
        $path = '/';
    }
    // Prefiksér med BASE_URL hvis definert og path ikke allerede har det
    if ($base !== '') {
        $needPrefix = (substr($path, 0, strlen($base) + 1) !== $base . '/')
                      && ($path !== $base);
        if ($needPrefix) {
            $path = $base . '/' . ltrim($path, '/');
        }
    }
    return $path . ($query ? ('?' . $query) : '');
}

$BASE = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
$nextRaw = isset($_REQUEST['next']) && is_string($_REQUEST['next']) ? trim($_REQUEST['next']) : '';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
    $password = $_POST['password'] ?? '';

    // Slå opp bruker med prepared statement
    $stmt = $conn->prepare("SELECT user_id, role, password FROM tblzuser WHERE email = ?");
    if ($stmt) {
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->bind_result($uid, $role, $hash);
        if ($stmt->fetch() && password_verify($password, (string)$hash)) {
            // OK – sett session
            $_SESSION['user_id']   = (int)$uid;
            $_SESSION['user_role'] = (string)$role;

            $isAdmin = ($role === 'admin');
            $target  = build_redirect_target($nextRaw, $isAdmin, $BASE);
            header('Location: ' . $target);
            exit;
        } else {
            $error = "Feil e-post eller passord.";
        }
        $stmt->close();
    } else {
        $error = "Teknisk feil: kunne ikke forberede spørring.";
    }
}
?>
<!DOCTYPE html>
<html lang="no">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Innlogging på SkipsWeb</title>
  <style>
    :root{
      --brand:#00529b;
      --bg:#f5f9fc;
      --fg:#14213d;
      --radius:12px;
      --shadow:0 2px 12px rgba(0,0,0,.06);
      --border:#d9e2ee;
      --focus:#cde3f8;
    }
    html,body{margin:0;padding:0;background:var(--bg);color:var(--fg);
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji","Segoe UI Emoji";}
    /* Toppbånd */
    .topbar{
      height:38px;background:var(--brand);display:flex;align-items:center;justify-content:center;
    }
    .topbar .logo{
      height:26px;width:auto;display:block;
    }
    /* Innhold */
    .wrap{
      max-width:1100px;margin:0 auto;padding:18px 14px;text-align:center;
    }
    h1{margin:.25rem 0 1rem 0;font-weight:600;font-size:20px;}
    .card{
      background:#fff;border:1px solid var(--border);border-radius:var(--radius);
      box-shadow:var(--shadow);max-width:420px;margin:0 auto;padding:18px 16px;text-align:left;
    }
    .field{margin:0 0 .75rem 0;}
    .label{display:block;font-size:14px;margin:0 0 .25rem 0;}
    .input{
      width:100%;box-sizing:border-box;border:1px solid var(--border);border-radius:8px;
      padding:10px 12px;font-size:14px;line-height:1.25;background:#fff;color:var(--fg);
    }
    .input:focus{outline:2px solid var(--focus);border-color:#bcd7f4;}
    .btn{
      display:inline-flex;align-items:center;justify-content:center;gap:8px;
      background:var(--brand);color:#fff;border:1px solid #00529b;border-radius:8px;
      padding:10px 14px;font-size:14px;line-height:1;cursor:pointer;box-shadow:var(--shadow);
    }
    .btn:focus{outline:2px solid var(--focus);outline-offset:2px}
    .mt-2l{ margin-top:1.6em; } /* ~2 tekstlinjer mellom passord og knapp */
    .alert{background:#fef2f2;border:1px solid #fbdada;color:#a62828;border-radius:8px;padding:10px 12px;margin:0 0 12px 0;}
    /* Responsiv: kortet skalerer naturlig ned, ingen ekstra regler nødvendig */
  </style>
</head>
<body>
  <!-- Toppbånd med midtstilt logo -->
  <div class="topbar">
    <img class="logo" src="<?= h(($BASE !== '' ? $BASE : '') . '/assets/img/skipsweb-logo.jpg') ?>" alt="SkipsWeb">
  </div>

  <div class="wrap">
    <h1>Innlogging på SkipsWeb</h1>

    <?php if (!empty($error)): ?>
      <div class="alert"><?= h($error) ?></div>
    <?php endif; ?>

    <form class="card" method="post" action="">
      <input type="hidden" name="next" value="<?= h($nextRaw) ?>">

      <div class="field">
        <label class="label" for="email">E-post</label>
        <input class="input" type="email" id="email" name="email" autocomplete="username" required autofocus>
      </div>

      <div class="field">
        <label class="label" for="password">Passord</label>
        <input class="input" type="password" id="password" name="password" autocomplete="current-password" required>
      </div>

      <div class="mt-2l">
        <button class="btn" type="submit">Logg inn</button>
      </div>
    </form>
  </div>
</body>

</html>
