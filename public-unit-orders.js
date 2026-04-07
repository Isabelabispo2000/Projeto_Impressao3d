(function(){
  const root = document.documentElement;
  const body = document.body;
  const token = (body?.dataset.unitToken || '').trim();
  const unitName = document.getElementById('orders-unit-name');
  const unitSubtitle = document.getElementById('orders-unit-subtitle');
  const lastUpdated = document.getElementById('orders-last-updated');
  const stateNode = document.getElementById('orders-state');
  const listNode = document.getElementById('orders-list');
  const refreshButton = document.getElementById('orders-refresh-button');
  const queueLink = document.getElementById('queue-link');

  let timer = null;

  function setState(message, visible = true){
    if (!stateNode) return;
    stateNode.textContent = message;
    stateNode.classList.toggle('is-visible', visible);
  }

  function escapeHtml(value){
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function hashString(value){
    let hash = 0;
    for(let index = 0; index < value.length; index += 1){
      hash = ((hash << 5) - hash) + value.charCodeAt(index);
      hash |= 0;
    }
    return hash;
  }

  function applyAccent(unit){
    const palette = [
      {match: 'centro', accent: '#60a5fa', soft: 'rgba(96,165,250,0.18)', glow: 'rgba(96,165,250,0.30)'},
      {match: 'norte', accent: '#a78bfa', soft: 'rgba(167,139,250,0.18)', glow: 'rgba(167,139,250,0.30)'},
      {match: 'sul', accent: '#34d399', soft: 'rgba(52,211,153,0.18)', glow: 'rgba(52,211,153,0.28)'},
      {match: 'leste', accent: '#f59e0b', soft: 'rgba(245,158,11,0.18)', glow: 'rgba(245,158,11,0.28)'},
      {match: 'oeste', accent: '#f472b6', soft: 'rgba(244,114,182,0.18)', glow: 'rgba(244,114,182,0.30)'}
    ];
    const key = `${unit?.name || ''} ${unit?.code || ''}`.toLowerCase();
    const choice = palette.find(item => key.includes(item.match)) || palette[Math.abs(hashString(key)) % palette.length];
    root.style.setProperty('--accent', choice.accent);
    root.style.setProperty('--accent-soft', choice.soft);
    root.style.setProperty('--accent-glow', choice.glow);
  }

  function formatDate(value){
    if (!value) return 'Sem data';
    const [year, month, day] = String(value).split('-');
    if (!year || !month || !day) return value;
    return `${day}/${month}/${year}`;
  }

  function formatUpdated(value){
    const date = value ? new Date(value) : new Date();
    return date.toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'});
  }

  function renderNotFound(){
    body.classList.add('not-found');
    if (unitName) unitName.textContent = 'Unidade não encontrada';
    if (unitSubtitle) unitSubtitle.textContent = 'O token informado não corresponde a uma unidade pública válida.';
    if (lastUpdated) lastUpdated.textContent = '--:--';
    if (queueLink) queueLink.setAttribute('href', '#');
    listNode.innerHTML = '<article class="orders-empty">Unidade não encontrada.</article>';
    setState('Unidade não encontrada.', true);
  }

  function renderOrders(data){
    body.classList.remove('not-found');
    applyAccent(data.unit);
    if (unitName) unitName.textContent = `Pedidos da ${data.unit.name}`;
    if (unitSubtitle) unitSubtitle.textContent = `${data.unit.city} • ${data.unit.state} • Código ${data.unit.code}`;
    if (lastUpdated) lastUpdated.textContent = formatUpdated(data.lastUpdatedAt);
    if (queueLink) queueLink.setAttribute('href', `../${data.unit.publicToken}`);

    if (!Array.isArray(data.orders) || data.orders.length === 0){
      listNode.innerHTML = '<article class="orders-empty">Nenhum pedido encontrado para esta unidade.</article>';
      setState('Nenhum pedido encontrado para esta unidade.', true);
      return;
    }

    setState(`${data.orders.length} pedido(s) sincronizado(s) para esta unidade.`, true);
    listNode.innerHTML = data.orders.map(order => `
      <article class="order-card">
        <div class="order-main">
          <span>Instrutor</span>
          <strong>${escapeHtml(order.instructorName)}</strong>
          <p>Pedido #${order.id} • ${escapeHtml(order.modelName)} • ${escapeHtml(order.sector)}</p>
        </div>
        <div class="order-meta">
          <span>Detalhes</span>
          <p>Solicitado em ${escapeHtml(formatDate(order.createdAt))}${order.deadline ? ` • Prazo ${escapeHtml(formatDate(order.deadline))}` : ''}</p>
          <p>${escapeHtml(order.purpose || 'Sem finalidade informada')}</p>
        </div>
        <div class="status-badge ${escapeHtml(order.statusKey)}">${escapeHtml(order.status)}</div>
      </article>
    `).join('');
  }

  async function fetchOrders(manual = false){
    if (!token){
      renderNotFound();
      return;
    }

    if (manual) setState('Atualizando pedidos da unidade...', true);

    try {
      const response = await fetch(`api.php?action=public-unit-orders&token=${encodeURIComponent(token)}`, {
        headers: {'Accept': 'application/json'},
        cache: 'no-store'
      });
      const payload = await response.json();
      if (!response.ok || !payload.ok){
        renderNotFound();
        stopRefresh();
        return;
      }

      renderOrders(payload.data);
      startRefresh(Number(payload.data?.refreshIntervalMs) || 15000);
    } catch (error) {
      setState('Não foi possível atualizar os pedidos agora. Tentaremos novamente automaticamente.', true);
    }
  }

  function startRefresh(interval){
    stopRefresh();
    timer = window.setInterval(() => fetchOrders(false), interval);
  }

  function stopRefresh(){
    if (timer){
      window.clearInterval(timer);
      timer = null;
    }
  }

  if (refreshButton){
    refreshButton.addEventListener('click', () => fetchOrders(true));
  }

  fetchOrders(false);
})();
