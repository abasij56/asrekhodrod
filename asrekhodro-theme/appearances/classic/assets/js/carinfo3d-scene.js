import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { DRACOLoader } from 'three/addons/loaders/DRACOLoader.js';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';

const INTRO_TARGET = [0, 0.5, 0];
const INTRO_CAMERA_DISTANCE = 8.2;
const INTRO_DEPRESSION_DEG = 15;
const INTRO_AZIMUTH_DEG = 38;
const INTRO_STAGE_ROTATION_DEFAULT_DEG = 32;
const INTRO_MODEL_TARGET_SIZE = 4.2;
const INTRO_CAMERA_FOV = 40;

function getResponsiveSceneFactors() {
  const width = window.innerWidth;

  if (width <= 480) {
    return {
      modelTargetSize: 3.75,
      cameraDistanceMul: 1.1,
      fov: 41,
    };
  }

  if (width <= 768) {
    return {
      modelTargetSize: 3.95,
      cameraDistanceMul: 1.02,
      fov: 40.5,
    };
  }

  if (width <= 1100) {
    return {
      modelTargetSize: 4.05,
      cameraDistanceMul: 1,
      fov: 40,
    };
  }

  return {
    modelTargetSize: INTRO_MODEL_TARGET_SIZE,
    cameraDistanceMul: 1,
    fov: INTRO_CAMERA_FOV,
  };
}

function isIntroViewport() {
  const introSection = document.querySelector('#carinfo3d-main > .carinfo3d-section');
  if (!introSection) {
    return window.scrollY < 120;
  }

  return window.scrollY < introSection.offsetHeight * 0.65;
}

function degToRad(degrees) {
  return (degrees * Math.PI) / 180;
}

function readIntroStageRotationDeg() {
  const block =
    document.querySelector('#carinfo3d-main [data-cinfo-3dmodel]') ||
    document.querySelector('[data-cinfo-3dmodel]');

  if (!block) {
    return INTRO_STAGE_ROTATION_DEFAULT_DEG;
  }

  const value = parseFloat(block.getAttribute('data-initial-rotation') ?? '');

  if (!Number.isFinite(value)) {
    return INTRO_STAGE_ROTATION_DEFAULT_DEG;
  }

  return ((value % 360) + 360) % 360;
}

function buildCameraPosition(
  distance,
  azimuthDeg,
  depressionDeg,
  target = INTRO_TARGET
) {
  const depression = degToRad(depressionDeg);
  const azimuth = degToRad(azimuthDeg);
  const horizontal = distance * Math.cos(depression);
  const lift = distance * Math.sin(depression);

  return [
    horizontal * Math.sin(azimuth),
    target[1] + lift,
    -horizontal * Math.cos(azimuth),
  ];
}

const HERO_WORLD_BASE = {
  fog: 0xe8eef6,
  rim: 0xffffff,
  rotate: 0.9,
  vibrancy: 1,
};

function getHeroWorld() {
  const factors = getResponsiveSceneFactors();
  const distance = INTRO_CAMERA_DISTANCE * factors.cameraDistanceMul;

  return {
    cam: buildCameraPosition(distance, INTRO_AZIMUTH_DEG, INTRO_DEPRESSION_DEG),
    target: [...INTRO_TARGET],
    fog: HERO_WORLD_BASE.fog,
    rim: HERO_WORLD_BASE.rim,
    rotate: HERO_WORLD_BASE.rotate,
    vibrancy: HERO_WORLD_BASE.vibrancy,
    stageRotation: degToRad(readIntroStageRotationDeg()),
    fov: factors.fov,
    modelTargetSize: factors.modelTargetSize,
  };
}

const CONTENT_WORLD = {
  cam: [9, 2.5, 3],
  target: [0, 0.5, 0],
  fog: 0xeef2f8,
  rim: 0xd0d8e8,
  rotate: 0.5,
  vibrancy: 0.18,
};

const DEFAULT_BODY_COLOR = 0xc41e2a;

const carPaint = {
  bodyMaterial: null,
  detailsMaterial: null,
  glassMaterial: null,
  selectedBodyColor: new THREE.Color(DEFAULT_BODY_COLOR),
  currentVibrancy: 1,
};

const AD_BOARD_PX = { width: 328, height: 60 };
const AD_BOARD_UNIT = 0.008; // 328px → ~2.62 world units wide
const AD_BOARD_GAP = 0.07;

const adBoards = {
  group: null,
  posters: [],
  boardWidth: AD_BOARD_PX.width * AD_BOARD_UNIT,
  boardHeight: AD_BOARD_PX.height * AD_BOARD_UNIT,
  gap: AD_BOARD_GAP,
  rowY: 1.05,
};

