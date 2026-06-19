<?php
// modules/layout.php — En-tête HTML partagé
// Usage : include __DIR__ . '/modules/layout.php'; avec $pageTitle et $activeNav définis
if (session_status() === PHP_SESSION_NONE) session_start();

$pageTitle  = $pageTitle  ?? 'Gestion Locative';
$activeNav  = $activeNav  ?? 'dashboard';
$breadcrumb = $breadcrumb ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= esc($pageTitle) ?> — إدارة الكراء</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="<?= $baseUrl ?? '' ?>assets/css/style.css">
</head>
<body>

<!-- ── Sidebar ──────────────────────────────────────────────── -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon">🏘️</div>
    <span class="logo-title">إدارة الكراء</span>
    <span class="logo-subtitle">Gestion Locative</span>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Navigation</div>

    <a href="<?= $baseUrl ?? '' ?>index.php"
       class="nav-item <?= $activeNav === 'dashboard' ? 'active' : '' ?>">
      <span class="nav-icon">🏠</span>
      Tableau de Bord
    </a>

    <a href="<?= $baseUrl ?? '' ?>locaux.php"
       class="nav-item <?= $activeNav === 'locaux' ? 'active' : '' ?>">
      <span class="nav-icon">🏢</span>
      Locaux
    </a>

    <a href="<?= $baseUrl ?? '' ?>loyer.php"
       class="nav-item <?= $activeNav === 'loyer' ? 'active' : '' ?>">
      <span class="nav-icon">💰</span>
      Loyer / Reçus
    </a>
  </nav>

  <div class="sidebar-footer">
    Système v1.0 · XAMPP Local
  </div>
</aside>

<!-- ── Main Wrapper ──────────────────────────────────────────── -->
<div class="main-wrapper">
  <header class="topbar">
    <div class="topbar-title">
      <?= esc($pageTitle) ?>
      <?php if ($breadcrumb): ?>
        <span class="breadcrumb">/ <?= esc($breadcrumb) ?></span>
      <?php endif; ?>
    </div>
    <div class="topbar-date" id="topbar-date"></div>
  </header>

  <main class="page-content">
<?php
// Helper : esc() doit être disponible
if (!function_exists('esc')) {
    function esc(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
if (!function_exists('formatTND')) {
    function formatTND(float $amount): string {
        return number_format($amount, 3, ',', ' ');
    }
}
