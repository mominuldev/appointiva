import { useEffect, useMemo, useState } from 'react';
import {
	CalendarDays,
	Hourglass,
	TrendingUp,
	CalendarClock,
	ArrowUpRight,
	ArrowRight,
	Hash,
	CalendarX2,
	Sparkles,
	Users,
	MessageCircle,
	Repeat,
	Wallet,
	RefreshCw,
	UserCog,
} from 'lucide-react';
import { apiClient } from '../api/client';
import { Slot } from '../slot-fill/Slot';
import { Card, PageHeader, SectionTitle, Badge, Skeleton, EmptyState } from '../components/ui';
import { statusTone, initials, avatarTone } from '../lib/status';
import { WEEKDAYS, MONTHS, formatPrice } from '../lib/format';

const TILES = [
	{ key: 'today', label: "Today's bookings", icon: CalendarDays, tone: 'from-brand-500 to-brand-700' },
	{ key: 'upcoming', label: 'Upcoming (7 days)', icon: CalendarClock, tone: 'from-sky-400 to-sky-600' },
	{ key: 'pending', label: 'Awaiting confirmation', icon: Hourglass, tone: 'from-amber-400 to-amber-600' },
	{ key: 'this_month', label: 'This month', icon: TrendingUp, tone: 'from-emerald-400 to-emerald-600' },
];

const PRO_FEATURES = [
	{ label: 'Multi-staff scheduling across your whole team', icon: Users },
	{ label: 'WhatsApp reminders that cut no-shows', icon: MessageCircle },
	{ label: 'Recurring bookings — weekly, bi-weekly, monthly', icon: Repeat },
	{ label: 'Deposits & partial payments', icon: Wallet },
	{ label: 'Two-way Google Calendar sync', icon: RefreshCw },
	{ label: 'Client self-service dashboard', icon: UserCog },
];

// Same brand/hideUpsell override Pro's white-label add-on injects via the
// appointiva_admin_config filter (see App.jsx's sidebar upsell card) — kept
// this card hidden too once a reseller/white-label site turns it off.
const brand = window.AppointivaAdminConfig?.brand || {};

function toDateKey( date ) {
	return `${ date.getFullYear() }-${ String( date.getMonth() + 1 ).padStart( 2, '0' ) }-${ String( date.getDate() ).padStart( 2, '0' ) }`;
}

function buildMonthGrid( year, month ) {
	const firstOfMonth = new Date( year, month, 1 );
	const daysInMonth = new Date( year, month + 1, 0 ).getDate();
	const leadingBlanks = firstOfMonth.getDay();
	const cells = [];

	for ( let i = 0; i < leadingBlanks; i++ ) {
		cells.push( null );
	}

	for ( let day = 1; day <= daysInMonth; day++ ) {
		cells.push( new Date( year, month, day ) );
	}

	return cells;
}

function MonthCalendar( { calendar, loading } ) {
	const today = new Date();
	const todayKey = toDateKey( today );
	const cells = useMemo( () => buildMonthGrid( today.getFullYear(), today.getMonth() ), [ todayKey ] );
	const monthLabel = `${ MONTHS[ today.getMonth() ] } ${ today.getFullYear() }`;

	return (
		<Card>
			<SectionTitle title={ monthLabel } description="Bookings scheduled this month, by day." />
			{ loading ? (
				<Skeleton className="h-64 w-full" />
			) : (
				<div>
					<div className="grid grid-cols-7 gap-1 pb-2 text-center text-[11px] font-semibold uppercase tracking-wide text-slate-400">
						{ WEEKDAYS.map( ( d ) => (
							<span key={ d }>{ d }</span>
						) ) }
					</div>
					<div className="grid grid-cols-7 gap-1">
						{ cells.map( ( date, i ) => {
							if ( ! date ) {
								return <span key={ `blank-${ i }` } />;
							}

							const key = toDateKey( date );
							const count = calendar?.[ key ] || 0;
							const isToday = key === todayKey;

							return (
								<div
									key={ key }
									className={ `flex aspect-square flex-col items-center justify-center gap-0.5 rounded-lg text-sm transition-colors ${
										isToday ? 'bg-brand-gradient font-semibold text-white shadow-soft' : 'text-slate-700 hover:bg-slate-50'
									}` }
								>
									<span>{ date.getDate() }</span>
									{ count > 0 && (
										<span
											className={ `flex h-4 min-w-[1rem] items-center justify-center rounded-full px-1 text-[10px] font-semibold leading-none ${
												isToday ? 'bg-white/25 text-white' : 'bg-brand-100 text-brand-700'
											}` }
										>
											{ count }
										</span>
									) }
								</div>
							);
						} ) }
					</div>
				</div>
			) }
		</Card>
	);
}

