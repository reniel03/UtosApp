let entryAnimationStarted = false;

const startEntryAnimation = () => {
    if (entryAnimationStarted) {
        return;
    }

    entryAnimationStarted = true;
    document.body.classList.add('is-entering');
    window.setTimeout(() => {
        document.body.classList.remove('is-entering');
        document.body.classList.add('entry-complete');
    }, 1450);
};

const carousel = document.getElementById('onboardingCarousel');

if (carousel) {
    const slides = [...carousel.querySelectorAll('.carousel-slide')];
    const progressSegments = [...carousel.querySelectorAll('.carousel-progress-segment')];
    const track = document.getElementById('carouselTrack');
    const announcement = document.getElementById('carouselAnnouncement');
    const nextButton = document.getElementById('carouselNext');
    const skipButton = document.getElementById('carouselSkip');
    const pageContent = document.getElementById('pageContent');
    let activeSlide = 0;
    let carouselTimer;

    const closeCarousel = () => {
        window.clearInterval(carouselTimer);
        carousel.hidden = true;
        document.body.classList.remove('has-onboarding');
        pageContent.removeAttribute('aria-hidden');
        pageContent.inert = false;
        pageContent.querySelector('.brand').focus();
        startEntryAnimation();
    };

    const showSlide = (index) => {
        activeSlide = index;
        slides.forEach((slide, slideIndex) => {
            const isActive = slideIndex === activeSlide;
            slide.classList.toggle('is-active', isActive);
            slide.setAttribute('aria-hidden', String(!isActive));
            progressSegments[slideIndex].classList.toggle('is-active', isActive);
            progressSegments[slideIndex].classList.toggle('is-complete', slideIndex < activeSlide);
        });
        track.style.transform = `translateX(-${activeSlide * 100}%)`;
        announcement.textContent = `Slide ${activeSlide + 1} of ${slides.length}`;
    };

    const advanceCarousel = () => {
        if (activeSlide === slides.length - 1) {
            closeCarousel();
            return;
        }

        showSlide(activeSlide + 1);
    };

    const restartAutoAdvance = () => {
        window.clearInterval(carouselTimer);
        if (!carousel.hidden) {
            carouselTimer = window.setInterval(advanceCarousel, 5000);
        }
    };

    nextButton.addEventListener('click', () => {
        advanceCarousel();
        restartAutoAdvance();
    });

    skipButton.addEventListener('click', closeCarousel);

    carousel.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeCarousel();
        } else if (event.key === 'ArrowRight') {
            advanceCarousel();
            restartAutoAdvance();
        } else if (event.key === 'ArrowLeft' && activeSlide > 0) {
            showSlide(activeSlide - 1);
            restartAutoAdvance();
        }
    });

    showSlide(0);
    skipButton.focus();
    restartAutoAdvance();
}

if (!carousel || carousel.hidden) {
    startEntryAnimation();
}

const loginButton = document.querySelector('.login-button');

if (loginButton) {
    loginButton.addEventListener('pointerdown', (event) => {
        if (event.button !== 0 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        const bounds = loginButton.getBoundingClientRect();
        const diameter = Math.max(bounds.width, bounds.height) * 2;
        const ripple = document.createElement('span');

        ripple.className = 'button-ripple';
        ripple.style.width = `${diameter}px`;
        ripple.style.height = `${diameter}px`;
        ripple.style.left = `${event.clientX - bounds.left - diameter / 2}px`;
        ripple.style.top = `${event.clientY - bounds.top - diameter / 2}px`;
        ripple.addEventListener('animationend', () => ripple.remove(), { once: true });

        loginButton.append(ripple);
    });
}
