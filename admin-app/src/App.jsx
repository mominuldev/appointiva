import { useEffect, useState } from 'react';
import { appointivaAdminRegistry } from './slot-fill/registry';
import {
	LayoutDashboard,
	CalendarCheck2,
	ListChecks,
	Users,
	Clock,
	MapPin,
	Bell,
	CreditCard,
	Settings as SettingsIcon,
	Sparkles,
	CalendarHeart,
} from 'lucide-react';
import { Dashboard } from './screens/Dashboard';
import { Bookings } from './screens/Bookings';
import { Services } from './screens/Services';
import { Staff } from './screens/Staff';
import { Availability } from './screens/Availability';
import { Locations } from './screens/Locations';
import { Notifications } from './screens/Notifications';
import { Payments } from './screens/Payments';
import { Settings } from './screens/Settings';
import { Slot } from './slot-fill/Slot';

const NAV_GROUPS = [
	{
		label: 'Overview',
		items: [
			{ id: 'dashboard', label: 'Dashboard', icon: LayoutDashboard, render: ( navigate ) => <Dashboard navigate={ navigate } /> },
			{ id: 'bookings', label: 'Bookings', icon: CalendarCheck2, render: ( navigate, params ) => <Bookings initialSelectedId={ params?.bookingId } /> },
		],
	},
	{
		label: 'Manage',
		items: [
			{ id: 'services', label: 'Services', icon: ListChecks, render: () => <Services /> },
			{ id: 'staff', label: 'Staff', icon: Users, render: () => <Staff /> },
			{ id: 'availability', label: 'Availability', icon: Clock, render: () => <Availability /> },
			{ id: 'locations', label: 'Locations', icon: MapPin, render: () => <Locations /> },
		],
	},
	{
		label: 'Configure',
		items: [
			{ id: 'notifications', label: 'Notifications', icon: Bell, render: () => <Notifications /> },
			{ id: 'payments', label: 'Payments', icon: CreditCard, render: () => <Payments /> },
			{ id: 'settings', label: 'Settings', icon: SettingsIcon, render: () => <Settings /> },
		],
	},
];

const cfg = window.AppointivaAdminConfig || {};

// Pro's white-label add-on injects `cfg.brand` via the appointiva_admin_config
// filter (see Admin\Assets::register()) when an agency/reseller has configured
// a brand override; every field is optional and independently falls back to
// Appointiva's own defaults, so this renders identically when Pro isn't
// active or white-labeling isn't enabled.
const brand = {
	name: cfg.brand?.name || 'Appointiva',
	tagline: cfg.brand?.tagline || 'Booking suite',
	logoUrl: cfg.brand?.logoUrl || null,
	hideUpsell: !! cfg.brand?.hideUpsell,
};

const EXTRA_SCREENS_SLOT = 'appointiva-admin-screens';

