/**
 * Velvet Vogue - Product Edit Dynamics
 */

window.fileQueues = {};

window.previewImages = function(input, previewContainerId, inputId) {
    const previewContainer = document.getElementById(previewContainerId);

    if (!window.fileQueues[inputId]) {
        window.fileQueues[inputId] = new DataTransfer();
    }
    const dt = window.fileQueues[inputId];

    if (input.files) {
        Array.from(input.files).forEach(file => {
            let exists = false;
            for(let i=0; i < dt.files.length; i++) {
                if(dt.files[i].name === file.name && dt.files[i].size === file.size) exists = true;
            }
            if(!exists) dt.items.add(file);
        });
    }

    input.files = dt.files;
    renderPreviews(dt, previewContainer, input, inputId);
};

function renderPreviews(dt, container, inputElement, inputId) {
    container.innerHTML = ''; 
    
    Array.from(dt.files).forEach((file, index) => {
        const reader = new FileReader();
        reader.onload = function(e) {
            const wrap = document.createElement('div');
            wrap.className = 'mini-preview-wrap';
            
            const img = document.createElement('img');
            img.src = e.target.result;
            img.className = 'mini-preview-img';
            
            const removeBtn = document.createElement('button');
            removeBtn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
            removeBtn.className = 'img-remove-btn';
            removeBtn.type = 'button';
            
            removeBtn.onclick = function(ev) {
                ev.stopPropagation(); 
                dt.items.remove(index); 
                inputElement.files = dt.files; 
                renderPreviews(dt, container, inputElement, inputId); 
            };
            
            wrap.appendChild(img);
            wrap.appendChild(removeBtn);
            container.appendChild(wrap);
        }
        reader.readAsDataURL(file);
    });
}

// Mark existing images for deletion when the form is saved.
window.markImageDeleted = function(imageID) {
    const card = document.getElementById('img-card-' + imageID);
    if(card) {
        // Visually shrink and hide
        gsap.to(card, {scale: 0, opacity: 0, duration: 0.3, onComplete: () => card.style.display = 'none'});
        
        // Inject a hidden input to tell PHP to wipe it
        const container = document.getElementById('deletedImagesContainer');
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'deleted_images[]';
        input.value = imageID;
        container.appendChild(input);
    }
};

