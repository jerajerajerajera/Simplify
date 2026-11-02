/* Seasonal touches: which holidays are on for a date, and the small one-off animations some of them play.
   Pure date rules, nothing is stored: every touch comes and goes by itself each year. Holidays that move are
   worked out here: Easter by the Gregorian rule, the lunar ones (Holi, Vesak, Diwali) from the moon's phase in India,
   and the Islamic, Jewish and Chinese ones from the browser's own calendars (Intl). dashboard.js decides where
   they show; the texts are in lang/<locale>/js.php under seasons. */
(() => {
  const day = (y, m, d) => new Date(y, m - 1, d);
  const plus = (date, days) => new Date(date.getFullYear(), date.getMonth(), date.getDate() + days);
  const one = (date) => [date, date];

  /** Easter Sunday (Meeus/Jones/Butcher). */
  const easter = (y) => {
    const a = y % 19, b = Math.floor(y / 100), c = y % 100, d = Math.floor(b / 4), e = b % 4, f = Math.floor((b + 8) / 25);
    const g = Math.floor((b - f + 1) / 3), h = (19 * a + b - d - g + 15) % 30, i = Math.floor(c / 4), k = c % 4;
    const l = (32 + 2 * e + 2 * i - h - k) % 7, m = Math.floor((a + 11 * h + 22 * l) / 451), n = h + l - 7 * m + 114;
    return day(y, Math.floor(n / 31), (n % 31) + 1);
  };

  /** The moment of new moon (phase 0) or full moon (phase .5) of lunation k, within minutes (Meeus, chapter 49). */
  const moon = (k, phase) => {
    k += phase;
    const T = k / 1236.85, rad = Math.PI / 180, E = 1 - 0.002516 * T;
    const M = (2.5534 + 29.1053567 * k) * rad, Mp = (201.5643 + 385.81693528 * k) * rad, F = (160.7108 + 390.67050284 * k) * rad;
    const [a, b, c, d, e, f, g] = phase === 0 ? [-0.4072, 0.17241, 0.01608, 0.01039, 0.00739, -0.00514, 0.00208] : [-0.40614, 0.17302, 0.01614, 0.01043, 0.00734, -0.00515, 0.00209];
    const jde = 2451550.09766 + 29.530588861 * k + 0.00015437 * T * T + a * Math.sin(Mp) + b * E * Math.sin(M) + c * Math.sin(2 * Mp)
      + d * Math.sin(2 * F) + e * E * Math.sin(Mp - M) + f * E * Math.sin(Mp + M) + g * E * E * Math.sin(2 * M);
    return new Date((jde - 2440587.5) * 864e5);
  };
  /** The calendar day in India (UTC+5:30) of the first new or full moon between from and to. */
  const moonDay = (y, phase, from, to) => {
    const first = Math.floor((y - 2000) * 12.3685) - 1;
    for (let k = first; k < first + 15; k++) {
      const india = new Date(moon(k, phase).getTime() + 5.5 * 36e5);
      const date = day(india.getUTCFullYear(), india.getUTCMonth() + 1, india.getUTCDate());
      if (date >= from && date <= to) return date;
    }
    return null;
  };

  /** The day in year y that another calendar calls month/day; null when this browser does not know that calendar. */
  const found = new Map();
  const calendarDay = (y, calendar, month, date) => {
    const key = [y, calendar, month, date].join();
    if (found.has(key)) return found.get(key);
    let result = null;
    try {
      const format = new Intl.DateTimeFormat('en-u-ca-' + calendar, { month: calendar === 'hebrew' ? 'long' : 'numeric', day: 'numeric' });
      if (format.resolvedOptions().calendar === calendar) {
        for (let d = day(y, 1, 1); d.getFullYear() === y && !result; d = plus(d, 1)) {
          const parts = format.formatToParts(d);
          if (parts.find((p) => p.type === 'month')?.value === String(month) && Number(parts.find((p) => p.type === 'day')?.value) === date) result = d;
        }
      }
    } catch { /* an old browser: this holiday is skipped */ }
    found.set(key, result);
    return result;
  };
  const span = (start, days) => (start ? [start, plus(start, days - 1)] : null);

  /* Every holiday: its Tabler icon, its colour, and its days in year y (one or more [first, last] pairs). The order
     here is the order of the year. dutch: only for people using Simplify in Dutch. */
  const SEASONS = [
    { key: 'newyear', icon: 'sparkles', color: '#7c5cd6', days: (y) => [one(day(y, 1, 1)), one(day(y, 12, 31))] },
    { key: 'lunar', icon: 'confetti', color: '#d23b2f', accent: ['#c0322a', '#ef6a5e'], days: (y) => [span(calendarDay(y, 'chinese', 1, 1), 3)] },
    { key: 'valentine', icon: 'heart', color: '#d6456e', days: (y) => [one(day(y, 2, 14))] },
    { key: 'holi', icon: 'palette', color: '#e0479e', days: (y) => [span(moonDay(y, .5, day(y, 2, 24), day(y, 3, 27)), 2)] },
    { key: 'fitr', icon: 'moon-stars', color: '#c9a227', days: (y) => [span(calendarDay(y, 'islamic-umalqura', 10, 1), 3)] },
    { key: 'easter', icon: 'egg', color: '#d39a1b', days: (y) => [span(plus(easter(y), -2), 4)] },
    { key: 'vaisakhi', icon: 'flag', color: '#e8890c', days: (y) => [[day(y, 4, 13), day(y, 4, 14)]] },
    // King's Day moves to the 26th when the 27th is a Sunday.
    { key: 'kingsday', icon: 'crown', color: '#f07a10', accent: ['#e46c00', '#ff9a3c'], dutch: true, days: (y) => [one(day(y, 4, day(y, 4, 27).getDay() === 0 ? 26 : 27))] },
    { key: 'vesak', icon: 'flower', color: '#c9a227', days: (y) => [span(moonDay(y, .5, day(y, 4, 26), day(y, 5, 25)), 2)] },
    { key: 'adha', icon: 'moon-stars', color: '#c9a227', days: (y) => [span(calendarDay(y, 'islamic-umalqura', 12, 10), 4)] },
    { key: 'midsummer', icon: 'sun', color: '#e2a41a', days: (y) => [[day(y, 6, 20), day(y, 6, 22)]] },
    { key: 'halloween', icon: 'pumpkin', color: '#d9731f', accent: ['#c2621a', '#f0a35a'], days: (y) => [[day(y, 10, 25), day(y, 11, 2)]] },
    // Diwali: the five days around the new moon of Kartika.
    { key: 'diwali', icon: 'flame', color: '#e07b16', days: (y) => { const night = moonDay(y, 0, day(y, 10, 16), day(y, 11, 15)); return [night && [plus(night, -2), plus(night, 2)]]; } },
    { key: 'hanukkah', icon: 'menorah', color: '#3a6fd0', days: (y) => [span(calendarDay(y, 'hebrew', 'Kislev', 25), 8)] },
    { key: 'christmas', icon: 'christmas-tree', color: '#2f8a4c', accent: ['#2c7a46', '#5cbf7d'], days: (y) => [[day(y, 12, 1), day(y, 12, 26)]] },
  ];

  /**
   * What is on for this date, shortest first (that one sets the accent colour and plays). Each entry has the
   * season's fields plus start (its first day) and day (1 on the first day). options.dutch adds the Dutch-only days;
   * options.born (a Date) adds the panel's birthday from its first anniversary on.
   */
  const active = (date = new Date(), options = {}) => {
    const today = day(date.getFullYear(), date.getMonth() + 1, date.getDate());
    const on = [];
    for (const season of SEASONS) {
      if (season.dutch && !options.dutch) continue;
      // Last year's dates too: Hanukkah can run into January.
      for (const y of [today.getFullYear() - 1, today.getFullYear()]) {
        const hit = season.days(y).find((range) => range && today >= range[0] && today <= range[1]);
        if (hit) {
          on.push({ ...season, start: hit[0], day: Math.round((today - hit[0]) / 864e5) + 1, length: Math.round((hit[1] - hit[0]) / 864e5) + 1 });
          break;
        }
      }
    }
    const born = options.born;
    if (born instanceof Date && !isNaN(born) && born.getMonth() === today.getMonth() && born.getDate() === today.getDate() && today.getFullYear() > born.getFullYear()) {
      on.push({ key: 'birthday', icon: 'cake', color: '#d6589a', start: today, day: 1, length: 1, years: today.getFullYear() - born.getFullYear() });
    }
    return on.sort((a, b) => a.length - b.length);
  };

  /** A server's birthday: created on this day, at least a year ago. created is a Unix timestamp. */
  const birthday = (created, date = new Date()) => {
    if (!created) return 0;
    const born = new Date(created * 1000);
    return born.getMonth() === date.getMonth() && born.getDate() === date.getDate() && date.getFullYear() > born.getFullYear() ? date.getFullYear() - born.getFullYear() : 0;
  };

  /* One-off animations: small fixed pieces that never catch a click and remove themselves. */
  const still = () => !document.body?.animate || window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
  const piece = (className, color, frames, timing) => {
    const el = document.createElement('span');
    el.className = 'ps-season-piece ' + className;
    el.setAttribute('aria-hidden', 'true');
    if (color) el.style.setProperty('--ps-season-color', color);
    document.body.appendChild(el);
    el.animate(frames, { fill: 'both', ...timing }).finished.then(() => el.remove(), () => el.remove());
  };
  /** Sparks bursting at a few points (x, y in px), one after another. */
  const fireworks = (points, colors) => {
    if (still()) return;
    points.forEach(([x, y], burst) => {
      for (let k = 0; k < 14; k++) {
        const angle = k / 14 * Math.PI * 2, reach = 26 + (k % 3) * 6;
        piece('is-spark', colors[(k + burst) % colors.length], [
          { transform: `translate(${x}px, ${y}px) scale(.4)`, opacity: 0 },
          { transform: `translate(${x}px, ${y}px) scale(1)`, opacity: 1, offset: .1 },
          { transform: `translate(${x + Math.cos(angle) * reach}px, ${y + Math.sin(angle) * reach + 10}px) scale(.6)`, opacity: 0 },
        ], { duration: 1100, delay: 300 + burst * 420, easing: 'cubic-bezier(.2, .7, .3, 1)' });
      }
    });
  };
  /** Confetti from one point. */
  const confetti = (x, y) => {
    if (still()) return;
    const colors = ['#d6589a', '#e2a41a', '#3a9bd9', '#3f9d5b', '#7c5cd6'];
    for (let k = 0; k < 22; k++) {
      const dx = (k / 22 - .5) * 260, up = 40 + (k % 5) * 14;
      piece('is-confetti', colors[k % colors.length], [
        { transform: `translate(${x}px, ${y}px) rotate(0)`, opacity: 1 },
        { transform: `translate(${x + dx * .6}px, ${y - up}px) rotate(${k * 40}deg)`, opacity: 1, offset: .3 },
        { transform: `translate(${x + dx}px, ${y + 160}px) rotate(${k * 90}deg)`, opacity: 0 },
      ], { duration: 1600 + (k % 4) * 150, delay: 150, easing: 'cubic-bezier(.2, .6, .4, 1)' });
    }
  };
  /** Soft clouds of coloured powder around a box (Holi). */
  const powder = (box) => {
    if (still()) return;
    ['#e0479e', '#f2b705', '#2fa4e7', '#3fbf6f', '#8e5bd8', '#f2702c'].forEach((color, i) => {
      const x = box.left + box.width * (.1 + i * .16), y = box.top + box.height / 2 - 14 + (i % 2 ? -6 : 8);
      piece('is-powder', color, [
        { transform: `translate(${x}px, ${y}px) scale(.3)`, opacity: 0 },
        { transform: `translate(${x}px, ${y - 6}px) scale(1)`, opacity: .55, offset: .25 },
        { transform: `translate(${x + (i % 3 - 1) * 12}px, ${y - 18}px) scale(1.6)`, opacity: 0 },
      ], { duration: 2200, delay: 250 + i * 120, easing: 'ease-out' });
    });
  };

  window.SimplifySeasons = { SEASONS, active, birthday, easter, calendarDay, moonDay, fireworks, confetti, powder };
})();
