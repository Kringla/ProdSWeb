<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Hent evt. next fra GET/POST
$nextRaw = $_GET['next'] ?? $_POST['next'] ?? '';
$nextRaw = is_string($nextRaw) ? trim($nextRaw) : '';
$BASE    = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';

/**
 * Bygg trygt redirectmål (kun relative paths), med fallback.
 */
function build_redirect_target(string $nextRaw, bool $isAdmin, string $BASE): string {
    $path  = parse_url($nextRaw, PHP_URL_PATH) ?? '';
    $query = parse_url($nextRaw, PHP_URL_QUERY) ?? '';

    if ($path === '' || $path === '/') {
        $path = $isAdmin ? '/admin/dashboard.php' : '/';
    }

    // Prefiksér med BASE_URL hvis nødvendig
    if ($BASE !== '') {
        $needPrefix = (substr($path, 0, strlen($BASE) + 1) !== $BASE . '/')
                      && ($path !== $BASE);
        if ($needPrefix) {
            $path = $BASE . '/' . ltrim($path, '/');
        }
    }

    return $path . ($query ? ('?' . $query) : '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Slå opp bruker
    $stmt = $conn->prepare("SELECT user_id, role, password FROM tblzuser WHERE email = ?");
    if ($stmt) {
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->bind_result($uid, $role, $hash);
        if ($stmt->fetch()) {
            if (password_verify($password, $hash)) {
                // OK: sett session
                $_SESSION['user_id']   = (int)$uid;
                $_SESSION['user_role'] = $role;

                $isAdmin = ($role === 'admin');
                $target  = build_redirect_target($nextRaw, $isAdmin, $BASE);

                header('Location: ' . $target);
                exit;
            } else {
                $error = "Feil e-post eller passord.";
            }
        } else {
            $error = "Feil e-post eller passord.";
        }
        $stmt->close();
    } else {
        $error = "Teknisk feil: kunne ikke forberede spørring.";
    }
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/menu.php';
?>
<div class="container mt-4">
  <?php if (!empty($error)): ?>
    <div class="card" style="border-left:4px solid #c0392b;margin-bottom:1rem;">
      <strong>Innlogging feilet:</strong> <?= e($error) ?>
    </div>
  <?php endif; ?>

  <form method="POST" action="" class="card" style="max-width:420px;">
      <input type="hidden" name="next" value="<?= e($nextRaw) ?>">

      <label for="email">E-post</label>
      <input id="email" type="email" name="email" required class="input" autocomplete="username" autofocus>

      <label for="password">Passord</label>
      <input id="password" type="password" name="password" required class="input" autocomplete="current-password">

      <button type="submit" class="btn primary mt-2">Logg inn</button>
  </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
