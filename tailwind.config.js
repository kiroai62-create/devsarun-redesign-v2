/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['./**/*.php', './assets/js/**/*.js', '!./node_modules/**'],
  theme: {
    extend: {
      colors: {
        'neo-black': '#0A0A0A',
        dark: '#111111',
        'neo-green': '#4ADE80',
        'neo-gray': '#1A1A1A',
      },
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'sans-serif'],
        mono: ['"JetBrains Mono"', 'ui-monospace', 'SFMono-Regular', 'Menlo', 'monospace'],
      },
    },
  },
  plugins: [],
};
