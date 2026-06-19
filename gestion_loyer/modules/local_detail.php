<?php
// modules/local_detail.php — Détail d'un local (3 onglets)
require_once __DIR__ . '/../config/db.php';

$baseUrl   = '../';
$pageTitle = 'Détail Local';
$activeNav = 'locaux';

$pdo = getPDO();
$id  = (int)($_GET['id'] ?? 0);

if (!$id) { header('Location: ../locaux.php'); exit; }

$local = $pdo->prepare("SELECT * FROM locaux WHERE id = ?");
$local->execute([$id]);
$local = $local->fetch();
if (!$local) { header('Location: ../locaux.php'); exit; }

$pageTitle  = esc($local['nom_local']);
$breadcrumb = 'Détail';

// ── Locataire actif ───────────────────────────────────────────
$locataireActif = $pdo->prepare(
    "SELECT * FROM locataires WHERE local_id = ? AND actif = 1 LIMIT 1"
);
$locataireActif->execute([$id]);
$locataireActif = $locataireActif->fetch();

// ── Archives ──────────────────────────────────────────────────
$archives = $pdo->prepare(
    "SELECT * FROM archives_contrats WHERE local_id = ? ORDER BY date_archive DESC"
);
$archives->execute([$id]);
$archives = $archives->fetchAll();

$errors  = [];
$success = '';

// ── POST : Modifier locataire actif ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_locataire') {
    $editId  = (int)($_POST['locataire_id'] ?? 0);
    $nom     = trim($_POST['nom'] ?? '');
    $cin     = trim($_POST['cin'] ?? '');
    $tel     = trim($_POST['num_tel'] ?? '');
    $mf      = trim($_POST['mf'] ?? '');
    $montant = (float)($_POST['montant_mensuel'] ?? 0);
    $freq    = $_POST['frequence_paiement'] ?? 'mois';
    $retenue = (int)($_POST['retenue'] ?? 0);
    $aug_mt  = (float)($_POST['augmentation_montant'] ?? 0);
    $aug_per = $_POST['augmentation_periode'] ?? 'annee';
    $type    = trim($_POST['type_local'] ?? '');
    $debut   = trim($_POST['date_debut'] ?? '');
    $note    = trim($_POST['note'] ?? '');

    // Extract extra charges
    $extra_charges = [];
    if (isset($_POST['charge_labels']) && isset($_POST['charge_amounts'])) {
        foreach ($_POST['charge_labels'] as $idx => $lbl) {
            $lbl = trim($lbl);
            $amt = (float)($_POST['charge_amounts'][$idx] ?? 0);
            if ($lbl !== '' || $amt > 0) {
                $extra_charges[] = ['label' => $lbl, 'amount' => $amt];
            }
        }
    }
    $charges_json = json_encode($extra_charges, JSON_UNESCAPED_UNICODE);

    // Contrat upload for edit: keep existing path if no new file uploaded
    $contrat_path = $locataireActif['contrat_path'] ?? null;
    if (!empty($_FILES['contrat_file']['name'])) {
      $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
      $ext = strtolower(pathinfo($_FILES['contrat_file']['name'], PATHINFO_EXTENSION));
      if (!in_array($ext, $allowed)) {
        $errors[] = 'Contrat : type non autorisé (PDF, JPG, PNG).';
      } elseif ($_FILES['contrat_file']['size'] > 10 * 1024 * 1024) {
        $errors[] = 'Contrat : fichier trop volumineux (max 10 Mo).';
      } else {
        $dir = __DIR__ . '/../uploads/contrats/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $fname = 'contrat_' . time() . '_' . $editId . '.' . $ext;
        if (move_uploaded_file($_FILES['contrat_file']['tmp_name'], $dir . $fname)) {
          $contrat_path = 'uploads/contrats/' . $fname;
        } else {
          $errors[] = 'Contrat : impossible d\'enregistrer le fichier.';
        }
      }
    }

    if ($nom === '')   $errors[] = 'Le nom est obligatoire.';
    if ($montant <= 0) $errors[] = 'Le montant mensuel est obligatoire.';

    if ($editId && empty($errors)) {
      if ($mf !== '') $retenue = 10;
      $stmtUpd = $pdo->prepare(
        "UPDATE locataires SET nom=?, cin=?, num_tel=?, mf=?, montant_mensuel=?,
         frequence_paiement=?, retenue=?, augmentation_montant=?,
         augmentation_periode=?, type_local=?, date_debut=?, note=?, contrat_path=?, charges_additionnelles=?
         WHERE id=? AND local_id=? AND actif=1"
      );
      $stmtUpd->execute([
        $nom, $cin, $tel, $mf, $montant, $freq, $retenue,
        $aug_mt, $aug_per, $type, $debut, $note, $contrat_path, $charges_json, $editId, $id
      ]);
      header("Location: local_detail.php?id={$id}&tab=actuel&ok=1");
      exit;
    }
}

