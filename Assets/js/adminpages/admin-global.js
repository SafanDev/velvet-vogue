/**
 * Velvet Vogue - Global Admin Engine
 */
document.addEventListener("DOMContentLoaded", function () {
    "use strict";

    if (typeof gsap !== "undefined") {
        gsap.config({ force3D: true });
    }

    const isDesktop = window.innerWidth > 991;
    const motionEnabled = !window.matchMedia('(prefers-reduced-motion: reduce)').matches
        && window.matchMedia('(hover: hover) and (pointer: fine)').matches;

    // 3D SWINGING NAVBAR LOGIC
    if(isDesktop) {
        (function initNav() {
            var navOuter = document.getElementById("navOuter");
            var navInner = document.getElementById("navInner");
            if (!navOuter || !navInner) return;

            var hoverPill = document.getElementById("hoverPill");
            var activePill = document.getElementById("activePill");
            var activeGlow = document.getElementById("activeGlow");
            var items = document.querySelectorAll(".nav-item");
            var itemsArr = Array.prototype.slice.call(items);

            var activeItem = document.querySelector(".nav-item.active");
            var ADJ_GAP = 7;

            function relRect(el) { var pR = navInner.getBoundingClientRect(); var eR = el.getBoundingClientRect(); return { left: eR.left - pR.left, top: eR.top - pR.top, width: eR.width, height: eR.height }; }
            function innerH() { return navInner.offsetHeight; }
            function innerW() { return navInner.offsetWidth; }
            function expandHover() { hoverPill.style.left = "0px"; hoverPill.style.top = "0px"; hoverPill.style.width = innerW() + "px"; hoverPill.style.height = innerH() + "px"; }

            function shrinkHover(item) {
                var r = relRect(item);
                var activeIdx = activeItem ? itemsArr.indexOf(activeItem) : -1;
                var itemIdx = itemsArr.indexOf(item);
                var left = r.left, width = r.width;

                if (item !== activeItem && activeIdx !== -1) {
                    if (itemIdx === activeIdx + 1) { left += ADJ_GAP; width -= ADJ_GAP; }
                    else if (itemIdx === activeIdx - 1) { width -= ADJ_GAP; }
                }
                hoverPill.style.left = left + "px";
                hoverPill.style.top = "0px";
                hoverPill.style.width = width + "px";
                hoverPill.style.height = innerH() + "px";
            }

            function moveActive(item) {
                if (!item) {
                    if(activePill) activePill.style.opacity = '0';
                    if(activeGlow) activeGlow.style.opacity = '0';
                    return;
                }

                if(activePill) activePill.style.opacity = '1';
                if(activeGlow) activeGlow.style.opacity = '1';

                var r = relRect(item), h = innerH();
                activePill.style.left = r.left + "px";
                activePill.style.top = "0px";
                activePill.style.width = r.width + "px";
                activePill.style.height = h + "px";
                activeGlow.style.left = r.left - 10 + "px";
                activeGlow.style.top = "-5px";
                activeGlow.style.width = r.width + 20 + "px";
                activeGlow.style.height = h + 10 + "px";
            }

            function silenceTransitions() { hoverPill.style.transition = "none"; activePill.style.transition = "none"; activeGlow.style.transition = "none"; }
            function restoreTransitions() { requestAnimationFrame(function () { requestAnimationFrame(function () { hoverPill.style.transition = ""; activePill.style.transition = ""; activeGlow.style.transition = ""; }); }); }

            silenceTransitions(); expandHover(); moveActive(activeItem); restoreTransitions();

            itemsArr.forEach(function (item) {
                var inner = item.querySelector(".item-inner");
                item.addEventListener("mouseenter", function () { shrinkHover(item); });

                // Shift the inner text only when the pointer is directly over the item.
                // not when hovering in the absolute dropdown below it.
                if (motionEnabled) {
                    var itemFrame = 0;
                    var itemPointerEvent = null;
                    item.addEventListener("mousemove", function (event) {
                        if(event.target.closest('.glass-dropdown')) return;
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
                item.addEventListener("mouseleave", function () { inner.style.transform = "translate(0px, 0px)"; });
            });

            // THE SUBTLE TILT FIX
            var tiltTargetX = 0, tiltTargetY = 0, tiltCurrentX = 0, tiltCurrentY = 0, tiltRAF;
            function lerpTilt() {
                tiltCurrentX += (tiltTargetX - tiltCurrentX) * 0.1; tiltCurrentY += (tiltTargetY - tiltCurrentY) * 0.1;

                // Increased perspective to 1500px to flatten the depth
                navOuter.style.transform = "perspective(1500px) rotateX(" + tiltCurrentX + "deg) rotateY(" + tiltCurrentY + "deg)";
                if (Math.abs(tiltTargetX - tiltCurrentX) > 0.01 || Math.abs(tiltTargetY - tiltCurrentY) > 0.01) { tiltRAF = requestAnimationFrame(lerpTilt); }
            }

            if (motionEnabled) {
                var navPointerEvent = null;
                var navPointerFrame = 0;
                navOuter.addEventListener("mousemove", function (event) {
                    navPointerEvent = event;
                    if (navPointerFrame) return;
                    navPointerFrame = requestAnimationFrame(function () {
                        navPointerFrame = 0;
                        var e = navPointerEvent;
                        var r = navOuter.getBoundingClientRect();
                        var nx = (e.clientX - r.left - r.width / 2) / (r.width / 2);
                        var ny = (e.clientY - r.top - r.height / 2) / (r.height / 2);
                        tiltTargetX = -ny * 2.5;
                        tiltTargetY = nx * 4.0;
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
                    expandHover();
                });
            } else {
                navOuter.addEventListener("mouseleave", expandHover);
            }

            var resizeTimer; window.addEventListener("resize", function () { clearTimeout(resizeTimer); resizeTimer = setTimeout(function () { silenceTransitions(); expandHover(); moveActive(activeItem); restoreTransitions(); }, 80); });
        })();
    }
});
window.vvSetMessage = function (element, message, options = {}) {
    if (!element) return;

    element.replaceChildren();
    if (options.iconClass) {
        const icon = document.createElement('i');
        icon.className = options.iconClass;
        element.appendChild(icon);
    }

    element.appendChild(document.createTextNode(String(message || '')));

    if (options.detail) {
        element.appendChild(document.createElement('br'));
        const detail = document.createElement('span');
        detail.style.fontSize = '0.7rem';
        detail.style.color = '#aaa';
        detail.textContent = String(options.detail);
        element.appendChild(detail);
    }
};
