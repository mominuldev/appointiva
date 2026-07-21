/** @type {import('tailwindcss').Config} */
module.exports = {
	content: [ './admin-app/src/**/*.{js,jsx}' ],
	// Scoped under .appointiva-admin so Tailwind's reset/utilities never leak
	// into wp-admin chrome outside our own screens.
	important: '.appointiva-admin',
	corePlugins: {
		preflight: false,
	},
	theme: {
		extend: {
			colors: {
				brand: {
					50: '#f2f1ff',
					100: '#e7e5ff',
					200: '#d2ccff',
					300: '#b3a5ff',
					400: '#9075ff',
					500: '#7c4dff',
					600: '#6d28f5',
					700: '#5c1fd6',
					800: '#4b1cad',
					900: '#3f1c8a',
				},
				accent: {
					50: '#eefcf6',
					500: '#12b981',
					600: '#0ea371',
				},
			},
			fontFamily: {
				sans: [ '-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', 'Inter', 'sans-serif' ],
			},
			boxShadow: {
				soft: '0 1px 2px 0 rgb(15 23 42 / 0.04), 0 1px 3px 0 rgb(15 23 42 / 0.06)',
				card: '0 1px 2px 0 rgb(15 23 42 / 0.03), 0 8px 24px -4px rgb(15 23 42 / 0.08)',
				popover: '0 12px 32px -8px rgb(15 23 42 / 0.18)',
			},
			borderRadius: {
				xl: '0.875rem',
				'2xl': '1.25rem',
			},
			backgroundImage: {
				'brand-gradient': 'linear-gradient(135deg, #7c4dff 0%, #6d28f5 45%, #4b1cad 100%)',
			},
		},
	},
	plugins: [],
};
