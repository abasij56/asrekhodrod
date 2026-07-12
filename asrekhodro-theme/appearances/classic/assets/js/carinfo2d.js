function isCarInfo2dPage() {
  return document.body.classList.contains('carinfo2d-page');
}

function isCarInfo2dLayoutZone(element) {
  return element instanceof HTMLElement && element.classList.contains('carinfo2d-zone');
}

function activateEmbeddedScripts(root) {
  if (!(root instanceof HTMLElement)) {
    return;
  }

  root.querySelectorAll('script').forEach((oldScript) => {
    const script = document.createElement('script');
    [...oldScript.attributes].forEach((attr) => {
      script.setAttribute(attr.name, attr.value);
    });
    script.textContent = oldScript.textContent;
    oldScript.replaceWith(script);
  });
}

function mountOverviewVideo() {
  if (!isCarInfo2dPage()) {
    return;
  }

  const main = document.getElementById('carinfo2d-main');
  if (!main) {
    return;
  }

  const overview = main.querySelector(':scope .ci-overview-slide');
  if (!overview) {
    return;
  }

  const slot = overview.querySelector('.ci-overview__video-slot');
  if (!slot || slot.classList.contains('is-filled')) {
    return;
  }

  const panel = overview.closest('.carinfo2d-scroll-panel') ?? main;
  const children = [...panel.children];
  const overviewIndex = children.indexOf(overview);
  if (overviewIndex < 0) {
    return;
  }

  const videoSection = children.slice(overviewIndex + 1).find(
    (element) => element instanceof HTMLElement && element.classList.contains('ci-section--video'),
  );

  if (!(videoSection instanceof HTMLElement)) {
    return;
  }

  while (videoSection.firstChild) {
    slot.appendChild(videoSection.firstChild);
  }

  slot.classList.remove('ci-overview__panel--empty', 'ci-overview__video-slot');
  slot.classList.add('ci-overview__panel--video', 'is-filled');
  slot.removeAttribute('aria-hidden');
  activateEmbeddedScripts(slot);
  videoSection.remove();
}

function init2dScrollPanel() {
  if (!isCarInfo2dPage()) {
    return;
  }

  const main = document.getElementById('carinfo2d-main');
  if (!main) {
    return;
  }

  const intro = main.firstElementChild;
  if (!(intro instanceof HTMLElement) || isCarInfo2dLayoutZone(intro)) {
    return;
  }

  const zoneMain = main.querySelector(':scope > .carinfo2d-zone--main');
  const zoneMainAfter = main.querySelector(':scope > .carinfo2d-zone--main-after');

  const contentNodes = [];
  let sibling = intro.nextElementSibling;
  while (sibling) {
    if (!isCarInfo2dLayoutZone(sibling)) {
      contentNodes.push(sibling);
    }
    sibling = sibling.nextElementSibling;
  }

  if (!contentNodes.length && !zoneMain && !zoneMainAfter) {
    return;
  }

  const sidebarTemplate = document.getElementById('carinfo2d-sidebar-template');
  const hasSidebar = Boolean(
    sidebarTemplate?.content?.querySelector('.page-sidebar-stack'),
  );

  const panel = document.createElement('div');
  panel.className = hasSidebar
    ? 'carinfo2d-scroll-panel'
    : 'container carinfo2d-scroll-panel';

  if (zoneMain instanceof HTMLElement) {
    panel.appendChild(zoneMain);
  }

  contentNodes.forEach((node) => {
    panel.appendChild(node);
  });

  if (zoneMainAfter instanceof HTMLElement) {
    panel.appendChild(zoneMainAfter);
  }

  if (hasSidebar && sidebarTemplate instanceof HTMLTemplateElement) {
    const body = document.createElement('div');
    body.className = 'container carinfo2d-body';

    const grid = document.createElement('div');
    grid.className = 'carinfo2d-grid';

    const mainCol = document.createElement('div');
    mainCol.className = 'carinfo2d-body-main';
    mainCol.appendChild(panel);

    const aside = document.createElement('aside');
    aside.className = 'carinfo2d-sidebar';
    aside.setAttribute('role', 'complementary');
    aside.setAttribute('aria-label', 'ستون جانبی');
    aside.appendChild(sidebarTemplate.content.cloneNode(true));

    grid.appendChild(mainCol);
    grid.appendChild(aside);
    body.appendChild(grid);
    main.appendChild(body);
    sidebarTemplate.remove();
  } else {
    main.appendChild(panel);
  }
}

function boot() {
  if (!isCarInfo2dPage()) {
    return;
  }

  document.documentElement.classList.add('carinfo2d-root');
  init2dScrollPanel();
  mountOverviewVideo();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}
