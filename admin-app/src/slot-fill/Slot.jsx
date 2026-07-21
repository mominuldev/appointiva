import { useEffect, useState } from 'react';
import { appointivaAdminRegistry } from './registry';

/**
 * Renders every fill registered for `name`, in ascending `order`. Re-renders
 * automatically if a Pro/extension bundle registers a fill after this Slot
 * has already mounted (extension scripts commonly load later on the page).
 */
export function Slot( { name, fillProps = {}, fallback = null } ) {
	const [ , forceRender ] = useState( 0 );

	useEffect( () => {
		return appointivaAdminRegistry.subscribe( ( changedSlot ) => {
			if ( changedSlot === name ) {
				forceRender( ( n ) => n + 1 );
			}
		} );
	}, [ name ] );

	const fills = appointivaAdminRegistry.getFills( name );

	if ( ! fills.length ) {
		return fallback;
	}

	return fills.map( ( fill ) => (
		<div key={ fill.id } data-appointiva-fill={ fill.id }>
			{ fill.render( fillProps ) }
		</div>
	) );
}