// ── POST : Libérer le local (rendre vacant) ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'liberer_local') {
    if ($locataireActif) {
        $archiveData = json_encode($locataireActif, JSON_UNESCAPED_UNICODE);
        $pdo->prepare("INSERT INTO archives_contrats (local_id, locataire_data, date_archive) VALUES (?, ?, NOW())")->execute([$id, $archiveData]);
        $pdo->prepare("UPDATE locataires SET actif = 0 WHERE id = ?")->execute([$locataireActif['id']]);
        header("Location: local_detail.php?id={$id}&tab=archive");
        exit;
    }
}

// ── POST : Modifier le local ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_local') {
    $nom     = trim($_POST['nom_local'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $proprio = trim($_POST['proprietaire'] ?? '');
    $cin_mf  = trim($_POST['cin_mf_proprietaire'] ?? '');

    if ($nom === '')     $errors[] = 'Le nom du local est obligatoire.';
    if ($adresse === '') $errors[] = "L'adresse est obligatoire.";
    if ($proprio === '') $errors[] = 'Le propriétaire est obligatoire.';

    if (empty($errors)) {
        $pdo->prepare(
            "UPDATE locaux SET nom_local=?, adresse=?, proprietaire=?, cin_mf_proprietaire=? WHERE id=?"
        )->execute([$nom, $adresse, $proprio, $cin_mf, $id]);
        header("Location: local_detail.php?id={$id}&ok=1");
        exit;
    }
}

// ── POST : Nouveau locataire ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_locataire') {
    $nom        = trim($_POST['nom']          ?? '');
    $cin        = trim($_POST['cin']          ?? '');
    $tel        = trim($_POST['num_tel']      ?? '');
    $mf         = trim($_POST['mf']           ?? '');
    $montant    = (float)($_POST['montant_mensuel'] ?? 0);
    $freq       = $_POST['frequence_paiement'] ?? 'mois';
    $retenue    = (int)($_POST['retenue']     ?? 0);
    $aug_mt     = (float)($_POST['augmentation_montant'] ?? 0);
    $aug_per    = $_POST['augmentation_periode'] ?? 'annee';
    $type_local = trim($_POST['type_local']   ?? '');
    $date_debut = trim($_POST['date_debut']   ?? '');
    $note       = trim($_POST['note']         ?? '');

    // Extract extra charges
    $extra_charges = [];
    if (isset($_POST['charge_labels']) && isset($_POST['charge_amounts'])) {
        foreach ($_POST['charge_labels'] as $idx => $lbl) {
            $lbl = trim($lbl);
            $amt = (float)($_POST['charge_amounts'][$idx] ?? 0);
            if ($lbl !== '' || $amt > 0) {
                $extra_charges[] = ['label' => $lbl, 'amount' => $amt];
            }
        }
    }
    $charges_json = json_encode($extra_charges, JSON_UNESCAPED_UNICODE);

    if ($nom === '')        $errors[] = 'Le nom est obligatoire.';
    if ($montant <= 0)      $errors[] = 'Le montant mensuel est obligatoire.';
    if ($date_debut === '') $errors[] = 'La date de début est obligatoire.';

    // Upload contrat
    $contrat_path = null;
    if (!empty($_FILES['contrat']['name'])) {
        $allowed = ['pdf', 'doc', 'docx'];
        $ext = strtolower(pathinfo($_FILES['contrat']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            $errors[] = 'Contrat : type non autorisé (pdf, doc, docx).';
        } elseif ($_FILES['contrat']['size'] > 10 * 1024 * 1024) {
            $errors[] = 'Contrat : fichier trop volumineux (max 10 Mo).';
        } else {
            $dir = __DIR__ . '/../uploads/contrats/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $fname = uniqid('contrat_') . '.' . $ext;
            if (move_uploaded_file($_FILES['contrat']['tmp_name'], $dir . $fname)) {
                $contrat_path = 'uploads/contrats/' . $fname;
            }
        }
    }

    if (empty($errors)) {
        // Archiver le locataire actif existant
        if ($locataireActif) {
            $archiveData = json_encode($locataireActif, JSON_UNESCAPED_UNICODE);
            $stmtArch = $pdo->prepare(
                "INSERT INTO archives_contrats (local_id, locataire_data, date_archive)
                 VALUES (?, ?, NOW())"
            );
            $stmtArch->execute([$id, $archiveData]);

            $stmtDeact = $pdo->prepare("UPDATE locataires SET actif = 0 WHERE id = ?");
            $stmtDeact->execute([$locataireActif['id']]);
        }

        // Retenue auto
        if ($mf !== '') $retenue = 10;

        $stmtIns = $pdo->prepare(
            "INSERT INTO locataires
             (local_id, nom, cin, num_tel, mf, montant_mensuel, frequence_paiement,
              retenue, augmentation_montant, augmentation_periode, type_local,
              contrat_path, date_debut, date_derniere_augmentation, actif, note, charges_additionnelles)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)" 
        );
        $stmtIns->execute([
            $id, $nom, $cin, $tel, $mf, $montant, $freq,
            $retenue, $aug_mt, $aug_per, $type_local,
            $contrat_path, $date_debut, $date_debut, 1, $note, $charges_json
        ]);

      }

    }

