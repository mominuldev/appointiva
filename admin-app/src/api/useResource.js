import { useCallback, useEffect, useState } from 'react';
import { apiClient } from './client';

/** CRUD state/actions for a REST collection at `/admin/{endpoint}`. */
export function useResource( endpoint ) {
	const [ items, setItems ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );

	const load = useCallback( () => {
		setLoading( true );
		apiClient
			.get( `/admin/${ endpoint }` )
			.then( ( data ) => setItems( data ) )
			.catch( ( e ) => setError( e.message ) )
			.finally( () => setLoading( false ) );
	}, [ endpoint ] );

	useEffect( load, [ load ] );

	const create = ( payload ) => apiClient.post( `/admin/${ endpoint }`, payload ).then( () => load() );
	const update = ( id, payload ) => apiClient.put( `/admin/${ endpoint }/${ id }`, payload ).then( () => load() );
	const remove = ( id ) => apiClient.del( `/admin/${ endpoint }/${ id }` ).then( () => load() );

	return { items, loading, error, load, create, update, remove };
}
