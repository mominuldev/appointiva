/**
 * Cross-bundle slot-fill registry.
 *
 * Appointiva Pro ships as a completely separate plugin with its own,
 * separately-built JS bundle — it cannot `import` anything from this app.
 * The only thing the two bundles reliably share at runtime is `window`, so
 * the registry lives there (mirroring how WooCommerce's block/extension
 * points work via a shared global rather than module imports).
 *
 * Usage from any script, including a Pro bundle loaded on the same admin
 * page:
 *
 *   window.Appointiva.admin.registerFill( 'appointiva-settings-tabs', {
 *       id: 'whatsapp',
 *       order: 50,
 *       label: 'WhatsApp',
 *       render: ( props ) => React.createElement( MyPanel, props ),
 *   } );
 */

function getStore() {
	window.Appointiva = window.Appointiva || {};
	window.Appointiva.admin = window.Appointiva.admin || {
		slots: {},
		listeners: new Set(),
		registerFill( slotName, fill ) {
			this.slots[ slotName ] = this.slots[ slotName ] || [];
			this.slots[ slotName ] = this.slots[ slotName ].filter( ( existing ) => existing.id !== fill.id );
			this.slots[ slotName ].push( fill );
			this.slots[ slotName ].sort( ( a, b ) => ( a.order ?? 10 ) - ( b.order ?? 10 ) );
			this.listeners.forEach( ( listener ) => listener( slotName ) );
		},
		getFills( slotName ) {
			return this.slots[ slotName ] || [];
		},
		subscribe( listener ) {
			this.listeners.add( listener );
			return () => this.listeners.delete( listener );
		},
	};

	return window.Appointiva.admin;
}

export const appointivaAdminRegistry = getStore();
