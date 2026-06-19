<?php
// locaux.php — Liste & ajout des locaux
require_once __DIR__ . '/config/db.php';

$pageTitle = 'Locaux';
$activeNav = 'locaux';
$baseUrl   = '';

$pdo = getPDO();
$errors  = [];
$success = '';

// ── POST : Ajouter un local ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_local') {
    $nom        = trim($_POST['nom_local']   ?? '');
    $adresse    = trim($_POST['adresse']     ?? '');
    $proprio    = trim($_POST['proprietaire'] ?? '');
    $cin_mf     = trim($_POST['cin_mf_proprietaire'] ?? '');

    if ($nom === '')     $errors[] = 'Le nom du local est obligatoire.';
    if ($adresse === '') $errors[] = "L'adresse est obligatoire.";
    if ($proprio === '') $errors[] = 'Le propriétaire est obligatoire.';

    // Upload document
    $doc_path = null;
    if (!empty($_FILES['document']['name'])) {
        $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        $ext     = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            $errors[] = 'Type de fichier non autorisé (pdf, doc, docx, jpg, png).';
        } elseif ($_FILES['document']['size'] > 10 * 1024 * 1024) {
            $errors[] = 'Fichier trop volumineux (max 10 Mo).';
        } else {
            $dir = __DIR__ . '/uploads/documents_locaux/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $fname = uniqid('local_') . '.' . $ext;
            if (move_uploaded_file($_FILES['document']['tmp_name'], $dir . $fname)) {
                $doc_path = 'uploads/documents_locaux/' . $fname;
            }
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            "INSERT INTO locaux (nom_local, adresse, proprietaire, cin_mf_proprietaire, document_path)
             VALUES (?,?,?,?,?)"
        );
        $stmt->execute([$nom, $adresse, $proprio, $cin_mf, $doc_path]);
        $success = 'Local « ' . $nom . ' » ajouté avec succès.';
    }
}

// ── POST : Supprimer un local ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_local') {
    $delId = (int)($_POST['local_id'] ?? 0);
    if ($delId) {
        // Check no active tenant
        $check = $pdo->prepare("SELECT COUNT(*) FROM locataires WHERE local_id = ? AND actif = 1");
        $check->execute([$delId]);
        if ((int)$check->fetchColumn() > 0) {
            $errors[] = 'Impossible de supprimer : ce local a un locataire actif. Archivez-le d\'abord.';
        } else {
            // Delete dependent rows first (archives, recus, inactive tenants)
            $pdo->prepare("DELETE FROM recus_historique WHERE local_id = ?")->execute([$delId]);
            $pdo->prepare("DELETE FROM archives_contrats WHERE local_id = ?")->execute([$delId]);
            $pdo->prepare("DELETE FROM locataires WHERE local_id = ?")->execute([$delId]);
            $pdo->prepare("DELETE FROM locaux WHERE id = ?")->execute([$delId]);
            $success = 'Local supprimé avec succès.';
        }
    }
}

// ── Liste des locaux ──────────────────────────────────────────
$locaux = $pdo->query(
    "SELECT l.*,
            lt.nom AS locataire_nom,
            lt.montant_mensuel,
            lt.frequence_paiement,
            lt.type_local
     FROM locaux l
     LEFT JOIN locataires lt ON lt.local_id = l.id AND lt.actif = 1
     ORDER BY l.id DESC"
)->fetchAll();

$showModal = isset($_GET['action']) && $_GET['action'] === 'add';

include __DIR__ . '/modules/layout.php';
?>

<!-- Flash / errors -->
<?php if ($success): ?>
  <div class="alert alert-success" data-auto-close>
    <span class="alert-icon">✅</span> <?= esc($success) ?>
  </div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
  <div class="alert alert-error">
    <span class="alert-icon">❌</span> <?= esc($err) ?>
  </div>
<?php endforeach; ?>

<!-- ── Header Bar ────────────────────────────────────────────── -->
<div class="flex items-center justify-between mb-3">
  <div>
    <h1 style="font-size:1.2rem;font-weight:800;color:var(--navy);">🏢 Locaux</h1>
    <p class="text-muted text-sm"><?= count($locaux) ?> local(aux) enregistré(s)</p>
  </div>
  <button class="btn btn-orange" onclick="openModal('modal-add-local')">
    ➕ Ajouter un local
  </button>
</div>

