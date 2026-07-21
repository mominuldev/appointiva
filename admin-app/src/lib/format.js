export const WEEKDAYS = [ 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ];
export const MONTHS = [ 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec' ];

const CURRENCY_SYMBOLS = { USD: '$', EUR: '€', GBP: '£' };

export function currencySymbol( code ) {
	return CURRENCY_SYMBOLS[ code ] || code || '';
}

export function formatPrice( booking ) {
	const price = Number( booking.price );
	return price > 0 ? `${ currencySymbol( booking.currency ) }${ price.toFixed( 2 ) }` : '—';
}

// Formats MySQL date/time strings by hand rather than via `new Date(str)` —
// that parses as UTC and re-renders in the browser's local offset, which can
// shift the displayed day/hour away from the site's actual timezone.
export function formatTime( hhmm ) {
	const [ h, min ] = hhmm.split( ':' ).map( Number );
	const period = h >= 12 ? 'PM' : 'AM';
	const hour12 = h % 12 || 12;
	return `${ hour12 }:${ String( min ).padStart( 2, '0' ) } ${ period }`;
}

export function formatDate( yyyyMmDd ) {
	const [ y, m, d ] = yyyyMmDd.split( '-' ).map( Number );
	const weekday = WEEKDAYS[ new Date( y, m - 1, d ).getDay() ];
	return `${ weekday }, ${ MONTHS[ m - 1 ] } ${ d }, ${ y }`;
}

export function formatDateTime( mysqlDateTime ) {
	if ( ! mysqlDateTime ) {
		return '—';
	}

	const [ datePart, timePart ] = mysqlDateTime.split( ' ' );
	return `${ formatDate( datePart ) } · ${ formatTime( timePart ) }`;
}
