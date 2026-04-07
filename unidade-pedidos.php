<?php
declare(strict_types=1);

$token = trim((string) ($_GET['token'] ?? ''));
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pedidos da Unidade | OctoView</title>
  <meta name="robots" content="noindex,nofollow">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="public-unit-orders.css">
</head>
<body data-unit-token="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
  <main class="orders-app">
    <section class="orders-shell">
      <header class="orders-hero">
        <div class="orders-hero-copy">
          <p class="orders-kicker">Acompanhamento Público</p>
          <h1 id="orders-unit-name">Carregando pedidos...</h1>
          <p class="orders-subtitle" id="orders-unit-subtitle">Esta página mostra os pedidos de impressão 3D vinculados à unidade.</p>
        </div>
        <div class="orders-hero-side">
          <div class="hero-chip">
            <span>Atualizado</span>
            <strong id="orders-last-updated">--:--</strong>
          </div>
          <button class="hero-refresh" type="button" id="orders-refresh-button">Atualizar agora</button>
        </div>
      </header>

      <section class="orders-board">
        <div class="orders-board-head">
          <div>
            <p class="orders-kicker">Pedidos da Unidade</p>
            <h2>Todos os pedidos públicos</h2>
          </div>
          <a class="queue-link" id="queue-link" href="#">Ver fila ativa</a>
        </div>

        <div class="orders-state" id="orders-state">Carregando pedidos da unidade...</div>
        <div class="orders-list" id="orders-list" aria-live="polite"></div>
      </section>
    </section>
  </main>

  <script src="public-unit-orders.js" defer></script>
</body>
</html>
