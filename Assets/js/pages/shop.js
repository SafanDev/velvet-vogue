document.addEventListener("DOMContentLoaded", function () {
  "use strict";
  gsap.registerPlugin(ScrollTrigger);

  // ==========================================
  // Master Color Mapping
  // ==========================================
  const colorMap = {
    Black: "#000000",
    White: "#ffffff",
    Grey: "#808080",
    Beige: "#F5F5DC",
    Navy: "#000080",
    Blue: "#0000FF",
    Red: "#FF0000",
    Burgundy: "#800020",
    Pink: "#FFC0CB",
    Purple: "#800080",
    Green: "#008000",
    Olive: "#808000",
    Brown: "#8B4513",
    Yellow: "#FFFF00",
    Gold: "#D4AF37",
    Silver: "#C0C0C0",
    Standard: "#222222",
  };

  // ==========================================
  // 1. GSAP ENTRANCE & CINEMATIC SCROLL
  // ==========================================
  const revealArchive = () => {
    gsap.from(".hero-content-block", {
      y: 30,
      opacity: 0,
      duration: 1.2,
      ease: "power4.out",
    });
    gsap.from(".gsap-reveal", {
      scrollTrigger: { trigger: ".archive-grid", start: "top 85%" },
      y: 40,
      opacity: 0,
      duration: 0.8,
      stagger: 0.1,
      ease: "power3.out",
    });
  };
  revealArchive();

  const heroTimeline = gsap.timeline({
    scrollTrigger: {
      trigger: ".hero-scroll-container",
      start: "top top",
      end: "bottom top",
      scrub: 1.2,
    },
  });

  heroTimeline.to(
    ".hero-bg",
    {
      scale: 1,
      filter: "brightness(0.1) grayscale(80%) blur(10px)",
      y: "20%",
      ease: "none",
    },
    0,
  );
  heroTimeline.to(
    ".hero-content-block",
    { y: "-30%", scale: 0.85, opacity: 0, ease: "none" },
    0,
  );

  ScrollTrigger.create({
    trigger: ".archive-wrapper",
    start: "top -50%",
    onEnter: () =>
      document.getElementById("stickyActionBar").classList.add("is-stuck"),
    onLeaveBack: () =>
      document.getElementById("stickyActionBar").classList.remove("is-stuck"),
  });

  // ==========================================
  // 2. MOBILE FILTER DRAWER LOGIC
  // ==========================================
  const filterSidebar = document.getElementById("filterSidebar");
  const mobileFilterOverlay = document.getElementById("mobileFilterOverlay");
  const openMobileFiltersBtn = document.getElementById("openMobileFilters");
  const closeMobileFiltersBtn = document.getElementById("closeMobileFilters");
  const applyMobileFiltersBtn = document.getElementById(
    "applyMobileFiltersBtn",
  );

  const toggleMobileFilters = (show) => {
    if (show) {
      filterSidebar.classList.add("open");
      mobileFilterOverlay.classList.add("open");
      document.body.style.overflow = "hidden";
    } else {
      filterSidebar.classList.remove("open");
      mobileFilterOverlay.classList.remove("open");
      document.body.style.overflow = "";
    }
  };

  if (openMobileFiltersBtn)
    openMobileFiltersBtn.addEventListener("click", () =>
      toggleMobileFilters(true),
    );
  if (closeMobileFiltersBtn)
    closeMobileFiltersBtn.addEventListener("click", () =>
      toggleMobileFilters(false),
    );
  if (mobileFilterOverlay)
    mobileFilterOverlay.addEventListener("click", () =>
      toggleMobileFilters(false),
    );
  if (applyMobileFiltersBtn) {
    applyMobileFiltersBtn.addEventListener("click", () => {
      toggleMobileFilters(false);
      executeFilterFetch();
    });
  }

  // ==========================================
  // 3. GHOST SEARCH & AJAX
  // ==========================================
  const searchOverlay = document.getElementById("searchOverlay");
  const searchInput = document.getElementById("ghostSearchInput");
  const searchResults = document.getElementById("searchResults");
  const searchCache = new Map();
  let searchTimeout;
  let searchController = null;

  document
    .getElementById("triggerGhostSearch")
    .addEventListener("click", () => {
      searchOverlay.style.display = "flex";
      gsap.to(searchOverlay, { opacity: 1, duration: 0.4 });
      setTimeout(() => searchInput.focus(), 100);
    });

  const hideSearch = () => {
    gsap.to(searchOverlay, {
      opacity: 0,
      duration: 0.3,
      onComplete: () => {
        searchOverlay.style.display = "none";
        searchInput.value = "";
        searchResults.innerHTML = "";
      },
    });
  };
  document.getElementById("closeSearch").addEventListener("click", hideSearch);
  window.addEventListener("keydown", (e) => {
    if (e.key === "Escape") hideSearch();
  });

  searchInput.addEventListener("input", function () {
    clearTimeout(searchTimeout);
    const query = this.value.trim();

    if (query.length < 2) {
      searchController?.abort();
      searchResults.innerHTML = "";
      return;
    }

    searchTimeout = setTimeout(async () => {
      const cacheKey = query.toLocaleLowerCase();
      const cached = searchCache.get(cacheKey);
      if (cached) {
        searchResults.innerHTML = cached;
        return;
      }

      searchController?.abort();
      searchController = new AbortController();

      try {
        const url = new URL("../Actions/search_action.php", window.location.href);
        url.searchParams.set("q", query);
        const response = await fetch(url, {
          method: "GET",
          headers: { Accept: "text/html" },
          signal: searchController.signal,
        });

        if (!response.ok) throw new Error(`Search request failed (${response.status})`);
        const html = await response.text();
        if (searchInput.value.trim() !== query) return;

        searchCache.set(cacheKey, html);
        if (searchCache.size > 30) searchCache.delete(searchCache.keys().next().value);
        searchResults.innerHTML = html;
        gsap.from(".search-result-item", {
          opacity: 0,
          y: 10,
          stagger: 0.05,
          duration: 0.4,
        });
      } catch (error) {
        if (error.name !== "AbortError") console.error("Search Error:", error);
      }
    }, 250);
  });

  // ==========================================
  // 4. CUSTOM SORT DROPDOWN
  // ==========================================
  const sortDropdown = document.getElementById("customSort");
  const sortSelected = document.getElementById("sortSelected");
  const sortOptions = document.querySelectorAll(".sort-options li");

  sortSelected.addEventListener("click", () =>
    sortDropdown.classList.toggle("open"),
  );

  sortOptions.forEach((opt) => {
    opt.addEventListener("click", function () {
      sortOptions.forEach((o) => o.classList.remove("active"));
      this.classList.add("active");
      sortSelected.innerHTML = `<span class="d-none d-sm-inline">SORT:</span> ${this.innerText} <i class="fa-solid fa-chevron-down ms-2"></i>`;
      sortDropdown.classList.remove("open");
      executeFilterFetch();
    });
  });

  document.addEventListener("click", (e) => {
    if (!sortDropdown.contains(e.target)) sortDropdown.classList.remove("open");
  });

  // ==========================================
  // 5. FILTERS: SLIDERS, SIZES, COLORS
  // ==========================================
  const priceSlider = document.getElementById("priceRangeSlider");
  const priceDisplay = document.getElementById("priceDisplay");
  if (priceSlider) {
    priceSlider.addEventListener("input", function () {
      priceDisplay.innerText =
        "RS. " + Number(this.value).toLocaleString("en-IN");
    });
    priceSlider.addEventListener("change", () => executeFilterFetch());
  }

  document.querySelectorAll(".filter-sidebar .size-btn").forEach((btn) => {
    btn.addEventListener("click", function () {
      this.classList.toggle("active");
      if (window.innerWidth >= 992) executeFilterFetch();
    });
  });

  document.querySelectorAll(".attr-pill").forEach((btn) => {
    btn.addEventListener("click", function () {
      this.classList.toggle("active");
      if (window.innerWidth >= 992) executeFilterFetch();
    });
  });

  // ==========================================
  // 6. HOVER WISHLIST & AJAX
  // ==========================================

  // ==========================================
  // 7. MAGNETIC ADD BUTTONS (GRID)
  // ==========================================
  const initMagneticButtons = () => {
    if (window.innerWidth < 992) return;
    document.querySelectorAll(".btn-add-magnetic:not([data-magnetic-ready])").forEach((btn) => {
      btn.dataset.magneticReady = "true";
      btn.addEventListener("mousemove", function (e) {
        const rect = btn.getBoundingClientRect();
        gsap.to(btn, {
          x: (e.clientX - rect.left - rect.width / 2) * 0.15,
          y: (e.clientY - rect.top - rect.height / 2) * 0.15,
          duration: 0.3,
        });
      });
      btn.addEventListener("mouseleave", function () {
        gsap.to(btn, {
          x: 0,
          y: 0,
          duration: 0.6,
          ease: "elastic.out(1, 0.3)",
        });
      });
    });
  };
  initMagneticButtons();

  // ==========================================
  // 8. DYNAMIC ATC MODAL & REAL DB DATA
  // ==========================================
  const atcModal = document.getElementById("atcModal");
  const atcOverlay = document.getElementById("atcOverlay");
  const modalImg = document.getElementById("modalProductImg");
  const imgLoader = document.getElementById("modalImgLoader");
  const colorContainer = document.getElementById("modalColorSwatches");
  const sizeContainer = document.getElementById("modalSizeSwatches");
  const modalContent = document.querySelector(".atc-modal-content");
  const imgContainer = document.getElementById("modalImgContainer");
  const qtyInput = document.getElementById("qtyInput");
  const confirmAddButton = document.getElementById("confirmAddToCart");
  const confirmMaskText = document.getElementById("maskBtnText");

  let currentProductID = null;
  let currentVariants = { colors: {}, sizes: [], combinations: {} };
  let cartRequestController = null;

  const sizeOrder = ["XS", "S", "M", "L", "XL", "XXL", "OS", "N/A", "STANDARD"];

  const sortSizes = (items) => [...items].sort((a, b) => {
    const left = sizeOrder.indexOf(String(a.size || a).toUpperCase());
    const right = sizeOrder.indexOf(String(b.size || b).toUpperCase());
    return (left === -1 ? 999 : left) - (right === -1 ? 999 : right);
  });

  const normaliseVariants = (variants) => {
    const source = variants && typeof variants === "object" ? variants : {};
    const colors = source.colors && typeof source.colors === "object" ? source.colors : {};
    const combinations = source.combinations && typeof source.combinations === "object"
      ? source.combinations
      : {};

    const cleanedCombinations = {};
    Object.entries(combinations).forEach(([color, options]) => {
      if (!Array.isArray(options)) return;
      const cleaned = options
        .map((option) => ({
          size: String(option?.size || "").trim(),
          stock: Math.max(0, Number(option?.stock || 0)),
        }))
        .filter((option) => option.size && option.stock > 0);
      if (cleaned.length) cleanedCombinations[color] = cleaned;
    });

    return {
      colors,
      sizes: Array.isArray(source.sizes) ? source.sizes : [],
      combinations: cleanedCombinations,
    };
  };

  const variantOptionsForColor = (color) => {
    const combinations = currentVariants.combinations || {};
    const direct = Array.isArray(combinations[color]) ? combinations[color] : [];
    if (direct.length) return sortSizes(direct.filter((item) => Number(item.stock || 0) > 0));

    // Compatibility for products created before the variant-matrix update.
    return sortSizes((currentVariants.sizes || []).map((size) => ({ size, stock: 10 })));
  };

  const renderModalSizes = (color) => {
    sizeContainer.replaceChildren();
    const options = variantOptionsForColor(color);

    options.forEach((option, index) => {
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = `size-btn-horiz animate-magnetic ${index === 0 ? "active" : ""}`;
      btn.innerText = option.size;
      btn.dataset.size = option.size;
      btn.dataset.stock = String(Math.max(1, Number(option.stock || 1)));
      btn.addEventListener("click", function () {
        sizeContainer.querySelectorAll(".size-btn-horiz").forEach((sizeBtn) => sizeBtn.classList.remove("active"));
        this.classList.add("active");
        const maxQuantity = Math.min(10, Number(this.dataset.stock || 1));
        qtyInput.value = Math.min(Number(qtyInput.value || 1), maxQuantity);
      });
      sizeContainer.appendChild(btn);
    });

    if (!options.length) {
      const unavailable = document.createElement("button");
      unavailable.type = "button";
      unavailable.className = "size-btn-horiz";
      unavailable.disabled = true;
      unavailable.innerText = "UNAVAILABLE";
      sizeContainer.appendChild(unavailable);
    }
  };

  const resetMiniCartButton = () => {
    if (confirmAddButton) {
      confirmAddButton.disabled = false;
      confirmAddButton.textContent = "ADD TO CART";
      confirmAddButton.removeAttribute("aria-busy");
    }
    if (confirmMaskText) {
      confirmMaskText.textContent = "ADD TO CART";
      confirmMaskText.style.opacity = "1";
    }
  };

  const setMiniCartBusy = (busy) => {
    if (confirmAddButton) {
      confirmAddButton.disabled = busy;
      confirmAddButton.textContent = busy ? "ADDING..." : "ADD TO CART";
      if (busy) confirmAddButton.setAttribute("aria-busy", "true");
      else confirmAddButton.removeAttribute("aria-busy");
    }
    if (confirmMaskText) {
      confirmMaskText.textContent = busy ? "ADDING..." : "ADD TO CART";
      confirmMaskText.style.opacity = "1";
    }
  };

  const showMiniCartError = (message) => {
    resetMiniCartButton();
    if (window.Swal) {
      Swal.fire({
        title: "Cart Update Failed",
        text: message || "The item could not be added.",
        icon: "error",
        background: "#0a0a0a",
        color: "#fff",
        confirmButtonColor: "#D4AF37",
      });
      return;
    }
    window.alert(message || "The item could not be added.");
  };

  window.openAddToCartModal = function (
    productID,
    productName,
    price,
    defaultImg,
    variantsJSON,
  ) {
    currentProductID = productID;

    document.getElementById("modalProductName").innerText = productName;
    document.getElementById("modalProductPrice").innerText =
      "RS. " + Number(price).toLocaleString("en-IN");

    try {
      const parsedVariants = typeof variantsJSON === "string" ? JSON.parse(variantsJSON) : variantsJSON;
      currentVariants = normaliseVariants(parsedVariants);
    } catch {
      currentVariants = { colors: {}, sizes: [], combinations: {} };
    }

    const colorLabel = document.getElementById("selectedColorName");
    colorContainer.replaceChildren();
    sizeContainer.replaceChildren();
    modalImg.src = defaultImg;

    const colors = Object.entries(currentVariants.colors || {})
      .filter(([colorName]) => variantOptionsForColor(colorName).length > 0);

    Object.keys(currentVariants.combinations || {}).forEach((colorName) => {
      if (!colors.some(([existingColor]) => existingColor === colorName)) {
        colors.push([colorName, defaultImg]);
      }
    });

    if (!colors.length && (currentVariants.sizes || []).length) {
      colors.push(["Standard", defaultImg]);
    }

    colors.forEach(([colorName, imagePath], index) => {
      const hex = colorMap[colorName] || colorMap.Standard;
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = `color-swatch animate-magnetic ${index === 0 ? "active" : ""}`;
      btn.style.background = hex;
      btn.dataset.color = colorName;
      btn.dataset.img = imagePath || defaultImg;
      btn.title = colorName;

      if (index === 0) {
        modalImg.src = btn.dataset.img;
        colorLabel.innerText = colorName.toUpperCase();
        renderModalSizes(colorName);
      }

      btn.addEventListener("click", function () {
        colorContainer.querySelectorAll(".color-swatch").forEach((swatch) => swatch.classList.remove("active"));
        this.classList.add("active");
        colorLabel.innerText = this.dataset.color.toUpperCase();
        renderModalSizes(this.dataset.color);
        qtyInput.value = 1;

        const targetImage = this.dataset.img || defaultImg;
        if (targetImage && targetImage !== modalImg.getAttribute("src")) {
          imgLoader.classList.add("active");
          const tempImg = new Image();
          tempImg.className = "atc-modal-crossfade-img";
          tempImg.src = targetImage;
          tempImg.onload = () => {
            imgContainer.appendChild(tempImg);
            imgLoader.classList.remove("active");
            gsap.to(tempImg, {
              opacity: 1,
              duration: 0.5,
              ease: "power2.inOut",
              onComplete: () => {
                modalImg.src = targetImage;
                tempImg.remove();
              },
            });
          };
          tempImg.onerror = () => imgLoader.classList.remove("active");
        }
      });
      colorContainer.appendChild(btn);
    });

    qtyInput.value = 1;
    if (colors.length && sizeContainer.querySelector(".size-btn-horiz.active")) {
      resetMiniCartButton();
    } else {
      setMiniCartBusy(false);
      confirmAddButton.disabled = true;
      confirmAddButton.textContent = "UNAVAILABLE";
      confirmMaskText.textContent = "UNAVAILABLE";
      colorLabel.innerText = "UNAVAILABLE";
    }

    atcOverlay.classList.add("open");
    atcModal.classList.add("open");
    document.body.style.overflow = "hidden";
  };

  const closeAtcModal = () => {
    cartRequestController?.abort();
    cartRequestController = null;
    atcModal.classList.remove("open");
    atcOverlay.classList.remove("open");
    document.body.style.overflow = "";

    setTimeout(() => {
      gsap.set(atcOverlay, { clearProps: "all" });
      gsap.set(modalContent, { clearProps: "all" });
      gsap.set(modalImg, { clearProps: "all" });
      currentProductID = null;
      currentVariants = { colors: {}, sizes: [], combinations: {} };
      resetMiniCartButton();
    }, 500);
  };
  document
    .getElementById("closeAtcModal")
    .addEventListener("click", closeAtcModal);
  atcOverlay.addEventListener("click", closeAtcModal);

  document.getElementById("qtyMinus").addEventListener("click", () => {
    if (qtyInput.value > 1) qtyInput.value--;
  });
  document.getElementById("qtyPlus").addEventListener("click", () => {
    const activeSize = sizeContainer.querySelector(".size-btn-horiz.active");
    const maxQuantity = Math.min(10, Number(activeSize?.dataset.stock || 10));
    if (Number(qtyInput.value) < maxQuantity) qtyInput.value++;
  });

  // ==========================================
  // 9. UNIFIED ADD TO CART ANIMATION
  // ==========================================
  confirmAddButton.addEventListener("click", function () {
      if (!currentProductID || this.disabled) return;

      const activeColorBtn = colorContainer.querySelector(".color-swatch.active");
      const activeSizeBtn = sizeContainer.querySelector(".size-btn-horiz.active");
      if (!activeColorBtn || !activeSizeBtn) {
        showMiniCartError("Select an available color and size.");
        return;
      }

      setMiniCartBusy(true);

      const selectedColor = activeColorBtn.dataset.color;
      const selectedSize = activeSizeBtn.dataset.size || activeSizeBtn.innerText;
      const stockLimit = Math.min(10, Math.max(1, Number(activeSizeBtn.dataset.stock || 1)));
      const quantity = Math.min(stockLimit, Math.max(1, Number(qtyInput.value || 1)));
      qtyInput.value = String(quantity);

      const formData = new FormData();
      formData.append("product_id", currentProductID);
      formData.append("color", selectedColor);
      formData.append("size", selectedSize);
      formData.append("quantity", quantity);
      const csrfToken = window.VelvetVogueSecurity?.csrfToken;
      if (csrfToken) formData.set("_csrf", csrfToken);

      cartRequestController?.abort();
      cartRequestController = new AbortController();
      const requestOptions = {
        method: "POST",
        body: formData,
        credentials: "same-origin",
        signal: cartRequestController.signal,
      };
      const cartRequest = window.VelvetVogueSecurity?.fetchJson
        ? window.VelvetVogueSecurity.fetchJson("../Actions/add_to_cart.php", requestOptions)
        : fetch("../Actions/add_to_cart.php", requestOptions).then(async (response) => {
            const data = await response.json().catch(() => ({}));
            if (!response.ok || data.status !== "success") {
              throw new Error(data.message || "The item could not be added.");
            }
            return data;
          });

      cartRequest
        .then((data) => {
            cartRequestController = null;
            const headerCartIcon =
              document.querySelector('a[href*="cart.php"]') ||
              document.querySelector(".fa-bag-shopping");
            let targetTop = "25px";
            let targetLeft = "calc(100vw - 60px)";

            if (headerCartIcon) {
              const cartRect = headerCartIcon.getBoundingClientRect();
              targetTop = cartRect.top + cartRect.height / 2 + "px";
              targetLeft = cartRect.left + cartRect.width / 2 + "px";
            }

            const imgRect = modalImg.getBoundingClientRect();
            const flyingImg = document.createElement("img");
            flyingImg.src = modalImg.src;
            flyingImg.style.position = "fixed";
            flyingImg.style.top = imgRect.top + "px";
            flyingImg.style.left = imgRect.left + "px";
            flyingImg.style.width = imgRect.width + "px";
            flyingImg.style.height = imgRect.height + "px";
            flyingImg.style.objectFit = "cover";
            flyingImg.style.zIndex = "99999";
            flyingImg.style.pointerEvents = "none";
            document.body.appendChild(flyingImg);

            modalImg.style.opacity = "0";

            const dramaTl = gsap.timeline({
              onComplete: () => {
                flyingImg.remove();
                closeAtcModal();
                gsap.to(modalImg, { opacity: 1, duration: 0.5 });
                setTimeout(resetMiniCartButton, 500);
              },
            });

            // Fade out modal background and content while animating
            dramaTl.to(
              atcOverlay,
              { backgroundColor: "rgba(0,0,0,1)", duration: 0.3 },
              0,
            );
            dramaTl.to(
              modalContent,
              {
                opacity: 0,
                x: window.innerWidth > 768 ? 30 : 0,
                y: window.innerWidth <= 768 ? 30 : 0,
                duration: 0.4,
                ease: "power2.in",
              },
              0,
            );

            // UNIFIED BIG AND CENTER ANIMATION
            dramaTl.to(
              flyingImg,
              {
                top: "50vh",
                left: "50vw",
                xPercent: -50,
                yPercent: -50,
                width: window.innerWidth > 768 ? "400px" : "260px",
                height: window.innerWidth > 768 ? "533px" : "346px",
                boxShadow: "0 40px 80px rgba(212,175,55,0.4)",
                rotation: -2,
                duration: 0.6,
                ease: "power3.out",
              },
              0,
            );

            // SUCK TO CART ANIMATION
            dramaTl.to(
              flyingImg,
              {
                top: targetTop,
                left: targetLeft,
                width: "15px",
                height: "20px",
                opacity: 0,
                rotation: 45,
                duration: 0.4,
                ease: "power4.in",
              },
              "+=0.15",
            );

            dramaTl.call(
              () => {
                if (headerCartIcon) {
                  gsap.fromTo(
                    headerCartIcon,
                    { scale: 1, rotation: 0 },
                    {
                      scale: 0.65,
                      rotation: -15,
                      duration: 0.15,
                      ease: "power3.out",
                      color: "var(--color-gold-metallic)",
                    },
                  );
                  gsap.to(headerCartIcon, {
                    scale: 1,
                    rotation: 0,
                    duration: 0.6,
                    ease: "elastic.out(1.2, 0.3)",
                    delay: 0.15,
                    clearProps: "color",
                  });

                  const glow = document.createElement("div");
                  glow.className = "cart-singularity-glow";
                  glow.style.top = targetTop;
                  glow.style.left = targetLeft;
                  document.body.appendChild(glow);

                  gsap.fromTo(
                    glow,
                    { scale: 2.5, opacity: 0 },
                    {
                      scale: 0,
                      opacity: 1,
                      duration: 0.3,
                      ease: "expo.in",
                      onComplete: () => glow.remove(),
                    },
                  );

                  const flare = document.createElement("div");
                  flare.className = "cart-singularity-flare";
                  flare.style.top = targetTop;
                  flare.style.left = targetLeft;
                  document.body.appendChild(flare);

                  const flareTl = gsap.timeline({
                    onComplete: () => flare.remove(),
                  });
                  flareTl
                    .fromTo(
                      flare,
                      { width: 0, opacity: 1 },
                      { width: "140px", duration: 0.15, ease: "power2.out" },
                    )
                    .to(flare, {
                      width: 0,
                      opacity: 0,
                      duration: 0.2,
                      ease: "power2.in",
                    });
                }

                setTimeout(() => {
                  if (typeof window.updateGlobalCartBadge === "function") {
                    window.updateGlobalCartBadge(data.cart_count);
                  }
                }, 150);
              },
              null,
              "-=0.1",
            );
        })
        .catch((error) => {
          cartRequestController = null;
          if (error.name === "AbortError") {
            resetMiniCartButton();
            return;
          }
          console.error("Cart Fetch Error:", error);
          showMiniCartError(error.message);
        });
    });

  // ==========================================
  // 10. PAGINATION & REAL-TIME FILTERS
  // ==========================================
  let currentPage = 1;
  const grid = document.getElementById("mainProductGrid");
  const loadMoreBtn = document.getElementById("loadMoreBtn");
  const loadMoreBtnWrapper = loadMoreBtn ? loadMoreBtn.parentElement : null;

  const filterCache = new Map();
  let filterController = null;

  const buildFilterParams = () => {
    const params = new URLSearchParams();
    const activeCategory = document.querySelector(".vv-btn-cat.active")?.dataset.filter || "All";
    const activeSort = document.querySelector(".sort-options li.active")?.dataset.val || "newest";
    const maxPrice = document.getElementById("priceRangeSlider")?.value || "100000";
    const selectedGenders = [...document.querySelectorAll(".filter-chk:checked")].map((item) => item.value);
    const selectedSizes = [...document.querySelectorAll(".filter-sidebar .size-btn.active")].map((item) => item.innerText.trim());
    const selectedColors = [...document.querySelectorAll(".attr-pill.active")].map((item) => item.dataset.value);

    params.set("category", activeCategory);
    params.set("sort", activeSort);
    params.set("max_price", maxPrice);
    params.set("genders", JSON.stringify(selectedGenders));
    params.set("sizes", JSON.stringify(selectedSizes));
    params.set("colors", JSON.stringify(selectedColors));
    params.set("page", String(currentPage));
    return params;
  };

  const renderFilterTags = () => {
    const container = document.getElementById("activeTagsContainer");
    const row = document.getElementById("activeFiltersRow");
    const telemetry = document.getElementById("sidebarTelemetry");
    const tags = [];
    const addTag = (label, className = "filter-tag") => {
      const tag = document.createElement("span");
      tag.className = className;
      tag.textContent = label;
      tags.push(tag);
    };

    const category = document.querySelector(".vv-btn-cat.active")?.dataset.filter;
    if (category && category !== "All") addTag(category, "filter-tag d-none d-sm-inline-flex");
    document.querySelectorAll(".filter-chk:checked").forEach((item) => addTag(item.value));
    document.querySelectorAll(".filter-sidebar .size-btn.active").forEach((item) => addTag(`Size: ${item.innerText.trim()}`));
    document.querySelectorAll(".attr-pill.active").forEach((item) => addTag(`Color: ${item.dataset.value}`));

    const priceValue = Number(document.getElementById("priceRangeSlider")?.value || 100000);
    if (priceValue < 100000) addTag(`Max Rs. ${priceValue.toLocaleString()}`);

    container.replaceChildren(...tags);
    row.style.display = tags.length ? "flex" : "none";
    telemetry.style.display = tags.length ? "none" : "block";
    if (tags.length) {
      gsap.fromTo(row, { opacity: 0, height: 0 }, { opacity: 1, height: "auto", duration: 0.3 });
    }
  };

  const applyFilterResponse = (responseHtml, isLoadMore) => {
    if (!isLoadMore) {
      grid.innerHTML = responseHtml;
      gsap.to(grid, { opacity: 1, duration: 0.25 });
      gsap.from(".p-card-unique", { y: 20, opacity: 0, duration: 0.45, stagger: 0.04 });
    } else if (responseHtml.trim() !== "") {
      grid.insertAdjacentHTML("beforeend", responseHtml);
    }

    initMagneticButtons();
    if (loadMoreBtn) loadMoreBtn.querySelector("span").innerText = "LOAD MORE PRODUCTS";

    const template = document.createElement("template");
    template.innerHTML = responseHtml;
    const fetchedCount = template.content.querySelectorAll("article").length;
    if (loadMoreBtnWrapper) {
      loadMoreBtnWrapper.style.display = fetchedCount < 12 ? "none" : "block";
    }
  };

  const executeFilterFetch = async (isLoadMore = false) => {
    if (!isLoadMore) {
      currentPage = 1;
      renderFilterTags();
      gsap.to(grid, { opacity: 0.3, duration: 0.15 });
    }

    const params = buildFilterParams();
    const url = new URL("../Actions/filter_products.php", window.location.href);
    url.search = params.toString();
    const cacheKey = url.href;

    if (!isLoadMore && filterCache.has(cacheKey)) {
      applyFilterResponse(filterCache.get(cacheKey), false);
      return;
    }

    filterController?.abort();
    filterController = new AbortController();

    try {
      const response = await fetch(url, {
        method: "GET",
        headers: { Accept: "text/html" },
        signal: filterController.signal,
      });
      if (!response.ok) {
        const message = await response.text();
        throw new Error(message || `Filter request failed (${response.status})`);
      }

      const responseHtml = await response.text();
      if (!isLoadMore) {
        filterCache.set(cacheKey, responseHtml);
        if (filterCache.size > 40) filterCache.delete(filterCache.keys().next().value);
      }
      applyFilterResponse(responseHtml, isLoadMore);
    } catch (error) {
      if (error.name === "AbortError") return;
      console.error("Filter Error:", error);
      gsap.to(grid, { opacity: 1, duration: 0.2 });
      if (loadMoreBtn) loadMoreBtn.querySelector("span").innerText = "TRY AGAIN";
      if (window.Swal) {
        Swal.fire({
          icon: "error",
          title: "Filters unavailable",
          text: "The collection could not be refreshed. Please try again.",
          background: "#111",
          color: "#fff",
          confirmButtonColor: "#d4af37",
        });
      }
    }
  };

  document.querySelectorAll(".vv-btn-cat").forEach((btn) => {
    btn.addEventListener("click", function () {
      document
        .querySelectorAll(".vv-btn-cat")
        .forEach((b) => b.classList.remove("active"));
      this.classList.add("active");
      executeFilterFetch();
    });
  });

  document.querySelectorAll(".filter-chk").forEach((chk) =>
    chk.addEventListener("change", () => {
      if (window.innerWidth >= 992) executeFilterFetch();
    }),
  );

  document.getElementById("clearAllFilters").addEventListener("click", () => {
    document
      .querySelectorAll(".vv-btn-cat")
      .forEach((b) => b.classList.remove("active"));
    document
      .querySelector('.vv-btn-cat[data-filter="All"]')
      .classList.add("active");
    document
      .querySelectorAll(".filter-chk")
      .forEach((c) => (c.checked = false));
    document
      .querySelectorAll(".filter-sidebar .size-btn")
      .forEach((b) => b.classList.remove("active"));
    document
      .querySelectorAll(".attr-pill")
      .forEach((b) => b.classList.remove("active"));
    if (priceSlider) {
      priceSlider.value = 100000;
      priceDisplay.innerText = "RS. 100,000";
    }
    executeFilterFetch();
  });

  if (loadMoreBtn) {
    loadMoreBtn.addEventListener("click", function () {
      this.querySelector("span").innerText = "LOADING...";
      currentPage++;
      executeFilterFetch(true);
    });
  }
});
