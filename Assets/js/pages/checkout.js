/**
 * Velvet Vogue - Editorial Checkout Logic
 */
document.addEventListener("DOMContentLoaded", function () {
    "use strict";
    gsap.registerPlugin(ScrollTrigger);

    // Initial Cinematic Reveal
    gsap.from(".gsap-fade-in", { y: 20, opacity: 0, duration: 1, stagger: 0.2, ease: "power2.out" });

    // Dynamic Form Expansion Logic for Radios
    const setupRadios = (radioName, formId) => {
        const radios = document.querySelectorAll(`input[name="${radioName}"]`);
        radios.forEach(radio => {
            radio.addEventListener('change', function() {
                // Remove active class
                document.querySelectorAll(`input[name="${radioName}"]`).forEach(r => r.closest('.vv-minimal-radio').classList.remove('active'));
                // Add to current
                this.closest('.vv-minimal-radio').classList.add('active');

                // Handle New Form Expansion
                const form = document.getElementById(formId);
                if (!form) return;

                if(this.value === 'new' || this.value === 'new_card') {
                    form.style.display = 'block';
                    gsap.fromTo(form, {height: 0, opacity: 0}, {height: 'auto', opacity: 1, duration: 0.4, ease: "power2.out"});
                } else {
                    gsap.to(form, {height: 0, opacity: 0, duration: 0.3, onComplete: () => form.style.display = 'none'});
                }
            });
        });
    };
    setupRadios('addressID', 'newAddressForm');
    if (document.getElementById('newCardForm')) {
        setupRadios('paymentMethod', 'newCardForm');
    }

    // Accordion Engine
    window.goToStep = function(stepNumber) {
        const allSteps = document.querySelectorAll('.co-step');

        allSteps.forEach((step, index) => {
            const stepIndex = index + 1;
            const body = step.querySelector('.step-body');

            if (stepIndex === stepNumber) {
                // Open Current
                step.classList.add('active');
                step.classList.remove('completed');
                body.style.display = 'block';
                gsap.fromTo(body, { height: 0, opacity: 0 }, { height: 'auto', opacity: 1, duration: 0.5, ease: "power3.out" });

            } else if (stepIndex < stepNumber) {
                // Complete Previous
                step.classList.remove('active');
                step.classList.add('completed');
                gsap.to(body, { height: 0, opacity: 0, duration: 0.3, onComplete: () => body.style.display = 'none' });
            } else {
                // Reset Future
                step.classList.remove('active');
                step.classList.remove('completed');
                gsap.to(body, { height: 0, opacity: 0, duration: 0.3, onComplete: () => body.style.display = 'none' });
            }
        });

        // Trigger Final Confirm Button ONLY on Step 3
        const finalBtn = document.getElementById('finalConfirmBtn');
        const placeholder = document.querySelector('.placeholder-action');

        if (stepNumber === 3) {
            placeholder.classList.add('d-none');
            finalBtn.classList.remove('d-none');
            gsap.fromTo(finalBtn, {y: 20, opacity: 0}, {y: 0, opacity: 1, duration: 0.5, ease: "power2.out"});
        } else {
            placeholder.classList.remove('d-none');
            finalBtn.classList.add('d-none');
        }

        // Smooth scroll to the active step
        setTimeout(() => {
            const activeStep = document.querySelector('.co-step.active');
            if (activeStep) {
                window.scrollTo({ top: activeStep.offsetTop - 80, behavior: 'smooth' });
            }
        }, 100);
    };

    // Auto-formatting (Visual Only for CC & EXP)
    const ccInput = document.querySelector('.cc-format');
    if (ccInput) {
        ccInput.addEventListener('input', function (e) {
            let val = e.target.value.replace(/\D/g, '');
            let newVal = '';
            for(let i=0; i<val.length; i++) {
                if(i > 0 && i % 4 === 0) newVal += ' ';
                newVal += val[i];
            }
            e.target.value = newVal;
        });
    }

    const expInput = document.querySelector('.exp-format');
    if (expInput) {
        expInput.addEventListener('input', function (e) {
            let val = e.target.value.replace(/\D/g, '');
            if(val.length > 2) {
                e.target.value = val.substring(0,2) + '/' + val.substring(2,4);
            } else {
                e.target.value = val;
            }
        });
    }

    // ==========================================
    // PROMO CODE LOGIC
    // ==========================================
    const btnApplyPromo = document.getElementById('btnApplyPromo');
    const btnRemovePromo = document.getElementById('btnRemovePromo');
    const promoCodeInput = document.getElementById('promoCodeInput');
    const promoFeedback = document.getElementById('promoFeedback');
    const promoInputState = document.getElementById('promoInputState');
    const promoActiveState = document.getElementById('promoActiveState');

    const summarySubtotal = document.getElementById('summarySubtotal');
    const summaryDiscount = document.getElementById('summaryDiscount');
    const summaryTotal = document.getElementById('summaryTotal');

    function showPromoFeedback(msg, color) {
        promoFeedback.style.display = 'block';
        promoFeedback.style.color = color;

        let iter = 0;
        const chars = '01X!<>-_\\/[]{}—=+*^?#';
        const intv = setInterval(() => {
            let sc = '';
            for(let i=0; i<msg.length; i++) sc += chars[Math.floor(Math.random()*chars.length)];
            promoFeedback.innerText = sc;
            if(++iter > 8) { clearInterval(intv); promoFeedback.innerText = msg; }
        }, 30);
    }

    if (btnApplyPromo) {
        btnApplyPromo.addEventListener('click', function() {
            const code = promoCodeInput.value.trim().toUpperCase();
            if(code === '') {
                showPromoFeedback('Please enter a code.', '#ff4d4d');
                return;
            }

            const ogText = btnApplyPromo.innerText;
            btnApplyPromo.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i>';
            btnApplyPromo.disabled = true;
            showPromoFeedback('Applying...', '#a0a0a0');

            const formData = new FormData();
            formData.append('promo_code', code);

            fetch('../Actions/apply_coupon.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    promoInputState.style.setProperty('display', 'none', 'important');
                    promoActiveState.style.setProperty('display', 'flex', 'important');

                    document.getElementById('activePromoCode').innerText = data.code;

                    showPromoFeedback(data.message, '#D4AF37'); // Gold success

                    if (summaryDiscount) summaryDiscount.innerText = `- RS. ${data.discount_amount.toLocaleString()}`;
                    if (summaryTotal) summaryTotal.innerText = `RS. ${data.new_total.toLocaleString()}`;
                } else {
                    showPromoFeedback(data.message, '#ff4d4d'); // Red error
                    gsap.fromTo(promoCodeInput, {x: -10}, {x: 0, duration: 0.3, ease: "bounce.out(2)"});
                }
            })
            .catch(err => {
                showPromoFeedback('System error. Please try again.', '#ff4d4d');
            })
            .finally(() => {
                btnApplyPromo.innerText = ogText;
                btnApplyPromo.disabled = false;
            });
        });
    }

    if (btnRemovePromo) {
        btnRemovePromo.addEventListener('click', function() {
            fetch('../Actions/remove_coupon.php', { method: 'POST' })
            .then(res => res.json())
            .then(data => {
                if (data.status !== 'success') {
                    throw new Error(data.message || 'Promo code could not be removed.');
                }

                promoInputState.style.setProperty('display', 'flex', 'important');
                promoActiveState.style.setProperty('display', 'none', 'important');
                promoCodeInput.value = '';
                showPromoFeedback('Promo code removed.', '#a0a0a0');

                const currentTotal = summarySubtotal ? parseFloat(summarySubtotal.getAttribute('data-val')) : 0;
                if (summaryDiscount) summaryDiscount.innerText = `- RS. 0`;
                if (summaryTotal) summaryTotal.innerText = `RS. ${currentTotal.toLocaleString()}`;
            })
            .catch(() => {
                showPromoFeedback('Promo code could not be removed. Please try again.', '#ff4d4d');
            });
        });
    }

    // ==========================================
    // Success Modal Execution (AJAX to DB)
    // ==========================================
    const btnAuthorize = document.getElementById('btnAuthorize');
    const modal = document.getElementById('successModal');

    if(btnAuthorize && modal) {
        btnAuthorize.addEventListener('click', function(e) {
            e.preventDefault();
            if (btnAuthorize.disabled) return;

            const checkoutIntent = document.getElementById('checkoutIntentToken')?.value || '';
            if (!checkoutIntent) {
                alert('Checkout authorization is unavailable. Reload the checkout page and try again.');
                return;
            }

            let formData = new FormData();
            formData.append('checkout_intent', checkoutIntent);

            // Get selected address
            const selectedAddress = document.querySelector('input[name="addressID"]:checked');
            if (!selectedAddress) {
                alert('Select a shipping address before placing the order.');
                return;
            }

            formData.append('addressID', selectedAddress.value);
            if (selectedAddress.value === 'new') {
                const inputs = document.getElementById('newAddressForm').querySelectorAll('input');
                const values = Array.from(inputs, input => input.value.trim());
                if (values.some(value => value === '')) {
                    alert('Complete the new shipping address before placing the order.');
                    return;
                }
                formData.append('recipientName', values[0]);
                formData.append('street', values[1]);
                formData.append('city', values[2]);
                formData.append('postalCode', values[3]);
            }

            // Get selected payment
            const selectedPayment = document.querySelector('input[name="paymentMethod"]:checked');
            if (selectedPayment) {
                formData.append('paymentMethod', selectedPayment.value);
            }

            const maskText = document.getElementById('checkoutMaskText');
            const arrow = document.getElementById('placeOrderArrow');

            if(maskText) maskText.innerText = 'PROCESSING...';
            if(arrow) arrow.style.display = 'none';
            btnAuthorize.disabled = true;

            const request = window.VelvetVogueSecurity?.fetchJson
                ? window.VelvetVogueSecurity.fetchJson('../Actions/process_order.php', { method: 'POST', body: formData })
                : fetch('../Actions/process_order.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                }).then(async response => {
                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok || payload.status === 'error') {
                        throw new Error(payload.message || `Order request failed (${response.status}).`);
                    }
                    return payload;
                });

            request.then(data => {
                document.getElementById('successOrderNumber').innerText = '#' + data.orderNumber;

                if (typeof window.updateGlobalCartBadge === 'function') {
                    window.updateGlobalCartBadge(0);
                }

                setTimeout(() => {
                    modal.classList.add('active');
                }, 500);
            }).catch(error => {
                if(maskText) maskText.innerText = 'PLACE ORDER';
                if(arrow) arrow.style.display = 'inline-block';
                btnAuthorize.disabled = false;
                alert(error.message || 'The order could not be completed. Please try again.');
                console.error(error);
            });
        });
    }
});