function isCarInfo3d2Page() {
  return document.body.classList.contains('carinfo3d2-page');
}

function shouldRun() {
  if (!document.body.classList.contains('carinfo3d-page')) {
    return false;
  }

  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    return false;
  }

  return true;
}

function setChromeHeight() {
  const header = document.querySelector('body.carinfo3d-page .site-header');
  if (!header) {
    return;
  }

  const height = Math.ceil(header.getBoundingClientRect().height);
  document.body.style.setProperty('--ci3d-chrome-height', `${height}px`);
}

function readBlockConfig() {
  const block =
    document.querySelector('#carinfo3d-main [data-cinfo-3dmodel]') ||
    document.querySelector('[data-cinfo-3dmodel]');
  if (!block) {
    return {
      modelUrl: '',
      imageUrl: '',
      dracoPath: '',
      defaultColor: '',
    };
  }

  return {
    modelUrl: (block.getAttribute('data-model-url') || '').trim(),
    imageUrl: (block.getAttribute('data-image-url') || '').trim(),
    dracoPath: (block.getAttribute('data-draco-path') || '').trim(),
    defaultColor: (block.getAttribute('data-default-color') || '').trim(),
  };
}

function fitObjectToStage(object, targetSize = INTRO_MODEL_TARGET_SIZE) {
  const box = new THREE.Box3().setFromObject(object);
  const size = box.getSize(new THREE.Vector3());
  const center = box.getCenter(new THREE.Vector3());
  const maxDim = Math.max(size.x, size.y, size.z) || 1;
  const scale = targetSize / maxDim;

  object.scale.setScalar(scale);
  object.position.sub(center.multiplyScalar(scale));
  object.position.y += size.y * scale * 0.5;
}

function getHeroVisual() {
  return document.querySelector('#carinfo3d-main .ci-3dmodel__visual');
}

function showHeroVisual() {
  getHeroVisual()?.classList.add('is-visible');
}

function hideHeroVisual() {
  getHeroVisual()?.classList.remove('is-visible');
}

// Generic paint detection — works across GLB naming conventions (ferrari.glb, Sketchfab exports, etc.)
const PAINT_INCLUDE_RE = /body|paint|exterior|car_body|karosserie|chassis/i;
const PAINT_EXCLUDE_RE =
  /wheel|rim|tire|tyre|glass|window|windshield|light|lamp|plastic|metal|shadow|collision|interior|mirror|brake|clip|calip|general|chrome|trim|badge|logo|plate|under|shadowcaster|cliper|object_/i;
const GLASS_RE = /glass|window|windshield/i;
const DETAILS_RE = /wheel|rim|tire|tyre|trim|metal|chrome|hubcap/i;
const FALLBACK_EXCLUDE_RE =
  /shadow|collision|under|interior|light|lamp|plastic|general|glass|window|wheel|rim|tire|tyre|object_/i;

function getMeshLabel(mesh) {
  const parts = [mesh.name || ''];
  const materials = Array.isArray(mesh.material) ? mesh.material : [mesh.material];

  materials.filter(Boolean).forEach((material) => {
    parts.push(material.name || '');
  });

  return parts.join(' ');
}

function isPaintMesh(label) {
  if (PAINT_EXCLUDE_RE.test(label)) {
    return false;
  }

  return PAINT_INCLUDE_RE.test(label);
}

function isGlassMesh(label) {
  return GLASS_RE.test(label);
}

function isDetailsMesh(label) {
  return DETAILS_RE.test(label) && !isPaintMesh(label);
}

function countMeshTriangles(mesh) {
  const geometry = mesh.geometry;
  if (!geometry) {
    return 0;
  }

  if (geometry.index) {
    return geometry.index.count / 3;
  }

  return geometry.attributes.position ? geometry.attributes.position.count / 3 : 0;
}

function assignSharedMaterial(mesh, material) {
  if (Array.isArray(mesh.material)) {
    mesh.material = mesh.material.map((slot) => {
      const slotLabel = `${mesh.name || ''} ${slot?.name || ''}`.trim();
      return isPaintMesh(slotLabel) ? material : slot;
    });
    return mesh.material.some((slot) => slot === material);
  }

  mesh.material = material;
  return true;
}

function findFallbackPaintMesh(model) {
  let bestMesh = null;
  let bestTriangles = 0;

  model.traverse((child) => {
    if (!child.isMesh) {
      return;
    }

    const label = getMeshLabel(child);
    if (FALLBACK_EXCLUDE_RE.test(label)) {
      return;
    }

    const triangles = countMeshTriangles(child);
    if (triangles > bestTriangles) {
      bestTriangles = triangles;
      bestMesh = child;
    }
  });

  return bestMesh;
}

