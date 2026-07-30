// /**
//  * Velvet Vogue - Order View AJAX
//  */

// document.addEventListener("DOMContentLoaded", function () {
//     "use strict";
    
//     // Animate the top row cards on load
//     gsap.from(".scroll-reveal", {
//         y: 20, opacity: 0, duration: 0.6, stagger: 0.1, ease: "power3.out"
//     });

//     const statusSelect = document.getElementById('orderStatusSelect');
//     const toastEl = document.getElementById('statusToast');
//     const toastMessage = document.getElementById('toastMessage');
//     let toastInstance = null;

//     if (toastEl) {
//         toastInstance = new bootstrap.Toast(toastEl, { delay: 3000 });
//     }

//     if(statusSelect) {
//         statusSelect.addEventListener('change', function() {
//             const newStatus = this.value;
//             const orderID = this.getAttribute('data-order-id');
//             const originalClass = this.className;
            
//             this.className = 'elegant-select';
//             this.classList.add(`status-${newStatus}`);

//             const formData = new FormData();
//             formData.append('action', 'update_status');
//             formData.append('newStatus', newStatus);

//             fetch('order-view.php?id=' + orderID, {
//                 method: 'POST',
//                 body: formData
//             })
//             .then(response => response.json())
//             .then(data => {
//                 if(data.status === 'success') {
//                     toastEl.classList.remove('border-danger');
//                     toastEl.classList.add('border-success');
//                     toastMessage.innerHTML = `<i class="fa-solid fa-check text-success me-2"></i> Status updated successfully.`;
//                     toastInstance.show();
//                 } else {
//                     throw new Error('Update failed');
//                 }
//             })
//             .catch(error => {
//                 this.className = originalClass; 
//                 toastEl.classList.remove('border-success');
//                 toastEl.classList.add('border-danger');
//                 toastMessage.innerHTML = `<i class="fa-solid fa-triangle-exclamation text-danger me-2"></i> Network error.`;
//                 toastInstance.show();
//             });
//         });
//     }
// });