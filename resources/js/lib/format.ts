const cop = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
const compact = new Intl.NumberFormat('es-CO', { notation: 'compact', compactDisplay: 'long', maximumFractionDigits: 1 });
const integer = new Intl.NumberFormat('es-CO');

export const money = (value: number) => cop.format(value);
export const compactNumber = (value: number) => compact.format(value);
export const count = (value: number) => integer.format(value);

/** Parses 'YYYY-MM-DD' as a local date: new Date('2026-11-12') would shift the day in UTC-5. */
export const parseDate = (value: string) => {
    const [y, m, d] = value.split('-').map(Number);
    return new Date(y, m - 1, d);
};

const capitalize = (text: string) => text.charAt(0).toUpperCase() + text.slice(1);

// 'Intl' gives "15 de oct"; "15 oct" is shorter and keeps axis labels from touching.
export const shortDay = (value: string) =>
    new Intl.DateTimeFormat('es-CO', { day: 'numeric', month: 'short' }).format(parseDate(value)).replace(' de ', ' ').replace('.', '');

export const longDay = (value: string) =>
    capitalize(new Intl.DateTimeFormat('es-CO', { weekday: 'long', day: 'numeric', month: 'long' }).format(parseDate(value)));

export const since = (iso: string | null) => {
    if (!iso) return 'Sin visitas';
    const days = Math.floor((Date.now() - new Date(iso).getTime()) / 86400000);
    if (days <= 0) return 'Hoy';
    if (days === 1) return 'Ayer';
    if (days < 14) return `Hace ${days} días`;
    if (days < 60) return `Hace ${Math.floor(days / 7)} semanas`;
    return `Hace ${Math.floor(days / 30)} meses`;
};
