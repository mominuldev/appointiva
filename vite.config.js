import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';

export default defineConfig( {
	plugins: [ react() ],
	// Vite's regular app builds auto-replace process.env.NODE_ENV with a
	// literal so React's dev-only branches get stripped; library builds don't
	// get that treatment automatically, leaving a literal `process.env`
	// reference that throws ReferenceError in the browser (no `process`
	// global exists there). Define it explicitly so React resolves to its
	// production build path, same as a normal app build would.
	define: {
		'process.env.NODE_ENV': JSON.stringify( 'production' ),
	},
	build: {
		outDir: 'assets/dist/admin',
		emptyOutDir: true,
		// Vite's default CSS minifier corrupts Tailwind's `.appointiva-admin`
		// ancestor-scoping prefix — it silently strips the prefix from every
		// utility rule, turning them into unscoped global selectors that
		// collide with wp-admin's own class names (this previously caused the
		// admin screen to render blank). This bundle is only loaded on our own
		// admin page, so the extra unminified KB is a non-issue.
		cssMinify: false,
		lib: {
			// WordPress enqueues this as a plain classic <script src> (no
			// type="module"). Rollup's default 'es' format assumes module
			// scoping for top-level const/let, but a classic script's
			// top-level lexical declarations land in the page's SHARED global
			// scope — where they can collide with window.wp (or anything else
			// already declared by WP core or another plugin; this previously
			// broke on an internal lucide-react variable that just happened to
			// minify down to the name "wp"). `lib` + iife wraps the whole
			// bundle in a function so nothing it declares touches the global
			// scope, and (unlike rollupOptions output.format: 'iife' alone)
			// still extracts CSS to its own file instead of injecting it via JS.
			entry: resolve( __dirname, 'admin-app/src/main.jsx' ),
			formats: [ 'iife' ],
			name: 'AppointivaAdmin',
			fileName: () => 'admin.js',
			cssFileName: 'admin',
		},
	},
} );
