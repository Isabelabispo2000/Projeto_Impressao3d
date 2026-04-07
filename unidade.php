<?php
declare(strict_types=1);

$unitId = (int) ($_GET['id'] ?? 0);
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Fila da Unidade | Octopus</title>
  <meta name="robots" content="noindex,nofollow">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="public-unit.css">
</head>
<body data-unit-id="<?= $unitId ?>">
  <main class="public-app">
    <section class="public-shell">
      <header class="public-hero">
        <div class="hero-copy">
          <p class="hero-kicker">Fila Publica da Unidade</p>
          <h1 id="unit-name">Carregando fila...</h1>
          <p class="hero-sub" id="unit-subtitle">Aguarde enquanto buscamos os pedidos ativos desta unidade.</p>
        </div>
        <div class="hero-meta">
          <div class="hero-pill">
            <span class="hero-pill-label">Atualizacao</span>
            <strong id="last-updated">--:--</strong>
          </div>
          <div class="hero-pill hero-pill-soft">
            <span class="hero-pill-label">Refresh</span>
            <strong>Automatico</strong>
          </div>
        </div>
      </header>

      <section class="queue-panel">
        <div class="queue-panel-head">
          <div>
            <p class="panel-kicker">Impressao 3D</p>
            <h2>Fila da unidade</h2>
          </div>
          <div class="queue-actions">
            <a class="back-button" href="../unidades">Voltar</a>
            <button class="refresh-button" type="button" id="refresh-button">Atualizar agora</button>
          </div>
        </div>

        <div class="queue-state" id="queue-state">Sincronizando dados da fila...</div>
        <div class="queue-list" id="queue-list" aria-live="polite"></div>
      </section>
    </section>
  </main>

  <script src="public-unit.js" defer></script>
</body>
</html>
