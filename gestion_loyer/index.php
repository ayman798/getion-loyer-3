<?php
// index.php — Tableau de Bord
require_once __DIR__ . '/config/db.php';

$pageTitle = 'Tableau de Bord';
$activeNav = 'dashboard';
$baseUrl   = '';

$pdo = getPDO();

// ── Stats ─────────────────────────────────────────────────────
$nbLocaux     = (int)$pdo->query("SELECT COUNT(*) FROM locaux")->fetchColumn();
$nbLocataires = (int)$pdo->query("SELECT COUNT(*) FROM locataires WHERE actif = 1")->fetchColumn();

// Total loyers du mois (mensuel + trimestriel dûs + semestriel dûs)
$year  = (int)date('Y');
$month = (int)date('m');

$allLocataires = $pdo->query("SELECT * FROM locataires WHERE actif = 1")->fetchAll();
$totalMois = 0.0;
$augAlerts  = [];

foreach ($allLocataires as $loc) {
    // Calcul loyer dû ce mois
    $dateDebut    = new DateTime($loc['date_debut']);
    $debutYear    = (int)$dateDebut->format('Y');
    $debutMonth   = (int)$dateDebut->format('m');
    $monthsElapsed = ($year - $debutYear) * 12 + ($month - $debutMonth);
    switch ($loc['frequence_paiement']) {
        case 'trimestre': $period = 3; break;
        case 'semestre':  $period = 6; break;
        default:          $period = 1; break;
    }
    if ($monthsElapsed >= 0 && $monthsElapsed % $period === 0) {
        $totalMois += (float)$loc['montant_mensuel'] * $period;
    }

    // Alertes augmentation
    if (!empty($loc['date_derniere_augmentation'])) {
        $lastAug = new DateTime($loc['date_derniere_augmentation']);
        $interval = $loc['augmentation_periode'] === 'deux_ans' ? '+2 years' : '+1 year';
        $nextAug  = (clone $lastAug)->modify($interval);
        $now      = new DateTime();
        $diff     = (int)$now->diff($nextAug)->days;
        if ($nextAug > $now && $diff <= 30) {
            $stmt = $pdo->prepare("SELECT nom_local FROM locaux WHERE id = ?");
            $stmt->execute([$loc['local_id']]);
            $localNom = $stmt->fetchColumn();
            $augAlerts[] = [
                'locataire' => $loc['nom'],
                'local'     => $localNom,
                'date'      => $nextAug->format('d/m/Y'),
                'pct'       => $loc['augmentation_montant'],
                'days'      => $diff,
            ];
        }
    }
}

// Vacants
$nbVacants = (int)$pdo->query(
    "SELECT COUNT(*) FROM locaux l WHERE NOT EXISTS
     (SELECT 1 FROM locataires lt WHERE lt.local_id = l.id AND lt.actif = 1)"
)->fetchColumn();

include __DIR__ . '/modules/layout.php';
?>

<!-- ── KPI Cards ─────────────────────────────────────────────── -->
<div class="stats-grid">
  <div class="stat-card blue">
    <div class="stat-icon blue">🏢</div>
    <div>
      <div class="stat-value"><?= $nbLocaux ?></div>
      <div class="stat-label">Total Locaux</div>
    </div>
  </div>

  <div class="stat-card orange">
    <div class="stat-icon orange">👥</div>
    <div>
      <div class="stat-value"><?= $nbLocataires ?></div>
      <div class="stat-label">Locataires actifs</div>
    </div>
  </div>

  <div class="stat-card teal">
    <div class="stat-icon teal">💵</div>
    <div>
      <div class="stat-value" style="font-size:1.3rem"><?= formatTND($totalMois) ?></div>
      <div class="stat-label">Loyers du mois (TND)</div>
    </div>
  </div>

  <div class="stat-card red">
    <div class="stat-icon red">🔑</div>
    <div>
      <div class="stat-value"><?= $nbVacants ?></div>
      <div class="stat-label">Locaux vacants</div>
    </div>
  </div>
</div>

