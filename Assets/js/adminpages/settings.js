/**
 * Velvet Vogue - Settings Logic & Smart Tab Persistence
 */
document.addEventListener("DOMContentLoaded", function () {
    "use strict";

    // 1. Initial Page Reveal
    gsap.from(".scroll-reveal", { y: 15, opacity: 0, duration: 0.6, stagger: 0.1, ease: "power2.out" });

    // 2. Sidebar Tab Switching & Persistence
    const tabs = document.querySelectorAll('.pref-tab');
    const sections = document.querySelectorAll('.pref-section');

    const activeTarget = localStorage.getItem('velvet_settings_tab') || 'pref-contact';
    
    tabs.forEach(t => {
        if(t.getAttribute('data-target') === activeTarget) {
            t.classList.add('active');
        } else {
            t.classList.remove('active');
        }
    });

    sections.forEach(s => {
        if(s.id === activeTarget) {
            s.classList.remove('d-none');
        } else {
            s.classList.add('d-none');
        }
    });

    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');

            sections.forEach(s => s.classList.add('d-none'));
            const targetId = this.getAttribute('data-target');
            const target = document.getElementById(targetId);
            target.classList.remove('d-none');
            
            localStorage.setItem('velvet_settings_tab', targetId);
            
            const rows = target.querySelectorAll('.pref-row');
            gsap.fromTo(rows, 
                { opacity: 0, x: 10 }, 
                { opacity: 1, x: 0, duration: 0.4, stagger: 0.05, ease: "power2.out" }
            );
        });
    });

    // 3. Smart HUD
    const statusPill = document.getElementById('statusPill');
    const syncStatusText = document.getElementById('syncStatusText');
    const allInputs = document.querySelectorAll('#settingsForm input, #settingsForm textarea');
    let hasUnsavedChanges = false;

    allInputs.forEach(input => {
        input.addEventListener('input', () => {
            if (!hasUnsavedChanges) {
                hasUnsavedChanges = true;
                statusPill.className = 'status-pill warning';
                syncStatusText.innerText = 'Unsaved Changes';
            }
        });
    });

    // 4. Handle Form Submission with Images
    const form = document.getElementById('settingsForm');
    const saveBtn = document.getElementById('saveBtn');
    const btnText = saveBtn.querySelector('.btn-text');
    const btnIcon = saveBtn.querySelector('.btn-icon');

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const ogText = btnText.innerText;
        btnText.innerText = 'Saving...';
        btnIcon.className = 'fa-solid fa-spinner fa-spin btn-icon';
        saveBtn.disabled = true;

        statusPill.className = 'status-pill syncing';
        syncStatusText.innerText = 'Syncing...';

        // FormData automatically packages file inputs!
        const formData = new FormData(this);

        fetch('settings.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(res => {
            if(res.status === 'success') {
                hasUnsavedChanges = false;
                statusPill.className = 'status-pill success';
                syncStatusText.innerText = 'System Saved';
                
                document.querySelectorAll('.custom-input-box').forEach(box => {
                    gsap.fromTo(box, 
                        { borderColor: '#d4af37', backgroundColor: 'rgba(212, 175, 55, 0.05)' }, 
                        { borderColor: 'rgba(255,255,255,0.15)', backgroundColor: '#000', duration: 1.2 }
                    );
                });

                // Refresh to show newly uploaded banner image immediately
                setTimeout(() => { window.location.reload(); }, 600);

            } else {
                throw new Error(res.message);
            }
        })
        .catch(err => {
            statusPill.className = 'status-pill';
            statusPill.style.backgroundColor = 'rgba(231, 76, 60, 0.1)';
            statusPill.style.borderColor = 'rgba(231, 76, 60, 0.2)';
            statusPill.style.color = '#e74c3c';
            statusPill.querySelector('.pulse-dot').style.backgroundColor = '#e74c3c';
            statusPill.querySelector('.pulse-dot').style.boxShadow = '0 0 10px #e74c3c';
            syncStatusText.innerText = 'Error Saving';
            alert(err.message);
        })
        .finally(() => { 
            setTimeout(() => {
                btnText.innerText = ogText; 
                btnIcon.className = 'fa-solid fa-check btn-icon';
                saveBtn.disabled = false; 
            }, 400); 
        });
    });
});