// Prototype car paint — raw GLB materials look flat and foggy.
function createBodyMaterial(color = DEFAULT_BODY_COLOR) {
  carPaint.selectedBodyColor.set(color);

  return new THREE.MeshPhysicalMaterial({
    color,
    metalness: 1.0,
    roughness: 0.4,
    clearcoat: 1.0,
    clearcoatRoughness: 0.03,
    transparent: true,
    opacity: 1,
  });
}

function applyCarMaterials(model, bodyColor = DEFAULT_BODY_COLOR) {
  carPaint.bodyMaterial = null;
  carPaint.detailsMaterial = null;
  carPaint.glassMaterial = null;

  const bodyMaterial = createBodyMaterial(bodyColor);
  const detailsMaterial = new THREE.MeshStandardMaterial({
    color: 0xffffff,
    metalness: 1.0,
    roughness: 0.35,
    transparent: true,
    opacity: 1,
  });
  const glassMaterial = new THREE.MeshPhysicalMaterial({
    color: 0xffffff,
    metalness: 0.2,
    roughness: 0,
    transmission: 1.0,
    transparent: true,
    opacity: 0.85,
  });

  let paintedMeshCount = 0;

  model.traverse((child) => {
    if (!child.isMesh) {
      return;
    }

    const label = getMeshLabel(child);

    if (isGlassMesh(label)) {
      child.material = glassMaterial;
      carPaint.glassMaterial = glassMaterial;
      return;
    }

    if (isDetailsMesh(label)) {
      child.material = detailsMaterial;
      carPaint.detailsMaterial = detailsMaterial;
      return;
    }

    if (isPaintMesh(label) && assignSharedMaterial(child, bodyMaterial)) {
      paintedMeshCount += 1;
    }
  });

  if (paintedMeshCount > 0) {
    carPaint.bodyMaterial = bodyMaterial;
    return;
  }

  const fallbackMesh = findFallbackPaintMesh(model);
  if (fallbackMesh) {
    fallbackMesh.material = bodyMaterial;
    carPaint.bodyMaterial = bodyMaterial;
    console.warn(
      '[carinfo3d] paint mesh not found by name; using largest mesh:',
      getMeshLabel(fallbackMesh)
    );
    return;
  }

  console.warn('[carinfo3d] no paintable mesh found in model');
}

function setCarBodyColor(hex) {
  carPaint.selectedBodyColor.set(hex);

  if (!carPaint.bodyMaterial) {
    return;
  }

  carPaint.bodyMaterial.color.set(hex);
}

function applyCarVibrancy(amount) {
  const t = THREE.MathUtils.clamp(amount, 0, 1);
  const fadedBody = new THREE.Color(0xb8c2d0);
  const fadedDetails = new THREE.Color(0xd0d8e4);
  carPaint.currentVibrancy = t;

  if (carPaint.bodyMaterial) {
    carPaint.bodyMaterial.color.copy(fadedBody).lerp(carPaint.selectedBodyColor, t);
    carPaint.bodyMaterial.opacity = THREE.MathUtils.lerp(0.2, 1, t);
    carPaint.bodyMaterial.clearcoat = THREE.MathUtils.lerp(0.25, 1, t);
  }

  if (carPaint.detailsMaterial) {
    carPaint.detailsMaterial.color.copy(fadedDetails).lerp(new THREE.Color(0xffffff), t);
    carPaint.detailsMaterial.opacity = THREE.MathUtils.lerp(0.18, 1, t);
  }

  if (carPaint.glassMaterial) {
    carPaint.glassMaterial.opacity = THREE.MathUtils.lerp(0.14, 0.85, t);
    carPaint.glassMaterial.transmission = THREE.MathUtils.lerp(0.5, 1, t);
  }

  if (adBoards.posters.length) {
    adBoards.posters.forEach((poster) => {
      poster.visible = true;
      if (poster.material) {
        poster.material.opacity = 1;
      }
    });
  }
}

function parseAdsPayload(raw) {
  if (!raw) {
    return null;
  }

  try {
    return JSON.parse(raw);
  } catch {
    return null;
  }
}

function readAdsConfig() {
  const scene = document.getElementById('ci-scene-3d');
  const embedded = scene?.getAttribute('data-ads-config');
  const fromScene = parseAdsPayload(embedded);
  if (fromScene) {
    return {
      items: Array.isArray(fromScene.items) ? fromScene.items : [],
      interval: Math.max(3, Number(fromScene.interval) || 5),
    };
  }

  const block =
    document.querySelector('#carinfo3d-main [data-cinfo-ads]') ||
    document.querySelector('[data-cinfo-ads]');
  if (!block) {
    return { items: [], interval: 5 };
  }

  const script = block.querySelector('script[type="application/json"]');
  const fromBlock = parseAdsPayload(script?.textContent?.trim());
  if (fromBlock) {
    return {
      items: Array.isArray(fromBlock.items) ? fromBlock.items : [],
      interval: Math.max(3, Number(fromBlock.interval) || 5),
    };
  }

  return { items: [], interval: 5 };
}

