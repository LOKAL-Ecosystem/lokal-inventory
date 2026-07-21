import './bootstrap';
import Swal from 'sweetalert2';
import { createIcons, icons } from 'lucide';

// Initialize Lucide icons
window.initLucideIcons = () => {
    createIcons({ icons });
};

// Configure SweetAlert2 defaults with primary color #4285F4
window.Swal = Swal.mixin({
    confirmButtonColor: '#4285F4',
    cancelButtonColor: '#202124',
    customClass: {
        confirmButton: 'btn btn-primary text-white font-medium px-4 py-2 rounded-md',
        cancelButton: 'btn btn-neutral text-white font-medium px-4 py-2 rounded-md ml-2'
    },
    buttonsStyling: false
});

document.addEventListener('DOMContentLoaded', () => {
    window.initLucideIcons();
});

