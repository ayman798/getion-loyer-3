<?php
// modules/locataire_form.php — Formulaire ajout locataire (standalone)
require_once __DIR__ . '/../config/db.php';

$baseUrl   = '../';
$pageTitle = 'Nouveau Locataire';
$activeNav = 'locaux';

$pdo    = getPDO();
$errors = [];

$localId = (int)($_GET['local_id'] ?? 0);
if (!$localId) { header('Location: ../locaux.php'); exit; }

$local = $pdo->prepare("SELECT * FROM locaux WHERE id = ?");
$local->execute([$localId]);
$local = $local->fetch();
if (!$local) { header('Location: ../locaux.php'); exit; }

// POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom     = trim($_POST['nom']      ?? '');
    $montant = (float)($_POST['montant_mensuel'] ?? 0);
    $debut   = trim($_POST['date_debut'] ?? '');
    if ($nom === '')   $errors[] = 'Nom obligatoire.';
    if ($montant <= 0) $errors[] = 'Montant obligatoire.';
    if ($debut === '') $errors[] = 'Date début obligatoire.';

    if (empty($errors)) {
        header("Location: local_detail.php?id={$localId}&action=add_locataire");
        exit;
    }
}

include __DIR__ . '/../modules/layout.php';
?>
<div class="mb-3">
  <a href="local_detail.php?id=<?= $localId ?>&tab=nouveau" class="btn btn-outline">
    ← Retour au détail local
  </a>
</div>

<div class="card">
  <div class="card-header">
    <h3>➕ Nouveau locataire pour : <?= esc($local['nom_local']) ?></h3>
  </div>
  <div class="card-body">
    <p class="text-muted text-sm mb-3">
      Remplissez le formulaire ci-dessous. Si un locataire actif existe déjà,
      il sera archivé automatiquement.
    </p>
    <a href="local_detail.php?id=<?= $localId ?>&tab=nouveau" class="btn btn-orange btn-lg">
      Accéder au formulaire complet →
    </a>
  </div>
</div>

<?php include __DIR__ . '/../modules/layout_footer.php'; ?>
