<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Unidades Publicas | Octopus</title>
  <meta name="robots" content="noindex,nofollow">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="public-unit.css">
</head>
<body>
  <a class="skip-link" href="#public-main">Pular para o conteudo principal</a>

  <main class="public-page" id="public-main">
    <section class="public-shell public-shell-compact">
      <div class="theme-floating public-theme-floating">
        <button id="theme-toggle-public" class="theme-toggle theme-toggle-icon" type="button" aria-label="Alternar tema" title="Alternar tema">
          <svg class="theme-icon theme-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
            <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"></path>
          </svg>
          <svg class="theme-icon theme-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
            <circle cx="12" cy="12" r="4"></circle>
            <path d="M12 2v2.5M12 19.5V22M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M2 12h2.5M19.5 12H22M4.9 19.1l1.8-1.8M17.3 6.7l1.8-1.8"></path>
          </svg>
        </button>
      </div>

      <section class="queue-panel public-flow" aria-labelledby="orders-title">
        <div class="queue-panel-head public-flow-head">
          <div class="public-toolbar">
            <div class="public-select-wrap">
              <label class="public-select-label" for="unit-select">Unidade</label>
              <select class="public-select" id="unit-select">
                <option value="">Selecione uma unidade</option>
              </select>
            </div>
            <div class="public-toolbar-meta">
              <span class="public-update-label">Ultima atualizacao: <strong id="last-updated">--:--</strong></span>
              <button class="refresh-icon-button" type="button" id="refresh-button" aria-label="Atualizar fila" title="Atualizar fila">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                  <path d="M21 12a9 9 0 1 1-2.64-6.36"></path>
                  <path d="M21 3v6h-6"></path>
                </svg>
              </button>
            </div>
          </div>
        </div>
        <div class="public-list-shell">
          <div class="public-table-wrap">
            <table class="public-table">
              <thead>
                <tr>
                  <th>Solicitante</th>
                  <th>Setor</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody id="orders-table-body"></tbody>
            </table>
          </div>
        </div>
      </section>
    </section>
  </main>

  <div vw class="enabled">
    <div vw-access-button class="active"></div>
    <div vw-plugin-wrapper>
      <div class="vw-plugin-top-wrapper"></div>
    </div>
  </div>

  <script src="public-unit.js" defer></script>
  <script src="https://vlibras.gov.br/app/vlibras-plugin.js"></script>
  <script>
    new window.VLibras.Widget('https://vlibras.gov.br/app');
  </script>
</body>
</html>