$activeTab = $_GET['tab'] ?? 'actuel';
include __DIR__ . '/../modules/layout.php';
?>

<!-- ── Local Header ──────────────────────────────────────────── -->
<div class="local-header">
  <div>
    <h2>🏢 <?= esc($local['nom_local']) ?></h2>
    <p><?= esc($local['adresse']) ?></p>
  </div>
  <div style="text-align:right;display:flex;flex-direction:column;align-items:flex-end;gap:.5rem">
    <div>
      <div style="font-size:.8rem;opacity:.7">Propriétaire</div>
      <div style="font-weight:700"><?= esc($local['proprietaire']) ?></div>
      <div style="font-size:.8rem;opacity:.7"><?= esc($local['cin_mf_proprietaire']) ?></div>
    </div>
    <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('modal-edit-local').classList.add('open')">
      ✏️ Modifier le local
    </button>
  </div>
</div>

<!-- Flash -->
<?php if (isset($_GET['ok'])): ?>
  <div class="alert alert-success mb-2" data-auto-close>✅ Locataire ajouté avec succès.</div>
<?php endif; ?>
<?php if ($success): ?>
  <div class="alert alert-success mb-2" data-auto-close>✅ <?= esc($success) ?></div>
<?php endif; ?>
<?php foreach ($errors as $e): ?>
  <div class="alert alert-error mb-1">❌ <?= esc($e) ?></div>
<?php endforeach; ?>

