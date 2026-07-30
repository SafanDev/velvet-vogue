/**
 * Velvet Vogue - The Atelier Engine (State-Driven, XSS Secured)
 */
let currentOutfit = {};
let currentGender = 'Women';
let currentCategory = 'Tops';

const categoryLayerMap = {
    "Tops": "Tops", "Bottoms": "Bottoms", "Dresses & Gowns": "Dresses",
    "Tailoring & Suiting": "Tailoring", "Outerwear": "Outerwear",
    "Accessories": "Accessories", "Footwear": "Footwear", "Bags": "Bags"
};

document.addEventListener("DOMContentLoaded", function () {
    const params = new URLSearchParams(window.location.search);
    if(params.get('gender') === 'Men') {
        currentGender = 'Men';
    } else {
        currentGender = 'Women';
    }

    document.querySelectorAll('.gen-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('btn-' + currentGender).classList.add('active');

    loadCategory(currentCategory, currentGender);

    document.querySelectorAll('.slot-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            currentCategory = this.getAttribute('data-category');

            const titleEl = document.getElementById('currentCatTitle');
            gsap.to(titleEl, { opacity: 0, y: -5, duration: 0.2, onComplete: () => {
                titleEl.innerText = currentCategory === 'Dresses & Gowns' ? 'Full Body' : currentCategory;
                gsap.to(titleEl, { opacity: 1, y: 0, duration: 0.2 });
            }});

            loadCategory(currentCategory, currentGender);
        });
    });

    document.getElementById('toggleUI').addEventListener('click', function() {
        document.body.classList.toggle('ui-hidden');
        const icon = this.querySelector('i');
        icon.className = document.body.classList.contains('ui-hidden') ? 'fa-solid fa-eye text-gold' : 'fa-solid fa-eye-slash';
    });
});

