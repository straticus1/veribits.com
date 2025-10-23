/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './pages/**/*.{js,ts,jsx,tsx,mdx}',
    './components/**/*.{js,ts,jsx,tsx,mdx}',
    './app/**/*.{js,ts,jsx,tsx,mdx}',
  ],
  theme: {
    extend: {
      colors: {
        // After Dark Systems Brand Colors
        brand: {
          blue: {
            light: '#1e40af',  // Blue 700
            DEFAULT: '#3730a3', // Violet 800
            dark: '#312e81',    // Violet 900
          },
          green: {
            light: '#059669',   // Emerald 600
            DEFAULT: '#047857', // Emerald 700
            dark: '#065f46',    // Emerald 800
          },
          amber: {
            light: '#fcd34d',   // Amber 300
            DEFAULT: '#fbbf24', // Amber 400
            dark: '#f59e0b',    // Amber 500
          },
        },
        // Dark theme backgrounds
        dark: {
          900: '#0f172a',  // Slate 900
          800: '#1e293b',  // Slate 800
          700: '#334155',  // Slate 700
          600: '#475569',  // Slate 600
        },
        // Status colors
        success: {
          50: '#f0fdf4',
          100: '#dcfce7',
          500: '#059669',  // Emerald matching brand
          600: '#047857',
          700: '#065f46',
        },
        warning: {
          50: '#fffbeb',
          100: '#fef3c7',
          500: '#fbbf24',  // Amber matching brand
          600: '#f59e0b',
          700: '#d97706',
        },
        danger: {
          50: '#fef2f2',
          100: '#fee2e2',
          500: '#ef4444',
          600: '#dc2626',
          700: '#b91c1c',
        },
        info: {
          50: '#eff6ff',
          100: '#dbeafe',
          500: '#3b82f6',
          600: '#2563eb',
          700: '#1d4ed8',
        },
      },
      backgroundImage: {
        'gradient-brand': 'linear-gradient(135deg, #1e40af 0%, #3730a3 100%)',
        'gradient-dark': 'linear-gradient(180deg, #0f172a 0%, #1e293b 100%)',
      },
      fontFamily: {
        sans: ['-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
      },
    },
  },
  plugins: [],
  darkMode: 'class',
}