<!-- ── Tabs ──────────────────────────────────────────────────── -->
<div class="tab-container">
  <div class="tabs">
    <button class="tab-btn <?= $activeTab==='archive' ? 'active':'' ?>"
            data-tab="archive" onclick="setTab('archive')">
      🗂️ Archive contrats
      <span class="badge badge-gray" style="margin-left:.3rem"><?= count($archives) ?></span>
    </button>
    <button class="tab-btn <?= $activeTab==='actuel' ? 'active':'' ?>"
            data-tab="actuel" onclick="setTab('actuel')">
      👤 Locataire actuel
    </button>
    <button class="tab-btn <?= $activeTab==='nouveau' ? 'active':'' ?>"
            data-tab="nouveau" onclick="setTab('nouveau')">
      ➕ Nouveau locataire
    </button>
  </div>

  <!-- ── Tab 1 : Archive ──────────────────────────────────────── -->
  <div class="tab-panel <?= $activeTab==='archive' ? 'active':'' ?>" id="tab-archive">
    <div class="card">
      <div class="card-header"><h3>🗂️ Historique des locataires</h3></div>
      <?php if (empty($archives)): ?>
        <div class="card-body text-muted">Aucun historique pour ce local.</div>
      <?php else: ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Nom</th><th>CIN</th><th>MF</th>
              <th>Montant</th><th>Fréquence</th>
              <th>Date début</th>
              <th>Contrat</th>
              <th>Archivé le</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($archives as $arch):
              $d = json_decode($arch['locataire_data'], true);
            ?>
            <tr>
              <td><strong><?= esc($d['nom'] ?? '—') ?></strong></td>
              <td><?= esc($d['cin'] ?? '—') ?></td>
              <td><?= esc($d['mf'] ?? '—') ?: '<span class="text-muted">Particulier</span>' ?></td>
              <td class="font-mono"><?= formatTND((float)($d['montant_mensuel'] ?? 0)) ?> TND</td>
              <td><span class="badge badge-blue"><?= esc($d['frequence_paiement'] ?? '') ?></span></td>
              <td><?= esc($d['date_debut'] ?? '—') ?></td>
              <td>
                <?php if (!empty($d['contrat_path'])): ?>
                  <a href="../<?= esc($d['contrat_path']) ?>" target="_blank" class="btn btn-outline btn-sm" style="padding: 2px 8px; font-size: 0.8rem;">📄 Voir le contrat</a>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td class="text-muted"><?= esc(substr($arch['date_archive'], 0, 10)) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Tab 2 : Locataire actuel ─────────────────────────────── -->
  <div class="tab-panel <?= $activeTab==='actuel' ? 'active':'' ?>" id="tab-actuel">
    <?php if (!$locataireActif): ?>
      <div class="card card-body text-muted" style="padding:2rem;text-align:center;">
        🔑 Ce local est vacant. Ajoutez un locataire via l'onglet « Nouveau locataire ».
      </div>
    <?php else: $l = $locataireActif; ?>
    <div class="card">
      <div class="card-header">
        <h3>👤 <?= esc($l['nom']) ?></h3>
        <div class="flex gap-2">
          <?php if ($l['contrat_path']): ?>
            <a href="../<?= esc($l['contrat_path']) ?>" class="btn btn-outline btn-sm"
               target="_blank">📄 Contrat</a>
          <?php endif; ?>
          <a href="../loyer.php?mois=<?= date('Y-m') ?>&recu=<?= (int) $l['id'] ?>"
             class="btn btn-orange btn-sm">🧾 Générer reçu</a>
          <button type="button" class="btn btn-primary btn-sm" id="btn-edit-locataire"
                  onclick="toggleEditLocataire()">✏️ Modifier</button>
          <form method="POST" style="display:inline;" onsubmit="return confirm('⚠️ Libérer ce local ? Le locataire actuel sera déplacé vers l\'archive et le local deviendra vacant.')">
            <input type="hidden" name="action" value="liberer_local">
            <button type="submit" class="btn btn-outline btn-sm" style="color:var(--red); border-color:var(--red);">🔓 Libérer (Rendre vacant)</button>
          </form>
        </div>
      </div>
      <div class="card-body">
        <form method="POST" id="form-edit-locataire" enctype="multipart/form-data">
          <input type="hidden" name="action" value="edit_locataire">
          <input type="hidden" name="locataire_id" value="<?= (int)$l['id'] ?>">
          <div class="form-grid" id="locataire-view-grid">

            <div class="form-group">
              <label>Nom complet</label>
              <input type="text" name="nom" value="<?= esc($l['nom']) ?>" class="edit-field" readonly>
            </div>
            <div class="form-group">
              <label>CIN</label>
              <input type="text" name="cin" value="<?= esc($l['cin']) ?>" class="edit-field" readonly>
            </div>
            <div class="form-group">
              <label>Téléphone</label>
              <input type="tel" name="num_tel" value="<?= esc($l['num_tel']) ?>" class="edit-field" readonly>
            </div>
            <div class="form-group">
              <label>Matricule Fiscal</label>
              <input type="text" name="mf" value="<?= esc($l['mf']) ?>" class="edit-field" readonly>
            </div>
            <div class="form-group">
              <label>Montant mensuel (TND)</label>
              <input type="number" name="montant_mensuel" step="0.001" min="0"
                     value="<?= (float)$l['montant_mensuel'] ?>" class="edit-field" readonly>
            </div>
            <div class="form-group">
              <label>Fréquence</label>
              <select name="frequence_paiement" class="edit-field" disabled>
                <?php foreach (['mois'=>'Mensuel','trimestre'=>'Trimestriel','semestre'=>'Semestriel'] as $v=>$lbl): ?>
                  <option value="<?= $v ?>" <?= $l['frequence_paiement']===$v?'selected':'' ?>><?= $lbl ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Retenue à la source</label>
              <select name="retenue" class="edit-field" disabled>
                <option value="0" <?= (int)$l['retenue']===0?'selected':'' ?>>0% — Particulier</option>
                <option value="10" <?= (int)$l['retenue']===10?'selected':'' ?>>10% — Société / MF</option>
              </select>
            </div>
            <?php
            $standardTypes = ['Magasin commercial', 'Appartement', 'Bureau professionnel', 'Garage', 'Entrepôt', 'Terrain'];
            $isAutre = !in_array($l['type_local'], $standardTypes);
            ?>
            <div class="form-group">
              <label>Type de local</label>
              <select id="edit_type_local_select" name="edit_type_local_select" class="edit-field" disabled onchange="handleEditTypeLocal(this)">
                <?php foreach ($standardTypes as $t): ?>
                  <option value="<?= $t ?>" <?= $l['type_local'] === $t ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
                <option value="Autre" <?= $isAutre ? 'selected' : '' ?>>Autre</option>
              </select>
              <input type="text" id="edit_type_local_autre" name="edit_type_local_autre"
                     placeholder="Précisez le type de local..."
                     value="<?= $isAutre ? esc($l['type_local']) : '' ?>"
                     style="display: <?= $isAutre ? 'block' : 'none' ?>; margin-top: .5rem;" class="edit-field" readonly>
              <!-- Hidden field that gets submitted -->
              <input type="hidden" id="edit_type_local" name="type_local" value="<?= esc($l['type_local']) ?>">
            </div>
            <div class="form-group">
              <label>Date début contrat</label>
              <input type="date" name="date_debut" value="<?= esc($l['date_debut']) ?>" class="edit-field" readonly>
            </div>
            <div class="form-group">
              <label>Augmentation (%)</label>
              <input type="text" name="augmentation_montant" id="loc_augmentation_montant"
                     value="<?= isset($l['augmentation_montant']) ? (int)$l['augmentation_montant'] : 0 ?>%" class="edit-field" readonly>
            </div>
            <div class="form-group">
              <label>Période d'augmentation</label>
              <select name="augmentation_periode" class="edit-field" disabled>
                <option value="annee" <?= $l['augmentation_periode']==='annee'?'selected':'' ?>>Chaque année</option>
                <option value="deux_ans" <?= $l['augmentation_periode']==='deux_ans'?'selected':'' ?>>Chaque 2 ans</option>
              </select>
            </div>
            <?php
            if ($l['date_derniere_augmentation']) {
                $lastA = new DateTime($l['date_derniere_augmentation']);
                $interval = $l['augmentation_periode'] === 'deux_ans' ? '+2 years' : '+1 year';
                $nextA = (clone $lastA)->modify($interval);
            }
            ?>
            <div class="form-group">
              <label>Prochaine augmentation</label>
              <input type="text" value="<?= isset($nextA) ? $nextA->format('d/m/Y') : '—' ?>" readonly
                style="<?= (isset($nextA) && $nextA <= new DateTime('+30 days') && $nextA >= new DateTime()) ? 'border-color:var(--yellow);background:#fffbe6' : '' ?>">
            </div>
            <div class="form-group full">
              <label>💰 مصاريف إضافية / Charges Additionnelles</label>
              <div id="charges-container-edit" style="display:flex; flex-direction:column; gap:0.25rem;">
                <!-- Rows populated dynamically by JS -->
              </div>
              <button type="button" class="btn btn-outline btn-sm" id="btn-add-charge-edit" disabled style="margin-top:0.5rem;">
                ➕ Ajouter une charge
              </button>
            </div>
            <div class="form-group full">
              <label>📝 Note</label>
              <textarea name="note" rows="3" class="edit-field" readonly><?= esc($l['note'] ?? '') ?></textarea>
            </div>

            <div class="form-group mb-3">
              <label for="contrat_file" style="font-weight:600; font-size:0.9rem;">📄 Importer le document du contrat (PDF, JPG, PNG)</label>
              <input type="file" name="contrat_file" id="contrat_file" class="edit-field" accept=".pdf,.jpg,.jpeg,.png" style="padding: 0.5rem;" disabled>
            </div>

            <!-- Save/Cancel buttons (hidden until edit mode) -->
            <div class="form-group full" id="edit-locataire-actions" style="display:none">
              <button type="submit" class="btn btn-orange">💾 Enregistrer les modifications</button>
              <button type="button" class="btn btn-outline" onclick="toggleEditLocataire(false)" style="margin-left:.5rem">Annuler</button>
            </div>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── Tab 3 : Nouveau locataire ────────────────────────────── -->
  <div class="tab-panel <?= $activeTab==='nouveau' ? 'active':'' ?>" id="tab-nouveau">
    <?php if ($locataireActif): ?>
      <div class="alert alert-warning mb-2">
        ⚠️ Ce local est actuellement occupé par <strong><?= esc($locataireActif['nom']) ?></strong>.
        L'ajout d'un nouveau locataire archivera automatiquement le locataire actuel.
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header"><h3>➕ Nouveau locataire</h3></div>
      <form method="POST" enctype="multipart/form-data" class="card-body" id="form-new-loc">
        <input type="hidden" name="action" value="add_locataire">
        <div class="form-grid">

          <div class="form-group">
            <label class="required" for="nom">Nom complet</label>
            <input type="text" id="nom" name="nom" required
                   placeholder="Prénom Nom / Raison sociale">
          </div>

          <div class="form-group">
            <label for="cin">CIN</label>
            <input type="text" id="cin" name="cin" placeholder="08123456">
          </div>

          <div class="form-group">
            <label for="num_tel">Téléphone</label>
            <input type="tel" id="num_tel" name="num_tel" placeholder="+216 XX XXX XXX">
          </div>

          <div class="form-group">
            <label for="mf">Matricule Fiscal (MF)</label>
            <input type="text" id="mf" name="mf" placeholder="Laisser vide si particulier">
            <div class="form-help">Si renseigné → retenue 10% automatique</div>
          </div>

          <div class="form-group">
            <label class="required" for="montant_mensuel">Montant mensuel (TND)</label>
            <input type="number" id="montant_mensuel" name="montant_mensuel"
                   step="0.001" min="0" placeholder="0.000" required>
          </div>

          <div class="form-group">
            <label for="frequence_paiement">Fréquence de paiement</label>
            <select id="frequence_paiement" name="frequence_paiement">
              <option value="mois">Mensuel</option>
              <option value="trimestre">Trimestriel</option>
              <option value="semestre">Semestriel</option>
            </select>
          </div>

          <div class="form-group">
            <label for="retenue">Retenue à la source</label>
            <select id="retenue" name="retenue">
              <option value="0">0% — Particulier</option>
              <option value="10">10% — Société / MF</option>
            </select>
          </div>

          <div class="form-group">
            <label for="type_local">Type de local</label>
            <select id="type_local_select" name="type_local_select"
                    onchange="handleTypeLocal(this)">
              <option>Magasin commercial</option>
              <option>Appartement</option>
              <option>Bureau professionnel</option>
              <option>Garage</option>
              <option>Entrepôt</option>
              <option>Terrain</option>
              <option>Autre</option>
            </select>
            <input type="text" id="type_local_autre" name="type_local_autre"
                   placeholder="Précisez le type de local..."
                   style="display:none;margin-top:.5rem">
            <!-- Hidden field that gets submitted -->
            <input type="hidden" id="type_local" name="type_local" value="Magasin commercial">
          </div>

          <div class="form-group">
            <label for="augmentation_montant">Augmentation (%)</label>
                 <input type="number" id="augmentation_montant" name="augmentation_montant"
                   step="1" min="0" max="100" value="0" placeholder="ex: 5 (pour 5%)">
            <div class="form-help">Pourcentage d'augmentation appliqué à chaque période.</div>
          </div>

          <div class="form-group">
            <label for="augmentation_periode">Période d'augmentation</label>
            <select id="augmentation_periode" name="augmentation_periode">
              <option value="annee">Chaque année</option>
              <option value="deux_ans">Chaque 2 ans</option>
            </select>
          </div>

          <div class="form-group">
            <label class="required" for="date_debut">Date de début du contrat</label>
            <input type="date" id="date_debut" name="date_debut" required>
          </div>

          <div class="form-group full">
            <label>Contrat (PDF ou Word)</label>
            <div class="file-upload-area">
              <input type="file" name="contrat" accept=".pdf,.doc,.docx">
              <div style="font-size:2rem">📑</div>
              <div class="file-label" style="margin-top:.5rem;font-size:.85rem;color:var(--muted);">
                Glissez le contrat ou cliquez pour sélectionner<br>
                <small>PDF, DOC, DOCX — max 10 Mo</small>
              </div>
            </div>
          </div>
          <div class="form-group full">
            <label>💰 مصاريف إضافية / Charges Additionnelles</label>
            <div id="charges-container-new" style="display:flex; flex-direction:column; gap:0.25rem;">
              <!-- Rows populated dynamically by JS -->
            </div>
            <button type="button" class="btn btn-outline btn-sm" id="btn-add-charge-new" style="margin-top:0.5rem;">
              ➕ Ajouter une charge
            </button>
          </div>
          <div class="form-group full">
            <label for="note">📝 Note (rappel / observation)</label>
            <textarea id="note" name="note" rows="3"
                      placeholder="Ex: Loyer en retard, dossier incomplet..."></textarea>
            <div class="form-help">Si une note est saisie, un indicateur rouge apparaîtra sur le tableau de bord.</div>
          </div>
        </div>

        <div class="flex gap-3 mt-3">
          <button type="submit" class="btn btn-orange btn-lg">
            💾 Enregistrer le locataire
          </button>
          <button type="reset" class="btn btn-outline">Réinitialiser</button>
        </div>
      </form>
    </div>
  </div>