function switchGender(newGender) {
    if(currentGender === newGender) return;

    document.querySelectorAll('.gen-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('btn-' + newGender).classList.add('active');

    currentGender = newGender;

    const baseModel = document.getElementById('baseModel');
    gsap.to('.dress-layer', { opacity: 0, duration: 0.3 });
    gsap.to(baseModel, { opacity: 0, duration: 0.3, onComplete: () => {
        resetWardrobe(false);

        baseModel.onload = () => {
            gsap.to(baseModel, { opacity: 1, duration: 0.4 });
            loadCategory(currentCategory, currentGender);
        };
        baseModel.src = `../Assets/images/wardrobe/base-${newGender.toLowerCase()}.webp`;
    }});
}

function loadCategory(category, gender) {
    const grid = document.getElementById('inventoryGrid');
    grid.replaceChildren();
    const loader = document.createElement('div');
    loader.className = 'loader-vogue';
    grid.appendChild(loader);

    fetch(`fetch_wardrobe_items.php?category=${encodeURIComponent(category)}&gender=${encodeURIComponent(gender)}`)
        .then(r => {
            if (!r.ok) throw new Error('Network Error');
            return r.json();
        })
        .then(data => {
            grid.replaceChildren();
            if (data.status !== 'success' || !Array.isArray(data.items)) {
                throw new Error(data.message || 'Invalid inventory response');
            }

            if (data.items.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'no-assets-msg';
                empty.textContent = `No interactive assets found for ${category}.`;
                grid.appendChild(empty);
                return;
            }

            data.items.forEach(item => {
                const card = document.createElement('div');
                card.className = 'wardrobe-item-card';
                card.dataset.productId = String(item.id);

                const badge = document.createElement('div');
                badge.className = 'equipped-badge';
                badge.textContent = '✓ EQUIPPED';

                const image = document.createElement('img');
                image.src = `../${item.thumbnail}`;
                image.alt = String(item.name || 'Product');
                image.loading = 'lazy';
                image.decoding = 'async';
                image.addEventListener('error', () => { image.src = '../Assets/images/fallback.webp'; }, { once: true });

                const name = document.createElement('span');
                name.className = 'item-name';
                name.textContent = String(item.name || 'Product');

                const price = document.createElement('span');
                price.className = 'price-tag';
                price.textContent = `Rs. ${Number(item.price || 0).toLocaleString()}`;

                card.append(badge, image, name, price);
                card.addEventListener('click', () => equipItem(category, item.wardrobeImage, Number(item.price || 0), Number(item.id), String(item.name || ''), item.thumbnail));
                grid.appendChild(card);
            });

            const layerKey = categoryLayerMap[category];
            if (currentOutfit[layerKey]) {
                const card = grid.querySelector(`[data-product-id="${CSS.escape(String(currentOutfit[layerKey].id))}"]`);
                if(card) card.classList.add('equipped');
            }

            gsap.from('.wardrobe-item-card', { opacity: 0, scale: 0.95, y: 15, stagger: 0.05, duration: 0.4, ease: 'power2.out' });
        })
        .catch(error => {
            const message = document.createElement('div');
            message.className = 'no-assets-msg';
            message.textContent = 'Secure connection failed. Please refresh.';
            grid.replaceChildren(message);
            console.error('Inventory Fetch Error:', error);
        });
}

function equipItem(category, pngPath, price, id, name, thumb) {
    let layerKey = categoryLayerMap[category];
    const layerImg = document.getElementById(`layer-${layerKey}`);

    if (category === 'Dresses & Gowns') { unequip('Tops'); unequip('Bottoms'); unequip('Tailoring'); unequip('Outerwear'); }
    if (category === 'Tops' || category === 'Bottoms' || category === 'Tailoring & Suiting' || category === 'Outerwear') { unequip('Dresses'); }

    const isRemoving = (currentOutfit[layerKey] && currentOutfit[layerKey].id === id);

    // Clear only the selected wardrobe slot.
    document.querySelectorAll('#inventoryGrid .wardrobe-item-card').forEach(c => c.classList.remove('equipped'));

    if (isRemoving) {
        unequip(layerKey);
    } else {
        layerImg.src = `../${pngPath}`;

        // Keep selection styling within the active category.
        const clickedCard = document.querySelector(`#inventoryGrid [data-product-id="${id}"]`);
        if(clickedCard) clickedCard.classList.add('equipped');

        layerImg.onload = () => {
            gsap.fromTo(layerImg,
                { opacity: 0, scale: 1.05, filter: "brightness(1.5)" },
                { opacity: 1, scale: 1, filter: "brightness(1)", duration: 0.6, ease: "power2.out" }
            );
        };

        currentOutfit[layerKey] = { id: id, price: parseFloat(price), name: name, thumb: thumb };
    }
    updatePrice();
}

function unequip(key) {
    if (!currentOutfit[key]) return;

    const img = document.getElementById(`layer-${key}`);
    if (img) {
        gsap.to(img, { opacity: 0, scale: 1.02, duration: 0.3, onComplete: () => { img.src = ""; } });
        delete currentOutfit[key];
        updatePrice();
    }
}

function updatePrice() {
    let total = 0;
    Object.values(currentOutfit).forEach(v => total += v.price);
    const display = document.getElementById('totalOutfitPrice');

    gsap.to(display, {
        innerText: total, duration: 0.6, snap: { innerText: 1 },
        onUpdate: () => { display.textContent = parseInt(display.innerText || '0', 10).toLocaleString(); }
    });
}

function resetWardrobe(animate = true) {
    Object.keys(currentOutfit).forEach(k => {
        const img = document.getElementById(`layer-${k}`);
        if(animate) {
            gsap.to(img, { opacity: 0, duration: 0.3, onComplete: () => img.src = "" });
        } else {
            img.src = ""; img.style.opacity = 0;
        }
    });
    currentOutfit = {};
    document.querySelectorAll('#inventoryGrid .wardrobe-item-card').forEach(c => c.classList.remove('equipped'));
    updatePrice();
}

// Build the modal with DOM nodes so product text remains plain text.
function showSummaryModal() {
    if(Object.keys(currentOutfit).length === 0) {
        alert("Your canvas is empty. Please equip items before securing.");
        return;
    }

    const list = document.getElementById('modalItemsList');
    list.innerHTML = ""; // Safe to clear
    let total = 0;
    let payload = [];

    for (let key in currentOutfit) {
        let item = currentOutfit[key];
        total += item.price;
        payload.push(item.id);

        // Secure createElement strategy (No template literals for HTML)
        const itemDiv = document.createElement('div');
        itemDiv.className = 'summary-item';

        const img = document.createElement('img');
        img.src = `../${item.thumb}`;
        img.alt = item.name;

        const detailsDiv = document.createElement('div');
        detailsDiv.className = 'summary-details';

        const nameSpan = document.createElement('span');
        nameSpan.className = 'summary-name';
        nameSpan.textContent = item.name; // Text Content blocks XSS

        const priceSpan = document.createElement('span');
        priceSpan.className = 'summary-price';
        priceSpan.textContent = `Rs. ${item.price.toLocaleString()}`; // Text Content blocks XSS

        detailsDiv.appendChild(nameSpan);
        detailsDiv.appendChild(priceSpan);
        itemDiv.appendChild(img);
        itemDiv.appendChild(detailsDiv);

        list.appendChild(itemDiv);
    }

    document.getElementById('modalFinalPrice').innerText = total.toLocaleString();
    document.getElementById('lookDataInput').value = JSON.stringify(payload);

    document.body.style.overflow = 'hidden'; // Scroll lock
    document.getElementById('outfitModal').classList.add('active');
}

function closeSummaryModal() {
    document.body.style.overflow = '';
    document.getElementById('outfitModal').classList.remove('active');
}