function configureAdTexture(texture, renderer) {
  texture.colorSpace = THREE.SRGBColorSpace;
  texture.minFilter = THREE.LinearFilter;
  texture.magFilter = THREE.LinearFilter;
  texture.generateMipmaps = false;
  if (renderer) {
    texture.anisotropy = Math.min(renderer.capabilities.getMaxAnisotropy(), 4);
  }
  texture.needsUpdate = true;
  return texture;
}

function loadAdTexture(url, renderer) {
  return new Promise((resolve, reject) => {
    const img = new Image();

    img.onload = () => {
      resolve(configureAdTexture(new THREE.Texture(img), renderer));
    };

    img.onerror = () => {
      reject(new Error(`Failed to load ad image: ${url}`));
    };

    img.src = url;
  });
}

function createAdBoardMesh(texture) {
  const material = new THREE.MeshBasicMaterial({
    map: texture,
    transparent: true,
    opacity: 1,
    side: THREE.DoubleSide,
    depthWrite: true,
    toneMapped: false,
  });

  const mesh = new THREE.Mesh(
    new THREE.PlaneGeometry(adBoards.boardWidth, adBoards.boardHeight),
    material
  );
  mesh.renderOrder = 10;
  return mesh;
}

function layoutSidelineRow(textures, x, faceInward) {
  const { boardWidth, gap, rowY } = adBoards;
  const count = textures.length;
  if (!count) {
    return;
  }

  const totalDepth = count * boardWidth + (count - 1) * gap;
  let z = -totalDepth / 2 + boardWidth / 2;

  textures.forEach((texture) => {
    const board = createAdBoardMesh(texture);
    board.position.set(x, rowY, z);
    board.rotation.y = faceInward > 0 ? Math.PI / 2 : -Math.PI / 2;
    adBoards.group.add(board);
    adBoards.posters.push(board);
    z += boardWidth + gap;
  });
}

function layoutFootballAdBoards(textures) {
  if (!textures.length) {
    return;
  }

  const splitAt = Math.ceil(textures.length / 2);
  layoutSidelineRow(textures.slice(0, splitAt), -9.4, 1);
  layoutSidelineRow(textures.slice(splitAt), 9.4, -1);
}

async function initAdBoards(scene, renderer) {
  const config = readAdsConfig();
  const items = config.items.filter((item) => item?.image);
  if (!items.length) {
    return;
  }

  adBoards.group = new THREE.Group();
  scene.add(adBoards.group);

  const textures = [];

  for (const item of items) {
    try {
      textures.push(await loadAdTexture(item.image, renderer));
    } catch (error) {
      console.warn('[carinfo3d]', error.message);
    }
  }

  layoutFootballAdBoards(textures);

  if (adBoards.posters.length) {
    applyCarVibrancy(carPaint.currentVibrancy);
  }
}

function initColorSwatches() {
  const block =
    document.querySelector('#carinfo3d-main [data-cinfo-3dmodel]') ||
    document.querySelector('[data-cinfo-3dmodel]');
  const swatches = block?.querySelectorAll('.ci-3dmodel__color');
  if (!swatches?.length) {
    return;
  }

  swatches.forEach((button) => {
    button.addEventListener('click', () => {
      const color = button.getAttribute('data-color');
      if (!color) {
        return;
      }

      setCarBodyColor(color);
      applyCarVibrancy(carPaint.currentVibrancy);

      swatches.forEach((item) => {
        const isActive = item === button;
        item.classList.toggle('is-active', isActive);
        item.setAttribute('aria-pressed', isActive ? 'true' : 'false');
      });
    });
  });
}
const IMMERSIVE_SECTION_SELECTOR =
  '#carinfo3d-main > .carinfo3d-section, #carinfo3d-main > .ci-hero-slide, #carinfo3d-main > .ci-hero, #carinfo3d-main > .ci-overview-slide, #carinfo3d-main > .ci-specs-slide, #carinfo3d-main > .ci-gallery-slide, #carinfo3d-main > .ci-faq-slide, #carinfo3d-main > .ci-related-slide, #carinfo3d-main > .ci-comments-slide';

