{{-- Shared by the Look fields (brand-style, look-preview): the panel name's style built in the browser, the same as
     Brand::textStyle on the server, and a small colour window (a colour field, a hue slider and the hex code). --}}
<script>
    window.SimplifyLook ??= {
        fonts: @js(\Simplify\Core\Support\Brand::FONTS),
        css(st, accent, dark = true) {
            const tint = accent || 'var(--primary-500, #2563eb)';
            const ink = st.brand_colour1 || (dark ? '#fafafa' : '#18181b');
            const gradient = st.brand_colour === 'gradient';
            const out = [];
            const font = this.fonts[st.brand_font];
            if (font) out.push('font-family: ' + font);
            out.push('font-weight: ' + (Number(st.brand_weight) || 700));
            if (Number(st.brand_spacing)) out.push('letter-spacing: ' + Number(st.brand_spacing) / 100 + 'em');
            if (st.brand_uppercase) out.push('text-transform: uppercase');
            if (gradient) out.push(`background-image: linear-gradient(110deg, ${ink} 40%, ${st.brand_colour2 || tint} 100%); -webkit-background-clip: text; background-clip: text; color: transparent; -webkit-text-fill-color: transparent`);
            else out.push('color: ' + (st.brand_colour === 'accent' ? tint : ink));
            if (st.brand_effect === 'glow') out.push(gradient ? `filter: drop-shadow(0 0 6px ${tint})` : `text-shadow: 0 0 10px color-mix(in srgb, ${tint} 80%, transparent), 0 0 2px ${tint}`);
            if (st.brand_effect === 'shadow') out.push(gradient ? 'filter: drop-shadow(0 2px 2px rgb(0 0 0 / .45))' : 'text-shadow: 0 2px 0 rgb(0 0 0 / .35), 0 3px 8px rgb(0 0 0 / .35)');
            return out.join('; ');
        },
        // A colour window for one value; read and write reach the value in the field around it.
        pop(read, write) {
            return {
                open: false, h: 220, s: 40, v: 90, hex: '',
                toHex() {
                    const s = this.s / 100, v = this.v / 100;
                    const f = (n) => { const k = (n + this.h / 60) % 6; return v - v * s * Math.max(0, Math.min(k, 4 - k, 1)) };
                    return '#' + [f(5), f(3), f(1)].map((x) => Math.round(x * 255).toString(16).padStart(2, '0')).join('');
                },
                fromHex(hex) {
                    const n = parseInt(hex.slice(1), 16), r = (n >> 16 & 255) / 255, g = (n >> 8 & 255) / 255, b = (n & 255) / 255;
                    const max = Math.max(r, g, b), d = max - Math.min(r, g, b);
                    let h = 0;
                    if (d) h = max === r ? ((g - b) / d) % 6 : max === g ? (b - r) / d + 2 : (r - g) / d + 4;
                    if (d) this.h = Math.round((h * 60 + 360) % 360);
                    this.s = max ? Math.round(d / max * 100) : 0;
                    this.v = Math.round(max * 100);
                },
                toggle(fallback) {
                    this.open = ! this.open;
                    if (! this.open) return;
                    const now = read();
                    this.fromHex(/^#[0-9a-f]{6}$/i.test(now || '') ? now : fallback);
                    this.hex = this.toHex();
                },
                apply() { write(this.hex = this.toHex()) },
                drag(event, area) {
                    const el = event.currentTarget;
                    el.setPointerCapture(event.pointerId);
                    const move = (e) => {
                        const r = el.getBoundingClientRect(), x = Math.max(0, Math.min(1, (e.clientX - r.left) / r.width));
                        if (area) { this.s = Math.round(x * 100); this.v = Math.round((1 - Math.max(0, Math.min(1, (e.clientY - r.top) / r.height))) * 100) }
                        else this.h = Math.round(x * 360);
                        this.apply();
                    };
                    move(event);
                    el.onpointermove = move;
                    el.onpointerup = () => (el.onpointermove = el.onpointerup = null);
                },
                typed() {
                    const v = this.hex.trim();
                    if (/^#?[0-9a-f]{6}$/i.test(v)) { const hex = (v[0] === '#' ? v : '#' + v).toLowerCase(); write(hex); this.fromHex(hex) }
                },
            };
        },
    };
</script>
