<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin(); // sperrer for ikke-admin

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/menu.php';
?>

<div class="dashboard">
    <h1>Velkommen til SkipsWeb</h1>
    <p>Du er logget inn som 
        <?php 
        echo (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') 
            ? 'administrator' 
            : 'bruker'; 
        ?>.
    </p>

    <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
        <p>Som administrator kan du legge til, endre eller slette data og brukere.</p>
    <?php else: ?>
        <p>Som vanlig bruker kan du søke i og lese data.</p>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