document.addEventListener("DOMContentLoaded", function () {
    "use strict";

    // 1. QUILL.JS (Loads existing data perfectly)
    const quill = new Quill('#quillEditorContainer', {
        theme: 'snow',
        placeholder: 'Enter rich editorial text here...',
        modules: { toolbar: [ ['bold', 'italic', 'underline'], [{ 'list': 'ordered'}, { 'list': 'bullet' }], ['clean'] ] }
    });
    
    const form = document.getElementById('addProductForm');
    const hiddenDescInput = document.getElementById('description');
    if(form) { form.addEventListener('submit', function() { hiddenDescInput.value = quill.root.innerHTML; }); }

    // 2. VALIDATION
    if (form) {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            const requiredFields = form.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                if (!field.value.trim() || (field.type === 'number' && field.value === '')) {
                    const wrapper = field.closest('.input-wrapper');
                    if (wrapper) { wrapper.classList.add('error-border-wrapper'); } 
                    else { field.classList.add('error-border'); }
                    isValid = false;
                }
            });

            if (!isValid) {
                e.preventDefault(); 
                const firstError = document.querySelector('.error-border, .error-border-wrapper');
                if(firstError) { firstError.scrollIntoView({behavior: 'smooth', block: 'center'}); }
            }
        });

        form.addEventListener('input', function(e) {
            if (e.target.hasAttribute('required')) {
                e.target.classList.remove('error-border');
                const wrapper = e.target.closest('.input-wrapper');
                if (wrapper) wrapper.classList.remove('error-border-wrapper');
            }
        });
    }

    // 3. NUM SPINNERS
    document.addEventListener('click', function(e) {
        if(e.target.classList.contains('num-plus')) {
            const input = e.target.previousElementSibling;
            input.value = parseInt(input.value || 0) + 1;
            input.dispatchEvent(new Event('input')); 
        }
        if(e.target.classList.contains('num-minus')) {
            const input = e.target.nextElementSibling;
            if(parseInt(input.value || 0) > 0) {
                input.value = parseInt(input.value || 0) - 1;
                input.dispatchEvent(new Event('input'));
            }
        }
    });

    // 4. PILL LOGIC
    const pills = document.querySelectorAll('.attr-pill');
    pills.forEach(pill => {
        pill.addEventListener('click', function() {
            this.classList.toggle('active');
        });
    });

    // 5. APPEND MATRIX (Does not delete existing rows!)
    const generateBtn = document.getElementById('generateVariantsBtn');
    const variantsContainer = document.getElementById('variantsContainer');
    const emptyGridMsg = document.getElementById('emptyGridMsg');
    const dropzonesContainer = document.getElementById('dynamicDropzonesContainer');
    const gridHeaders = document.getElementById('gridHeaders');
    const bulkModule = document.getElementById('bulkCommandModule');

    if(generateBtn && variantsContainer && dropzonesContainer) {
        generateBtn.addEventListener('click', function() {
            
            const activeSizes = Array.from(document.querySelectorAll('#sizeSelector .attr-pill.active')).map(el => el.dataset.value);
            const activeColors = Array.from(document.querySelectorAll('#colorSelector .attr-pill.active')).map(el => el.dataset.value);
            
            const finalSizes = activeSizes.length > 0 ? activeSizes : ['Standard'];
            const finalColors = activeColors.length > 0 ? activeColors : ['Standard'];
            
            // Generate Matrix - APPEND ONLY
            finalSizes.forEach(size => {
                finalColors.forEach(color => {
                    const comboId = `${size}-${color}`;
                    // Check if this specific row already exists in the DOM
                    const existingRow = variantsContainer.querySelector(`.variant-row[data-combo="${comboId}"]`);
                    
                    if (!existingRow) {
                        let defaultSku = `VV-${size.substring(0,3).toUpperCase()}-${color.substring(0,3).toUpperCase()}-${Math.floor(Math.random()*1000)}`;
                        if(size === 'Standard' && color === 'Standard') defaultSku = '';

                        const newRowHtml = `
                        <div class="variant-row mb-3 pb-3 border-bottom-dark" data-combo="${comboId}">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-gold font-body fw-bold" style="font-size: 0.85rem; letter-spacing: 1px;">${size} &bull; ${color}</span>
                                <button type="button" class="btn-action-ghost remove-row-btn m-0" style="width: 24px; height: 24px; font-size: 0.8rem;"><i class="fa-solid fa-xmark pointer-events-none"></i></button>
                            </div>
                            <div class="row g-2">
                                <div class="col-12 col-md-5">
                                    <div class="input-wrapper">
                                        <input type="text" name="v_sku[]" class="luxury-input-small font-monospace text-gold px-3" placeholder="SKU" value="${defaultSku}" required>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="input-wrapper">
                                        <button type="button" class="num-btn num-minus">-</button>
                                        <input type="number" name="v_stock[]" class="luxury-input-small font-monospace matrix-stock-input" placeholder="0" required min="0" value="0">
                                        <button type="button" class="num-btn num-plus">+</button>
                                    </div>
                                </div>
                                <div class="col-6 col-md-4">
                                    <div class="input-wrapper">
                                        <button type="button" class="num-btn num-minus">-</button>
                                        <input type="number" name="v_price[]" class="luxury-input-small font-monospace matrix-price-input" placeholder="0.00" value="0">
                                        <button type="button" class="num-btn num-plus">+</button>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="v_size[]" value="${size}">
                            <input type="hidden" name="v_color[]" value="${color}">
                        </div>
                        `;
                        variantsContainer.insertAdjacentHTML('afterbegin', newRowHtml);
                    }
                });
            });
            
            if(emptyGridMsg) emptyGridMsg.style.display = 'none';
            if(gridHeaders) gridHeaders.style.display = 'flex'; 
            if(bulkModule) bulkModule.style.display = 'flex'; 

            // Append new color Dropzones if they don't exist
            if (activeColors.length > 0 && activeColors[0] !== 'Standard') {
                activeColors.forEach(color => {
                    const safeColorName = color.replace(/\s+/g, '_');
                    // Check if dropzone exists
                    const existingDrop = document.querySelector(`.color-dropzone-existing[data-color="${color}"]`) || document.querySelector(`#upload_${safeColorName}`);
                    
                    if (!existingDrop) {
                        const dropzoneHtml = `
                        <div class="col-12 col-md-6 color-dropzone-existing" data-color="${color}">
                            <div class="luxury-dropzone-wrapper">
                                <span class="dropzone-title text-white">${color.toUpperCase()} MEDIA</span>
                                <div class="luxury-dropzone" onclick="document.getElementById('upload_${safeColorName}').click()">
                                    <input type="file" id="upload_${safeColorName}" name="image_upload_${safeColorName}[]" accept="image/*" class="d-none" multiple onchange="previewImages(this, 'preview_${safeColorName}', 'upload_${safeColorName}')">
                                    <div class="dropzone-content">
                                        <i class="fa-solid fa-camera dropzone-icon"></i>
                                        <span class="dropzone-text">Add ${color} Images</span>
                                    </div>
                                </div>
                                <div id="preview_${safeColorName}" class="d-flex flex-wrap gap-2 mt-2"></div>
                            </div>
                        </div>
                        `;
                        dropzonesContainer.insertAdjacentHTML('beforeend', dropzoneHtml);
                    }
                });
            }
        });

        variantsContainer.addEventListener('click', function(e) {
            if(e.target.classList.contains('remove-row-btn') || e.target.closest('.remove-row-btn')) {
                const row = e.target.closest('.variant-row');
                if(row) {
                    row.style.opacity = 0;
                    setTimeout(() => {
                        row.remove();
                        if(variantsContainer.querySelectorAll('.variant-row').length === 0) {
                            if(emptyGridMsg) emptyGridMsg.style.display = 'block';
                            if(gridHeaders) gridHeaders.style.display = 'none';
                            if(bulkModule) bulkModule.style.display = 'none';
                        }
                    }, 300);
                }
            }
        });
    }

    // 6. BULK ACTIONS
    const bulkApplyBtn = document.getElementById('bulkApplyBtn');
    if(bulkApplyBtn) {
        bulkApplyBtn.addEventListener('click', function() {
            const bulkStock = document.getElementById('bulkStockInput').value;
            const bulkPrice = document.getElementById('bulkPriceInput').value;

            if(bulkStock !== '') {
                document.querySelectorAll('.matrix-stock-input').forEach(input => {
                    input.value = bulkStock;
                    input.dispatchEvent(new Event('input')); 
                });
            }
            if(bulkPrice !== '') {
                document.querySelectorAll('.matrix-price-input').forEach(input => {
                    input.value = bulkPrice;
                });
            }
            
            document.getElementById('bulkStockInput').value = '';
            document.getElementById('bulkPriceInput').value = '';
        });
    }
}); 