document.addEventListener("DOMContentLoaded", function () {
  "use strict";
  gsap.registerPlugin(ScrollTrigger);

  // 1. Initial Page Reveal (Hero Section)
  gsap.from(".gsap-img-reveal", {
    scale: 0.95,
    opacity: 0,
    duration: 1.5,
    ease: "power3.out",
  });
  gsap.from(".pd-info-content > *", {
    y: 20,
    opacity: 0,
    duration: 0.8,
    stagger: 0.1,
    ease: "power2.out",
    delay: 0.3,
  });

  // 2. Scroll Reveal for Secondary Section (Below Fold)
  gsap.utils.toArray(".gsap-scroll-reveal").forEach((el) => {
    gsap.from(el, {
      scrollTrigger: { trigger: el, start: "top 85%" },
      y: 30,
      opacity: 0,
      duration: 1,
      ease: "power3.out",
    });
  });

  // ==========================================
  // 3. MAGNETIC & PHYSICS HOVERS FOR ICONS
  // ==========================================
  const initIconAnimations = () => {
    if (window.innerWidth > 991) {
      document.querySelectorAll(".animate-magnetic").forEach((icon) => {
        icon.addEventListener("mousemove", (e) => {
          const rect = icon.getBoundingClientRect();
          const x = (e.clientX - rect.left - rect.width / 2) * 0.3;
          const y = (e.clientY - rect.top - rect.height / 2) * 0.3;
          gsap.to(icon, { x: x, y: y, duration: 0.3, ease: "power2.out" });
        });
        icon.addEventListener("mouseleave", () => {
          gsap.to(icon, {
            x: 0,
            y: 0,
            duration: 0.7,
            ease: "elastic.out(1, 0.3)",
          });
        });
      });
    }

    document.querySelectorAll(".pd-feature-cube").forEach((cube) => {
      const icon = cube.querySelector(".feature-icon");
      const text = cube.querySelector(".pd-feature-text");

      cube.addEventListener("mouseenter", () => {
        gsap.to(text, { color: "#fff", duration: 0.3 });
        gsap.to(cube, {
          borderColor: "var(--color-gold-metallic)",
          backgroundColor: "rgba(212,175,55,0.05)",
          duration: 0.3,
        });

        if (cube.classList.contains("hover-gem"))
          gsap.to(icon, {
            y: -10,
            rotation: 15,
            scale: 1.2,
            color: "var(--color-gold-metallic)",
            duration: 0.5,
            ease: "back.out(2)",
          });
        if (cube.classList.contains("hover-scissors"))
          gsap.to(icon, {
            y: -10,
            rotation: -25,
            scale: 1.2,
            color: "var(--color-gold-metallic)",
            duration: 0.5,
            ease: "back.out(2)",
          });
        if (cube.classList.contains("hover-truck"))
          gsap.to(icon, {
            x: 15,
            scale: 1.1,
            color: "var(--color-gold-metallic)",
            duration: 0.5,
            ease: "back.out(2)",
          });
        if (cube.classList.contains("hover-shield"))
          gsap.to(icon, {
            scale: 1.3,
            color: "var(--color-gold-metallic)",
            duration: 0.5,
            ease: "elastic.out(1.5, 0.3)",
          });
      });

      cube.addEventListener("mouseleave", () => {
        gsap.to(icon, {
          x: 0,
          y: 0,
          rotation: 0,
          scale: 1,
          color: "#444",
          duration: 0.5,
          ease: "power2.out",
        });
        gsap.to(text, { color: "#888", duration: 0.3 });
        gsap.to(cube, {
          borderColor: "rgba(255,255,255,0.03)",
          backgroundColor: "rgba(255,255,255,0.01)",
          duration: 0.3,
        });
      });
    });

    document.querySelectorAll(".logistics-item").forEach((item) => {
      const icon = item.querySelector(".log-icon");
      item.addEventListener("mouseenter", () => {
        gsap.to(item, {
          borderColor: "var(--color-gold-metallic)",
          backgroundColor: "rgba(212,175,55,0.05)",
          duration: 0.3,
        });
        if (item.classList.contains("hover-box"))
          gsap.to(icon, {
            y: -12,
            scale: 1.2,
            color: "var(--color-gold-metallic)",
            duration: 0.4,
            ease: "back.out(2)",
          });
        if (item.classList.contains("hover-plane"))
          gsap.to(icon, {
            x: 15,
            y: -15,
            scale: 1.2,
            color: "var(--color-gold-metallic)",
            duration: 0.4,
            ease: "back.out(2)",
          });
        if (item.classList.contains("hover-rotate"))
          gsap.to(icon, {
            rotation: -180,
            color: "var(--color-gold-metallic)",
            duration: 0.5,
            ease: "power2.out",
          });
      });
      item.addEventListener("mouseleave", () => {
        gsap.to(icon, {
          x: 0,
          y: 0,
          rotation: 0,
          scale: 1,
          color: "#555",
          duration: 0.4,
          ease: "power2.out",
        });
        gsap.to(item, {
          borderColor: "rgba(255,255,255,0.05)",
          backgroundColor: "rgba(255,255,255,0.02)",
          duration: 0.3,
        });
      });
    });
  };
  initIconAnimations();

  // ==========================================
  // 4. LUXURY IMAGE PAN & ZOOM ON HOVER
  // ==========================================
  const imgContainer = document.getElementById("imgPanContainer");
  const mainImg = document.getElementById("pdMainImage");

  if (imgContainer && mainImg && window.innerWidth > 991) {
    imgContainer.addEventListener("mousemove", (e) => {
      const rect = imgContainer.getBoundingClientRect();
      const x = ((e.clientX - rect.left) / rect.width) * 100;
      const y = ((e.clientY - rect.top) / rect.height) * 100;

      mainImg.style.transformOrigin = `${x}% ${y}%`;
      mainImg.style.transform = "scale(1.8)";
    });

    imgContainer.addEventListener("mouseleave", () => {
      mainImg.style.transformOrigin = "center center";
      mainImg.style.transform = "scale(1)";
    });
  }

  // ==========================================
  // 5. PREMIUM IMAGE CROSSFADE ENGINE
  // ==========================================
  const pdWrapper = document.querySelector(".pd-wrapper");
  if (!pdWrapper) return;

  let variantMatrix = {};
  try {
    variantMatrix = JSON.parse(pdWrapper.dataset.variants || "{}");
  } catch {
    variantMatrix = {};
  }

  const colorSwatches = document.querySelectorAll("#pdColorSwatches .color-swatch");
  const colorLabel = document.getElementById("pdSelectedColor");
  const imgLoader = document.getElementById("pdImgLoader");
  const sizeContainer = document.getElementById("pdSizeSelectors");
  const qtyInput = document.getElementById("pdQtyInput");
  const sizeOrder = ["XS", "S", "M", "L", "XL", "XXL", "OS", "N/A", "STANDARD"];
  let cartRequestController = null;

  const renderProductSizes = (color) => {
    if (!sizeContainer) return;
    sizeContainer.replaceChildren();

    const options = Array.isArray(variantMatrix[color]) ? [...variantMatrix[color]] : [];
    options
      .filter((option) => Number(option.stock || 0) > 0)
      .sort((a, b) => {
        const left = sizeOrder.indexOf(String(a.size).toUpperCase());
        const right = sizeOrder.indexOf(String(b.size).toUpperCase());
        return (left === -1 ? 999 : left) - (right === -1 ? 999 : right);
      })
      .forEach((option, index) => {
        const button = document.createElement("button");
        button.type = "button";
        button.className = `size-btn-horiz animate-magnetic ${index === 0 ? "active" : ""}`;
        button.innerText = option.size;
        button.dataset.size = option.size;
        button.dataset.stock = String(Math.max(1, Number(option.stock || 1)));
        button.addEventListener("click", function () {
          sizeContainer.querySelectorAll(".size-btn-horiz").forEach((item) => item.classList.remove("active"));
          this.classList.add("active");
          if (qtyInput) {
            const maximum = Math.min(10, Number(this.dataset.stock || 1));
            qtyInput.value = Math.min(Number(qtyInput.value || 1), maximum);
          }
        });
        sizeContainer.appendChild(button);
      });

    if (!sizeContainer.querySelector(".size-btn-horiz")) {
      const unavailable = document.createElement("button");
      unavailable.type = "button";
      unavailable.className = "size-btn-horiz";
      unavailable.disabled = true;
      unavailable.innerText = "UNAVAILABLE";
      sizeContainer.appendChild(unavailable);
    }
  };

  colorSwatches.forEach((swatch) => {
    swatch.addEventListener("click", function () {
      if (this.classList.contains("active")) return;

      colorSwatches.forEach((item) => item.classList.remove("active"));
      this.classList.add("active");
      colorLabel.innerText = this.dataset.color.toUpperCase();
      renderProductSizes(this.dataset.color);
      if (qtyInput) qtyInput.value = 1;

      const targetImgUrl = this.dataset.img;
      if (targetImgUrl && targetImgUrl !== mainImg.getAttribute("src")) {
        imgLoader.classList.add("active");
        const tempImg = new Image();
        tempImg.className = "pd-crossfade-img";
        tempImg.src = targetImgUrl;
        tempImg.onload = () => {
          imgContainer.appendChild(tempImg);
          imgLoader.classList.remove("active");
          gsap.to(tempImg, {
            opacity: 1,
            duration: 0.6,
            ease: "power2.inOut",
            onComplete: () => {
              mainImg.src = targetImgUrl;
              tempImg.remove();
            },
          });
        };
        tempImg.onerror = () => imgLoader.classList.remove("active");
      }
    });
  });

  const initialColor = document.querySelector("#pdColorSwatches .color-swatch.active")?.dataset.color;
  if (initialColor) renderProductSizes(initialColor);

  if (qtyInput) {
    document.getElementById("pdQtyMinus")?.addEventListener("click", () => {
      if (Number(qtyInput.value) > 1) qtyInput.value--;
    });
    document.getElementById("pdQtyPlus")?.addEventListener("click", () => {
      const activeSize = sizeContainer?.querySelector(".size-btn-horiz.active");
      const maximum = Math.min(10, Number(activeSize?.dataset.stock || 10));
      if (Number(qtyInput.value) < maximum) qtyInput.value++;
    });
  }

  // ==========================================
  // 8. UNIFIED ADD TO CART ANIMATION
  // ==========================================
  const productID = pdWrapper.dataset.productId;
  const addToCartBtn = document.getElementById("pdAddToCartBtn");
  const maskText = document.getElementById("pdMaskBtnText");

  const setProductCartBusy = (busy) => {
    if (!addToCartBtn || !maskText) return;
    const label = busy ? "ADDING..." : "ADD TO CART";
    addToCartBtn.disabled = busy;
    addToCartBtn.textContent = label;
    maskText.textContent = label;
    maskText.style.opacity = "1";
    if (busy) addToCartBtn.setAttribute("aria-busy", "true");
    else addToCartBtn.removeAttribute("aria-busy");
  };

  if (addToCartBtn) {
    addToCartBtn.addEventListener("click", function () {
      if (this.disabled) return;

      const activeColorBtn = document.querySelector("#pdColorSwatches .color-swatch.active");
      const activeSizeBtn = document.querySelector("#pdSizeSelectors .size-btn-horiz.active");
      if (!activeColorBtn || !activeSizeBtn) {
        const message = "Select an available color and size.";
        if (window.Swal) {
          Swal.fire({ title: "Cart Update Failed", text: message, icon: "error", background: "#0a0a0a", color: "#fff", confirmButtonColor: "#D4AF37" });
        } else {
          window.alert(message);
        }
        return;
      }

      setProductCartBusy(true);

      const selectedColor = activeColorBtn.dataset.color;
      const selectedSize = activeSizeBtn.dataset.size || activeSizeBtn.innerText;
      const stockLimit = Math.min(10, Math.max(1, Number(activeSizeBtn.dataset.stock || 1)));
      const quantity = Math.min(stockLimit, Math.max(1, Number(qtyInput?.value || 1)));
      if (qtyInput) qtyInput.value = String(quantity);

      const formData = new FormData();
      formData.append("product_id", productID);
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

            mainImg.style.transform = "scale(1)";
            const imgRect = mainImg.getBoundingClientRect();

            const flyingImg = document.createElement("img");
            flyingImg.src = mainImg.src;
            flyingImg.style.position = "fixed";
            flyingImg.style.top = imgRect.top + "px";
            flyingImg.style.left = imgRect.left + "px";
            flyingImg.style.width = imgRect.width + "px";
            flyingImg.style.height = imgRect.height + "px";
            flyingImg.style.objectFit = "cover";
            flyingImg.style.zIndex = "99999";
            flyingImg.style.pointerEvents = "none";
            document.body.appendChild(flyingImg);

            gsap.to(mainImg, { opacity: 0.1, duration: 0.3 });

            const dramaTl = gsap.timeline({
              onComplete: () => {
                flyingImg.remove();
                gsap.to(mainImg, { opacity: 1, duration: 0.5 });
                setTimeout(() => {
                  setProductCartBusy(false);
                }, 500);
              },
            });

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
            setProductCartBusy(false);
            return;
          }
          console.error("Cart Fetch Error:", error);
          setProductCartBusy(false);
          if (window.Swal) {
            Swal.fire({
              title: "Cart Update Failed",
              text: error.message || "The item could not be added.",
              icon: "error",
              background: "#0a0a0a",
              color: "#fff",
              confirmButtonColor: "#D4AF37",
            });
          } else {
            window.alert(error.message || "The item could not be added.");
          }
        });
    });
  }
});