function mountOverviewVideo() {
  if (!document.body.classList.contains('carinfo3d-page')) {
    return;
  }

  const main = document.getElementById('carinfo3d-main');
  if (!main) {
    return;
  }

  const overview = main.querySelector(':scope > .ci-overview-slide');
  if (!overview) {
    return;
  }

  const slot = overview.querySelector('.ci-overview__video-slot');
  if (!slot || slot.classList.contains('is-filled')) {
    return;
  }

  const children = [...main.children];
  const overviewIndex = children.indexOf(overview);
  if (overviewIndex < 0) {
    return;
  }

  const videoSection = children.slice(overviewIndex + 1).find(
    (element) => element instanceof HTMLElement && element.classList.contains('ci-section--video')
  );

  if (!(videoSection instanceof HTMLElement)) {
    return;
  }

  while (videoSection.firstChild) {
    slot.appendChild(videoSection.firstChild);
  }

  slot.classList.remove('ci-overview__panel--empty');
  slot.classList.add('ci-overview__panel--video', 'is-filled');
  slot.removeAttribute('aria-hidden');
  videoSection.remove();
}

function getSectionScrollTop(section, index = -1) {
  if (index === 0 || section.classList.contains('carinfo3d-section')) {
    return 0;
  }

  return Math.max(0, section.offsetTop);
}

function scrollToIntroTop(behavior = 'auto') {
  window.scrollTo({ top: 0, left: 0, behavior });
}

function initIntroScroll() {
  if ('scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
  }

  const syncIntroLayout = () => {
    setChromeHeight();
    scrollToIntroTop('auto');
  };

  syncIntroLayout();
  window.requestAnimationFrame(syncIntroLayout);

  if (document.fonts?.ready) {
    document.fonts.ready.then(syncIntroLayout);
  }

  window.addEventListener('carinfo3d:worldchange', (event) => {
    if (event.detail?.key === 'intro') {
      scrollToIntroTop('smooth');
    }
  });

  window.addEventListener('resize', syncIntroLayout);
}

function groupHeroWithFacts() {
  const main = document.getElementById('carinfo3d-main');
  if (!main) {
    return;
  }

  const hero = main.querySelector(':scope > .ci-hero');
  if (!hero || hero.parentElement?.classList.contains('ci-hero-slide')) {
    return;
  }

  const next = hero.nextElementSibling;
  const facts =
    next instanceof HTMLElement && next.classList.contains('ci-facts-section') ? next : null;

  const wrap = document.createElement('div');
  wrap.className = 'ci-hero-slide';
  hero.parentNode?.insertBefore(wrap, hero);
  wrap.appendChild(hero);
  if (facts) {
    wrap.appendChild(facts);
  }
}

function isCarInfo3d2LayoutZone(element) {
  return element instanceof HTMLElement && element.classList.contains('carinfo3d2-zone');
}

