/**
 * Storefront Page & Section Builder Editor
 */
window.storefrontPageEditor = function (initial, options = {}) {
    'use strict';

    return {
        sections: (initial || []).map((s, i) => {
            const cfg = s.config || {};
            // Ensure items is an array if present
            let items = cfg.items || [];
            if (typeof items === 'string') {
                try { items = JSON.parse(items); } catch { items = []; }
            }
            if (!Array.isArray(items)) items = [];

            return {
                ...s,
                key: 'sec_' + i + '_' + Math.random().toString(36).substr(2, 9),
                active: s.active !== false,
                visibility: s.visibility || 'all',
                starts_at: s.starts_at ? s.starts_at.replace(' ', 'T').substring(0, 16) : '',
                ends_at: s.ends_at ? s.ends_at.replace(' ', 'T').substring(0, 16) : '',
                config: {
                    title: '',
                    subtitle: '',
                    button_text: '',
                    button_link: '',
                    source: 'new-arrivals',
                    limit: 8,
                    items: items,
                    text: '',
                    slider_id: options.defaultSliderId || 1,
                    category: '',
                    brand: '',
                    product_ids: [],
                    ends_at: '',
                    image: '',
                    ...cfg
                }
            };
        }),

        selectedIndex: 0,
        pageModalOpen: false,
        addSectionModalOpen: false,
        dragIndex: null,
        error: '',
        activeImageTarget: null,
        searchSectionQuery: '',

        init() {
            // Listen to global media-picker:selected events via bridge
            const bridge = document.getElementById('storefrontMediaPickerBridge');
            if (bridge) {
                bridge.addEventListener('media-picker:selected', (e) => {
                    const media = e.detail?.media;
                    if (!media) return;
                    const item = Array.isArray(media) ? media[0] : media;
                    const url = item?.url || (item?.id ? '/storage/' + item.id : '');
                    if (!url) return;

                    if (this.activeImageTarget) {
                        const { sectionIndex, itemIndex, field } = this.activeImageTarget;
                        const sec = this.sections[sectionIndex];
                        if (sec) {
                            if (itemIndex !== null && itemIndex !== undefined && sec.config.items?.[itemIndex]) {
                                sec.config.items[itemIndex][field || 'image'] = url;
                            } else if (field) {
                                sec.config[field] = url;
                            }
                        }
                    }
                    this.activeImageTarget = null;
                });
            }

            // Select first section if available
            if (this.sections.length > 0) {
                this.selectedIndex = 0;
            }
        },

        get currentSection() {
            return this.sections[this.selectedIndex] || null;
        },

        selectSection(index) {
            if (index >= 0 && index < this.sections.length) {
                this.selectedIndex = index;
            }
        },

        openMediaPicker(target) {
            this.activeImageTarget = target;
            const trigger = document.querySelector('#storefrontMediaPickerBridge [data-media-picker-trigger]');
            if (trigger) {
                trigger.click();
            } else {
                alert('Media picker is loading. Please try again.');
            }
        },

        removeImage(target) {
            const { sectionIndex, itemIndex, field } = target;
            const sec = this.sections[sectionIndex];
            if (sec) {
                if (itemIndex !== null && itemIndex !== undefined && sec.config.items?.[itemIndex]) {
                    sec.config.items[itemIndex][field || 'image'] = '';
                } else if (field) {
                    sec.config[field] = '';
                }
            }
        },

        add(type) {
            const defaults = this.getDefaultConfig(type);
            const newSection = {
                key: 'sec_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9),
                type: type,
                active: true,
                visibility: 'all',
                starts_at: '',
                ends_at: '',
                config: defaults
            };

            this.sections.push(newSection);
            this.selectedIndex = this.sections.length - 1;
            this.addSectionModalOpen = false;
        },

        getDefaultConfig(type) {
            switch (type) {
                case 'hero_slider':
                    return {
                        title: '',
                        subtitle: '',
                        slider_id: options.defaultSliderId || 1,
                        button_text: '',
                        button_link: ''
                    };
                case 'product_carousel':
                    return {
                        title: 'Featured Products',
                        subtitle: 'Discover our top selling wholesale garments',
                        source: 'new-arrivals',
                        category: '',
                        brand: '',
                        product_ids: [],
                        limit: 8,
                        button_text: 'View All',
                        button_link: '/products'
                    };
                case 'category_grid':
                case 'category_carousel':
                    return {
                        title: 'Shop by Category',
                        subtitle: 'Explore curated garment styles and collections',
                        button_text: 'All Categories',
                        button_link: '/categories'
                    };
                case 'promo_banner':
                    return {
                        title: 'Exclusive Deals',
                        subtitle: 'Save up to 30% on pre-packed box cartons',
                        button_text: 'Shop Deals',
                        button_link: '/products',
                        items: [
                            {
                                title: 'Seasonal Collection',
                                text: 'Premium heavyweight cotton styles ready for export',
                                image: '',
                                link: '/products'
                            }
                        ]
                    };
                case 'content_grid':
                    return {
                        title: 'Editorial Highlights',
                        subtitle: 'Crafted with premium materials and ethical manufacturing',
                        button_text: '',
                        button_link: '',
                        items: [
                            {
                                title: '320 GSM Fleece',
                                text: 'Heavyweight brushed fleece engineered for streetwear brands.',
                                image: '',
                                link: '/products'
                            },
                            {
                                title: 'Consistent Sizing',
                                text: 'Every carton packed according to strict ratio distributions.',
                                image: '',
                                link: '/products'
                            }
                        ]
                    };
                case 'testimonials':
                    return {
                        title: 'What Buyers Say',
                        subtitle: 'Trusted by boutique owners and apparel labels worldwide',
                        items: [
                            {
                                title: 'Boutique Owner, UK',
                                text: 'Outstanding fabric quality and exact box ratios. Will definitely reorder.',
                                image: '',
                                link: ''
                            }
                        ]
                    };
                case 'rich_text':
                    return {
                        title: 'Our Craftsmanship',
                        subtitle: '',
                        text: '<p>Every garment is manufactured using combed compact cotton with double-stitched seams and pre-shrunk finishing.</p>'
                    };
                case 'countdown_deal':
                    const nextWeek = new Date();
                    nextWeek.setDate(nextWeek.getDate() + 7);
                    return {
                        title: 'Limited Flash Sale',
                        subtitle: 'Special wholesale rates ending soon',
                        ends_at: nextWeek.toISOString().substring(0, 16),
                        image: '',
                        button_text: 'Order Boxes Now',
                        button_link: '/products'
                    };
                case 'benefit_banner':
                    return {
                        title: 'Why Buy From Us',
                        subtitle: 'Designed for retail store owners and garment distributors',
                        items: [
                            { title: 'Global Express Air & Sea', text: 'Door to door shipping with tracking' },
                            { title: 'Pre-Sorted Box Packs', text: 'S, M, L, XL, XXL in perfect store ratios' },
                            { title: 'Sample Verification', text: 'Inspect individual sample pieces before bulk' }
                        ]
                    };
                case 'newsletter':
                    return {
                        title: 'Stay in the Know',
                        subtitle: 'Subscribe to get release drops, wholesale price lists, and promotions.',
                        button_text: 'Subscribe',
                        button_link: ''
                    };
                case 'cta':
                    return {
                        title: 'Start Ordering Wholesale Today',
                        subtitle: 'Low MOQs, factory direct pricing, and fast international delivery.',
                        button_text: 'Browse Catalog',
                        button_link: '/products'
                    };
                case 'brand_strip':
                    return {
                        title: 'Authorized Brands',
                        subtitle: 'Official licensed and custom manufacture labels',
                        button_text: '',
                        button_link: ''
                    };
                default:
                    return {
                        title: '',
                        subtitle: '',
                        button_text: '',
                        button_link: ''
                    };
            }
        },

        remove(index) {
            if (confirm('Are you sure you want to remove this section?')) {
                this.sections.splice(index, 1);
                if (this.selectedIndex >= this.sections.length) {
                    this.selectedIndex = Math.max(0, this.sections.length - 1);
                }
            }
        },

        duplicate(index) {
            const original = this.sections[index];
            if (!original) return;

            const cloned = JSON.parse(JSON.stringify(original));
            cloned.key = 'sec_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            cloned.config.title = (cloned.config.title || original.type) + ' (Copy)';

            this.sections.splice(index + 1, 0, cloned);
            this.selectedIndex = index + 1;
        },

        move(from, to) {
            if (to >= 0 && to < this.sections.length && from !== null && from !== undefined) {
                const item = this.sections.splice(from, 1)[0];
                this.sections.splice(to, 0, item);
                this.selectedIndex = to;
            }
        },

        addItem(section) {
            if (!Array.isArray(section.config.items)) {
                section.config.items = [];
            }
            if (section.config.items.length >= 12) {
                alert('Maximum 12 items allowed per section.');
                return;
            }

            section.config.items.push({
                title: 'New Item #' + (section.config.items.length + 1),
                text: '',
                image: '',
                link: ''
            });
        },

        removeItem(section, itemIndex) {
            if (Array.isArray(section.config.items)) {
                section.config.items.splice(itemIndex, 1);
            }
        },

        getSectionIcon(type) {
            switch (type) {
                case 'hero_slider': return 'ph ph-presentation';
                case 'product_carousel': return 'ph ph-t-shirt';
                case 'category_grid':
                case 'category_carousel': return 'ph ph-squares-four';
                case 'promo_banner': return 'ph ph-tag';
                case 'content_grid': return 'ph ph-layout';
                case 'testimonials': return 'ph ph-chat-circle-dots';
                case 'rich_text': return 'ph ph-article';
                case 'countdown_deal': return 'ph ph-timer';
                case 'benefit_banner': return 'ph ph-shield-check';
                case 'newsletter': return 'ph ph-envelope-simple';
                case 'cta': return 'ph ph-megaphone-simple';
                case 'brand_strip': return 'ph ph-trademark';
                default: return 'ph ph-puzzle-piece';
            }
        },

        getSectionLabel(type) {
            switch (type) {
                case 'hero_slider': return 'Hero Banner Slider';
                case 'product_carousel': return 'Product Carousel';
                case 'category_grid': return 'Category Grid';
                case 'category_carousel': return 'Category Carousel';
                case 'promo_banner': return 'Promo Banners';
                case 'content_grid': return 'Content Feature Grid';
                case 'testimonials': return 'Customer Testimonials';
                case 'rich_text': return 'Rich Text Block';
                case 'countdown_deal': return 'Flash Countdown Deal';
                case 'benefit_banner': return 'Trust & Benefit Strip';
                case 'newsletter': return 'Newsletter Signup';
                case 'cta': return 'Call to Action (CTA)';
                case 'brand_strip': return 'Brand Logos Strip';
                default: return type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            }
        },

        getSectionDesc(type) {
            switch (type) {
                case 'hero_slider': return 'Full-width slider with animated headings, images, and CTAs.';
                case 'product_carousel': return 'Dynamic product collection (New arrivals, deals, category).';
                case 'category_grid': return 'Visual grid showcasing store categories with image tiles.';
                case 'category_carousel': return 'Horizontal sliding category showcase with icons and thumbnails.';
                case 'promo_banner': return 'Attention-grabbing promotional cards with images and links.';
                case 'content_grid': return 'Multi-column editorial feature cards with photos and text.';
                case 'testimonials': return 'Customer reviews, ratings, and feedback quotes.';
                case 'rich_text': return 'Rich text block for brand story, about us, or store policy.';
                case 'countdown_deal': return 'Urgency timer banner for flash sales and wholesale specials.';
                case 'benefit_banner': return 'Trust badges: Free delivery, guarantee, wholesale ratios.';
                case 'newsletter': return 'High-conversion newsletter capture banner.';
                case 'cta': return 'High impact call to action banner driving shoppers to catalog.';
                case 'brand_strip': return 'Row of partner, authorized, and manufactured brand logos.';
                default: return 'Custom storefront layout section.';
            }
        },

        cleanPayload() {
            return this.sections.map((s, index) => {
                const cfg = { ...(s.config || {}) };

                // Clean string slider_id to int
                if (s.type === 'hero_slider') {
                    cfg.slider_id = parseInt(cfg.slider_id, 10) || options.defaultSliderId || 1;
                }

                // Clean limit to int
                if (cfg.limit !== undefined) {
                    cfg.limit = parseInt(cfg.limit, 10) || 8;
                }

                // Ensure items is an array with clean objects
                if (['promo_banner', 'content_grid', 'testimonials', 'benefit_banner'].includes(s.type)) {
                    if (Array.isArray(cfg.items)) {
                        cfg.items = cfg.items.map(it => ({
                            title: (it.title || '').trim(),
                            text: (it.text || '').trim(),
                            image: (it.image || '').trim() || null,
                            link: (it.link || '').trim() || null
                        }));
                    } else {
                        cfg.items = [];
                    }
                }

                return {
                    type: s.type,
                    active: !!s.active,
                    visibility: s.visibility || 'all',
                    starts_at: s.starts_at ? s.starts_at.replace('T', ' ') : null,
                    ends_at: s.ends_at ? s.ends_at.replace('T', ' ') : null,
                    config: cfg
                };
            });
        },

        submitForm(publishNow = false) {
            this.error = '';
            try {
                const cleaned = this.cleanPayload();
                document.getElementById('storefrontSectionsInput').value = JSON.stringify(cleaned);
                document.getElementById('publishNowInput').value = publishNow ? '1' : '0';
                document.getElementById('storefrontPageForm').submit();
            } catch (err) {
                this.error = 'Failed to prepare page sections: ' + err.message;
            }
        }
    };
};
