/* assets/js/recu_calc.js — Moteur de calcul du reçu arabe */

// ── Conversion chiffres → lettres arabes (TND) ────────────────
const ONES_AR = [
  '', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة',
  'ستة', 'سبعة', 'ثمانية', 'تسعة', 'عشرة',
  'أحد عشر', 'اثنا عشر', 'ثلاثة عشر', 'أربعة عشر', 'خمسة عشر',
  'ستة عشر', 'سبعة عشر', 'ثمانية عشر', 'تسعة عشر'
];

const TENS_AR = [
  '', '', 'عشرون', 'ثلاثون', 'أربعون', 'خمسون',
  'ستون', 'سبعون', 'ثمانون', 'تسعون'
];

const HUNDREDS_AR = [
  '', 'مائة', 'مئتان', 'ثلاثمائة', 'أربعمائة', 'خمسمائة',
  'ستمائة', 'سبعمائة', 'ثمانمائة', 'تسعمائة'
];

function threeDigitsToArabic(n) {
  if (n === 0) return '';
  let result = '';
  const h = Math.floor(n / 100);
  const remainder = n % 100;
  const t = Math.floor(remainder / 10);
  const o = remainder % 10;

  if (h > 0) result += HUNDREDS_AR[h];

  if (remainder > 0) {
    if (result) result += ' و';
    if (remainder < 20) {
      result += ' ' + ONES_AR[remainder];
    } else {
      if (o > 0) result += ' ' + ONES_AR[o] + ' و';
      result += ' ' + TENS_AR[t];
    }
  }
  return result.trim();
}

function numberToArabicWords(amount) {
  if (isNaN(amount) || amount < 0) return '---';

  // Split integer and millimes (3 decimal places)
  const rounded = Math.round(amount * 1000);
  const dinars  = Math.floor(rounded / 1000);
  const millimes = rounded % 1000;

  if (dinars === 0 && millimes === 0) return 'صفر دينار';

  let result = '';

  if (dinars > 0) {
    if (dinars === 1) {
      result = 'دينار واحد';
    } else if (dinars === 2) {
      result = 'ديناران';
    } else if (dinars < 1000) {
      result = threeDigitsToArabic(dinars) + ' دينار';
    } else if (dinars < 1000000) {
      const thousands = Math.floor(dinars / 1000);
      const rem       = dinars % 1000;
      if (thousands === 1) result = 'ألف';
      else if (thousands === 2) result = 'ألفان';
      else result = threeDigitsToArabic(thousands) + ' آلاف';
      if (rem > 0) result += ' و' + threeDigitsToArabic(rem);
      result += ' دينار';
    } else {
      const millions = Math.floor(dinars / 1000000);
      const rem      = dinars % 1000000;
      result = threeDigitsToArabic(millions) + ' مليون';
      if (rem > 0) result += ' و' + numberToArabicWords(rem / 1000).replace(' دينار','');
      result += ' دينار';
    }
  }

  if (millimes > 0) {
    const millStr = String(millimes).padStart(3, '0');
    if (result) result += ' و';
    result += millStr + ' مليم';
  }

  return result;
}

// ── Formatage TND ─────────────────────────────────────────────
function formatTND(val) {
  const n = parseFloat(val) || 0;
  return n.toFixed(3);
}

function parseNum(val) {
  return parseFloat(String(val).replace(/\s/g, '').replace(',', '.')) || 0;
}

// ── Recalcul principal ────────────────────────────────────────
function recalcRecu() {
  const brut     = parseNum(document.getElementById('inp_brut')?.value);
  const tauxRet  = parseNum(document.getElementById('inp_retenue_taux')?.value);
  const syndic   = parseNum(document.getElementById('inp_syndic')?.value);

  const retenue  = brut * (tauxRet / 100);
  const net      = brut - retenue + syndic;

  // Afficher retenue montant
  const elRetMt = document.getElementById('disp_retenue_mt');
  if (elRetMt) elRetMt.textContent = '- ' + formatTND(retenue) + ' د.ت';

  // Afficher net
  const elNet = document.getElementById('disp_net');
  if (elNet) elNet.textContent = formatTND(net) + ' د.ت';

  // Montant en lettres
  const elLettres = document.getElementById('disp_lettres');
  if (elLettres) elLettres.textContent = numberToArabicWords(net);

  // Champ caché pour sauvegarde
  const hidNet     = document.getElementById('h_montant_net');
  const hidRetenue = document.getElementById('h_retenue_montant');
  if (hidNet)     hidNet.value     = net.toFixed(3);
  if (hidRetenue) hidRetenue.value = retenue.toFixed(3);
}

// ── Bind events ───────────────────────────────────────────────
function initRecuCalc() {
  const ids = ['inp_brut', 'inp_retenue_taux', 'inp_syndic'];
  ids.forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', recalcRecu);
  });

  // Premier calcul
  recalcRecu();
}

document.addEventListener('DOMContentLoaded', initRecuCalc);
