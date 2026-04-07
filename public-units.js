(function(){
  const stateNode = document.getElementById('units-state');
  const gridNode = document.getElementById('units-grid');
  const updatedNode = document.getElementById('units-last-updated');
  const refreshButton = document.getElementById('units-refresh-button');
  let timer = null;

  function setState(message, visible = true){
    if (!stateNode) return;
    stateNode.textContent = message;
    stateNode.classList.toggle('is-visible', visible);
  }

  function formatUpdated(value){
    const date = value ? new Date(value) : new Date();
    return date.toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'});
  }

  function escapeHtml(value){
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function renderUnits(data){
    if (updatedNode) updatedNode.textContent = formatUpdated(data.lastUpdatedAt);
    if (!Array.isArray(data.units) || data.units.length === 0){
      gridNode.innerHTML = '<article class="units-empty">Nenhuma unidade cadastrada para exibição pública.</article>';
      setState('Nenhuma unidade cadastrada no momento.', true);
      return;
    }

    setState(`${data.units.length} unidade(s) disponível(is) para consulta pública.`, true);
    gridNode.innerHTML = data.units.map((unit, index) => {
      const activeOrders = Number(unit.activeOrders) || 0;
      const indicatorClass = activeOrders > 0 ? 'queue-indicator busy' : 'queue-indicator';
      const cardClass = index === 0 ? 'unit-card unit-card-large' : 'unit-card';
      return `
        <a class="${cardClass}" href="unidade/${unit.id}">
          <div class="unit-card-top">
            <div class="unit-card-copy">
              <strong>${escapeHtml(unit.name)}</strong>
              <p>${escapeHtml(unit.city)} • ${escapeHtml(unit.state)} • ${escapeHtml(unit.code)}</p>
            </div>
            <div class="${indicatorClass}">${activeOrders > 0 ? `${activeOrders} em fila` : 'Sem fila'}</div>
          </div>
          <div class="unit-card-meta">
            <div class="meta-pill">
              <span>Pedidos em Fila</span>
              <strong>${activeOrders}</strong>
            </div>
            <div class="meta-pill">
              <span>Acessar</span>
              <strong>Ver fila</strong>
            </div>
          </div>
        </a>
      `;
    }).join('');
  }

  async function loadUnits(manual = false){
    if (manual) setState('Atualizando unidades públicas...', true);
    try{
      const response = await fetch('api.php?action=public-units', {
        headers: {'Accept':'application/json'},
        cache: 'no-store'
      });
      const payload = await response.json();
      if (!response.ok || !payload.ok){
        setState('Não foi possível carregar as unidades públicas agora.', true);
        stopRefresh();
        return;
      }
      renderUnits(payload.data);
      startRefresh(Number(payload.data?.refreshIntervalMs) || 15000);
    }catch(error){
      setState('Falha de conexão ao buscar as unidades públicas.', true);
    }
  }

  function startRefresh(interval){
    stopRefresh();
    timer = window.setInterval(() => loadUnits(false), interval);
  }

  function stopRefresh(){
    if (timer){
      window.clearInterval(timer);
      timer = null;
    }
  }

  if (refreshButton){
    refreshButton.addEventListener('click', () => loadUnits(true));
  }

  loadUnits(false);
})();
