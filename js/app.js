// Sportibai Micro-Interactions & Scroll Triggers
// Inspired by brewedops.com fluid animations and Apple HIG physics

document.addEventListener('DOMContentLoaded', function () {
    // 1. Reveal elements on intersection (with motion blur support)
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var reveals = document.querySelectorAll('.reveal, .reveal-blur');

    if (reduce || !('IntersectionObserver' in window)) {
        for (var i = 0; i < reveals.length; i++) {
            reveals[i].classList.add('in');
        }
    } else {
        var observer = new IntersectionObserver(function (entries) {
            for (var j = 0; j < entries.length; j++) {
                if (entries[j].isIntersecting) {
                    entries[j].target.classList.add('in');
                    observer.unobserve(entries[j].target);
                }
            }
        }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });

        for (var k = 0; k < reveals.length; k++) {
            observer.observe(reveals[k]);
        }
    }

    // 2. Dynamic Floating Nav Elevation on Scroll
    var navPill = document.querySelector('.nav');
    if (navPill) {
        var onScroll = function () {
            if (window.scrollY > 30) {
                navPill.style.boxShadow = 'inset 0 0.5px 0 rgba(255, 255, 255, 0.15), 0 16px 40px rgba(0, 0, 0, 0.6)';
                navPill.style.background = 'rgba(15, 15, 16, 0.88)';
            } else {
                navPill.style.boxShadow = 'var(--nav-edge-light), var(--shadow-subtle)';
                navPill.style.background = 'var(--nav-glass)';
            }
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    // 3. Apple Design Tactile Feedback (apple-design.md §1 & §10 - Kill latency on pointer-down)
    var tactileTargets = document.querySelectorAll('.btn, .rail-chip, .brewed-chip, .highlight-card, .order-tab, .filter-chip');
    tactileTargets.forEach(function (el) {
        el.addEventListener('pointerdown', function () {
            this.style.transform = 'scale(0.972)';
            this.style.transition = 'transform 80ms cubic-bezier(0.2, 0, 0, 1)';
        });
        var release = function () {
            this.style.transform = '';
            this.style.transition = 'transform 260ms cubic-bezier(0.16, 1, 0.3, 1)';
        };
        el.addEventListener('pointerup', release);
        el.addEventListener('pointerleave', release);
        el.addEventListener('pointercancel', release);
    });

    // 4. Smooth Anchor Scroll Restoration on Load
    if (window.location.hash && window.location.hash.startsWith('#item-')) {
        var targetEl = document.querySelector(window.location.hash);
        if (targetEl) {
            setTimeout(function () {
                targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 200);
        }
    }

    // 5. BrewedOps-Style Interactive Horizontal Stage ([ Why Sportibai ] Built for Athletes)
    var stageContainer = document.querySelector('.brewed-stage-container');
    if (stageContainer) {
        var track = stageContainer.querySelector('.brewed-track');
        var slides = stageContainer.querySelectorAll('.brewed-slide');
        var stepBtns = stageContainer.querySelectorAll('.brewed-step-btn');
        var lineFill = stageContainer.querySelector('.brewed-tracker-line-fill');
        var prevBtn = stageContainer.querySelector('#brewed-prev-btn');
        var nextBtn = stageContainer.querySelector('#brewed-next-btn');

        var currentIndex = 0;
        var totalSlides = slides.length;
        var autoPlayInterval = null;

        function goToSlide(index) {
            if (index < 0) index = totalSlides - 1;
            if (index >= totalSlides) index = 0;
            currentIndex = index;

            // Apply horizontal swipe with Apple critically-damped spring (apple-design.md §4)
            track.style.transition = 'transform 480ms cubic-bezier(0.16, 1, 0.3, 1)';
            track.style.transform = 'translateX(-' + (currentIndex * 100) + '%)';

            // Optical frame-level motion blur settle (apple-design.md §11)
            slides.forEach(function (slide, i) {
                if (i === currentIndex) {
                    slide.classList.add('active');
                    slide.style.filter = 'blur(6px)';
                    slide.style.opacity = '0.75';
                    requestAnimationFrame(function () {
                        setTimeout(function () {
                            slide.style.filter = 'blur(0px)';
                            slide.style.opacity = '1';
                        }, 50);
                    });
                } else {
                    slide.classList.remove('active');
                }
            });

            // Update step indicator dots & labels
            stepBtns.forEach(function (btn, i) {
                if (i === currentIndex) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });

            // Update connecting line fill
            if (lineFill && totalSlides > 1) {
                var pct = (currentIndex / (totalSlides - 1)) * 100;
                lineFill.style.width = pct + '%';
            }
        }

        stepBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var targetIdx = parseInt(this.getAttribute('data-step') || '0', 10);
                goToSlide(targetIdx);
                resetAutoPlay();
            });
        });

        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                goToSlide(currentIndex - 1);
                resetAutoPlay();
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                goToSlide(currentIndex + 1);
                resetAutoPlay();
            });
        }

        // Touch swipe support (mobile)
        var touchStartX = 0;
        var touchEndX = 0;
        stageContainer.addEventListener('touchstart', function (e) {
            touchStartX = e.touches[0].clientX;
        }, { passive: true });

        stageContainer.addEventListener('touchend', function (e) {
            touchEndX = e.changedTouches[0].clientX;
            var diff = touchStartX - touchEndX;
            if (Math.abs(diff) > 45) {
                if (diff > 0) {
                    goToSlide(currentIndex + 1); // Swipe left
                } else {
                    goToSlide(currentIndex - 1); // Swipe right
                }
                resetAutoPlay();
            }
        }, { passive: true });

        function startAutoPlay() {
            autoPlayInterval = setInterval(function () {
                goToSlide(currentIndex + 1);
            }, 6000);
        }

        function resetAutoPlay() {
            clearInterval(autoPlayInterval);
            startAutoPlay();
        }

        stageContainer.addEventListener('mouseenter', function () {
            clearInterval(autoPlayInterval);
        });

        stageContainer.addEventListener('mouseleave', function () {
            startAutoPlay();
        });

        // Mouse-Wheel Scroll Hijack / Scrubbing:
        // When scrolling down while the stage is in view, step through 01 -> 02 -> 03 before continuing down.
        var isWheelThrottled = false;
        function handleWheelScrub(e) {
            if (isWheelThrottled) return;

            var rect = stageContainer.getBoundingClientRect();
            var winHeight = window.innerHeight || document.documentElement.clientHeight;
            // The stage is active for wheel scrub when occupying the center reading zone
            var isCentered = (rect.top <= winHeight * 0.55 && rect.bottom >= winHeight * 0.40);
            if (!isCentered) return;

            if (e.deltaY > 15) {
                // User scrolls DOWN
                if (currentIndex < totalSlides - 1) {
                    e.preventDefault();
                    goToSlide(currentIndex + 1);
                    resetAutoPlay();
                    isWheelThrottled = true;
                    setTimeout(function () { isWheelThrottled = false; }, 650);
                }
                // If already at 03 POWER (last slide), don't preventDefault -> scrolls down naturally
            } else if (e.deltaY < -15) {
                // User scrolls UP
                if (currentIndex > 0) {
                    e.preventDefault();
                    goToSlide(currentIndex - 1);
                    resetAutoPlay();
                    isWheelThrottled = true;
                    setTimeout(function () { isWheelThrottled = false; }, 650);
                }
                // If already at 01 GEAR (first slide), don't preventDefault -> scrolls up naturally
            }
        }

        window.addEventListener('wheel', handleWheelScrub, { passive: false });
        stageContainer.addEventListener('wheel', handleWheelScrub, { passive: false });

        // Initialize first slide
        goToSlide(0);
        startAutoPlay();
    }
});