<!-- ── Table ─────────────────────────────────────────────────── -->
<div class="card">
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Nom du local</th>
          <th>Adresse</th>
          <th>Propriétaire</th>
          <th>Locataire actuel</th>
          <th>Type</th>
          <th>Statut</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($locaux as $row):
          $vacant = empty($row['locataire_nom']);
        ?>
        <tr>
          <td class="text-muted font-mono"><?= (int)$row['id'] ?></td>
          <td><strong><?= esc($row['nom_local']) ?></strong></td>
          <td style="max-width:220px"><?= esc($row['adresse']) ?></td>
          <td>
            <?= esc($row['proprietaire']) ?>
            <?php if ($row['cin_mf_proprietaire']): ?>
              <div class="muted"><?= esc($row['cin_mf_proprietaire']) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <?php if (!$vacant): ?>
              <strong><?= esc($row['locataire_nom']) ?></strong>
              <div class="muted font-mono"><?= formatTND((float)$row['montant_mensuel']) ?> TND</div>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td><?= esc($row['type_local'] ?? '—') ?></td>
          <td>
            <?php if ($vacant): ?>
              <span class="badge badge-gray">🔑 Vacant</span>
            <?php else: ?>
              <span class="badge badge-green">● Occupé</span>
            <?php endif; ?>
          </td>
          <td style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap">
            <a href="modules/local_detail.php?id=<?= (int)$row['id'] ?>"
               class="btn btn-primary btn-sm">Détail</a>
            <?php if ($row['document_path']): ?>
              <a href="<?= esc($row['document_path']) ?>"
                 class="btn btn-outline btn-sm" target="_blank">📄 Doc</a>
            <?php endif; ?>
            <form method="POST" style="display:inline"
                  onsubmit="return confirm('⚠️ Supprimer ce local définitivement ?\nCette action est irréversible.')">
              <input type="hidden" name="action" value="delete_local">
              <input type="hidden" name="local_id" value="<?= (int)$row['id'] ?>">
              <button type="submit" class="btn btn-sm"
                      style="background:#e74c3c;color:#fff;border:none;cursor:pointer">
                🗑️ Supprimer
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>

        <?php if (empty($locaux)): ?>
        <tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--muted);">
          Aucun local enregistré. Cliquez sur « Ajouter un local ».
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── Modal Ajout Local ─────────────────────────────────────── -->
<div class="modal-overlay <?= $showModal ? 'open' : '' ?>" id="modal-add-local">
  <div class="modal">
    <div class="modal-header">
      <h3>➕ Nouveau local</h3>
      <button class="btn btn-ghost btn-icon" data-close-modal="modal-add-local">✕</button>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="add_local">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label class="required" for="nom_local">Nom du local</label>
            <input type="text" id="nom_local" name="nom_local"
                   placeholder="ex: Magasin Centre-Ville" required>
          </div>

          <div class="form-group">
            <label class="required" for="proprietaire">Propriétaire</label>
            <input type="text" id="proprietaire" name="proprietaire"
                   placeholder="Nom complet" required>
          </div>

          <div class="form-group">
            <label for="cin_mf_proprietaire">CIN / MF Propriétaire</label>
            <input type="text" id="cin_mf_proprietaire" name="cin_mf_proprietaire"
                   placeholder="ex: 08123456">
          </div>

          <div class="form-group full">
            <label class="required" for="adresse">Adresse complète</label>
            <textarea id="adresse" name="adresse" rows="2"
                      placeholder="Rue, Ville, Code Postal" required></textarea>
          </div>

          <div class="form-group full">
            <label>Document (titre de propriété, bail...)</label>
            <div class="file-upload-area">
              <input type="file" name="document" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
              <div style="font-size:2rem">📁</div>
              <div class="file-label" style="margin-top:.5rem;font-size:.85rem;color:var(--muted);">
                Glissez un fichier ou cliquez pour sélectionner<br>
                <small>PDF, DOC, DOCX, JPG — max 10 Mo</small>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-close-modal="modal-add-local">Annuler</button>
        <button type="submit" class="btn btn-orange">Enregistrer le local</button>
      </div>
    </form>
  </div>
</div>

<script>
// Auto-open modal if ?action=add
<?php if ($showModal): ?>
document.addEventListener('DOMContentLoaded', () => openModal('modal-add-local'));
<?php endif; ?>
</script>

<?php include __DIR__ . '/modules/layout_footer.php'; ?>
