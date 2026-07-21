import { useEffect, useState } from 'react';
import { CalendarDays, Hourglass, TrendingUp, CalendarClock, ArrowUpRight } from 'lucide-react';
import { apiClient } from '../api/client';
import { Slot } from '../slot-fill/Slot';
import { Card, PageHeader, Skeleton } from '../components/ui';

const TILES = [
	{ key: 'today', label: "Today's bookings", icon: CalendarDays, tone: 'from-brand-500 to-brand-700' },
	{ key: 'upcoming', label: 'Upcoming (7 days)', icon: CalendarClock, tone: 'from-sky-400 to-sky-600' },
	{ key: 'pending', label: 'Awaiting confirmation', icon: Hourglass, tone: 'from-amber-400 to-amber-600' },
	{ key: 'this_month', label: 'This month', icon: TrendingUp, tone: 'from-emerald-400 to-emerald-600' },
];

export function Dashboard() {
	const [ stats, setStats ] = useState( null );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		apiClient
			.get( '/admin/stats' )
			.then( setStats )
			.catch( ( e ) => setError( e.message ) );
	}, [] );

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

			<div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
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

			<Card padded={ false } className="overflow-hidden">
				<div className="flex flex-col gap-4 bg-brand-gradient p-6 text-white sm:flex-row sm:items-center sm:justify-between">
					<div>
						<p className="text-sm font-semibold uppercase tracking-wide text-brand-100">Get bookings faster</p>
						<h3 className="mt-1 text-lg font-semibold">Add the booking widget to any page</h3>
						<p className="mt-1 max-w-md text-sm text-brand-100">
							Use the "Appointiva Booking Widget" block, or drop the <code className="rounded bg-white/20 px-1.5 py-0.5">[appointiva_booking]</code> shortcode anywhere.
						</p>
					</div>
					<a
						href="edit.php?post_type=page"
						className="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-brand-700 shadow-soft transition-transform hover:scale-[1.02]"
					>
						Go to Pages
						<ArrowUpRight className="h-4 w-4" />
					</a>
				</div>
			</Card>

			<Slot name="appointiva-dashboard-widgets" />
		</div>
	);
}