function init3d2ScrollPanel() {
  if (!isCarInfo3d2Page()) {
    return;
  }

  const main = document.getElementById('carinfo3d-main');
  if (!main) {
    return;
  }

  const intro =
    main.querySelector(':scope > .carinfo3d-section') ??
    main.firstElementChild;
  if (!(intro instanceof HTMLElement)) {
    return;
  }

  const zoneMain = main.querySelector(':scope > .carinfo3d2-zone--main');
  const zoneMainAfter = main.querySelector(':scope > .carinfo3d2-zone--main-after');

  const contentNodes = [];
  let sibling = intro.nextElementSibling;
  while (sibling) {
    if (!isCarInfo3d2LayoutZone(sibling)) {
      contentNodes.push(sibling);
    }
    sibling = sibling.nextElementSibling;
  }

  if (!contentNodes.length && !zoneMain && !zoneMainAfter) {
    return;
  }

  const sidebarTemplate = document.getElementById('carinfo3d2-sidebar-template');
  const hasSidebar = Boolean(
    sidebarTemplate?.content?.querySelector('.page-sidebar-stack'),
  );

  const panel = document.createElement('div');
  panel.className = hasSidebar
    ? 'carinfo3d2-scroll-panel'
    : 'container carinfo3d2-scroll-panel';

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
    body.className = 'container carinfo3d2-body';

    const grid = document.createElement('div');
    grid.className = 'carinfo3d2-grid';

    const mainCol = document.createElement('div');
    mainCol.className = 'carinfo3d2-body-main';
    mainCol.appendChild(panel);

    const aside = document.createElement('aside');
    aside.className = 'carinfo3d2-sidebar';
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

function getImmersiveSections() {
  if (isCarInfo3d2Page()) {
    return [
      ...document.querySelectorAll('#carinfo3d-main > .carinfo3d-section'),
      ...document.querySelectorAll('.carinfo3d2-scroll-panel > *'),
    ].filter((section) => section instanceof HTMLElement);
  }

  return [...document.querySelectorAll(IMMERSIVE_SECTION_SELECTOR)];
}

function buildRandomWorld(base) {
  const azimuth = Math.random() * Math.PI * 2;
  const distance = THREE.MathUtils.lerp(5.2, 9.8, Math.random());
  const height = THREE.MathUtils.lerp(1.35, 4.2, Math.random());

  return {
    cam: [Math.cos(azimuth) * distance, height, Math.sin(azimuth) * distance],
    target: [...base.target],
    fog: base.fog,
    rim: base.rim,
    rotate: base.rotate * THREE.MathUtils.lerp(0.6, 1.2, Math.random()),
    vibrancy: base.vibrancy,
    stageRotation: Math.random() * Math.PI * 2,
  };
}

function getWorldForSection(section) {
  if (getSectionWorldKey(section) === 'intro') {
    const world = getHeroWorld();

    return {
      cam: [...world.cam],
      target: [...world.target],
      fog: world.fog,
      rim: world.rim,
      rotate: world.rotate,
      vibrancy: world.vibrancy,
      stageRotation: world.stageRotation ?? 0,
    };
  }

  return buildRandomWorld(getBaseWorldForSection(section));
}

function getSectionWorldKey(section) {
  if (section.classList.contains('carinfo3d-section')) {
    return 'intro';
  }

  if (section.classList.contains('ci-overview-slide')) {
    return 'overview';
  }

  if (section.classList.contains('ci-specs-slide')) {
    return 'specs';
  }

  if (section.classList.contains('ci-gallery-slide')) {
    return 'gallery';
  }

  if (section.classList.contains('ci-faq-slide')) {
    return 'faq';
  }

  if (section.classList.contains('ci-related-slide')) {
    return 'related';
  }

  if (section.classList.contains('ci-comments-slide')) {
    return 'comments';
  }

  if (section.classList.contains('ci-facts-section')) {
    return 'hero';
  }

  if (section.classList.contains('ci-hero')) {
    return 'hero';
  }

  if (section.classList.contains('ci-section--video')) {
    return 'overview';
  }

  return 'hero';
}

function getBaseWorldForSection(section) {
  return getSectionWorldKey(section) === 'intro' ? getHeroWorld() : CONTENT_WORLD;
}

function initImmersiveSlides() {
  const sections = getImmersiveSections();

  if (!sections.length) {
    return;
  }

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) {
          return;
        }

        const section = entry.target;
        window.dispatchEvent(
          new CustomEvent('carinfo3d:worldchange', {
            detail: {
              section,
              key: getSectionWorldKey(section),
            },
          })
        );
      });
    },
    { threshold: 0.45 }
  );

  sections.forEach((section) => observer.observe(section));
}

function initSmoothSectionScroll() {
  const sections = getImmersiveSections();

  if (sections.length < 2) {
    return;
  }

  let locked = false;

  window.addEventListener(
    'wheel',
    (event) => {
      if (locked || event.ctrlKey || Math.abs(event.deltaY) < 18) {
        return;
      }

      const lightbox = document.querySelector('[data-car-lightbox]');
      if (lightbox && !lightbox.hidden) {
        return;
      }

      if (event.target instanceof Element && event.target.closest('.site-header')) {
        return;
      }

      const current = sections.reduce(
        (best, section, index) => {
          const distance = Math.abs(section.getBoundingClientRect().top);
          return distance < best.distance ? { index, distance } : best;
        },
        { index: 0, distance: Number.POSITIVE_INFINITY }
      );
      const nextIndex = Math.min(
        sections.length - 1,
        Math.max(0, current.index + (event.deltaY > 0 ? 1 : -1))
      );

      if (nextIndex === current.index) {
        return;
      }

      event.preventDefault();
      locked = true;

      const top = getSectionScrollTop(sections[nextIndex], nextIndex);
      window.scrollTo({ top, behavior: 'smooth' });

      window.setTimeout(() => {
        locked = false;
      }, 850);
    },
    { passive: false }
  );
}

async function loadGltfModel(url, dracoPath, bodyColor = DEFAULT_BODY_COLOR) {
  const loader = new GLTFLoader();

  if (dracoPath) {
    const dracoLoader = new DRACOLoader();
    dracoLoader.setDecoderPath(dracoPath);
    loader.setDRACOLoader(dracoLoader);
  }

  const gltf = await loader.loadAsync(url);
  const model = gltf.scene.children[0] || gltf.scene;
  model.traverse((child) => {
    if (child.isMesh) {
      child.castShadow = true;
      child.receiveShadow = true;
    }
  });

  applyCarMaterials(model, bodyColor);
  fitObjectToStage(model, INTRO_MODEL_TARGET_SIZE);
  return model;
}

