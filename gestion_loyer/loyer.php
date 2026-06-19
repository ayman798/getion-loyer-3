<?php
// loyer.php — Génération des reçus mensuels
require_once __DIR__ . '/config/db.php';

$pageTitle = 'Loyer / Reçus';
$activeNav = 'loyer';
$baseUrl = '';
$extraScripts = ['assets/js/recu_calc.js'];

$pdo = getPDO();

// ── Navigation mois ───────────────────────────────────────────
$moisParam = $_GET['mois'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $moisParam)) {
  $moisParam = date('Y-m');
}
[$year, $month] = array_map('intval', explode('-', $moisParam));

// Mois précédent / suivant
$prevDt = new DateTime("$year-$month-01");
$prevDt->modify('-1 month');
$nextDt = new DateTime("$year-$month-01");
$nextDt->modify('+1 month');
$prevMois = $prevDt->format('Y-m');
$nextMois = $nextDt->format('Y-m');

// Noms des mois en arabe et français
$moisArabe = [
  1 => 'جانفي',
  2 => 'فيفري',
  3 => 'مارس',
  4 => 'أفريل',
  5 => 'ماي',
  6 => 'جوان',
  7 => 'جويلية',
  8 => 'أوت',
  9 => 'سبتمبر',
  10 => 'أكتوبر',
  11 => 'نوفمبر',
  12 => 'ديسمبر'
];
$moisFr = [
  1 => 'Janvier',
  2 => 'Février',
  3 => 'Mars',
  4 => 'Avril',
  5 => 'Mai',
  6 => 'Juin',
  7 => 'Juillet',
  8 => 'Août',
  9 => 'Septembre',
  10 => 'Octobre',
  11 => 'Novembre',
  12 => 'Décembre'
];

// ── Fonctions métier ──────────────────────────────────────────
function isRentDueThisMonth(array $loc, int $year, int $month): bool
{
  $dateDebut = new DateTime($loc['date_debut']);
  $debutYear = (int) $dateDebut->format('Y');
  $debutMonth = (int) $dateDebut->format('m');
  $monthsElapsed = ($year - $debutYear) * 12 + ($month - $debutMonth);
  if ($monthsElapsed < 0)
    return false;
  switch ($loc['frequence_paiement']) {
    case 'trimestre':
      $period = 3;
      break;
    case 'semestre':
      $period = 6;
      break;
    default:
      $period = 1;
      break;
  }
  return $monthsElapsed % $period === 0;
}

function dayToAr(int $day, DateTime $date, array $months_ar): string
{
  $lastDay = (int) (clone $date)->modify('last day of this month')->format('d');
  if ($day === 1)
    return 'غرة';
  if ($day === $lastDay)
    return 'آخر';
  return (string) $day;
}

function getPeriodeDates(array $loc, int $year, int $month): array
{
  $months_ar = [
    1 => 'جانفي',
    2 => 'فيفري',
    3 => 'مارس',
    4 => 'أفريل',
    5 => 'ماي',
    6 => 'جوان',
    7 => 'جويلية',
    8 => 'أوت',
    9 => 'سبتمبر',
    10 => 'أكتوبر',
    11 => 'نوفمبر',
    12 => 'ديسمبر'
  ];

  switch ($loc['frequence_paiement']) {
    case 'mois':
      $debut = new DateTime(sprintf('%04d-%02d-01', $year, $month));
      $fin = (clone $debut)->modify('last day of this month');
      break;

    case 'trimestre':
      $debut = new DateTime($loc['date_debut']);
      while (
        (int) $debut->format('Y') < $year ||
        ((int) $debut->format('Y') === $year && (int) $debut->format('m') < $month)
      ) {
        $debut->modify('+3 months');
      }
      $fin = (clone $debut)->modify('+3 months -1 day');
      break;

    case 'semestre':
      $debut = new DateTime($loc['date_debut']);
      while (
        (int) $debut->format('Y') < $year ||
        ((int) $debut->format('Y') === $year && (int) $debut->format('m') < $month)
      ) {
        $debut->modify('+6 months');
      }
      $fin = (clone $debut)->modify('+6 months -1 day');
      break;

    default:
      $debut = new DateTime(sprintf('%04d-%02d-01', $year, $month));
      $fin = (clone $debut)->modify('last day of this month');
  }

  $dDay = (int) $debut->format('d');
  $fDay = (int) $fin->format('d');
  $debutAr = dayToAr($dDay, $debut, $months_ar) . ' ' . $months_ar[(int) $debut->format('m')] . ' ' . $debut->format('Y');
  $finAr = dayToAr($fDay, $fin, $months_ar) . ' ' . $months_ar[(int) $fin->format('m')] . ' ' . $fin->format('Y');

  return [
    'debut' => $debut->format('Y-m-d'),
    'fin' => $fin->format('Y-m-d'),
    'debut_ar' => $debutAr,
    'fin_ar' => $finAr,
  ];
}

