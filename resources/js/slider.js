function dragScroll() {
    return {
        isDown: false,
        startX: 0,
        scrollLeft: 0,

        init() {
            const slider = this.$refs.slider;

            slider.addEventListener('mousedown', (e) => {
                this.isDown = true;
                slider.classList.add('cursor-grabbing');
                this.startX = e.pageX - slider.offsetLeft;
                this.scrollLeft = slider.scrollLeft;
            });

            slider.addEventListener('mouseleave', () => {
                this.isDown = false;
            });

            slider.addEventListener('mouseup', () => {
                this.isDown = false;
            });

            slider.addEventListener('mousemove', (e) => {
                if (!this.isDown) return;
                e.preventDefault();

                const x = e.pageX - slider.offsetLeft;
                const walk = (x - this.startX) * 2; // speed multiplier

                slider.scrollLeft = this.scrollLeft - walk;
            });

            // Touch support
            let startXTouch = 0;
            let scrollStartTouch = 0;

            slider.addEventListener('touchstart', (e) => {
                startXTouch = e.touches[0].pageX;
                scrollStartTouch = slider.scrollLeft;
            });

            slider.addEventListener('touchmove', (e) => {
                const x = e.touches[0].pageX;
                const walk = (startXTouch - x) * 2;
                slider.scrollLeft = scrollStartTouch + walk;
            });
        }
    }
}