</div><!-- /.tab-container -->

<div class="mt-3">
  <a href="../locaux.php" class="btn btn-ghost">← Retour aux locaux</a>
</div>

<script>
function setTab(name) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.querySelector(`[data-tab="${name}"]`).classList.add('active');
  document.getElementById(`tab-${name}`).classList.add('active');
}

function toggleEditLocataire(enable = true) {
  const augInput = document.getElementById('loc_augmentation_montant');
  if (enable) {
    if (augInput) {
      augInput.value = augInput.value.replace('%', '');
      augInput.type = 'number';
      augInput.step = '1';
      augInput.min = '0';
      augInput.max = '100';
      augInput.readOnly = false;
      augInput.style.background = '';
      augInput.style.borderColor = '';
    }
  } else {
    const form = document.getElementById('form-edit-locataire');
    if (form) form.reset();
    if (augInput) {
      augInput.type = 'text';
      augInput.readOnly = true;
    }
    initEditTypeLocal();
  }

  document.querySelectorAll('.edit-field').forEach(el => {
    if (el === augInput) return;
    if (el.id === 'edit_type_local_autre') {
      const sel = document.getElementById('edit_type_local_select');
      el.readOnly = !enable || (sel && sel.value !== 'Autre');
    } else if (el.tagName === 'SELECT') {
      el.disabled = !enable;
    } else {
      el.readOnly = !enable;
    }
    el.style.background = enable ? '' : '';
    el.style.borderColor = enable ? '' : '';
  });
  // Ensure file inputs are enabled/disabled correctly
  document.querySelectorAll('.edit-field').forEach(el => {
    if (el.type === 'file') {
      el.disabled = !enable;
    }
  });

  // Toggle charge edit controls
  document.querySelectorAll('.charge-btn-edit').forEach(btn => {
    btn.disabled = !enable;
  });
  const btnAddCharge = document.getElementById('btn-add-charge-edit');
  if (btnAddCharge) {
    btnAddCharge.disabled = !enable;
  }

  document.getElementById('edit-locataire-actions').style.display = enable ? 'block' : 'none';
  document.getElementById('btn-edit-locataire').style.display = enable ? 'none' : '';
}