function getMultiplicateur(string $freq): int
{
  switch ($freq) {
    case 'trimestre':
      return 3;
    case 'semestre':
      return 6;
    default:
      return 1;
  }
}

function getProchAug(array $loc): ?string
{
  if (empty($loc['date_derniere_augmentation']))
    return null;
  $last = new DateTime($loc['date_derniere_augmentation']);
  $intv = $loc['augmentation_periode'] === 'deux_ans' ? '+2 years' : '+1 year';
  return (clone $last)->modify($intv)->format('d/m/Y');
}

// ── Récupérer tous les locataires actifs ──────────────────────
$tous = $pdo->query(
  "SELECT l.*, loc.nom_local, loc.adresse, loc.proprietaire, loc.cin_mf_proprietaire
     FROM locataires l
     JOIN locaux loc ON l.local_id = loc.id
     WHERE l.actif = 1
     ORDER BY l.nom ASC"
)->fetchAll();

$locatairesDuMois = array_filter($tous, function ($l) use ($year, $month) {
  return isRentDueThisMonth($l, $year, $month);
});
$locatairesDuMois = array_values($locatairesDuMois);

// ── Affichage reçu(s) demandé(s) ─────────────────────────────
$recuIds = [];
if (!empty($_GET['recu'])) {
  $raw = $_GET['recu'];
  if (is_array($raw)) {
    $recuIds = array_map('intval', $raw);
  } else {
    $recuIds = array_map('intval', array_filter(explode(',', (string) $raw)));
  }
  $recuIds = array_values(array_unique(array_filter($recuIds, fn($id) => $id > 0)));
}

$recuLocataires = [];
foreach ($recuIds as $recuId) {
  $found = null;
  foreach ($locatairesDuMois as $l) {
    if ((int) $l['id'] === $recuId) {
      $found = $l;
      break;
    }
  }
  // Si pas dans le mois, chercher quand même
  if (!$found) {
    $st = $pdo->prepare(
      "SELECT l.*, loc.nom_local, loc.adresse, loc.proprietaire, loc.cin_mf_proprietaire
             FROM locataires l JOIN locaux loc ON l.local_id = loc.id
             WHERE l.id = ?"
    );
    $st->execute([$recuId]);
    $found = $st->fetch() ?: null;
  }
  if ($found) {
    $recuLocataires[] = $found;
  }
}

include __DIR__ . '/modules/layout.php';

// ── Helper : badge fréquence ──────────────────────────────────
function freqBadge(string $f): string
{
  switch ($f) {
    case 'trimestre':
      return '<span class="badge badge-orange">🟠 Trimestriel</span>';
    case 'semestre':
      return '<span class="badge badge-purple">🟣 Semestriel</span>';
    default:
      return '<span class="badge badge-blue">🔵 Mensuel</span>';
  }
}
?>