function initScene(container) {
  const blockConfig = readBlockConfig();
  const hasModel = blockConfig.modelUrl !== '';
  const hasImage = blockConfig.imageUrl !== '' && getHeroVisual();

  let userOrbiting = false;
  const heroWorld = getHeroWorld();
  let carVibrancy = heroWorld.vibrancy;
  let targetCarVibrancy = heroWorld.vibrancy;
  let stageSpinOffset = heroWorld.stageRotation ?? 0;
  let stageSpinSpeed = heroWorld.rotate * 0.11;
  const targetCam = new THREE.Vector3(...heroWorld.cam);
  const targetLook = new THREE.Vector3(...heroWorld.target);

  const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: false });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.setSize(window.innerWidth, window.innerHeight);
  renderer.setClearColor(0xeef2f8);
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.25;
  container.appendChild(renderer.domElement);

  const scene = new THREE.Scene();
  scene.background = new THREE.Color(0xeef2f8);
  scene.fog = new THREE.Fog(heroWorld.fog, 26, 60);

  const pmrem = new THREE.PMREMGenerator(renderer);
  scene.environment = pmrem.fromScene(new RoomEnvironment(), 0.08).texture;

  const camera = new THREE.PerspectiveCamera(
    heroWorld.fov ?? INTRO_CAMERA_FOV,
    window.innerWidth / window.innerHeight,
    0.1,
    120
  );
  camera.position.set(...heroWorld.cam);

  const controls = new OrbitControls(camera, renderer.domElement);
  controls.target.set(...heroWorld.target);
  controls.enableDamping = true;
  controls.dampingFactor = 0.05;
  controls.enableZoom = false;
  controls.enablePan = false;
  controls.minPolarAngle = Math.PI / 5;
  controls.maxPolarAngle = Math.PI / 2.1;
  controls.autoRotate = true;
  controls.autoRotateSpeed = heroWorld.rotate;

  controls.addEventListener('start', () => {
    userOrbiting = true;
    controls.autoRotate = false;
  });

  controls.addEventListener('end', () => {
    userOrbiting = false;
    targetCam.copy(camera.position);
    targetLook.copy(controls.target);
    window.setTimeout(() => {
      if (!userOrbiting) {
        controls.autoRotate = true;
      }
    }, 3000);
  });

  const stageGroup = new THREE.Group();
  scene.add(stageGroup);

  const ground = new THREE.Mesh(
    new THREE.CircleGeometry(22, 80).rotateX(-Math.PI / 2),
    new THREE.MeshStandardMaterial({
      color: 0xf8fafc,
      roughness: 0.55,
      metalness: 0.08,
      emissive: 0xf0f4fa,
      emissiveIntensity: 0.15,
    })
  );
  ground.receiveShadow = true;
  stageGroup.add(ground);

  const grid = new THREE.GridHelper(44, 88, 0xc8d0dc, 0xdce4ee);
  grid.position.y = 0.01;
  stageGroup.add(grid);

  const rearGrid = new THREE.GridHelper(30, 60, 0xd0d8e4, 0xe4eaf2);
  rearGrid.rotation.x = Math.PI / 2;
  rearGrid.position.set(0, 8, -12);
  stageGroup.add(rearGrid);

  initAdBoards(scene, renderer);

  let stageModel = null;
  let stageModelBaseScale = 1;

  const ringMat = new THREE.MeshBasicMaterial({
    color: 0xb8c4d4,
    wireframe: true,
    transparent: true,
    opacity: 0.45,
  });
  [
    [6, 0.04, 16],
    [4.2, 0.03, 12],
    [2.8, 0.025, 8],
  ].forEach(([radius, tube, segments], index) => {
    const ring = new THREE.Mesh(
      new THREE.TorusGeometry(radius, tube, 8, segments),
      ringMat.clone()
    );
    ring.rotation.x = Math.PI / 2;
    ring.position.y = 0.15 + index * 0.08;
    ring.material.opacity = 0.35 - index * 0.08;
    stageGroup.add(ring);
  });

  const pillarMat = new THREE.MeshStandardMaterial({
    color: 0xffffff,
    roughness: 0.3,
    metalness: 0.1,
    emissive: 0xf8fafc,
    emissiveIntensity: 0.4,
    transparent: true,
    opacity: 0.7,
  });
  [
    [-8, 3, -6],
    [8, 2.5, -5],
    [-6, 2, 7],
    [7, 3.5, 6],
  ].forEach(([x, height, z]) => {
    const pillar = new THREE.Mesh(
      new THREE.CylinderGeometry(0.08, 0.12, height, 12),
      pillarMat
    );
    pillar.position.set(x, height / 2, z);
    stageGroup.add(pillar);
  });

  const keyLight = new THREE.DirectionalLight(0xffffff, 2.2);
  keyLight.position.set(6, 12, 8);
  keyLight.castShadow = true;
  scene.add(keyLight);

  const fillLight = new THREE.DirectionalLight(0xe8f0ff, 1.1);
  fillLight.position.set(-8, 6, 4);
  scene.add(fillLight);

  scene.add(new THREE.AmbientLight(0xffffff, 0.85));
  scene.add(new THREE.HemisphereLight(0xffffff, 0xdce4f0, 0.9));

  const rimLight = new THREE.DirectionalLight(heroWorld.rim, 0.6);
  rimLight.position.set(-4, 4, -6);
  scene.add(rimLight);

  window.addEventListener('carinfo3d:worldchange', (event) => {
    const section = event.detail?.section;
    const world = section
      ? getWorldForSection(section)
      : (() => {
          const introWorld = getHeroWorld();

          return {
            cam: [...introWorld.cam],
            target: [...introWorld.target],
            fog: introWorld.fog,
            rim: introWorld.rim,
            rotate: introWorld.rotate,
            vibrancy: introWorld.vibrancy,
            stageRotation: introWorld.stageRotation ?? 0,
          };
        })();

    targetCarVibrancy = world.vibrancy;
    targetCam.set(...world.cam);
    targetLook.set(...world.target);
    stageSpinOffset = world.stageRotation;
    stageSpinSpeed = world.rotate * 0.11;
    controls.autoRotateSpeed = world.rotate;
    scene.fog.color.setHex(world.fog);
    rimLight.color.setHex(world.rim);
  });

  if (hasModel) {
    initColorSwatches();

    const initialBodyColor = blockConfig.defaultColor || DEFAULT_BODY_COLOR;

    loadGltfModel(blockConfig.modelUrl, blockConfig.dracoPath, initialBodyColor)
      .then((model) => {
        hideHeroVisual();
        stageGroup.add(model);
        stageModel = model;
        stageModelBaseScale = model.scale.x || 1;
        applyResponsiveModelScale();
        applyCarVibrancy(carVibrancy);
      })
      .catch((error) => {
        console.warn('[carinfo3d] Model load failed:', error);
        if (hasImage) {
          showHeroVisual();
        }
      });
  } else if (hasImage) {
    showHeroVisual();
  }

  function applyResponsiveModelScale() {
    if (!stageModel) {
      return;
    }

    const targetSize = getResponsiveSceneFactors().modelTargetSize;
    const scaleFactor = targetSize / INTRO_MODEL_TARGET_SIZE;
    stageModel.scale.setScalar(stageModelBaseScale * scaleFactor);
  }

  function onResize() {
    const introWorld = getHeroWorld();

    camera.aspect = window.innerWidth / window.innerHeight;
    camera.fov = introWorld.fov ?? INTRO_CAMERA_FOV;
    camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth, window.innerHeight);
    setChromeHeight();
    applyResponsiveModelScale();

    if (!userOrbiting && isIntroViewport()) {
      targetCam.set(...introWorld.cam);
    }
  }

  window.addEventListener('resize', onResize);

  const clock = new THREE.Clock();
  renderer.setAnimationLoop(() => {
    const elapsed = clock.getElapsedTime();

    if (!userOrbiting) {
      stageGroup.rotation.y = stageSpinOffset + elapsed * stageSpinSpeed;
    }

    carVibrancy += (targetCarVibrancy - carVibrancy) * 0.06;
    applyCarVibrancy(carVibrancy);

    if (!userOrbiting) {
      camera.position.lerp(targetCam, 0.035);
      controls.target.lerp(targetLook, 0.035);
    }

    controls.update();
    renderer.render(scene, camera);
  });

  document.body.classList.add('carinfo3d-page--has-scene');

  const introSection = document.querySelector('#carinfo3d-main > .carinfo3d-section');
  if (introSection) {
    window.dispatchEvent(
      new CustomEvent('carinfo3d:worldchange', {
        detail: {
          section: introSection,
          key: 'intro',
        },
      })
    );
  }
}

function boot() {
  if (!shouldRun()) {
    return;
  }

  document.documentElement.classList.add('carinfo3d-root');
  if (isCarInfo3d2Page()) {
    document.documentElement.classList.add('carinfo3d2-root');
  }
  setChromeHeight();
  initIntroScroll();

  const container = document.getElementById('ci-scene-3d');
  if (!container) {
    return;
  }

  try {
    if (isCarInfo3d2Page()) {
      init3d2ScrollPanel();
    } else {
      groupHeroWithFacts();
      mountOverviewVideo();
    }
    initScene(container);
    initImmersiveSlides();
    if (!isCarInfo3d2Page()) {
      initSmoothSectionScroll();
    }
    scrollToIntroTop('auto');
  } catch (error) {
    console.warn('[carinfo3d] Scene init failed:', error);
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}
