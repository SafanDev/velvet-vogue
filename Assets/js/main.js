/**
 * Velvet Vogue - Global Main JS
 */
document.addEventListener("DOMContentLoaded", function () {
  "use strict";

  // ==========================================
  // 1. INITIALIZE GSAP SCROLL REVEALS (LENIS REMOVED FOR PERFORMANCE)
  // ==========================================
  if (typeof gsap !== "undefined" && typeof ScrollTrigger !== "undefined") {
    gsap.registerPlugin(ScrollTrigger);

    // Global Footer Reveal Animation
    if (document.querySelector(".main-footer")) {
      gsap.to(".footer-content", {
        scrollTrigger: { trigger: ".main-footer", start: "top 90%" },
        y: 0,
        opacity: 1,
        duration: 1.2,
        ease: "power3.out",
      });
    }
  }

  // ==========================================
  // 2. HEADER NAVBAR INTERACTIVE LOGIC
  // ==========================================
  (function initNav() {
    var navOuter = document.getElementById("navOuter");
    var navInner = document.getElementById("navInner");
    if (!navOuter || !navInner) return;

    var hoverPill = document.getElementById("hoverPill");
    var activePill = document.getElementById("activePill");
    var activeGlow = document.getElementById("activeGlow");
    var items = document.querySelectorAll(".nav-item");
    var itemsArr = Array.prototype.slice.call(items);
    var activeItem = document.querySelector(".nav-item.active") || items[0];
    var ADJ_GAP = 7;
    var motionEnabled = !window.matchMedia('(prefers-reduced-motion: reduce)').matches
      && window.matchMedia('(hover: hover) and (pointer: fine)').matches;

    function relRect(el) {
      var pR = navInner.getBoundingClientRect();
      var eR = el.getBoundingClientRect();
      return {
        left: eR.left - pR.left,
        top: eR.top - pR.top,
        width: eR.width,
        height: eR.height,
      };
    }
    function innerH() {
      return navInner.offsetHeight;
    }
    function innerW() {
      return navInner.offsetWidth;
    }

    function expandHover() {
      hoverPill.style.left = "0px";
      hoverPill.style.top = "0px";
      hoverPill.style.width = innerW() + "px";
      hoverPill.style.height = innerH() + "px";
    }

    function shrinkHover(item) {
      var r = relRect(item);
      var activeIdx = itemsArr.indexOf(activeItem);
      var itemIdx = itemsArr.indexOf(item);
      var left = r.left,
        width = r.width;

      if (item !== activeItem) {
        if (itemIdx === activeIdx + 1) {
          left += ADJ_GAP;
          width -= ADJ_GAP;
        } else if (itemIdx === activeIdx - 1) {
          width -= ADJ_GAP;
        }
      }
      hoverPill.style.left = left + "px";
      hoverPill.style.top = "0px";
      hoverPill.style.width = width + "px";
      hoverPill.style.height = innerH() + "px";
    }

    function moveActive(item) {
      if (!item) return;
      var r = relRect(item),
        h = innerH();
      activePill.style.left = r.left + "px";
      activePill.style.top = "0px";
      activePill.style.width = r.width + "px";
      activePill.style.height = h + "px";
      activeGlow.style.left = r.left - 10 + "px";
      activeGlow.style.top = "-5px";
      activeGlow.style.width = r.width + 20 + "px";
      activeGlow.style.height = h + 10 + "px";
    }

    function silenceTransitions() {
      hoverPill.style.transition = "none";
      activePill.style.transition = "none";
      activeGlow.style.transition = "none";
    }
    function restoreTransitions() {
      requestAnimationFrame(function () {
        requestAnimationFrame(function () {
          hoverPill.style.transition = "";
          activePill.style.transition = "";
          activeGlow.style.transition = "";
        });
      });
    }

    silenceTransitions();
    expandHover();
    moveActive(activeItem);
    restoreTransitions();

    var tiltTargetX = 0,
      tiltTargetY = 0,
      tiltCurrentX = 0,
      tiltCurrentY = 0,
      tiltRAF;
    function lerpTilt() {
      tiltCurrentX += (tiltTargetX - tiltCurrentX) * 0.1;
      tiltCurrentY += (tiltTargetY - tiltCurrentY) * 0.1;
      navOuter.style.transform =
        "perspective(700px) rotateX(" +
        tiltCurrentX +
        "deg) rotateY(" +
        tiltCurrentY +
        "deg)";
      if (
        Math.abs(tiltTargetX - tiltCurrentX) > 0.01 ||
        Math.abs(tiltTargetY - tiltCurrentY) > 0.01
      ) {
        tiltRAF = requestAnimationFrame(lerpTilt);
      }
    }

    if (motionEnabled) {
      var pendingPointerEvent = null;
      var pointerFrame = 0;
      navOuter.addEventListener("mousemove", function (event) {
        pendingPointerEvent = event;
        if (pointerFrame) return;
        pointerFrame = requestAnimationFrame(function () {
          pointerFrame = 0;
          var e = pendingPointerEvent;
          var r = navOuter.getBoundingClientRect();
          var nx = (e.clientX - r.left - r.width / 2) / (r.width / 2);
          var ny = (e.clientY - r.top - r.height / 2) / (r.height / 2);
          tiltTargetX = -ny * 7;
          tiltTargetY = nx * 11;
          navOuter.style.setProperty("--mx", (((e.clientX - r.left) / r.width) * 100) + "%");
          navOuter.style.setProperty("--my", (((e.clientY - r.top) / r.height) * 100) + "%");
          cancelAnimationFrame(tiltRAF);
          tiltRAF = requestAnimationFrame(lerpTilt);
        });
      }, { passive: true });

      navOuter.addEventListener("mouseleave", function () {
        tiltTargetX = 0;
        tiltTargetY = 0;
        navOuter.style.setProperty("--mx", "50%");
        navOuter.style.setProperty("--my", "30%");
        cancelAnimationFrame(tiltRAF);
        tiltRAF = requestAnimationFrame(lerpTilt);
      });
    }

    itemsArr.forEach(function (item) {
      var inner = item.querySelector(".item-inner");
      item.addEventListener("mouseenter", function () {
        shrinkHover(item);
      });
      if (motionEnabled) {
        var itemFrame = 0;
        var itemPointerEvent = null;
        item.addEventListener("mousemove", function (event) {
          itemPointerEvent = event;
          if (itemFrame) return;
          itemFrame = requestAnimationFrame(function () {
            itemFrame = 0;
            var e = itemPointerEvent;
            var r = item.getBoundingClientRect();
            var mx = ((e.clientX - r.left - r.width / 2) / (r.width / 2)) * 3.5;
            var my = ((e.clientY - r.top - r.height / 2) / (r.height / 2)) * 2.0;
            inner.style.transform = "translate(" + mx + "px, " + my + "px)";
          });
        }, { passive: true });
      }
      item.addEventListener("mouseleave", function () {
        inner.style.transform = "translate(0px, 0px)";
      });

      item.addEventListener("click", function (e) {
        itemsArr.forEach(function (i) {
          i.classList.remove("active");
        });
        item.classList.add("active");
        activeItem = item;
        moveActive(item);
        var iR = navInner.getBoundingClientRect();
        var ripple = document.createElement("div");
        ripple.classList.add("ripple");
        ripple.style.left = e.clientX - iR.left + "px";
        ripple.style.top = e.clientY - iR.top + "px";
        navInner.appendChild(ripple);
        ripple.addEventListener("animationend", function () {
          ripple.remove();
        });
      });
    });

    navInner.addEventListener("mouseleave", function () {
      expandHover();
    });
    var resizeTimer;
    window.addEventListener("resize", function () {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function () {
        silenceTransitions();
        expandHover();
        moveActive(activeItem);
        restoreTransitions();
      }, 80);
    });
  })();
});

