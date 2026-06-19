<?php
// modules/recu_format_new.php — Reçu format جديد (nouvelle mise en page)
require_once __DIR__ . '/../config/db.php';

$pdo = getPDO();
$id = (int) ($_GET['id'] ?? 0);

$moisParam = $_GET['mois'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $moisParam))
  $moisParam = date('Y-m');
[$year, $month] = array_map('intval', explode('-', $moisParam));

if (!$id)
  die('ID locataire requis.');

$st = $pdo->prepare(
  "SELECT l.*, loc.nom_local, loc.adresse, loc.proprietaire, loc.cin_mf_proprietaire
     FROM locataires l JOIN locaux loc ON l.local_id = loc.id
     WHERE l.id = ?"
);
$st->execute([$id]);
$l = $st->fetch();
if (!$l)
  die('Locataire introuvable.');

// ── Variables mappées (mêmes noms que le spec) ──────────────────
$tenant_name = htmlspecialchars($l['nom']);
$local_type = htmlspecialchars($l['type_local']);
$address = htmlspecialchars($l['adresse']);
$owner_name = htmlspecialchars($l['proprietaire']);
$owner_cin = htmlspecialchars($l['cin_mf_proprietaire']);

// ── Calculs ─────────────────────────────────────────────────────
switch ($l['frequence_paiement']) {
  case 'trimestre':
    $mult = 3;
    break;
  case 'semestre':
    $mult = 6;
    break;
  default:
    $mult = 1;
    break;
}
$gross_amount = round((float) $l['montant_mensuel'] * $mult, 3);
$discount_pct = (int) $l['retenue'];
$discount_amount = round($gross_amount * $discount_pct / 100, 3);
$syndic = 0.000;

// Extra charges parsing
$extra_charges = json_decode($l['charges_additionnelles'] ?? '[]', true) ?: [];
$sum_charges = 0.0;
foreach ($extra_charges as $ec) {
  $sum_charges += (float)($ec['amount'] ?? 0) * $mult;
}
$net_amount = round($gross_amount - $discount_amount - $syndic + $sum_charges, 3);