function handleTypeLocal(sel) {
  const autreInput = document.getElementById('type_local_autre');
  const hiddenField = document.getElementById('type_local');
  if (sel.value === 'Autre') {
    autreInput.style.display = 'block';
    autreInput.required = true;
    autreInput.addEventListener('input', () => {
      hiddenField.value = autreInput.value;
    });
    hiddenField.value = autreInput.value;
  } else {
    autreInput.style.display = 'none';
    autreInput.required = false;
    hiddenField.value = sel.value;
  }
}

function handleEditTypeLocal(sel) {
  const autreInput = document.getElementById('edit_type_local_autre');
  const hiddenField = document.getElementById('edit_type_local');
  if (sel.value === 'Autre') {
    autreInput.style.display = 'block';
    autreInput.required = true;
    autreInput.readOnly = false;
    autreInput.addEventListener('input', () => {
      hiddenField.value = autreInput.value;
    });
    hiddenField.value = autreInput.value;
  } else {
    autreInput.style.display = 'none';
    autreInput.required = false;
    autreInput.readOnly = true;
    hiddenField.value = sel.value;
  }
}

function initEditTypeLocal() {
  const sel = document.getElementById('edit_type_local_select');
  const autreInput = document.getElementById('edit_type_local_autre');
  const hiddenField = document.getElementById('edit_type_local');
  if (sel && autreInput && hiddenField) {
    if (sel.value === 'Autre') {
      autreInput.style.display = 'block';
      hiddenField.value = autreInput.value;
    } else {
      autreInput.style.display = 'none';
      hiddenField.value = sel.value;
    }
  }
}