<?php if (!empty($recuLocataires)): ?>
  <!-- ═══════════════════════════════════════════════════════════
       VUE REÇU INDIVIDUEL — FORMAT JDID (nouvelle mise en page)
  ════════════════════════════════════════════════════════════ -->
  <?php
  function fmtTND_inline(float $v): string
  {
    return number_format($v, 3, ',', ' ') . ' د.ت';
  }
  ?>

  <!-- Boutons action (masqués à l'impression) -->
  <div class="flex items-center justify-between mb-3 no-print">
    <a href="loyer.php?mois=<?= esc($moisParam) ?>" class="btn btn-outline">
      ← Retour aux reçus
    </a>
    <div class="flex gap-2">
      <button onclick="window.print()" class="btn btn-orange">
        🖨️ طباعة الوصل
      </button>
    </div>
  </div>

  <!-- ── CSS scopé pour le nouveau format ── -->
  <style>
    /* ============================================================
       recu_jdid — وصل كراء (format جديد)
       Police : Amiri  •  Direction : RTL  •  Noir sur blanc
       ============================================================ */
    .rj-receipt {
      width: 100%;
      max-width: 100%;
      margin: 0 auto;
      background: #fff;
      padding: 12px;
      font-family: 'Amiri', 'Traditional Arabic', 'Noto Naskh Arabic', serif;
      direction: rtl;
      text-align: right;
      color: #000;
      box-sizing: border-box;
      font-size: 16px;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    .rj-receipt, .rj-outer-frame,
    .rj-inner-frame,
    .rj-bottom,
    .rj-signature,
    .rj-calc { page-break-inside: avoid; }

    /* Cadre extérieur (double bordure) */
    .rj-outer-frame {
      border: 3px double #000;
      padding: 4px;
    }

    /* Titre centré */
    .rj-title {
      text-align: center;
      font-size: 34px;
      font-weight: 700;
      font-family: 'Amiri', serif;
      padding: 15px 0 10px;
      margin: 0 0 10px;
      color: #000;
      letter-spacing: 0.05em;
    }

    /* Cadre intérieur (contenu) */
    .rj-inner-frame {
      border: 2px solid #000;
    }

    /* Lignes du corps */
    .rj-row {
      display: grid;
      grid-template-columns: minmax(140px, auto) 1fr;
      gap: 0 8px;
      align-items: center;
      padding: 5px 8px;
      font-size: 14px;
      line-height: 1.55;
      direction: rtl;
    }
    .rj-row .rj-label {
      justify-self: end;
      font-weight: 700;
      white-space: nowrap;
      color: #000;
      text-align: right;
    }
    .rj-row .rj-value {
      justify-self: start;
      min-width: 0;
      display: flex;
      align-items: center;
      justify-content: flex-end;
      text-align: right;
      font-size: 14px;
      color: #000;
      padding-left: 4px;
    }
    .rj-row .rj-value-text {
      display: inline-block;
      direction: ltr;
      text-align: right;
      unicode-bidi: isolate;
      max-width: 100%;
      word-break: break-word;
    }
    .rj-row .rj-value.rj-bold .rj-value-text {
      font-weight: 700;
    }
    .rj-row .rj-period-sep {
      font-weight: 700;
      margin: 0 6px;
    }
    .rj-row .rj-value .rj-period-sep {
      font-weight: 700;
      margin: 0 6px;
    }

    /* Bande date (تونس في) */
    .rj-date-band {
      display: none !important;
      text-align: center;
      font-size: 18px;
      font-weight: 700;
      padding: 8px 0;
      border-top: 2px solid #000;
      border-bottom: 2px solid #000;
      color: #000;
    }

    /* Section basse : signature + calculs */
    .rj-bottom {
      display: flex;
      align-items: stretch;
      border: 2px solid #000;
      border-top: none;
      min-height: 175px;
    }

    /* Moitié signature (50%) */
    .rj-signature {
      flex: 1;
      padding: 14px 16px;
      display: flex;
      flex-direction: column;
      min-height: 100%;
      position: relative;
    }
    .rj-sig-header {
      font-size: 14px;
      font-weight: 700;
      color: #000;
      margin-bottom: 6px;
      line-height: 1.55;
    }
    .rj-sig-date {
      margin-top: auto;
      text-align: right;
      font-size: 14px;
      font-weight: 700;
      color: #000;
      position: absolute;
      right: 16px;
      bottom: 14px;
      left: auto;
      white-space: nowrap;
    }
    .rj-sig-header .rj-owner-detail {
      font-weight: 400;
    }
    .rj-signature-space {
      flex: 1;
      min-height: 60px;
    }

    /* Séparateur vertical */
    .rj-bottom-divider {
      width: 2px;
      background: #000;
      flex-shrink: 0;
    }

    /* Moitié calculs (50%) */
    .rj-calc {
      flex: 1;
      padding: 14px 16px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      min-height: 100%;
    }
    .rj-calc-row {
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      padding: 7px 0;
      font-size: 15px;
      color: #000;
    }
    .rj-calc-row .rj-calc-label {
      font-weight: 600;
      white-space: nowrap;
    }
    .rj-calc-row .rj-calc-amount {
      font-weight: 600;
      text-align: left;
      direction: ltr;
      unicode-bidi: embed;
      white-space: nowrap;
      padding-right: 4px;
    }
    .rj-dotted-sep {
      border: none;
      border-top: 2px dotted #000;
      margin: 6px 0;
    }
    .rj-calc-row.rj-total {
      font-size: 16px;
      font-weight: 800;
    }
    .rj-calc-row.rj-total .rj-calc-label {
      font-weight: 800;
    }
    .rj-calc-row.rj-total .rj-calc-amount {
      font-weight: 800;
      font-size: 16px;
    }

    /* Montant en lettres */
    .rj-amount-words {
      text-align: center;
      font-size: 14px;
      font-weight: 700;
      font-style: italic;
      padding: 6px 10px;
      border: 2px solid #000;
      border-top: none;
      color: #000;
      line-height: 1.55;
    }

    /* Pied de page (rappel augmentation) */
    .rj-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 12px;
      font-weight: 600;
      padding: 6px 10px;
      border: 2px solid #000;
      border-top: none;
      color: #000;
      flex-wrap: wrap;
      gap: 4px;
    }
    .rj-footer span {
      white-space: nowrap;
    }
    .rj-footer-sep {
      color: #000;
      margin: 0 2px;
    }

    /* Impression */
    @media print {
      html, body {
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
      }
      body {
        margin: 0 !important;
        padding: 0 !important;
      }
      .rj-receipt {
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
        margin: 0 auto !important;
        padding: 4px !important;
        page-break-inside: avoid;
      }
      .rj-outer-frame { page-break-inside: avoid; }
      @page {
        size: A5 portrait;
        margin: 0 !important;
      }
      .rj-receipt-wrapper {
        page-break-after: always !important;
        break-after: page !important;
      }
      .rj-receipt-wrapper:last-child {
        page-break-after: avoid !important;
        break-after: avoid !important;
      }
    }
  </style>

  <?php foreach ($recuLocataires as $l):
    $periode = getPeriodeDates($l, $year, $month);
    $mult = getMultiplicateur($l['frequence_paiement']);
    $brut = round((float) $l['montant_mensuel'] * $mult, 3);
    $taux = (int) $l['retenue'];
    $retenue = round($brut * $taux / 100, 3);
    $syndic = 0.0;

    // Extract and sum extra charges
    $extra_charges = json_decode($l['charges_additionnelles'] ?? '[]', true) ?: [];
    $sum_charges = 0.0;
    foreach ($extra_charges as $ec) {
      $sum_charges += (float)($ec['amount'] ?? 0) * $mult;
    }
    $net = round($brut - $retenue - $syndic + $sum_charges, 3);
    $prochAug = getProchAug($l);
    $dateEmission = date('d/m/Y');

    $aug_pct = number_format((float) $l['augmentation_montant'], 0);
    $aug_period = $l['augmentation_periode'] === 'deux_ans' ? 'سنتين' : 'سنة';
    $last_raise = esc($l['date_derniere_augmentation'] ?? '—');
  ?>

  <!-- ══════════════════════════════════════════════════════════════
       REÇU — FORMAT JDID (وصل كراء)
  ═══════════════════════════════════════════════════════════════ -->
  <div class="rj-receipt-wrapper">
  <div class="rj-receipt">
    <!-- ① Cadre extérieur (double bordure) -->
    <div class="rj-outer-frame">

      <!-- ② Titre centré -->
      <h1 class="rj-title">وصل كراء</h1>

      <!-- ③ Cadre intérieur avec lignes -->
      <div class="rj-inner-frame">
        <div class="rj-row">
          <span class="rj-label">استلمت من :</span>
          <span class="rj-value"><span class="rj-value-text"><?= esc($l['nom']) ?></span></span>
        </div>
        <div class="rj-row">
          <span class="rj-label">مبلغ قدره :</span>
          <span class="rj-value rj-bold"><span class="rj-value-text rj-letters-placeholder" data-net="<?= $net ?>">—</span></span>
        </div>
        <div class="rj-row">
          <span class="rj-label">معلوم كراء محل :</span>
          <span class="rj-value"><span class="rj-value-text"><?= esc($l['type_local']) ?></span></span>
        </div>
        <div class="rj-row">
          <span class="rj-label">الكائن به :</span>
          <span class="rj-value"><span class="rj-value-text"><?= esc($l['adresse']) ?></span></span>
        </div>
        <div class="rj-row">
          <span class="rj-label">الفترة من :</span>
          <span class="rj-value">
            <span class="rj-value-text"><?= esc($periode['debut_ar']) ?> <span class="rj-period-sep">إلى :</span> <?= esc($periode['fin_ar']) ?></span>
          </span>
        </div>
      </div>

      <!-- ⑤ Section basse : Signature + Calculs -->
      <div class="rj-bottom">

        <!-- Moitié droite : Calculs (renders right in RTL) -->
        <div class="rj-calc">
          <div class="rj-calc-row">
            <span class="rj-calc-label">المبلغ الخام :</span>
            <span class="rj-calc-amount"><?= fmtTND_inline($brut) ?></span>
          </div>
          <?php if ($taux > 0): ?>
            <div class="rj-calc-row">
              <span class="rj-calc-label">خصم من المورد ( <?= $taux ?> % ) :</span>
              <span class="rj-calc-amount">- <?= fmtTND_inline($retenue) ?></span>
            </div>
          <?php endif; ?>
          <div class="rj-calc-row" <?= empty($extra_charges) ? 'style="margin-bottom: 60px;"' : '' ?>>
            <span class="rj-calc-label">سنديك :</span>
            <span class="rj-calc-amount"><?= fmtTND_inline($syndic) ?></span>
          </div>
          <?php foreach ($extra_charges as $index => $ec): 
            $is_last = ($index === count($extra_charges) - 1);
            $charge_val = (float)($ec['amount'] ?? 0) * $mult;
          ?>
            <div class="rj-calc-row" <?= $is_last ? 'style="margin-bottom: 60px;"' : '' ?>>
              <span class="rj-calc-label"><?= esc($ec['label']) ?> :</span>
              <span class="rj-calc-amount"><?= fmtTND_inline($charge_val) ?></span>
            </div>
          <?php endforeach; ?>
          <hr class="rj-dotted-sep">
          <div class="rj-calc-row rj-total">
            <span class="rj-calc-label">الصافي المستحق :</span>
            <span class="rj-calc-amount"><?= fmtTND_inline($net) ?></span>
          </div>
        </div>

        <!-- Bordure verticale -->
        <div class="rj-bottom-divider"></div>

        <!-- Moitié gauche : Signature (renders left in RTL) -->
        <div class="rj-signature">
          <div class="rj-sig-header">
            إمضاء المؤجر :
            <span class="rj-owner-detail"><?= esc($l['proprietaire']) ?> — <?= esc($l['cin_mf_proprietaire']) ?></span>
          </div>
          <div class="rj-signature-space"></div>
          <div class="rj-sig-date">تونس في : <?= esc($dateEmission) ?></div>
        </div>

      </div>

      <!-- ⑥ Pied de page : rappel augmentation -->
      <?php if ((float)$l['augmentation_montant'] > 0): ?>
        <div class="rj-footer">
          <span>تذكير : الزيادة <?= $aug_pct ?>% كل <?= $aug_period ?></span>
          <span class="rj-footer-sep">|</span>
          <span>آخر زيادة : <?= $last_raise ?></span>
          <span class="rj-footer-sep">|</span>
          <span>القادمة : <?= esc($prochAug ?? '—') ?></span>
        </div>
      <?php endif; ?>

    </div><!-- /rj-outer-frame -->
  </div><!-- /rj-receipt -->
  </div><!-- /rj-receipt-wrapper -->

  <?php endforeach; ?>

  <script>
  document.addEventListener('DOMContentLoaded', () => {
    if (typeof numberToArabicWords === 'function') {
      document.querySelectorAll('.rj-letters-placeholder').forEach(el => {
        const net = parseFloat(el.dataset.net);
        el.textContent = numberToArabicWords(net);
      });
    }
    <?php if (isset($_GET['print']) && $_GET['print'] === '1'): ?>
    window.print();
    <?php endif; ?>
  });
  </script>

<?php else: ?>
    <!-- ═══════════════════════════════════════════════════════════
       LISTE DES REÇUS DU MOIS
  ════════════════════════════════════════════════════════════ -->

    <!-- En-tête avec navigation mois -->
    <div class="flex items-center justify-between mb-3">
      <div>
        <h1 style="font-size:1.2rem;font-weight:800;color:var(--navy);">
          💰 Reçus du mois
        </h1>
        <div style="font-family:var(--font-ar);font-size:1rem;color:var(--muted);direction:rtl;margin-top:.2rem">
          <?= $moisArabe[$month] ?>   <?= $year ?>
        </div>
      </div>

      <div class="flex items-center gap-3">
        <div class="month-nav">
          <a href="loyer.php?mois=<?= esc($prevMois) ?>" class="btn btn-outline btn-sm">‹ Précédent</a>
          <div class="month-label"><?= $moisFr[$month] ?>   <?= $year ?></div>
          <a href="loyer.php?mois=<?= esc($nextMois) ?>" class="btn btn-outline btn-sm">Suivant ›</a>
        </div>
        <?php if (!empty($locatairesDuMois)): ?>
            <button type="button" onclick="imprimerSelection()" class="btn btn-orange no-print">
              🖨️ Imprimer la sélection
            </button>
            
        <?php endif; ?>
      </div>
    </div>

    <!-- Compteur -->
    <div class="alert alert-info mb-3" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
      <span class="alert-icon">📋</span>
      <span>
        <strong><?= count($locatairesDuMois) ?> reçu(s)</strong> à générer pour
        <?= $moisFr[$month] ?>   <?= $year ?>
        <?php if (count($locatairesDuMois) === 0): ?>
            — Aucun loyer dû ce mois (vérifiez les dates et fréquences).
        <?php endif; ?>
      </span>
      <?php if (!empty($locatairesDuMois)): ?>
        <label style="margin-left:auto;display:flex;align-items:center;gap:.4rem;cursor:pointer;white-space:nowrap;">
          <input type="checkbox" id="select-all-recus" onchange="toggleSelectAll(this)">
          <span>Tout sélectionner</span>
        </label>
      <?php endif; ?>
    </div>

    <!-- Liste des reçus -->
    <div class="card">
      <?php foreach ($locatairesDuMois as $l):
        $mult = getMultiplicateur($l['frequence_paiement']);
        $brut = (float) $l['montant_mensuel'] * $mult;
        $taux = (int) $l['retenue'];
        $ret = $brut * $taux / 100;
        $net = $brut - $ret;
        $initiale = mb_strtoupper(mb_substr($l['nom'], 0, 1, 'UTF-8'), 'UTF-8');
        ?>
        <div class="recu-list-item">
          <div class="recu-list-left">
            <input type="checkbox" class="recu-checkbox" value="<?= (int) $l['id'] ?>">
            <div class="recu-list-avatar"><?= esc($initiale) ?></div>
            <div>
              <div class="recu-list-name"><?= esc($l['nom']) ?></div>
              <div class="recu-list-addr">
                <?= esc($l['nom_local']) ?> — <?= esc($l['adresse']) ?>
              </div>
              <div style="margin-top:.3rem">
                <?= freqBadge($l['frequence_paiement']) ?>
                <?php if ($taux > 0): ?>
                    <span class="badge badge-orange">Retenue <?= $taux ?>%</span>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="recu-list-right">
            <div class="recu-list-amount">
              <div class="font-mono" style="font-size:1rem;font-weight:800;color:var(--navy)">
                <?= formatTND($brut) ?> TND
              </div>
              <?php if ($taux > 0): ?>
                  <div style="font-size:.75rem;color:var(--muted)">
                    Net : <?= formatTND($net) ?> TND
                  </div>
              <?php endif; ?>
            </div>

            <a href="loyer.php?mois=<?= esc($moisParam) ?>&recu=<?= (int) $l['id'] ?>"
               class="btn btn-primary btn-sm no-print">
              👁 Voir le reçu
            </a>
            <a href="loyer.php?mois=<?= esc($moisParam) ?>&recu=<?= (int) $l['id'] ?>&print=1"
               class="btn btn-outline btn-sm no-print" target="_blank">
              🖨️ Imprimer
            </a>
          </div>
        </div>
      <?php endforeach; ?>

      <?php if (empty($locatairesDuMois)): ?>
          <div style="padding:3rem;text-align:center;color:var(--muted);">
            <div style="font-size:3rem;margin-bottom:1rem">📭</div>
            <div>Aucun loyer dû pour ce mois.</div>
            <div class="text-sm mt-1">Naviguez avec les boutons ‹ › pour voir d'autres mois.</div>
          </div>
      <?php endif; ?>
    </div>

    <script>
    function toggleSelectAll(master) {
      document.querySelectorAll('.recu-checkbox').forEach(cb => {
        cb.checked = master.checked;
      });
    }

    function imprimerSelection() {
      const checked = document.querySelectorAll('.recu-checkbox:checked');
      if (checked.length === 0) {
        alert('Veuillez sélectionner au moins un reçu.');
        return;
      }
      const ids = Array.from(checked).map(cb => cb.value).join(',');
      const mois = <?= json_encode($moisParam) ?>;
      window.open('loyer.php?mois=' + encodeURIComponent(mois) + '&recu=' + ids + '&print=1', '_blank');
    }
    </script>

<?php endif; // reçu individuel / liste ?>

<?php include __DIR__ . '/modules/layout_footer.php'; ?>
