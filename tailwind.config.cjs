/**
 * Konfigurasi Tailwind untuk membangun public/vendor/jpos.css.
 *
 * Palet `brand` di sini HARUS sama persis dengan konfigurasi inline yang dulu
 * dipakai Tailwind Play CDN (lihat resources/views/partials/head-assets.blade.php,
 * cabang mode 'play'), supaya hasil kompilasi identik dengan tampilan lama.
 *
 * Build: npx tailwindcss@3 -c tailwind.config.cjs -i resources/css/jpos.css -o public/vendor/jpos.css --minify
 */
module.exports = {
  content: ['./resources/views/**/*.blade.php'],
  theme: {
    extend: {
      colors: {
        brand: {
          50: '#eef7ff',
          100: '#d9edff',
          200: '#bce0ff',
          300: '#8ecdff',
          400: '#59b0ff',
          500: '#2f8fff',
          600: '#1c6ff0',
          700: '#1857c4',
          800: '#1a499d',
          900: '#1b407d',
        },
      },
    },
  },
  plugins: [],
};
