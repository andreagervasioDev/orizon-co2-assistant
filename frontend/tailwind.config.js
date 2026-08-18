/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        orizon: {
          50: '#f0faf6',
          100: '#d9f2e6',
          200: '#b6e5d1',
          300: '#84d1b5',
          400: '#4fb593',
          500: '#2c9977',
          600: '#1f7c61',
          700: '#1c634f',
          800: '#1a4f41',
          900: '#174237',
        },
      },
    },
  },
  plugins: [],
}