export function App() {
	// Google's OAuth redirect (see Settings.jsx's Google Calendar card) lands
	// back on this page as a fresh full page load, with no client-side routing
	// to carry the "which screen was I on" state across that reload — so we
	// read it once from the query string instead.
	const initialId = new URLSearchParams( window.location.search ).get( 'appointiva_gcal' ) ? 'settings' : 'dashboard';
	const [ activeId, setActiveId ] = useState( initialId );
	const [ navParams, setNavParams ] = useState( null );

	function navigate( screenId, params ) {
		setActiveId( screenId );
		setNavParams( params || null );
	}

	// Appointiva Pro (and any other extension) registers extra screens here via
	// window.Appointiva.admin.registerFill('appointiva-admin-screens', { id, label, icon, render, order }).
	// Extension bundles load as separate <script> tags AFTER this one (see the
	// 'appointiva-admin' script dependency in each extension's PHP enqueue),
	// so they register their fill strictly after this component's first
	// render — without subscribing here too (the same way <Slot> does), the
	// nav item would only ever appear after some unrelated state change
	// happened to force a re-render.
	const [ , forceRender ] = useState( 0 );

	useEffect( () => {
		return appointivaAdminRegistry.subscribe( ( changedSlot ) => {
			if ( changedSlot === EXTRA_SCREENS_SLOT ) {
				forceRender( ( n ) => n + 1 );
			}
		} );
	}, [] );

	const extraScreens = appointivaAdminRegistry.getFills( EXTRA_SCREENS_SLOT );

	const groups =
		extraScreens.length > 0
			? [ ...NAV_GROUPS, { label: 'Extensions', items: extraScreens.map( ( f ) => ( { ...f, icon: f.icon || Sparkles } ) ) } ]
			: NAV_GROUPS;

	const allItems = groups.flatMap( ( group ) => group.items );
	const active = allItems.find( ( item ) => item.id === activeId ) || allItems[ 0 ];

	return (
		// `.appointiva-admin` must stay on its own wrapper with no utility classes
		// alongside it: Tailwind's `important: '.appointiva-admin'` config compiles
		// every utility as `.appointiva-admin .some-utility` (a descendant
		// selector), which can never match utilities on that same element.
		<div className="appointiva-admin">
			<div className="flex min-h-[calc(100vh-64px)] overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 shadow-soft">
				<aside className="flex w-64 shrink-0 flex-col border-r border-slate-200 bg-white">
					<div className="flex items-center gap-2.5 px-5 py-6">
						{ brand.logoUrl ? (
							<img src={ brand.logoUrl } alt={ brand.name } className="h-9 w-9 shrink-0 rounded-xl object-contain" />
						) : (
							<div className="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-gradient shadow-soft">
								<CalendarHeart className="h-5 w-5 text-white" strokeWidth={ 2.25 } />
							</div>
						) }
						<div>
							<p className="text-sm font-bold leading-none text-slate-900">{ brand.name }</p>
							<p className="mt-1 text-[11px] font-medium uppercase tracking-wider text-slate-400">{ brand.tagline }</p>
						</div>
					</div>

					<nav className="appointiva-scrollbar flex-1 space-y-6 overflow-y-auto px-3 pb-4">
						{ groups.map( ( group ) => (
							<div key={ group.label }>
								<p className="px-3 pb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">{ group.label }</p>
								<div className="space-y-0.5">
									{ group.items.map( ( item ) => {
										const isActive = item.id === active.id;
										const Icon = item.icon;

										return (
											<button
												key={ item.id }
												onClick={ () => navigate( item.id ) }
												className={ `group relative flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-150 ${
													isActive ? 'bg-brand-50 text-brand-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
												}` }
											>
												{ isActive && <span className="absolute -left-3 h-5 w-1 rounded-r-full bg-brand-gradient" /> }
												<Icon className={ `h-4 w-4 ${ isActive ? 'text-brand-600' : 'text-slate-400 group-hover:text-slate-500' }` } strokeWidth={ 2 } />
												{ item.label }
											</button>
										);
									} ) }
								</div>
							</div>
						) ) }
					</nav>

					{ ! brand.hideUpsell && (
						<div className="m-3 rounded-xl bg-gradient-to-br from-brand-50 to-white p-4">
							<div className="flex items-center gap-1.5 text-brand-700">
								<Sparkles className="h-3.5 w-3.5" />
								<p className="text-xs font-semibold">Growing your business?</p>
							</div>
							<p className="mt-1.5 text-xs leading-relaxed text-slate-500">
								Appointiva Pro adds multi-staff scheduling, WhatsApp reminders, and a client dashboard.
							</p>
						</div>
					) }
				</aside>

				<div className="flex flex-1 flex-col overflow-hidden">
					<header className="flex items-center justify-between border-b border-slate-200 bg-white/80 px-8 py-4 backdrop-blur">
						<div>
							<p className="text-xs font-medium text-slate-400">{ brand.name }</p>
							<h2 className="text-lg font-semibold text-slate-900">{ active.label }</h2>
						</div>
						<div className="flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-700">
							<span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
							v{ cfg.version || '1.0.0' }
						</div>
					</header>

					<main className="appointiva-scrollbar flex-1 overflow-y-auto px-8 py-8">
						<div className="appointiva-animate-in mx-auto">{ active.render( navigate, navParams ) }</div>
					</main>
				</div>

				<Slot name="appointiva-admin-nav-extra" />
			</div>
		</div>
	);
}
