<?php
?>
<div class="kartu segera">
  <svg class="segera-wajah" viewBox="0 0 120 120" width="120" height="120" role="img" aria-label="Wajah tersenyum">
    <circle cx="60" cy="60" r="54" fill="#f6d86b" stroke="#c9a227" stroke-width="3"/>
    <ellipse cx="42" cy="50" rx="6" ry="8" fill="#3a2e0f"/>
    <ellipse cx="78" cy="50" rx="6" ry="8" fill="#3a2e0f"/>
    <path d="M34 72c6 16 46 16 52 0" fill="none" stroke="#3a2e0f" stroke-width="5" stroke-linecap="round"/>
    <circle cx="30" cy="68" r="6" fill="#f0a48a" opacity=".55"/>
    <circle cx="90" cy="68" r="6" fill="#f0a48a" opacity=".55"/>
  </svg>
  <h2><?= e($judul) ?></h2>
  <?= lencana('Coming soon', 'emas') ?>
  <p>Sistem ini sedang disiapkan dan akan segera hadir. Terima kasih atas kesabarannya.</p>
  <a class="btn" href="<?= e(url('dashboard')) ?>"><?= ikon('kembali', 15) ?> Kembali ke Beranda</a>
</div>
