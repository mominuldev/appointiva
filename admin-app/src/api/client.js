const cfg = window.AppointivaAdminConfig || {};

async function request( path, options = {} ) {
	const response = await fetch( `${ cfg.apiUrl }${ path }`, {
		...options,
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': cfg.nonce,
			...( options.headers || {} ),
		},
	} );

	const json = await response.json().catch( () => ( {} ) );

	if ( ! response.ok ) {
		throw new Error( json.message || 'Request failed' );
	}

	return json;
}

export const apiClient = {
	get: ( path ) => request( path ),
	post: ( path, body ) => request( path, { method: 'POST', body: JSON.stringify( body ) } ),
	put: ( path, body ) => request( path, { method: 'PUT', body: JSON.stringify( body ) } ),
	del: ( path ) => request( path, { method: 'DELETE' } ),
};
