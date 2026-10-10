import Alpine from 'alpinejs';

Alpine.data('commerceProductGallery', () => {
    'use strict';

    return {
        images: [],
        activeIndex: 0,
        failedImages: {},
        trigger: null,
        openProduct(images, trigger) {
            if (!images.length) return;
            this.images = images;
            this.activeIndex = 0;
            this.trigger = trigger;
            this.$refs.productViewer.showModal();
        },
        move(direction) {
            if (this.images.length < 2) return;
            this.activeIndex = (this.activeIndex + direction + this.images.length) % this.images.length;
        },
        restoreFocus() {
            this.trigger?.focus();
        },
    };
});
