<?php
// modules/recu_generate.php — Génération reçu standalone (impression directe)
require_once __DIR__ . '/../config/db.php';

$pdo = getPDO();
$id  = (int)($_GET['id'] ?? 0);

$moisParam = $_GET['mois'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $moisParam)) $moisParam = date('Y-m');
[$year, $month] = array_map('intval', explode('-', $moisParam));

if (!$id) die('ID locataire requis.');

$st = $pdo->prepare(
    "SELECT l.*, loc.nom_local, loc.adresse, loc.proprietaire, loc.cin_mf_proprietaire
     FROM locataires l JOIN locaux loc ON l.local_id = loc.id
     WHERE l.id = ?"
);
$st->execute([$id]);
$l = $st->fetch();
if (!$l) die('Locataire introuvable.');

// ── Calculs ───────────────────────────────────────────────────
switch ($l['frequence_paiement']) {
    case 'trimestre': $mult = 3; break;
    case 'semestre':  $mult = 6; break;
    default:          $mult = 1; break;
}
$brut  = round((float)$l['montant_mensuel'] * $mult, 3);
$taux  = (int)$l['retenue'];
$ret   = round($brut * $taux / 100, 3);
$syndic = 0.000; // emplacement réservé — modifiable si une charge syndic est ajoutée
$net   = round($brut - $ret - $syndic, 3);

// Période
$months_ar_tbl = [
    1=>'جانفي',2=>'فيفري',3=>'مارس',4=>'أفريل',5=>'ماي',6=>'جوان',
    7=>'جويلية',8=>'أوت',9=>'سبتمبر',10=>'أكتوبر',11=>'نوفمبر',12=>'ديسمبر'
];

switch ($l['frequence_paiement']) {
    case 'mois':
        $debut = new DateTime(sprintf('%04d-%02d-01', $year, $month));
        $fin   = (clone $debut)->modify('last day of this month');
        break;
    case 'trimestre':
        $debut = new DateTime($l['date_debut']);
        while ((int)$debut->format('Y') < $year ||
               ((int)$debut->format('Y') === $year && (int)$debut->format('m') < $month)) {
            $debut->modify('+3 months');
        }
        $fin = (clone $debut)->modify('+3 months -1 day');
        break;
    case 'semestre':
        $debut = new DateTime($l['date_debut']);
        while ((int)$debut->format('Y') < $year ||
               ((int)$debut->format('Y') === $year && (int)$debut->format('m') < $month)) {
            $debut->modify('+6 months');
        }
        $fin = (clone $debut)->modify('+6 months -1 day');
        break;
    default:
        $debut = new DateTime(sprintf('%04d-%02d-01', $year, $month));
        $fin   = (clone $debut)->modify('last day of this month');
}

function dayAr(int $day, DateTime $dt): string {
    $last = (int)(clone $dt)->modify('last day of this month')->format('d');
    if ($day === 1)    return 'غرة';
    if ($day === $last) return 'آخر';
    return (string)$day;
}

$debutAr = dayAr((int)$debut->format('d'), $debut) . ' ' . $months_ar_tbl[(int)$debut->format('m')] . ' ' . $debut->format('Y');
$finAr   = dayAr((int)$fin->format('d'), $fin)     . ' ' . $months_ar_tbl[(int)$fin->format('m')]   . ' ' . $fin->format('Y');

// Prochaine augmentation
$prochAug = null;
if ($l['date_derniere_augmentation']) {
    $lastA = new DateTime($l['date_derniere_augmentation']);
    $prochAug = (clone $lastA)->modify($l['augmentation_periode'] === 'deux_ans' ? '+2 years' : '+1 year')->format('d/m/Y');
}

// Titre dynamique selon le type de local (optionnel — garde "وصل كراء" par défaut)
$titre = 'وصل كراء';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>وصل كراء - <?= htmlspecialchars($l['nom']) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Amiri:ital,wght@0,400;0,700;1,400&family=IBM+Plex+Sans+Arabic:wght@400;600;700;800&family=IBM+Plex+Mono:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/recu.css">
</head>
<body>

