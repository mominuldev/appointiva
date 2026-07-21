( function ( blocks, element, blockEditor, components, i18n, serverSideRender ) {
	var el = element.createElement;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var ServerSideRender = serverSideRender;

	blocks.registerBlockType( 'appointiva/booking-widget', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			return el(
				element.Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Booking Widget Settings', 'appointiva' ) },
						el( TextControl, {
							label: __( 'Service ID (optional)', 'appointiva' ),
							help: __( 'Leave empty to let visitors pick from all active services.', 'appointiva' ),
							type: 'number',
							value: attributes.serviceId || '',
							onChange: function ( value ) {
								setAttributes( { serviceId: value ? parseInt( value, 10 ) : 0 } );
							},
						} )
					)
				),
				el( ServerSideRender, {
					block: 'appointiva/booking-widget',
					attributes: attributes,
				} )
			);
		},
		save: function () {
			return null;
		},
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n,
	window.wp.serverSideRender
);
