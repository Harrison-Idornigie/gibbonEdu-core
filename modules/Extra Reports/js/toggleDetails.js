/**
 * toggleDetails.js
 * Handles collapsible sections using Alpine.js
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('toggleDetails', () => ({
        isExpanded: true,
        
        init() {
            // Hide all sections except first one on initial load
            const allToggles = document.querySelectorAll('[x-data="toggleDetails"]');
            if (this.$el !== allToggles[0]) {
                this.isExpanded = false;
            }
        },

        toggle() {
            this.isExpanded = !this.isExpanded;
        }
    }));
});