// Auto-detect retenue from MF
const mfInput = document.getElementById('mf');
const retSel  = document.getElementById('retenue');
if (mfInput && retSel) {
  mfInput.addEventListener('input', () => {
    retSel.value = mfInput.value.trim() !== '' ? '10' : '0';
  });
}

function addChargeRow(containerId, label = '', amount = '', isReadOnly = false) {
  const container = document.getElementById(containerId);
  if (!container) return;

  const row = document.createElement('div');
  row.className = 'charge-row';
  row.style.display = 'flex';
  row.style.gap = '0.5rem';
  row.style.alignItems = 'center';
  row.style.marginBottom = '0.5rem';

  const lblInput = document.createElement('input');
  lblInput.type = 'text';
  lblInput.name = 'charge_labels[]';
  lblInput.className = 'edit-field';
  lblInput.placeholder = 'عنوان / Libellé (ex: Place Parking, Nettoyage...)';
  lblInput.value = label;
  lblInput.readOnly = isReadOnly;
  lblInput.style.flex = '1';

  const amtInput = document.createElement('input');
  amtInput.type = 'number';
  amtInput.name = 'charge_amounts[]';
  amtInput.className = 'edit-field';
  amtInput.step = '0.001';
  amtInput.min = '0';
  amtInput.placeholder = 'مبلغ / Montant (TND)';
  amtInput.value = amount;
  amtInput.readOnly = isReadOnly;
  amtInput.style.width = '180px';

  const removeBtn = document.createElement('button');
  removeBtn.type = 'button';
  removeBtn.className = 'btn btn-outline btn-icon charge-btn-edit';
  removeBtn.style.color = 'var(--red)';
  removeBtn.style.borderColor = 'var(--red)';
  removeBtn.style.padding = '0.2rem 0.6rem';
  removeBtn.textContent = '×';
  removeBtn.disabled = isReadOnly;
  removeBtn.onclick = () => row.remove();

  row.appendChild(lblInput);
  row.appendChild(amtInput);
  row.appendChild(removeBtn);

  container.appendChild(row);
}

