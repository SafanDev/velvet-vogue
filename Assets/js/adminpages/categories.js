/**
 * Velvet Vogue - Category Management Logic
 */

document.addEventListener("DOMContentLoaded", function () {
    "use strict";

    // Intro Animations
    gsap.from(".gsap-card", {
        y: 30, opacity: 0, duration: 0.8, stagger: 0.1, ease: "power3.out", delay: 0.2
    });

    // 1. DYNAMIC FORM SHAPESHIFTER (Edit Mode Logic)
    const cards = document.querySelectorAll('.cat-card');
    const form = document.getElementById('categoryForm');
    const title = document.getElementById('consoleTitle');
    const submitBtnText = document.getElementById('submitBtn').querySelector('.btn-text'); // Targeted specifically to avoid overriding the icon
    const cancelBtn = document.getElementById('cancelEditBtn');
    const dropzone = document.getElementById('bannerDropzone');
    const dropzoneContent = document.getElementById('dropzoneContent');
    
    // Inputs
    const idInput = document.getElementById('categoryID');
    const nameInput = document.getElementById('categoryName');
    const slugInput = document.getElementById('slug');
    const descInput = document.getElementById('description');
    const sortInput = document.getElementById('sortOrder');
    const activeInput = document.getElementById('isActive');
    const existingImgInput = document.getElementById('existingImage');

    cards.forEach(card => {
        card.addEventListener('click', function(e) {
            if (e.target.closest('.delete-btn')) return;

            // Extract Data from the card's attributes
            const id = this.dataset.id;
            const name = this.dataset.name;
            const slug = this.dataset.slug;
            const desc = this.dataset.desc;
            const sort = this.dataset.sort;
            const active = this.dataset.active;
            const imgUrl = this.dataset.img;

            // Populate Form
            idInput.value = id;
            nameInput.value = name;
            slugInput.value = slug;
            descInput.value = desc;
            sortInput.value = sort;
            activeInput.checked = (active === '1');
            existingImgInput.value = imgUrl;

            // Update Image Preview
            if(imgUrl) {
                dropzone.style.backgroundImage = `url('../${imgUrl}')`;
                dropzoneContent.style.opacity = '0'; // Hide text when image exists
            } else {
                dropzone.style.backgroundImage = 'none';
                dropzoneContent.style.opacity = '1';
            }

            // Force label active states (moves the floating text up)
            const inputs = [nameInput, slugInput, descInput, sortInput];
            inputs.forEach(input => input.dispatchEvent(new Event('input')));

            // Morph Form into Edit Mode
            title.innerText = "Edit Category";
            submitBtnText.innerText = "Update Category";
            cancelBtn.classList.remove('d-none');
            
            // Visual feedback on the grid
            cards.forEach(c => c.classList.remove('editing-pulse'));
            this.classList.add('editing-pulse');

            // Scroll to form on mobile
            if (window.innerWidth < 1200) {
                document.getElementById('consoleCard').scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // 2. CANCEL EDIT (Revert to Add Mode)
    cancelBtn.addEventListener('click', function() {
        // Reset form completely
        form.reset();
        idInput.value = '0';
        existingImgInput.value = '';
        
        dropzone.style.backgroundImage = 'none';
        dropzoneContent.style.opacity = '1';

        title.innerText = "Add New Category";
        submitBtnText.innerText = "Save Category";
        this.classList.add('d-none');

        cards.forEach(c => c.classList.remove('editing-pulse'));
    });

    // 3. IMAGE UPLOAD PREVIEW (Live update of the dropzone background)
    const imgInput = document.getElementById('imageURL');
    imgInput.addEventListener('change', function(e) {
        if(this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(ev) {
                dropzone.style.backgroundImage = `url('${ev.target.result}')`;
                dropzoneContent.style.opacity = '0'; // Hide the icon/text gracefully
            }
            reader.readAsDataURL(this.files[0]);
        }
    });

    // Handle Dropzone hover text reveal if image is present
    dropzone.addEventListener('mouseenter', () => {
        if(dropzone.style.backgroundImage !== 'none' && dropzone.style.backgroundImage !== '') {
            dropzoneContent.style.opacity = '1';
            dropzoneContent.querySelector('.dropzone-text').innerText = "Change Image";
        }
    });
    dropzone.addEventListener('mouseleave', () => {
        if(dropzone.style.backgroundImage !== 'none' && dropzone.style.backgroundImage !== '') {
            dropzoneContent.style.opacity = '0';
        }
    });

    // 4. AUTO-SLUG GENERATOR
    // As the admin types the category name, we automatically suggest a formatted slug
    nameInput.addEventListener('keyup', function() {
        if (idInput.value === '0') {
            let text = this.value;
            let formattedSlug = text.toLowerCase()
                .replace(/[^\w\s-]/g, '') // Remove non-word chars
                .replace(/[\s_-]+/g, '-') // Replace spaces and underscores with dashes
                .replace(/^-+|-+$/g, ''); // Remove trailing dashes
            
            slugInput.value = formattedSlug;
            slugInput.dispatchEvent(new Event('input')); // trigger the floating label
        }
    });

});