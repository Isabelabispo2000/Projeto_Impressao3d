(function(){
  const API_URL = 'api.php';
  const THEME_KEY = 'octoview_theme';
  const root = document.documentElement;
  const unitSelect = document.getElementById('unit-select');
  const ordersTableBody = document.getElementById('orders-table-body');
  const updatedNode = document.getElementById('last-updated');
  const refreshButton = document.getElementById('refresh-button');
  const themeButton = document.getElementById('theme-toggle-public');

  let units = [];
  let selectedUnitId = 0;
  let refreshTimer = null;

  function applyStoredTheme(){
    const stored = localStorage.getItem(THEME_KEY);
    const theme = stored || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    root.setAttribute('data-theme', theme);
    syncThemeButtons(theme);
  }

  function toggleTheme(){
    const current = root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    const next = current === 'dark' ? 'light' : 'dark';
    root.setAttribute('data-theme', next);
    localStorage.setItem(THEME_KEY, next);
    syncThemeButtons(next);
  }

  function syncThemeButtons(theme){
    document.querySelectorAll('.theme-toggle').forEach((button) => {
      const label = theme === 'dark' ? 'Ativar tema claro' : 'Ativar tema escuro';
      button.setAttribute('aria-pressed', String(theme === 'dark'));
      button.setAttribute('aria-label', label);
      button.setAttribute('title', label);
    });
  }

  function escapeHtml(value){
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function formatUpdated(value){
    const date = value ? new Date(value) : new Date();
    return date.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
  }

  function readInitialUnitId(){
    const params = new URLSearchParams(window.location.search);
    const id = Number.parseInt(params.get('id') || '', 10);
    return Number.isFinite(id) && id > 0 ? id : 0;
  }

  function updateUrl(unitId){
    const url = new URL(window.location.href);
    if (unitId > 0) url.searchParams.set('id', String(unitId));
    else url.searchParams.delete('id');
    window.history.replaceState({}, '', url);
  }

  function statusClass(status){
    const normalized = String(status || '').toLowerCase();
    if (normalized.includes('produc') || normalized.includes('imprim')) return 'progress';
    if (normalized.includes('conclu')) return 'done';
    if (normalized.includes('retir')) return 'withdrawn';
    if (normalized.includes('cancel')) return 'cancelled';
    return 'pending';
  }

  function renderUnitOptions(){
    if (!unitSelect) return;

    if (!Array.isArray(units) || units.length === 0){
      unitSelect.innerHTML = '<option value="">Nenhuma unidade disponivel</option>';
      unitSelect.disabled = true;
      return;
    }

    unitSelect.disabled = false;
    unitSelect.innerHTML = ['<option value="">Selecione uma unidade</option>'].concat(
      units.map((unit) => {
        const queueCount = Number(unit.activeOrders) || 0;
        const selected = Number(unit.id) === Number(selectedUnitId) ? ' selected' : '';
        return `<option value="${unit.id}"${selected}>${escapeHtml(unit.name)} - ${escapeHtml(unit.city)}/${escapeHtml(unit.state)}${queueCount ? ` (${queueCount} na fila)` : ''}</option>`;
      })
    ).join('');
  }

  function renderOrdersTable(data){
    if (updatedNode) updatedNode.textContent = formatUpdated(data.lastUpdatedAt);

    if (!Array.isArray(data.queue) || data.queue.length === 0){
      ordersTableBody.innerHTML = '<tr><td class="public-empty" colspan="3">Nenhum pedido ativo na fila desta unidade no momento.</td></tr>';
      return;
    }

    ordersTableBody.innerHTML = data.queue.map((item) => `
      <tr>
        <td>
          <div class="public-cell-strong">${escapeHtml(item.instructorName)}</div>
          <div class="public-cell-muted">${escapeHtml(item.modelName || 'Modelo personalizado')}</div>
        </td>
        <td>${escapeHtml(item.sector || 'Sem setor')}</td>
        <td><span class="status-pill ${statusClass(item.status)}">${escapeHtml(item.status)}</span></td>
      </tr>
    `).join('');
  }

  function renderOrdersError(message){
    ordersTableBody.innerHTML = `<tr><td class="public-empty" colspan="3">${escapeHtml(message)}</td></tr>`;
  }

  async function loadUnits(manual = false){
    try{
      const response = await fetch(`${API_URL}?action=public-units`, {
        headers: {'Accept': 'application/json'},
        cache: 'no-store'
      });
      const payload = await response.json();
      if (!response.ok || !payload.ok){
        renderOrdersError('Nao foi possivel carregar as unidades publicas agora.');
        return;
      }

      units = Array.isArray(payload.data?.units) ? payload.data.units : [];
      if (updatedNode) updatedNode.textContent = formatUpdated(payload.data?.lastUpdatedAt);
      if (!units.some((unit) => Number(unit.id) === Number(selectedUnitId))) selectedUnitId = readInitialUnitId();
      if (!units.some((unit) => Number(unit.id) === Number(selectedUnitId))) selectedUnitId = Number(units[0]?.id || 0);
      renderUnitOptions();
      if (selectedUnitId > 0) await loadOrders(selectedUnitId, false);
      else renderOrdersError('Nenhuma unidade disponivel para consulta publica.');
      startAutoRefresh(Number(payload.data?.refreshIntervalMs) || 15000);
    }catch(error){
      renderOrdersError('Nao foi possivel carregar a fila publica agora.');
    }
  }

  async function loadOrders(unitId, manual = false){
    if (!unitId){
      renderOrdersError('Selecione uma unidade para visualizar a fila de pedidos.');
      return;
    }

    try{
      const response = await fetch(`${API_URL}?action=public-unit-queue-id&id=${encodeURIComponent(unitId)}`, {
        headers: {'Accept': 'application/json'},
        cache: 'no-store'
      });
      const payload = await response.json();
      if (!response.ok || !payload.ok){
        renderOrdersError('Nao foi possivel carregar a fila da unidade selecionada.');
        return;
      }
      renderOrdersTable(payload.data);
    }catch(error){
      renderOrdersError('Falha de conexao ao buscar a fila da unidade.');
    }
  }

  async function selectUnit(unitId, manual = false){
    selectedUnitId = unitId;
    updateUrl(unitId);
    renderUnitOptions();
    await loadOrders(unitId, manual);
  }

  function startAutoRefresh(interval){
    stopAutoRefresh();
    refreshTimer = window.setInterval(() => loadUnits(false), interval);
  }

  function stopAutoRefresh(){
    if (refreshTimer){
      window.clearInterval(refreshTimer);
      refreshTimer = null;
    }
  }

  if (unitSelect){
    unitSelect.addEventListener('change', (event) => {
      const value = Number(event.target.value || 0);
      if (!value){
        selectedUnitId = 0;
        updateUrl(0);
        renderOrdersError('Selecione uma unidade para visualizar a fila de pedidos.');
        return;
      }
      selectUnit(value, true);
    });
  }

  if (themeButton) themeButton.addEventListener('click', toggleTheme);
  if (refreshButton) refreshButton.addEventListener('click', () => loadUnits(true));

  applyStoredTheme();
  selectedUnitId = readInitialUnitId();
  loadUnits(false);
})();
