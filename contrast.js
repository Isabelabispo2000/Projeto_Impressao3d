(function(){
  const STORAGE_KEY = 'contrast';

  function isActive(){
    try {
      return localStorage.getItem(STORAGE_KEY) === 'active';
    } catch (error) {
      return document.body.classList.contains('high-contrast');
    }
  }

  function persist(active){
    try {
      localStorage.setItem(STORAGE_KEY, active ? 'active' : 'inactive');
    } catch (error) {}
  }

  function syncButton(active){
    const button = document.getElementById('contrast-toggle');
    if (!button) return;
    const label = active ? 'Desativar alto contraste' : 'Ativar alto contraste';
    button.setAttribute('aria-pressed', String(active));
    button.setAttribute('aria-label', label);
    button.setAttribute('title', label);
  }

  function apply(active){
    document.body.classList.toggle('high-contrast', active);
    syncButton(active);
    persist(active);
  }

  function init(){
    const button = document.getElementById('contrast-toggle');
    if (!button) return;

    apply(isActive() || !!window.__contrastEnabled);
    button.addEventListener('click', () => {
      apply(!document.body.classList.contains('high-contrast'));
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, {once: true});
  } else {
    init();
  }
})();