// Init custom type fields
document.addEventListener('DOMContentLoaded', () => {
  const sel = document.getElementById('type_local_select');
  if (sel) {
    document.getElementById('type_local').value = sel.value;
    sel.addEventListener('change', () => handleTypeLocal(sel));
  }
  initEditTypeLocal();

  // Load existing charges for current tenant
  <?php if ($locataireActif): ?>
    const initialCharges = <?= $locataireActif['charges_additionnelles'] ?: '[]' ?>;
    initialCharges.forEach(c => {
      addChargeRow('charges-container-edit', c.label, c.amount, true);
    });
  <?php endif; ?>

  // Listeners for "Add charge" buttons
  const btnAddEdit = document.getElementById('btn-add-charge-edit');
  if (btnAddEdit) {
    btnAddEdit.onclick = () => addChargeRow('charges-container-edit', '', '', false);
  }

  const btnAddNew = document.getElementById('btn-add-charge-new');
  if (btnAddNew) {
    btnAddNew.onclick = () => addChargeRow('charges-container-new', '', '', false);
  }
});
</script>

<!-- ── Modal Modifier Local ─────────────────────────────────── -->
<div class="modal-overlay" id="modal-edit-local">
  <div class="modal">
    <div class="modal-header">
      <h3>✏️ Modifier le local</h3>
      <button class="btn btn-ghost btn-icon"
              onclick="document.getElementById('modal-edit-local').classList.remove('open')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit_local">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label class="required">Nom du local</label>
            <input type="text" name="nom_local" value="<?= esc($local['nom_local']) ?>" required>
          </div>
          <div class="form-group">
            <label class="required">Propriétaire</label>
            <input type="text" name="proprietaire" value="<?= esc($local['proprietaire']) ?>" required>
          </div>
          <div class="form-group">
            <label>CIN / MF Propriétaire</label>
            <input type="text" name="cin_mf_proprietaire" value="<?= esc($local['cin_mf_proprietaire']) ?>">
          </div>
          <div class="form-group full">
            <label class="required">Adresse complète</label>
            <textarea name="adresse" rows="2" required><?= esc($local['adresse']) ?></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline"
                onclick="document.getElementById('modal-edit-local').classList.remove('open')">Annuler</button>
        <button type="submit" class="btn btn-orange">💾 Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<?php
$extraScripts = [];
include __DIR__ . '/../modules/layout_footer.php';
?>
