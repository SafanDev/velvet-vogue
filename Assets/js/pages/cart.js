document.addEventListener("DOMContentLoaded", function () {
    "use strict";

    if (window.gsap && window.ScrollTrigger) {
        gsap.registerPlugin(ScrollTrigger);
        gsap.fromTo(".gsap-fade-in",
            { y: 20, opacity: 0 },
            { y: 0, opacity: 1, duration: 0.8, stagger: 0.1, ease: "power2.out" }
        );
        gsap.fromTo(".gsap-cart-item",
            { x: -30, opacity: 0 },
            { x: 0, opacity: 1, duration: 0.8, stagger: 0.15, ease: "power3.out", delay: 0.2 }
        );
    }

    const formatPrice = (num) => `RS. ${Number(num).toLocaleString("en-IN")}`;

    const setupCartBadge = () => {
        const cartIconLinks = document.querySelectorAll('a[href*="cart.php"]');
        const initialCountObj = document.getElementById("initialCartCount");
        const initialCount = initialCountObj ? Number.parseInt(initialCountObj.value, 10) || 0 : 0;

        cartIconLinks.forEach((link) => {
            link.style.position = "relative";
            if (!link.querySelector(".vv-header-cart-badge") && initialCount > 0) {
                const badge = document.createElement("span");
                badge.className = "vv-header-cart-badge";
                badge.innerText = String(initialCount);
                link.appendChild(badge);
            }
        });
    };

    const updateCartBadges = (newCount) => {
        document.querySelectorAll(".vv-header-cart-badge").forEach((badge) => {
            badge.innerText = String(newCount);
            badge.style.display = newCount === 0 ? "none" : "flex";
            if (newCount > 0 && window.gsap) {
                gsap.fromTo(badge, { scale: 0.5 }, { scale: 1, duration: 0.4, ease: "back.out(3)" });
            }
        });

        const dossierNum = document.getElementById("dossierNum");
        if (dossierNum) dossierNum.innerText = String(newCount);
    };

    const recalculateTotals = () => {
        let newSubtotal = 0;

        document.querySelectorAll(".gsap-cart-item").forEach((row) => {
            const price = Number.parseFloat(row.dataset.price || "0");
            const input = row.querySelector(".qty-input");
            const qty = input ? Number.parseInt(input.value, 10) || 1 : 1;
            const lineTotal = price * qty;
            const lineTotalEl = row.querySelector(".ci-line-total");

            if (lineTotalEl) {
                if (window.gsap) {
                    gsap.fromTo(lineTotalEl, { scale: 1.1, color: "#fff" }, {
                        scale: 1,
                        color: "var(--color-gold-metallic)",
                        duration: 0.4,
                    });
                }
                lineTotalEl.innerText = formatPrice(lineTotal);
            }
            newSubtotal += lineTotal;
        });

        const subtotalEl = document.getElementById("cartSubtotal");
        const totalEl = document.getElementById("cartTotal");
        if (!subtotalEl || !totalEl) return;

        if (window.gsap) {
            gsap.fromTo([subtotalEl, totalEl],
                { scale: 1.1, color: "#fff", textShadow: "0 0 20px rgba(255,255,255,0.8)" },
                {
                    scale: 1,
                    color: "var(--color-gold-metallic)",
                    textShadow: "0 0 0 rgba(255,255,255,0)",
                    duration: 0.5,
                    ease: "back.out(2)",
                }
            );
        }

        subtotalEl.innerText = formatPrice(newSubtotal);
        totalEl.innerText = formatPrice(newSubtotal);
    };

    const showCartError = (message) => {
        const text = message || "The cart could not be updated.";
        if (window.Swal) {
            Swal.fire({
                title: "Cart Update Failed",
                text,
                icon: "error",
                background: "#0a0a0a",
                color: "#fff",
                confirmButtonColor: "#D4AF37",
            });
            return;
        }
        window.alert(text);
    };

    const syncCartAction = async (action, cartId, quantity = null) => {
        const formData = new FormData();
        formData.append("action", action);
        formData.append("cart_id", cartId);
        if (quantity !== null) formData.append("quantity", String(quantity));

        const response = await fetch("../Actions/update_cart.php", {
            method: "POST",
            body: formData,
            credentials: "same-origin",
            cache: "no-store",
            headers: {
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || payload.status !== "success") {
            throw new Error(payload.message || "The cart could not be updated.");
        }
        return payload;
    };

    const setRowBusy = (row, busy) => {
        row.dataset.busy = busy ? "1" : "0";
        row.setAttribute("aria-busy", busy ? "true" : "false");
        row.querySelectorAll(".btn-qty-minus, .btn-qty-plus, .btn-remove-item").forEach((button) => {
            button.disabled = busy;
        });
    };

    setupCartBadge();

    document.querySelectorAll(".cart-item-card").forEach((row) => {
        const btnMinus = row.querySelector(".btn-qty-minus");
        const btnPlus = row.querySelector(".btn-qty-plus");
        const removeButton = row.querySelector(".btn-remove-item");
        const input = row.querySelector(".qty-input");
        const cartId = row.dataset.cartId;

        const changeQuantity = async (delta) => {
            if (!input || !cartId || row.dataset.busy === "1") return;

            const previousValue = Number.parseInt(input.value, 10) || 1;
            const nextValue = Math.max(1, Math.min(10, previousValue + delta));
            if (nextValue === previousValue) return;

            input.value = String(nextValue);
            recalculateTotals();
            setRowBusy(row, true);

            try {
                const data = await syncCartAction("update", cartId, nextValue);
                updateCartBadges(Number(data.cart_count || 0));
            } catch (error) {
                input.value = String(previousValue);
                recalculateTotals();
                showCartError(error.message);
            } finally {
                setRowBusy(row, false);
            }
        };

        btnMinus?.addEventListener("click", () => changeQuantity(-1));
        btnPlus?.addEventListener("click", () => changeQuantity(1));

        removeButton?.addEventListener("click", async () => {
            if (!cartId || row.dataset.busy === "1") return;
            setRowBusy(row, true);

            try {
                const data = await syncCartAction("remove", cartId);
                updateCartBadges(Number(data.cart_count || 0));

                const removeRow = () => {
                    row.remove();
                    recalculateTotals();
                    if (data.is_empty || !document.querySelector(".cart-item-card")) {
                        window.location.reload();
                    }
                };

                if (window.gsap) {
                    gsap.to(row, {
                        scale: 0.95,
                        opacity: 0,
                        x: 60,
                        duration: 0.35,
                        ease: "power2.in",
                        onComplete: () => gsap.to(row, {
                            height: 0,
                            padding: 0,
                            margin: 0,
                            border: "none",
                            duration: 0.25,
                            onComplete: removeRow,
                        }),
                    });
                } else {
                    removeRow();
                }
            } catch (error) {
                setRowBusy(row, false);
                showCartError(error.message);
            }
        });
    });
});
