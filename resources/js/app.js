import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    const shell = document.querySelector('[data-app-shell]');
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const close = document.querySelector('[data-sidebar-close]');

    toggle?.addEventListener('click', () => shell?.classList.toggle('sidebar-open'));
    close?.addEventListener('click', () => shell?.classList.remove('sidebar-open'));

    const publicHeader = document.querySelector('[data-public-header]');
    const publicNavigation = document.querySelector('[data-public-nav]');
    const publicNavigationToggle = document.querySelector('[data-public-nav-toggle]');

    const closePublicNavigation = () => {
        publicHeader?.classList.remove('nav-open');
        publicNavigationToggle?.setAttribute('aria-expanded', 'false');
        publicNavigationToggle?.setAttribute('aria-label', 'Abrir menú');
    };

    publicNavigationToggle?.addEventListener('click', () => {
        const isOpen = publicHeader?.classList.toggle('nav-open') ?? false;

        publicNavigationToggle.setAttribute('aria-expanded', String(isOpen));
        publicNavigationToggle.setAttribute('aria-label', isOpen ? 'Cerrar menú' : 'Abrir menú');
    });

    publicNavigation?.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', closePublicNavigation);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closePublicNavigation();
        }
    });

    const updatePublicHeader = () => {
        publicHeader?.classList.toggle('is-scrolled', window.scrollY > 12);
    };

    updatePublicHeader();
    window.addEventListener('scroll', updatePublicHeader, { passive: true });

    const contactFeedback = document.querySelector('[data-contact-feedback]');

    if (contactFeedback) {
        contactFeedback.scrollIntoView({
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
            block: 'center',
        });
        contactFeedback.focus({ preventScroll: true });
    }

    const contactForm = document.querySelector('[data-contact-form]');

    contactForm?.addEventListener('submit', () => {
        const submitButton = contactForm.querySelector('[data-contact-submit]');
        const submitLabel = submitButton?.querySelector('span');

        if (submitButton) {
            submitButton.disabled = true;
        }

        if (submitLabel) {
            submitLabel.textContent = 'Enviando…';
        }
    });

    const siteModeForm = document.querySelector('form[data-site-mode]');

    if (siteModeForm) {
        const siteModeInputs = siteModeForm.querySelectorAll('input[name="site_mode"]');
        const existingSiteFields = siteModeForm.querySelector('[data-existing-site]');
        const newSiteFields = siteModeForm.querySelector('[data-new-site]');

        const setSiteFieldsState = (container, enabled, requiredSelector) => {
            if (!container) {
                return;
            }

            container.hidden = !enabled;
            container.querySelectorAll('input, select, textarea').forEach((field) => {
                field.disabled = !enabled;
                field.required = enabled && field.matches(requiredSelector);
            });
        };

        const updateSiteMode = () => {
            const selectedMode = siteModeForm.querySelector('input[name="site_mode"]:checked')?.value;

            setSiteFieldsState(existingSiteFields, selectedMode === 'existing', '[data-required-when-existing]');
            setSiteFieldsState(newSiteFields, selectedMode === 'new', '[data-required-when-new]');
        };

        siteModeInputs.forEach((input) => input.addEventListener('change', updateSiteMode));
        updateSiteMode();
    }
});
