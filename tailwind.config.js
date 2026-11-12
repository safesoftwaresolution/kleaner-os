import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: ['class'],
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{vue,js,ts,jsx,tsx}',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['"Plus Jakarta Sans Variable"', ...defaultTheme.fontFamily.sans],
                display: ['"Outfit Variable"', ...defaultTheme.fontFamily.sans],
            },
            keyframes: {
                rise: {
                    '0%': { opacity: '0', transform: 'translateY(10px)' },
                    '100%': { opacity: '1', transform: 'none' },
                },
                pop: {
                    '0%': { opacity: '0', transform: 'scale(0.92)' },
                    '100%': { opacity: '1', transform: 'scale(1)' },
                },
                bubble: {
                    '0%': { opacity: '0', transform: 'translateY(0) scale(1)' },
                    '15%': { opacity: '0.45' },
                    '100%': { opacity: '0', transform: 'translateY(-110vh) scale(1.5)' },
                },
                float: {
                    '0%, 100%': { transform: 'translateY(0)' },
                    '50%': { transform: 'translateY(-8px)' },
                },
                'pulse-ring': {
                    '0%': { boxShadow: '0 0 0 0 hsl(var(--spark) / 0.55)' },
                    '100%': { boxShadow: '0 0 0 12px hsl(var(--spark) / 0)' },
                },
            },
            animation: {
                rise: 'rise 0.45s cubic-bezier(0.2, 0.8, 0.2, 1) both',
                pop: 'pop 0.25s ease-out both',
                bubble: 'bubble 14s linear infinite',
                float: 'float 6s ease-in-out infinite',
                'pulse-ring': 'pulse-ring 1.8s ease-out infinite',
            },
            borderRadius: {
                lg: 'var(--radius)',
                md: 'calc(var(--radius) - 2px)',
                sm: 'calc(var(--radius) - 4px)',
            },
            colors: {
                background: 'hsl(var(--background))',
                foreground: 'hsl(var(--foreground))',
                card: {
                    DEFAULT: 'hsl(var(--card))',
                    foreground: 'hsl(var(--card-foreground))',
                },
                popover: {
                    DEFAULT: 'hsl(var(--popover))',
                    foreground: 'hsl(var(--popover-foreground))',
                },
                primary: {
                    DEFAULT: 'hsl(var(--primary))',
                    foreground: 'hsl(var(--primary-foreground))',
                },
                secondary: {
                    DEFAULT: 'hsl(var(--secondary))',
                    foreground: 'hsl(var(--secondary-foreground))',
                },
                muted: {
                    DEFAULT: 'hsl(var(--muted))',
                    foreground: 'hsl(var(--muted-foreground))',
                },
                accent: {
                    DEFAULT: 'hsl(var(--accent))',
                    foreground: 'hsl(var(--accent-foreground))',
                },
                destructive: {
                    DEFAULT: 'hsl(var(--destructive))',
                    foreground: 'hsl(var(--destructive-foreground))',
                },
                spark: 'hsl(var(--spark))',
                brand: {
                    cyan: 'hsl(var(--brand-cyan))',
                    deep: 'hsl(var(--brand-deep))',
                    ink: 'hsl(var(--brand-ink))',
                },
                status: {
                    queued: 'hsl(var(--status-queued))',
                    'queued-text': 'hsl(var(--status-queued-text))',
                    washing: 'hsl(var(--status-washing))',
                    ready: 'hsl(var(--status-ready))',
                    delivered: 'hsl(var(--status-delivered))',
                },
                border: 'hsl(var(--border))',
                input: 'hsl(var(--input))',
                ring: 'hsl(var(--ring))',
                chart: {
                    1: 'hsl(var(--chart-1))',
                    2: 'hsl(var(--chart-2))',
                    3: 'hsl(var(--chart-3))',
                    4: 'hsl(var(--chart-4))',
                    5: 'hsl(var(--chart-5))',
                },
                sidebar: {
                    DEFAULT: 'hsl(var(--sidebar-background))',
                    foreground: 'hsl(var(--sidebar-foreground))',
                    primary: 'hsl(var(--sidebar-primary))',
                    'primary-foreground': 'hsl(var(--sidebar-primary-foreground))',
                    accent: 'hsl(var(--sidebar-accent))',
                    'accent-foreground': 'hsl(var(--sidebar-accent-foreground))',
                    border: 'hsl(var(--sidebar-border))',
                    ring: 'hsl(var(--sidebar-ring))',
                },
            },
        },
    },
    plugins: [require('tailwindcss-animate')],
};
