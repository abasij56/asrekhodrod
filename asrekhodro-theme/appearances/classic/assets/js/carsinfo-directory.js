/**
 * Carsinfo encyclopedia directory — brand/model browser.
 */
(function () {
  "use strict";

  var root = document.querySelector("[data-carsinfo-directory]");
  if (!root) {
    return;
  }

  var dataEl = root.querySelector("[data-carsinfo-directory-data]");
  if (!dataEl) {
    return;
  }

  var payload;
  try {
    payload = JSON.parse(dataEl.textContent || "{}");
  } catch (e) {
    return;
  }

  var countries = Array.isArray(payload.countries) ? payload.countries : [];
  var brands = Array.isArray(payload.brands) ? payload.brands : [];

  var state = {
    view: "brands",
    brandId: null,
    countryId: "all",
    query: "",
  };

  var els = {
    viewBrands: root.querySelector('[data-dir-view="brands"]'),
    viewModels: root.querySelector('[data-dir-view="models"]'),
    brandsGrid: root.querySelector("[data-dir-brands]"),
    modelsGrid: root.querySelector("[data-dir-models]"),
    brandsEmpty: root.querySelector("[data-dir-brands-empty]"),
    modelsEmpty: root.querySelector("[data-dir-models-empty]"),
    search: root.querySelector("[data-dir-search]"),
    count: root.querySelector("[data-dir-count]"),
    filters: root.querySelector("[data-dir-filters]"),
    back: root.querySelector("[data-dir-back]"),
    stepBrands: root.querySelector('[data-dir-step="brands"]'),
    stepModels: root.querySelector('[data-dir-step="models"]'),
    stepHub: root.querySelector('[data-dir-step="hub"]'),
    modelsBrandLogo: root.querySelector("[data-dir-models-logo]"),
    modelsBrandName: root.querySelector("[data-dir-models-name]"),
    modelsBrandDesc: root.querySelector("[data-dir-models-desc]"),
  };

  function normalize(str) {
    return (str || "").toLowerCase().trim();
  }

  function countryName(id) {
    if (id === "all") {
      return "";
    }
    var match = countries.find(function (c) {
      return String(c.id) === String(id);
    });
    return match ? match.name : "";
  }

  function modelsForBrand(brand, countryId) {
    if (!brand || !Array.isArray(brand.models)) {
      return [];
    }
    if (countryId === "all") {
      return brand.models;
    }
    return brand.models.filter(function (model) {
      var ids = Array.isArray(model.countries) ? model.countries : [];
      return ids.some(function (id) {
        return String(id) === String(countryId);
      });
    });
  }

  function brandMatches(brand) {
    var q = normalize(state.query);
    var models = modelsForBrand(brand, state.countryId);

    if (state.view === "brands") {
      if (models.length === 0) {
        return false;
      }
      if (!q) {
        return true;
      }
      if (normalize(brand.name).indexOf(q) !== -1) {
        return true;
      }
      return models.some(function (model) {
        return (
          normalize(model.title).indexOf(q) !== -1 ||
          normalize(model.subtitle).indexOf(q) !== -1
        );
      });
    }

    return true;
  }

  function modelMatches(model, brand) {
    var q = normalize(state.query);
    if (!q) {
      return true;
    }
    return (
      normalize(model.title).indexOf(q) !== -1 ||
      normalize(model.subtitle).indexOf(q) !== -1 ||
      normalize(brand.name).indexOf(q) !== -1
    );
  }

  function setSteps() {
    if (!els.stepBrands || !els.stepModels || !els.stepHub) {
      return;
    }

    els.stepBrands.className =
      "carsinfo-directory__step" +
      (state.view === "brands" ? " is-active" : " is-done");
    els.stepModels.className =
      "carsinfo-directory__step" +
      (state.view === "models"
        ? " is-active"
        : state.view === "brands"
          ? ""
          : " is-done");
    els.stepHub.className = "carsinfo-directory__step";
  }

  function showView(name) {
    state.view = name;

    if (els.viewBrands) {
      els.viewBrands.classList.toggle("is-visible", name === "brands");
      els.viewBrands.hidden = name !== "brands";
    }
    if (els.viewModels) {
      els.viewModels.classList.toggle("is-visible", name === "models");
      els.viewModels.hidden = name !== "models";
    }
    if (els.filters) {
      els.filters.hidden = name !== "brands";
    }

    setSteps();
    render();
  }

  function renderFilters() {
    if (!els.filters) {
      return;
    }

    els.filters.innerHTML = "";

    var allBtn = document.createElement("button");
    allBtn.type = "button";
    allBtn.className =
      "carsinfo-directory__filter" +
      (state.countryId === "all" ? " is-active" : "");
    allBtn.setAttribute("data-filter", "all");
    allBtn.textContent = "همه";
    els.filters.appendChild(allBtn);

    countries.forEach(function (country) {
      var btn = document.createElement("button");
      btn.type = "button";
      btn.className =
        "carsinfo-directory__filter" +
        (String(state.countryId) === String(country.id) ? " is-active" : "");
      btn.setAttribute("data-filter", String(country.id));
      btn.textContent = country.name;
      els.filters.appendChild(btn);
    });
  }

  function renderBrandLogo(container, brand) {
    if (!container) {
      return;
    }

    container.classList.add("carsinfo-directory__brand-logo");
    container.classList.remove("carsinfo-directory__brand-logo--image");
    container.textContent = "";
    container.removeAttribute("aria-hidden");

    var logo = brand.logo || {};
    if (logo.url) {
      container.classList.add("carsinfo-directory__brand-logo--image");
      var img = document.createElement("img");
      img.src = logo.url;
      img.alt = logo.alt || brand.name || "";
      img.loading = "lazy";
      img.decoding = "async";
      if (logo.width) {
        img.width = logo.width;
      }
      if (logo.height) {
        img.height = logo.height;
      }
      container.appendChild(img);
      return;
    }

    container.classList.remove("carsinfo-directory__brand-logo--image");
    container.textContent = brand.abbr || "?";
  }

  function renderBrands() {
    if (!els.brandsGrid) {
      return;
    }

    var visible = 0;
    els.brandsGrid.innerHTML = "";

    brands.forEach(function (brand, brandIndex) {
      if (!brandMatches(brand)) {
        return;
      }

      var models = modelsForBrand(brand, state.countryId);
      visible += 1;

      var card = document.createElement("button");
      card.type = "button";
      card.className = "carsinfo-directory__brand";
      card.style.setProperty("--ci-dir-i", String(brandIndex));
      card.setAttribute("data-brand-id", String(brand.id));

      var logo = document.createElement("div");
      renderBrandLogo(logo, brand);

      var name = document.createElement("div");
      name.className = "carsinfo-directory__brand-name";
      name.textContent = brand.name;

      var meta = document.createElement("div");
      meta.className = "carsinfo-directory__brand-meta";
      meta.textContent = models.length + " مدل";

      card.appendChild(logo);
      card.appendChild(name);
      card.appendChild(meta);

      var originName = countryName(state.countryId);
      if (originName) {
        var origin = document.createElement("span");
        origin.className = "carsinfo-directory__brand-origin";
        origin.textContent = originName;
        card.appendChild(origin);
      }

      card.addEventListener("click", function () {
        openBrand(brand.id);
      });

      els.brandsGrid.appendChild(card);
    });

    if (els.brandsEmpty) {
      els.brandsEmpty.hidden = visible !== 0;
    }
    if (els.count) {
      els.count.textContent = visible + " برند";
    }
  }

  function renderModels() {
    var brand = brands.find(function (b) {
      return String(b.id) === String(state.brandId);
    });
    if (!brand) {
      showView("brands");
      return;
    }

    var models = modelsForBrand(brand, state.countryId).filter(function (model) {
      return modelMatches(model, brand);
    });

    if (els.modelsBrandLogo) {
      renderBrandLogo(els.modelsBrandLogo, brand);
    }
    if (els.modelsBrandName) {
      els.modelsBrandName.textContent = brand.name;
    }
    if (els.modelsBrandDesc) {
      var countryLabel = countryName(state.countryId);
      var desc = models.length + " مدل در دانشنامه";
      if (countryLabel) {
        desc += " · " + countryLabel;
      }
      els.modelsBrandDesc.textContent = desc;
    }

    if (!els.modelsGrid) {
      return;
    }

    els.modelsGrid.innerHTML = "";

    models.forEach(function (model) {
      var card = document.createElement("article");
      card.className = "carsinfo-directory__model";

      var link = document.createElement("a");
      link.className = "carsinfo-directory__model-link";
      link.href = model.url || "#";

      var media = document.createElement("div");
      media.className = "carsinfo-directory__model-media";

      if (model.image && model.image.url) {
        var img = document.createElement("img");
        img.src = model.image.url;
        img.alt =
          (model.image.alt || brand.name + " " + model.title).trim();
        img.loading = "lazy";
        img.decoding = "async";
        if (model.image.width) {
          img.width = model.image.width;
        }
        if (model.image.height) {
          img.height = model.image.height;
        }
        media.appendChild(img);
      }

      var body = document.createElement("div");
      body.className = "carsinfo-directory__model-body";

      var title = document.createElement("div");
      title.className = "carsinfo-directory__model-title";
      title.textContent = model.title;

      body.appendChild(title);

      if (model.subtitle) {
        var sub = document.createElement("div");
        sub.className = "carsinfo-directory__model-sub";
        sub.textContent = model.subtitle;
        body.appendChild(sub);
      }

      if (Array.isArray(model.specs) && model.specs.length) {
        var specs = document.createElement("div");
        specs.className = "carsinfo-directory__model-specs";
        model.specs.forEach(function (spec) {
          var span = document.createElement("span");
          span.className = "carsinfo-directory__model-spec";
          span.textContent = spec;
          specs.appendChild(span);
        });
        body.appendChild(specs);
      }

      var cta = document.createElement("div");
      cta.className = "carsinfo-directory__model-cta";
      cta.textContent = "ورود به صفحه خودرو";
      body.appendChild(cta);

      link.appendChild(media);
      link.appendChild(body);
      card.appendChild(link);
      els.modelsGrid.appendChild(card);
    });

    if (els.modelsEmpty) {
      els.modelsEmpty.hidden = models.length !== 0;
    }
    if (els.count) {
      els.count.textContent = models.length + " مدل · " + brand.name;
    }
  }

  function render() {
    if (state.view === "brands") {
      renderBrands();
    } else {
      renderModels();
    }
  }

  function openBrand(id) {
    state.brandId = id;
    state.query = "";
    if (els.search) {
      els.search.value = "";
    }
    showView("models");
  }

  if (els.search) {
    els.search.addEventListener("input", function () {
      state.query = els.search.value;

      if (state.view === "brands") {
        renderBrands();
        return;
      }

      var q = normalize(state.query);
      if (q) {
        var match = brands.find(function (b) {
          if (normalize(b.name).indexOf(q) !== -1) {
            return true;
          }
          return modelsForBrand(b, state.countryId).some(function (m) {
            return normalize(m.title).indexOf(q) !== -1;
          });
        });
        if (match && String(match.id) !== String(state.brandId)) {
          state.brandId = match.id;
        }
      }

      renderModels();
    });
  }

  if (els.filters) {
    els.filters.addEventListener("click", function (e) {
      var btn = e.target.closest(".carsinfo-directory__filter");
      if (!btn) {
        return;
      }

      state.countryId = btn.getAttribute("data-filter") || "all";
      els.filters.querySelectorAll(".carsinfo-directory__filter").forEach(function (b) {
        b.classList.toggle("is-active", b === btn);
      });
      render();
    });
  }

  if (els.back) {
    els.back.addEventListener("click", function () {
      state.brandId = null;
      state.query = "";
      if (els.search) {
        els.search.value = "";
      }
      showView("brands");
    });
  }

  renderFilters();
  renderBrands();
})();
