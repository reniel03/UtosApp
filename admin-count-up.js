(() => {
    const metricSelectors = [
        '.stat-card strong',
        '.overview-value',
        '.status-list b',
        '.task-summary article > strong',
        '.user-stats article > strong',
        '.transaction-stats article > strong',
        '.transaction-stats article > em'
    ].join(', ');

    function animateMetrics(elements) {
        const metrics = elements.flatMap((element) => {
            const finalText = element.textContent.trim();
            const match = finalText.match(/^(\D*)([\d,]+)(\D*)$/);
            if (!match) return [];

            const target = Number(match[2].replace(/,/g, ''));
            if (!Number.isFinite(target) || target === 0) return [];

            const metric = { element, finalText, prefix: match[1], target, suffix: match[3] };
            element.setAttribute('aria-label', finalText);
            return [metric];
        });

        if (!metrics.length || window.matchMedia('(prefers-reduced-motion: reduce)').matches || document.visibilityState === 'hidden') return;

        const duration = 1200;
        const startTime = performance.now();
        metrics.forEach(({ element, prefix, suffix }) => { element.textContent = `${prefix}0${suffix}`; });

        function updateMetrics(currentTime) {
            const progress = Math.min((currentTime - startTime) / duration, 1);
            metrics.forEach(({ element, finalText, prefix, target, suffix }) => {
                const currentValue = Math.round(target * progress);
                element.textContent = `${prefix}${currentValue.toLocaleString('en-US')}${suffix}`;
                if (progress === 1) element.textContent = finalText;
            });

            if (progress < 1) requestAnimationFrame(updateMetrics);
        }

        requestAnimationFrame(updateMetrics);
    }

    const animateMetric = (element) => animateMetrics([element]);
    window.animateCountUp = animateMetric;
    window.animateCountUps = animateMetrics;
    document.querySelectorAll(metricSelectors).forEach(animateMetric);
})();
