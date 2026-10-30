/* Presentation only. Never fetches data or changes form payloads, actions or company context. */
(() => {
  'use strict';
  const boot = () => document.querySelectorAll('[data-company-workspace]').forEach((root, rootIndex) => {
    if (root.dataset.workspaceReady) return;
    const headings = Array.from(root.children).filter(el => el.matches('h2, [data-workspace-section]'));
    if (headings.length < 2) return;
    root.dataset.workspaceReady = 'true';
    const tabs = document.createElement('ul');
    tabs.className = 'nav nav-tabs company-workspace-tabs'; tabs.setAttribute('role', 'tablist'); tabs.setAttribute('aria-label', 'Seções');
    const toolbar = document.createElement('div'); toolbar.className = 'company-workspace-actions';
    const panels = headings.map((heading, index) => {
      const panel = document.createElement('div'); panel.className = 'company-workspace-panel';
      const id = 'company-workspace-' + rootIndex + '-' + index;
      panel.id = id; panel.setAttribute('role', 'tabpanel'); panel.tabIndex = -1;
      const label = heading.dataset.workspaceLabel || heading.textContent.trim();
      const creation = heading.dataset.workspaceCreate !== undefined || /^(Novo |Nova |Emitir |Criar )/i.test(label);
      const button = document.createElement('button'); button.type = 'button'; button.textContent = heading.dataset.workspaceLabel || label;
      button.id = id + '-tab'; button.setAttribute('aria-controls', id); panel.setAttribute('aria-labelledby', button.id);
      if (creation) { button.className = 'btn btn-primary'; toolbar.append(button); }
      else { const li = document.createElement('li'); li.setAttribute('role', 'presentation'); button.setAttribute('role', 'tab'); li.append(button); tabs.append(li); }
      heading.before(panel);
      let node = heading;
      // Move, do not clone: IDs, form ownership, listeners and tokens remain intact.
      while (node && (node === heading || !headings.includes(node))) {
        const next = node.nextSibling; panel.append(node); node = next;
      }
      return { panel, button, creation, label, defaultPanel: heading.hasAttribute('data-workspace-default') };
    });
    root.insertBefore(toolbar, panels[0].panel); root.insertBefore(tabs, panels[0].panel);
    const activate = (chosen, focus = false) => {
      panels.forEach(entry => {
        const active = entry === chosen; entry.panel.hidden = !active;
        entry.button.setAttribute('aria-selected', String(active));
        if (!entry.creation) { entry.button.tabIndex = active || (chosen.creation && entry === panels.find(item => !item.creation)) ? 0 : -1; entry.button.parentElement.classList.toggle('active', active); }
        else entry.button.setAttribute('aria-expanded', String(active));
      });
      if (focus) chosen.panel.focus({ preventScroll: true });
    };
    panels.forEach(entry => entry.button.addEventListener('click', () => activate(entry, true)));
    tabs.addEventListener('keydown', event => {
      if (!['ArrowLeft','ArrowRight','Home','End'].includes(event.key)) return;
      const choices = panels.filter(entry => !entry.creation), current = choices.findIndex(entry => entry.button === document.activeElement);
      if (current < 0) return;
      event.preventDefault();
      const next = event.key === 'Home' ? 0 : event.key === 'End' ? choices.length - 1 : (current + (event.key === 'ArrowRight' ? 1 : -1) + choices.length) % choices.length;
      activate(choices[next]); choices[next].button.focus();
    });
    root.addEventListener('invalid', event => {
      const entry = panels.find(item => item.panel.contains(event.target)); if (entry) activate(entry);
    }, true);
    // Error responses retain every operation in view, never hide a validation failure.
    if (root.querySelector('[role="alert"], .alert-danger')) {
      panels.forEach(entry => { entry.panel.hidden = false; entry.button.setAttribute('aria-selected','false'); });
    } else {
      // A selected record is rendered first by the server; directory requests start at the list.
      activate(panels.find(entry => entry.defaultPanel) || panels[0]);
      const search = new URLSearchParams(location.search);
      if (search.has('client_search')) {
        const creation = panels.find(entry => entry.creation); if (creation) activate(creation);
      }
    }
    const revealAnchor = (hash = location.hash) => {
      let id; try { id = decodeURIComponent(hash.slice(1)); } catch (_) { return; }
      if (!id) return;
      const target = document.getElementById(id);
      const entry = panels.find(item => target && item.panel.contains(target));
      if (entry) activate(entry);
    };
    revealAnchor(); window.addEventListener('hashchange', () => revealAnchor());
    root.addEventListener('click', event => {
      const link = event.target.closest('a[href^="#"]');
      if (link) revealAnchor(link.hash);
    });
  });
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
