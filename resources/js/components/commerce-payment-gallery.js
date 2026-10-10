import Alpine from 'alpinejs';

Alpine.data('commercePaymentGallery', (images) => {
    'use strict';

    return {
        images,
        activeIndex: 0,
        failedImages: {},
        trigger: null,
        open(index, trigger) {
            this.activeIndex = index;
            this.trigger = trigger;
            this.$refs.viewer.showModal();
        },
        move(direction) {
            this.activeIndex = (this.activeIndex + direction + this.images.length) % this.images.length;
        },
        restoreFocus() {
            this.trigger?.focus();
        },
    };
});