function RecentBookings( { bookings, loading, onOpen, onViewAll } ) {
	return (
		<Card padded={ false } className="flex flex-1 flex-col overflow-hidden">
			<div className="flex items-center justify-between px-6 pt-6">
				<SectionTitle title="Recent bookings" description="The latest appointments booked through your site." className="mb-0" />
				<button
					onClick={ onViewAll }
					className="inline-flex shrink-0 items-center gap-1 text-sm font-medium text-brand-600 transition-colors hover:text-brand-700"
				>
					View all
					<ArrowRight className="h-3.5 w-3.5" />
				</button>
			</div>

			{ loading ? (
				<div className="space-y-3 p-6">
					{ [ ...Array( 4 ) ].map( ( _, i ) => (
						<Skeleton key={ i } className="h-12 w-full" />
					) ) }
				</div>
			) : bookings.length === 0 ? (
				<div className="p-6 pt-2">
					<EmptyState
						icon={ CalendarX2 }
						title="No bookings yet"
						description="Once a customer books through your widget, it'll show up here."
					/>
				</div>
			) : (
				<ul className="mt-2 divide-y divide-slate-50">
					{ bookings.map( ( booking ) => (
						<li
							key={ booking.id }
							onClick={ () => onOpen( booking.id ) }
							className="flex cursor-pointer items-center gap-3 px-6 py-3.5 transition-colors hover:bg-slate-50/60"
						>
							<span className={ `flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold ${ avatarTone( booking.customer_name || '' ) }` }>
								{ initials( booking.customer_name ) }
							</span>
							<div className="min-w-0 flex-1">
								<p className="truncate text-sm font-medium text-slate-800">{ booking.customer_name || 'Unknown' }</p>
								<p className="flex items-center gap-1 truncate text-xs text-slate-400">
									<Hash className="h-3 w-3 shrink-0" />
									{ booking.uuid.slice( 0, 8 ).toUpperCase() }
									<span>·</span>
									<span className="truncate">{ booking.service_name || '—' }</span>
								</p>
							</div>
							<div className="shrink-0 text-right">
								<Badge tone={ statusTone( booking.status ) } dot>
									{ booking.status.replace( '_', ' ' ) }
								</Badge>
								<p className="mt-1 text-xs font-medium text-slate-500">{ formatPrice( booking ) }</p>
							</div>
						</li>
					) ) }
				</ul>
			) }
		</Card>
	);
}

export function Dashboard( { navigate } ) {
	const [ stats, setStats ] = useState( null );
	const [ recent, setRecent ] = useState( [] );
	const [ recentLoading, setRecentLoading ] = useState( true );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		apiClient
			.get( '/admin/stats' )
			.then( setStats )
			.catch( ( e ) => setError( e.message ) );

		apiClient
			.get( '/admin/bookings?per_page=6&sort=created_at' )
			.then( ( data ) => setRecent( data.items ) )
			.catch( ( e ) => setError( e.message ) )
			.finally( () => setRecentLoading( false ) );
	}, [] );

	function goToBookings( bookingId ) {
		navigate?.( 'bookings', bookingId ? { bookingId } : null );
	}

	return (
		<div className="space-y-8">
			<PageHeader
				eyebrow="Overview"
				title="Welcome back"
				description="Here's what's happening with your bookings right now."
			/>

			{ error && (
				<Card className="border-rose-200 bg-rose-50 text-sm text-rose-700">{ error }</Card>
			) }
			<div className="grid gap-4 grid-cols-4 mb-4">
				{ TILES.map( ( tile ) => (
					<Card key={ tile.key } className="relative overflow-hidden">
						<div className={ `mb-4 flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br text-white shadow-soft ${ tile.tone }` }>
							<tile.icon className="h-5 w-5" strokeWidth={ 2 } />
						</div>
						<p className="text-xs font-medium uppercase tracking-wide text-slate-400">{ tile.label }</p>
						{ stats ? (
							<p className="mt-1.5 text-3xl font-semibold tracking-tight text-slate-900">{ stats[ tile.key ] }</p>
						) : (
							<Skeleton className="mt-2 h-8 w-12" />
						) }
					</Card>
				) ) }
			</div>
			<div className="grid gap-4 grid-cols-12">
				<div className="col-span-12 lg:col-span-8">

					<div className="flex lg:col-span-3 mb-4">
						<RecentBookings bookings={ recent } loading={ recentLoading } onOpen={ goToBookings } onViewAll={ () => goToBookings() } />
					</div>
				</div>

				<div className="col-span-12 lg:col-span-4 sticky-top">
					<div className="grid grid-cols-1 gap-6 lg:grid-cols-5">
						<div className="lg:col-span-2">
							<MonthCalendar calendar={ stats?.calendar } loading={ ! stats } />
						</div>
					</div>
				</div>
			</div>

			{ ! brand.hideUpsell && (
				<Card padded={ false } className="overflow-hidden">
					<div className="relative overflow-hidden bg-brand-gradient p-6 text-white sm:p-8">
						<div className="pointer-events-none absolute -right-20 -top-20 h-64 w-64 rounded-full bg-white/10 blur-3xl" />
						<div className="relative">
							<span className="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-white">
								<Sparkles className="h-3.5 w-3.5" />
								Appointiva Pro
							</span>

							<h3 className="mt-4 text-xl font-semibold tracking-tight text-white sm:text-2xl">Booking features that pay for themselves</h3>
							<p className="mt-2 max-w-xl text-sm text-brand-100">
								Appointiva Pro is a companion plugin that adds multi-staff scheduling, WhatsApp reminders, deposits, and more.
								Regular updates, no lock-in.
							</p>

							<div className="mt-6 grid gap-x-8 gap-y-3 grid-cols-2">
								{ PRO_FEATURES.map( ( feature ) => (
									<div key={ feature.label } className="flex items-start gap-2.5">
										<feature.icon className="mt-0.5 h-4 w-4 shrink-0 text-brand-100" strokeWidth={ 2 } />
										<p className="text-sm text-brand-50">{ feature.label }</p>
									</div>
								) ) }
							</div>

							<div className="mt-6 flex flex-wrap items-center gap-3">
								<a
									href="https://appointiva.com/pro"
									target="_blank"
									rel="noopener noreferrer"
									className="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-brand-700 shadow-soft transition-transform hover:scale-[1.02]"
								>
									Get Pro
									<ArrowUpRight className="h-4 w-4" />
								</a>
								<a
									href="https://appointiva.com/pricing"
									target="_blank"
									rel="noopener noreferrer"
									className="inline-flex shrink-0 items-center rounded-lg border border-white/30 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-white/10"
								>
									See pricing
								</a>
							</div>
						</div>
					</div>
				</Card>
			) }

			<Slot name="appointiva-dashboard-widgets" />
		</div>
	);
}
