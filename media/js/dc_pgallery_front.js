/**
 * DC Gallery front init — GLightbox, Macy, Swiper.
 * Loaded from header; product descriptions cannot contain inline scripts.
 */
(function () {
    'use strict';

    function whenReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn, { once: true });
        } else {
            fn();
        }
    }

    function waitFor(predicate, cb, attempt) {
        attempt = attempt || 0;
        if (attempt > 150) {
            return;
        }
        if (predicate()) {
            cb();
            return;
        }
        requestAnimationFrame(function () {
            waitFor(predicate, cb, attempt + 1);
        });
    }

    var lbInstance = null;

    function destroyGlightbox() {
        if (!lbInstance) {
            return;
        }
        try {
            lbInstance.destroy();
        } catch (e) {
            /* ignore */
        }
        lbInstance = null;
    }

    function initGlightbox() {
        if (typeof GLightbox === 'undefined') {
            return;
        }
        if (!document.querySelector('.dcg-glightbox')) {
            return;
        }

        var loop = true;
        var noLoop = document.querySelector('.dc-gallery[data-dcg-glightbox-loop="0"] .dcg-glightbox');
        if (noLoop) {
            loop = false;
        }

        destroyGlightbox();
        lbInstance = GLightbox({
            selector: '.dcg-glightbox',
            loop: loop,
        });
    }

    function bootGlightbox() {
        waitFor(function () {
            return typeof GLightbox !== 'undefined';
        }, initGlightbox);
    }

    function initMacyGallery(el) {
        if (el.dataset.dcgInited === '1') {
            return;
        }
        waitFor(function () {
            return typeof Macy !== 'undefined';
        }, function () {
            var inner = el.querySelector('.dcg-macy-inner');
            if (!inner) {
                return;
            }
            Macy({
                container: inner,
                trueOrder: true,
                waitForImages: true,
                columns: parseInt(el.getAttribute('data-dcg-columns') || '4', 10),
                margin: parseInt(el.getAttribute('data-dcg-space') || '16', 10),
            });
            el.dataset.dcgInited = '1';
        });
    }

    function initSwiperGallery(el) {
        if (el.dataset.dcgInited === '1') {
            return;
        }
        waitFor(function () {
            return typeof Swiper !== 'undefined';
        }, function () {
            var mode = el.getAttribute('data-dcg-mode') || '';
            var space = parseInt(el.getAttribute('data-dcg-space') || '16', 10);
            var loop = el.getAttribute('data-dcg-loop') === '1';
            var slidesVisible = parseInt(el.getAttribute('data-dcg-slides-visible') || '3', 10);

            if (mode === 'thumbs') {
                var tEl = el.querySelector('.dcg-swiper-thumbs');
                var mEl = el.querySelector('.dcg-swiper-main');
                if (!tEl || !mEl) {
                    return;
                }
                var thumbs = new Swiper(tEl, {
                    spaceBetween: 10,
                    slidesPerView: 'auto',
                    freeMode: true,
                    watchSlidesProgress: true,
                    preventClicks: false,
                    preventClicksPropagation: false,
                });
                new Swiper(mEl, {
                    spaceBetween: space,
                    loop: loop,
                    navigation: {
                        nextEl: mEl.querySelector('.swiper-button-next'),
                        prevEl: mEl.querySelector('.swiper-button-prev'),
                    },
                    pagination: {
                        el: mEl.querySelector('.swiper-pagination'),
                        clickable: true,
                    },
                    thumbs: { swiper: thumbs },
                    preventClicks: false,
                    preventClicksPropagation: false,
                });
                el.dataset.dcgInited = '1';
                return;
            }

            var config = {
                spaceBetween: space,
                loop: loop,
                pagination: {
                    el: el.querySelector('.swiper-pagination'),
                    clickable: true,
                },
                navigation: {
                    nextEl: el.querySelector('.swiper-button-next'),
                    prevEl: el.querySelector('.swiper-button-prev'),
                },
                preventClicks: false,
                preventClicksPropagation: false,
            };

            if (mode === 'slideshow') {
                config.slidesPerView = 1;
            } else if (mode === 'carousel') {
                config.slidesPerView = slidesVisible;
            } else if (mode === 'coverflow') {
                config.effect = 'coverflow';
                config.grabCursor = true;
                config.centeredSlides = true;
                config.slidesPerView = 'auto';
                config.coverflowEffect = {
                    rotate: 50,
                    stretch: 0,
                    depth: 100,
                    modifier: 1,
                    slideShadows: true,
                };
            } else if (mode === 'cards') {
                config.effect = 'cards';
                config.grabCursor = true;
            }

            new Swiper(el, config);
            el.dataset.dcgInited = '1';
        });
    }

    function initGalleries(root) {
        root = root || document;

        root.querySelectorAll('.dc-gallery[data-dcg-mode="tiles"]').forEach(initMacyGallery);

        root.querySelectorAll('.dc-gallery[data-dcg-mode]').forEach(function (el) {
            var mode = el.getAttribute('data-dcg-mode');
            if (mode === 'tiles' || mode === 'normal') {
                return;
            }
            initSwiperGallery(el);
        });

        bootGlightbox();
    }

    function bindAccordion() {
        var accordion = document.querySelector('[data-dc-product-accordion]');
        if (!accordion) {
            return;
        }

        accordion.addEventListener('click', function (event) {
            var trigger = event.target.closest('.dc-accordion-trigger');
            if (!trigger || !accordion.contains(trigger)) {
                return;
            }
            var item = trigger.closest('.dc-accordion-item');
            if (!item) {
                return;
            }

            window.setTimeout(function () {
                if (!item.classList.contains('is-open')) {
                    return;
                }
                initGalleries(item);
            }, 380);
        });
    }

    whenReady(function () {
        initGalleries(document);
        bindAccordion();
    });
})();