<div class="no-print">
  <button class="btn-print" onclick="window.print()">🖨️ طباعة الوصل</button>
  <button class="btn-close" onclick="window.close()">إغلاق</button>
</div>

<div class="receipt">
  <div class="receipt-frame">

    <!-- TITRE -->
    <h1 class="receipt-title"><?= htmlspecialchars($titre) ?></h1>

    <!-- CORPS -->
    <div>
      <div class="receipt-row">
        <span class="label">: استلمت من</span>
        <span class="value"><?= htmlspecialchars($l['nom']) ?></span>
      </div>
      <div class="receipt-row">
        <span class="label">: مبلغ قدره</span>
        <span class="value" id="lettres_body">—</span>
      </div>
      <div class="receipt-row">
        <span class="label">: معلوم كراء</span>
        <span class="value"><?= htmlspecialchars($l['type_local']) ?></span>
      </div>
      <div class="receipt-row">
        <span class="label">: الكائن به</span>
        <span class="value"><?= htmlspecialchars($l['adresse']) ?></span>
      </div>
      <div class="receipt-row">
        <span class="label">: الفترة من</span>
        <span class="value">
          <?= htmlspecialchars($debutAr) ?> &nbsp;&nbsp; <strong>إلى :</strong> &nbsp; <?= htmlspecialchars($finAr) ?>
        </span>
      </div>
    </div>

    <!-- BAS : SIGNATURE + CALCUL -->
    <div class="receipt-bottom">
      <div class="receipt-signature">
        <div class="sig-title">
          إمضاء المؤجر : <span class="owner-name"><?= htmlspecialchars($l['proprietaire']) ?></span>
        </div>
        <div class="signature-line"></div>
        <div class="signature-line"></div>
        <div class="sig-date">تونس في : <?= date('d/m/Y') ?></div>
      </div>

      <div class="receipt-calc">
        <div class="calc-row">
          <span>: المبلغ الخام</span>
          <span class="calc-amount"><?= number_format($brut, 3, ',', ' ') ?> د.ت</span>
        </div>
        <?php if ($taux > 0): ?>
        <div class="calc-row">
          <span>: خصم من المورد (<?= $taux ?>%)</span>
          <span class="calc-amount">- <?= number_format($ret, 3, ',', ' ') ?> د.ت</span>
        </div>
        <?php endif; ?>
        <div class="calc-row">
          <span>: سنديك</span>
          <span class="calc-amount"><?= number_format($syndic, 3, ',', ' ') ?> د.ت</span>
        </div>
        <div class="calc-divider"></div>
        <div class="calc-row calc-total">
          <span>: الصافي المستحق</span>
          <span class="calc-amount"><?= number_format($net, 3, ',', ' ') ?> د.ت</span>
        </div>
      </div>
    </div>

    <!-- MONTANT EN LETTRES (rappel sous le tableau) -->
    <div class="receipt-lettres" id="lettres_footer">—</div>

    <?php if ((float)$l['augmentation_montant'] > 0): ?>
    <!-- RAPPEL AUGMENTATION -->
    <div class="receipt-reminder">
      تذكير : الزيادة <?= number_format((float)$l['augmentation_montant'], 0) ?>%
      كل <?= $l['augmentation_periode'] === 'deux_ans' ? 'سنتين' : 'سنة' ?>
      &nbsp;|&nbsp; آخر زيادة : <?= htmlspecialchars($l['date_derniere_augmentation'] ?? '—') ?>
      &nbsp;|&nbsp; القادمة : <?= htmlspecialchars($prochAug ?? '—') ?>
    </div>
    <?php endif; ?>

    <div class="receipt-footer-info">
      المؤجر : <?= htmlspecialchars($l['proprietaire']) ?> — <?= htmlspecialchars($l['cin_mf_proprietaire']) ?>
    </div>

  </div>
</div>

<script src="../assets/js/recu_calc.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const net = <?= $net ?>;
  const txt = numberToArabicWords(net);
  document.getElementById('lettres_body').textContent  = txt;
  document.getElementById('lettres_footer').textContent = txt;
});
</script>
</body>
</html>