<!-- ── Augmentation Alerts ───────────────────────────────────── -->
<?php if (!empty($augAlerts)): ?>
<div class="augmentation-alert mb-3">
  <div class="alert-title">⚠️ Augmentations prévues dans les 30 prochains jours</div>
  <div class="alert-items">
    <?php foreach ($augAlerts as $a): ?>
    <div class="alert-item">
      🔔 <strong><?= esc($a['locataire']) ?></strong> — <?= esc($a['local']) ?>
      · Augmentation de <strong><?= number_format((float)$a['pct'], 0) ?>%</strong>
      le <strong><?= esc($a['date']) ?></strong>
      <span class="badge badge-orange"><?= $a['days'] ?> jours</span>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- ── Recent Locaux ─────────────────────────────────────────── -->
<div class="card mb-3">
  <div class="card-header">
    <h2>🏢 Locaux récents</h2>
    <a href="locaux.php" class="btn btn-outline btn-sm">Voir tous</a>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Local</th>
          <th>Adresse</th>
          <th>Propriétaire</th>
          <th>Locataire actuel</th>
          <th>Statut</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $rows = $pdo->query(
            "SELECT l.*, 
                    lt.nom AS locataire_nom, lt.montant_mensuel, lt.frequence_paiement,
                    lt.note AS locataire_note
             FROM locaux l
             LEFT JOIN locataires lt ON lt.local_id = l.id AND lt.actif = 1
             ORDER BY l.id DESC LIMIT 10"
        )->fetchAll();

        foreach ($rows as $r):
            $vacant = empty($r['locataire_nom']);
        ?>
        <tr>
          <td><strong><?= esc($r['nom_local']) ?></strong></td>
          <td><?= esc($r['adresse']) ?></td>
          <td>
            <?= esc($r['proprietaire']) ?>
            <div class="muted"><?= esc($r['cin_mf_proprietaire']) ?></div>
          </td>
          <td>
            <?php if (!$vacant): ?>
              <?= esc($r['locataire_nom']) ?>
              <div class="muted font-mono"><?= formatTND((float)$r['montant_mensuel']) ?> TND</div>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($vacant): ?>
              <span class="badge badge-gray">Vacant</span>
            <?php else: ?>
              <span class="badge badge-green">● Occupé</span>
            <?php endif; ?>
          </td>
          <td style="display:flex;align-items:center;gap:.5rem">
            <a href="modules/local_detail.php?id=<?= (int)$r['id'] ?>"
               class="btn btn-outline btn-sm">Détail</a>
            <?php if (!empty($r['locataire_note'])): ?>
              <span class="note-dot" data-note="<?= esc($r['locataire_note']) ?>"
                    title="<?= esc($r['locataire_note']) ?>">●</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── Quick Links ───────────────────────────────────────────── -->
<div class="flex gap-3">
  <a href="locaux.php?action=add" class="btn btn-primary">
    ➕ Ajouter un local
  </a>
  <a href="loyer.php" class="btn btn-orange">
    🧾 Générer les reçus du mois
  </a>
</div>

<style>
.note-dot {
  display: inline-flex;
  width: 14px;
  height: 14px;
  background: #e74c3c;
  border-radius: 50%;
  cursor: pointer;
  position: relative;
  flex-shrink: 0;
  font-size: 0; /* hide the bullet character */
}
.note-dot::after {
  content: attr(data-note);
  display: none;
  position: absolute;
  right: 20px;
  top: 50%;
  transform: translateY(-50%);
  background: #1a3a5c;
  color: #fff;
  padding: .45rem .75rem;
  border-radius: 6px;
  font-size: .78rem;
  white-space: pre-wrap;
  max-width: 240px;
  z-index: 999;
  line-height: 1.5;
  box-shadow: 0 4px 12px rgba(0,0,0,.25);
  font-family: var(--font-ar, sans-serif);
  direction: rtl;
  text-align: right;
}
.note-dot:hover::after {
  display: block;
}
</style>
<?php include __DIR__ . '/modules/layout_footer.php'; ?>
