<?php
// modules/locataire_archive.php — Vue des archives (standalone)
require_once __DIR__ . '/../config/db.php';

$baseUrl   = '../';
$pageTitle = 'Archives Locataires';
$activeNav = 'locaux';

$pdo = getPDO();

$archives = $pdo->query(
    "SELECT ac.*, loc.nom_local, loc.adresse
     FROM archives_contrats ac
     JOIN locaux loc ON ac.local_id = loc.id
     ORDER BY ac.date_archive DESC"
)->fetchAll();

include __DIR__ . '/../modules/layout.php';
?>

<div class="flex items-center justify-between mb-3">
  <div>
    <h1 style="font-size:1.2rem;font-weight:800;color:var(--navy);">🗂️ Archives des contrats</h1>
    <p class="text-muted text-sm"><?= count($archives) ?> contrat(s) archivé(s)</p>
  </div>
  <a href="../locaux.php" class="btn btn-outline">← Retour aux locaux</a>
</div>

<div class="card">
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Local</th>
          <th>Locataire archivé</th>
          <th>CIN / MF</th>
          <th>Montant</th>
          <th>Fréquence</th>
          <th>Type local</th>
          <th>Date début</th>
          <th>Archivé le</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($archives as $arch):
          $d = json_decode($arch['locataire_data'], true) ?? [];
        ?>
        <tr>
          <td>
            <strong><?= esc($arch['nom_local']) ?></strong>
            <div class="muted"><?= esc($arch['adresse']) ?></div>
          </td>
          <td><strong><?= esc($d['nom'] ?? '—') ?></strong></td>
          <td>
            <?php if (!empty($d['cin'])): ?>
              CIN: <?= esc($d['cin']) ?><br>
            <?php endif; ?>
            <?php if (!empty($d['mf'])): ?>
              MF: <?= esc($d['mf']) ?>
            <?php else: ?>
              <span class="text-muted">Particulier</span>
            <?php endif; ?>
          </td>
          <td class="font-mono"><?= formatTND((float)($d['montant_mensuel'] ?? 0)) ?> TND</td>
          <td><?= esc($d['frequence_paiement'] ?? '—') ?></td>
          <td><?= esc($d['type_local'] ?? '—') ?></td>
          <td><?= esc($d['date_debut'] ?? '—') ?></td>
          <td class="text-muted"><?= esc(substr($arch['date_archive'], 0, 10)) ?></td>
        </tr>
        <?php endforeach; ?>

        <?php if (empty($archives)): ?>
        <tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--muted);">
          Aucune archive disponible.
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../modules/layout_footer.php'; ?>