// ── Période ─────────────────────────────────────────────────────
$months_ar_tbl = [
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

switch ($l['frequence_paiement']) {
  case 'mois':
    $debut = new DateTime(sprintf('%04d-%02d-01', $year, $month));
    $fin = (clone $debut)->modify('last day of this month');
    break;
  case 'trimestre':
    $debut = new DateTime($l['date_debut']);
    while (
      (int) $debut->format('Y') < $year ||
      ((int) $debut->format('Y') === $year && (int) $debut->format('m') < $month)
    ) {
      $debut->modify('+3 months');
    }
    $fin = (clone $debut)->modify('+3 months -1 day');
    break;
  case 'semestre':
    $debut = new DateTime($l['date_debut']);
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

function dayAr(int $day, DateTime $dt): string
{
  $last = (int) (clone $dt)->modify('last day of this month')->format('d');
  if ($day === 1)
    return 'غرة';
  if ($day === $last)
    return 'آخر';
  return (string) $day;
}

$period_start = dayAr((int) $debut->format('d'), $debut) . ' ' . $months_ar_tbl[(int) $debut->format('m')] . ' ' . $debut->format('Y');
$period_end = dayAr((int) $fin->format('d'), $fin) . ' ' . $months_ar_tbl[(int) $fin->format('m')] . ' ' . $fin->format('Y');
$emission_date = date('d/m/Y');

// ── Augmentation ────────────────────────────────────────────────
$last_raise = htmlspecialchars($l['date_derniere_augmentation'] ?? '—');
$next_due = '—';
if ($l['date_derniere_augmentation']) {
  $lastA = new DateTime($l['date_derniere_augmentation']);
  $next_due = (clone $lastA)->modify($l['augmentation_periode'] === 'deux_ans' ? '+2 years' : '+1 year')->format('d/m/Y');
}
$aug_pct = number_format((float) $l['augmentation_montant'], 0);
$aug_period = $l['augmentation_periode'] === 'deux_ans' ? 'سنتين' : 'سنة';

// ── Formatage montants ──────────────────────────────────────────
function fmtTND(float $v): string
{
  return number_format($v, 3, ',', ' ') . ' د.ت';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>وصل كراء - <?= $tenant_name ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Amiri:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">

  <style>
    /* ============================================================
       recu_format_new — وصل كراء (format جديد)
       Police : Amiri  •  Direction : RTL  •  Noir sur blanc
       Impression A4 ready
       ============================================================ */

    *,
    *::before,
    *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Amiri', 'Traditional Arabic', 'Noto Naskh Arabic', serif;
      background: #e8ecf0;
      color: #000;
      direction: rtl;
      padding: 20px 12px 48px;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }

    /* ── Boutons action (masqués à l'impression) ───────────────── */
    .no-print {
      display: flex;
      gap: 10px;
      justify-content: center;
      margin-bottom: 20px;
    }

    .no-print button {
      font-family: 'Amiri', serif;
      font-size: 16px;
      font-weight: 700;
      padding: 10px 28px;
      border: 2px solid #000;
      border-radius: 4px;
      cursor: pointer;
      background: #fff;
      color: #000;
      transition: background .15s ease;
    }

    .no-print button:hover {
      background: #000;
      color: #fff;
    }

    .btn-print {
      background: #000 !important;
      color: #fff !important;
    }

    .btn-print:hover {
      background: #333 !important;
    }

    /* ── Conteneur principal ────────────────────────────────────── */
    .receipt {
      max-width: 700px;
      margin: 0 auto;
      background: #fff;
      padding: 16px;
    }

    /* ── Cadre extérieur (double bordure) ───────────────────────── */
    .receipt-outer-frame {
      border: 3px double #000;
      padding: 6px;
    }

    /* ── Titre centré ──────────────────────────────────────────── */
    .receipt-title {
      text-align: center;
      font-size: 40px;
      font-weight: 700;
      font-family: 'Amiri', serif;
      padding: 14px 0 12px;
      letter-spacing: 0.06em;
      color: #000;
    }

    /* ── Cadre intérieur (contenu) ──────────────────────────────── */
    .receipt-inner-frame {
      border: 2px solid #000;
    }

    /* ── Lignes du corps ───────────────────────────────────────── */
    .receipt-row {
      display: flex;
      flex-direction: row-reverse;
      justify-content: flex-start;
      align-items: baseline;
      padding: 8px 12px;
      border-bottom: 1px solid #000;
      font-size: 18px;
      line-height: 1.8;
    }

    .receipt-row:last-child {
      border-bottom: none;
    }

    .receipt-row .label {
      font-weight: 700;
      white-space: nowrap;
      flex-shrink: 0;
      color: #000;
    }

    .receipt-row .value {
      flex: 1;
      text-align: right;
      direction: rtl;
      font-size: 19px;
      color: #000;
      padding-right: 8px;
    }

    .receipt-row .value.bold {
      font-weight: 700;
    }

    /* Ligne période (deux dates sur une ligne) */
    .receipt-row .value .period-separator {
      font-weight: 700;
      margin: 0 6px;
    }

    /* ── Bande date (tونس في) ──────────────────────────────────── */
    .receipt-date-band {
      display: none !important;
      text-align: center;
      font-size: 20px;
      font-weight: 700;
      padding: 10px 0;
      border-top: 2px solid #000;
      border-bottom: 2px solid #000;
      color: #000;
    }

    /* ── Section basse : signature + calculs ────────────────────── */
    .receipt-bottom {
      display: flex;
      border: 2px solid #000;
      border-top: none;
      min-height: 160px;
    }

    /* ── Moitié gauche (Signature) ──────────────────────────────── */
    .receipt-signature {
      flex: 1;
      padding: 14px 16px;
      display: flex;
      flex-direction: column;
      position: relative;
    }

    .sig-header {
      font-size: 17px;
      font-weight: 700;
      color: #000;
      margin-bottom: 12px;
      line-height: 1.7;
    }

    .sig-header .owner-detail {
      font-weight: 400;
    }

    .signature-space {
      flex: 1;
      min-height: 60px;
    }

    .sig-date {
      margin-top: auto;
      text-align: left;
      font-size: 15px;
      font-weight: 700;
      color: #000;
    }

    /* ── Séparateur vertical ────────────────────────────────────── */
    .receipt-bottom-divider {
      width: 2px;
      background: #000;
      flex-shrink: 0;
    }

    /* ── Moitié droite (Calculs) ────────────────────────────────── */
    .receipt-calc {
      flex: 1;
      padding: 14px 16px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .calc-row {
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      padding: 5px 0;
      font-size: 16px;
      color: #000;
    }

    .calc-row .calc-label {
      font-weight: 600;
      white-space: nowrap;
    }

    .calc-row .calc-amount {
      font-weight: 600;
      text-align: left;
      direction: ltr;
      unicode-bidi: embed;
      white-space: nowrap;
      padding-right: 4px;
    }

    .calc-dotted-separator {
      border: none;
      border-top: 2px dotted #000;
      margin: 6px 0;
    }

    .calc-row.calc-total {
      font-size: 19px;
      font-weight: 800;
    }

    .calc-row.calc-total .calc-label {
      font-weight: 800;
    }

    .calc-row.calc-total .calc-amount {
      font-weight: 800;
      font-size: 19px;
    }

    /* ── Montant en lettres ─────────────────────────────────────── */
    .receipt-amount-words {
      text-align: center;
      font-size: 15px;
      font-weight: 700;
      font-style: italic;
      padding: 8px 12px;
      border: 2px solid #000;
      border-top: none;
      color: #000;
      line-height: 1.6;
    }

    /* ── Pied de page (rappel augmentation) ─────────────────────── */
    .receipt-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 13px;
      font-weight: 600;
      padding: 8px 12px;
      border: 2px solid #000;
      border-top: none;
      color: #000;
      flex-wrap: wrap;
      gap: 4px;
    }

    .receipt-footer span {
      white-space: nowrap;
    }

    .footer-sep {
      color: #000;
      margin: 0 2px;
    }

    /* ── Impression ─────────────────────────────────────────────── */
    @media print {
      body {
        background: #fff !important;
        padding: 0 !important;
      }

      .no-print {
        display: none !important;
      }

      html, body {
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
      }

      .receipt {
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
      }

      @page {
        size: A5 portrait;
        margin: 0 !important;
      }
    }
  </style>
</head>

<body>

  <!-- Boutons (masqués à l'impression) -->
  <div class="no-print">
    <button class="btn-print" onclick="window.print()">🖨️ طباعة الوصل</button>
    <button class="btn-close" onclick="window.close()">✕ إغلاق</button>
  </div>

  <div class="receipt">
    <!-- ① Cadre extérieur (double bordure) -->
    <div class="receipt-outer-frame">

      <!-- ② Titre centré -->
      <h1 class="receipt-title">وصل كراء</h1>

      <!-- ③ Cadre intérieur avec lignes -->
      <div class="receipt-inner-frame">
        <div class="receipt-row">
          <span class="label">استلمت من :</span>
          <span class="value"><?= $tenant_name ?></span>
        </div>
        <div class="receipt-row">
          <span class="label">مبلغ قدره :</span>
          <span class="value bold" id="lettres_body">—</span>
        </div>
        <div class="receipt-row">
          <span class="label">معلوم كراء محل :</span>
          <span class="value"><?= $local_type ?></span>
        </div>
        <div class="receipt-row">
          <span class="label">الكائن به :</span>
          <span class="value"><?= $address ?></span>
        </div>
        <div class="receipt-row">
          <span class="label">الفترة من :</span>
          <span class="value">
            <?= $period_start ?>
            <span class="period-separator">إلى :</span>
            <?= $period_end ?>
          </span>
        </div>
      </div>

      <!-- ⑤ Section basse : Signature + Calculs -->
      
      <div class="receipt-bottom">

        <!-- Moitié gauche : Signature -->
        <div class="receipt-signature">
          <div class="sig-header">
            إمضاء المؤجر :
            <span class="owner-detail"><?= $owner_name ?> — <?= $owner_cin ?></span>
          </div>
          <div class="signature-space"></div>
          <div class="sig-date">تونس في : <?= $emission_date ?></div>
        </div>

        <!-- Bordure verticale -->
        <div class="receipt-bottom-divider"></div>

        <!-- Moitié droite : Calculs -->
        <div class="receipt-calc">
          <div class="calc-row">
            <span class="calc-label">المبلغ الخام :</span>
            <span class="calc-amount"><?= fmtTND($gross_amount) ?></span>
          </div>
          <?php if ($discount_pct > 0): ?>
            <div class="calc-row">
              <span class="calc-label">خصم من المورد ( <?= $discount_pct ?> % ) :</span>
              <span class="calc-amount">- <?= fmtTND($discount_amount) ?></span>
            </div>
          <?php endif; ?>
          <div class="calc-row">
            <span class="calc-label">سنديك :</span>
            <span class="calc-amount"><?= fmtTND($syndic) ?></span>
          </div>
          <?php foreach ($extra_charges as $ec): 
            $charge_val = (float)($ec['amount'] ?? 0) * $mult;
          ?>
            <div class="calc-row">
              <span class="calc-label"><?= esc($ec['label']) ?> :</span>
              <span class="calc-amount"><?= fmtTND($charge_val) ?></span>
            </div>
          <?php endforeach; ?>
          <hr class="calc-dotted-separator">
          <div class="calc-row calc-total">
            <span class="calc-label">الصافي المستحق :</span>
            <span class="calc-amount"><?= fmtTND($net_amount) ?></span>
          </div>
        </div>

      </div>

      <!-- Montant en lettres -->
      <div class="receipt-amount-words" id="lettres_footer">—</div>

      <!-- ⑥ Pied de page : rappel augmentation -->
      <?php if ((float) $l['augmentation_montant'] > 0): ?>
        <div class="receipt-footer">
          <span>تذكير : الزيادة <?= $aug_pct ?>% كل <?= $aug_period ?></span>
          <span class="footer-sep">|</span>
          <span>آخر زيادة : <?= $last_raise ?></span>
          <span class="footer-sep">|</span>
          <span>القادمة : <?= $next_due ?></span>
        </div>
      <?php endif; ?>

    </div><!-- /receipt-outer-frame -->
  </div><!-- /receipt -->

  <!-- Réutilise le moteur de conversion montant → lettres arabes existant -->
  <script src="../assets/js/recu_calc.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const net = <?= $net_amount ?>;
      const txt = numberToArabicWords(net);
      document.getElementById('lettres_body').textContent = txt;
      document.getElementById('lettres_footer').textContent = txt;
    });
  </script>
</body>

</html>