// ==========================================
// 3. GLOBAL BADGE UPDATERS
// ==========================================
window.updateGlobalCartBadge = function (newCount) {
  let badge = document.getElementById("globalCartBadge");
  const cartIcon = document.querySelector("a.ai-cart");

  if (newCount > 0) {
    if (!badge && cartIcon) {
      badge = document.createElement("span");
      badge.className = "vv-header-cart-badge";
      badge.id = "globalCartBadge";
      cartIcon.appendChild(badge);
    }
    if (badge) {
      badge.innerText = newCount;
      if (typeof gsap !== "undefined")
        gsap.fromTo(
          badge,
          { scale: 0 },
          { scale: 1, duration: 0.4, ease: "back.out(2)" },
        );
    }
  } else if (badge) {
    badge.remove();
  }
};

window.updateGlobalWishBadge = function (newCount) {
  let badge = document.getElementById("globalWishBadge");
  const wishIcon = document.querySelector("a.ai-wish");

  if (newCount > 0) {
    if (!badge && wishIcon) {
      badge = document.createElement("span");
      badge.className = "vv-header-wish-badge";
      badge.id = "globalWishBadge";
      wishIcon.appendChild(badge);
    }
    if (badge) {
      badge.innerText = newCount;
      if (typeof gsap !== "undefined")
        gsap.fromTo(
          badge,
          { scale: 0 },
          { scale: 1, duration: 0.4, ease: "back.out(2)" },
        );
    }
  } else if (badge) {
    badge.remove();
  }
};

// ==========================================
// 4. RESPONSIVE MOBILE MENU (CINEMATIC)
// ==========================================
const mobBtn = document.getElementById("mobileMenuBtn");
const mobOverlay = document.getElementById("mobileNavOverlay");
const mobLinks = document.querySelectorAll(".mobile-link");
let isMobMenuOpen = false;

if (mobBtn && mobOverlay) {
  mobBtn.addEventListener("click", function () {
    isMobMenuOpen = !isMobMenuOpen;

    // Morph the hamburger icon
    mobBtn.classList.toggle("active");

    if (isMobMenuOpen) {
      // Open Menu
      mobOverlay.classList.add("active");
      document.body.style.overflow = "hidden"; // Lock background scrolling

      // GSAP Staggered Reveal (Text slides up from invisible box)
      if (typeof gsap !== "undefined") {
        gsap.to(mobLinks, {
          y: 0,
          opacity: 1,
          duration: 0.8,
          stagger: 0.1,
          ease: "power4.out",
          delay: 0.2,
        });
      } else {
        // Fallback if GSAP fails to load
        mobLinks.forEach((link) => {
          link.style.transform = "translateY(0)";
          link.style.opacity = "1";
        });
      }
    } else {
      // Close Menu
      mobOverlay.classList.remove("active");
      document.body.style.overflow = ""; // Unlock scrolling

      // GSAP Hide (Slide back down)
      if (typeof gsap !== "undefined") {
        gsap.to(mobLinks, {
          y: "100%",
          opacity: 0,
          duration: 0.4,
          ease: "power2.in",
        });
      }
    }
  });
}
