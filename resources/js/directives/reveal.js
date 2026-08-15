/*
 * v-reveal : fait apparaître un élément (fondu + léger décalage vers le haut)
 * quand il entre dans le viewport. Un seul IntersectionObserver partagé pour
 * toute la page plutôt qu'un par élément — inutile de multiplier les
 * observateurs pour une poignée de sections.
 *
 * `binding.value` (optionnel, en ms) décale l'apparition : c'est ce qui
 * permet l'effet de cascade sur une liste (`v-reveal="index * 80"`).
 */
const prefersReducedMotion = () =>
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

let observer;

function getObserver() {
    if (!observer) {
        observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-revealed');
                        observer.unobserve(entry.target);
                    }
                });
            },
            { threshold: 0.15, rootMargin: '0px 0px -40px 0px' },
        );
    }

    return observer;
}

export default {
    mounted(el, binding) {
        el.classList.add('reveal');

        if (binding.value) {
            el.style.transitionDelay = `${binding.value}ms`;
        }

        // Sans préférence de mouvement réduit, l'élément apparaît directement :
        // il n'y a rien à animer, mais la classe reste posée pour la cohérence.
        if (prefersReducedMotion()) {
            el.classList.add('is-revealed');
            return;
        }

        getObserver().observe(el);
    },
};
