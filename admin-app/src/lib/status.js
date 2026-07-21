export const STATUS_TONE = {
	pending: 'warning',
	offer_sent: 'info',
	confirmed: 'success',
	declined: 'danger',
	cancelled: 'danger',
	completed: 'brand',
	no_show: 'neutral',
	expired: 'neutral',
};

export function statusTone( status ) {
	return STATUS_TONE[ status ] || 'neutral';
}

export function initials( name = '' ) {
	return name
		.split( ' ' )
		.filter( Boolean )
		.slice( 0, 2 )
		.map( ( part ) => part[ 0 ]?.toUpperCase() )
		.join( '' ) || '?';
}

const AVATAR_TONES = [
	'bg-brand-100 text-brand-700',
	'bg-emerald-100 text-emerald-700',
	'bg-amber-100 text-amber-700',
	'bg-sky-100 text-sky-700',
	'bg-rose-100 text-rose-700',
];

export function avatarTone( seed = '' ) {
	let hash = 0;
	for ( let i = 0; i < seed.length; i++ ) {
		hash = ( hash + seed.charCodeAt( i ) ) % AVATAR_TONES.length;
	}
	return AVATAR_TONES[ hash ];
}
