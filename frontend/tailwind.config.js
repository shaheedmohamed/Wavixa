/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        dark: {
          950: '#01040f',
          900: '#020617',
          850: '#050914',
          800: '#080d1e',
          750: '#090e1c',
          700: '#0c1222',
          600: '#12182b',
          500: '#1a2338',
          400: '#334155',
        },
        emerald: {
          400: '#00E676',
          500: '#00C853',
          600: '#00A344',
          700: '#008F3A',
        },
        slate: {
          50: '#f8fafc',
          100: '#f1f5f9',
          200: '#e2e8f0',
          300: '#cbd5e1',
          400: '#94a3b8',
          500: '#64748b',
          600: '#475569',
          700: '#334155',
          800: '#1e293b',
          900: '#0f172a',
          950: '#020617',
        },
      },
      fontFamily: {
        sans: ['Cairo', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
        mono: ['SFMono-Regular', 'Menlo', 'Monaco', 'Consolas', 'Liberation Mono', 'Courier New', 'monospace'],
      },
      boxShadow: {
        'emerald': '0 0 12px rgba(0, 200, 83, 0.12)',
        'emerald-lg': '0 0 20px rgba(0, 200, 83, 0.18)',
        'soft': '0 1px 3px rgba(0,0,0,0.22), 0 6px 24px rgba(0,0,0,0.28)',
        'card': '0 0 0 1px rgba(255,255,255,0.06), 0 8px 32px rgba(0,0,0,0.28)',
      },
      borderRadius: {
        '2xl': '1rem',
        '3xl': '1.5rem',
      },
      keyframes: {
        'fade-up': {
          '0%': { opacity: '0', transform: 'translateY(18px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        'fade-in': {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        'scale-in': {
          '0%': { opacity: '0', transform: 'scale(0.96)' },
          '100%': { opacity: '1', transform: 'scale(1)' },
        },
        'message-in': {
          '0%': { opacity: '0', transform: 'translateX(16px)' },
          '100%': { opacity: '1', transform: 'translateX(0)' },
        },
        'chart-draw': {
          '0%': { strokeDashoffset: '500' },
          '100%': { strokeDashoffset: '0' },
        },
        'pulse-subtle': {
          '0%, 100%': { opacity: '0.9' },
          '50%': { opacity: '0.45' },
        },
        'float': {
          '0%, 100%': { transform: 'translateY(0)' },
          '50%': { transform: 'translateY(-6px)' },
        },
      },
      animation: {
        'fade-up': 'fade-up 0.7s cubic-bezier(0.22, 1, 0.36, 1) forwards',
        'fade-in': 'fade-in 0.6s ease forwards',
        'scale-in': 'scale-in 0.5s cubic-bezier(0.22, 1, 0.36, 1) forwards',
        'message-in': 'message-in 0.4s ease forwards',
        'chart-draw': 'chart-draw 1.6s ease-out forwards',
        'pulse-subtle': 'pulse-subtle 2.2s ease-in-out infinite',
        'float': 'float 6s ease-in-out infinite',
      },
      transitionTimingFunction: {
        'out-expo': 'cubic-bezier(0.22, 1, 0.36, 1)',
      },
    },
  },
  plugins: [],
}
