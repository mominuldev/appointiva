import { useEffect, useState } from 'react';
import {
	CalendarX2,
	Hash,
	Search,
	ChevronLeft,
	ChevronRight,
	ArrowLeft,
	Send,
	Check,
	X as XIcon,
	Trash2,
	Mail,
	Phone,
	CalendarDays,
	Clock3,
	Tag,
	StickyNote,
	MessageSquare,
	Ban,
	CheckCircle2,
	UserX,
} from 'lucide-react';
import { apiClient } from '../api/client';
import { Card, PageHeader, SectionTitle, Badge, Select, Input, Field, Textarea, Button, IconButton, Modal, EmptyState, Skeleton, Toast } from '../components/ui';
import { statusTone, initials, avatarTone } from '../lib/status';
import { currencySymbol, formatPrice, formatTime, formatDate, formatDateTime } from '../lib/format';

const STATUS_OPTIONS = [ 'pending', 'offer_sent', 'confirmed', 'declined', 'cancelled', 'completed', 'no_show', 'expired' ];
const PER_PAGE_OPTIONS = [ 10, 25, 50, 100 ];

const emptyOffer = { open: false, booking: null, price: '', note: '', saving: false };

function InfoRow( { icon: Icon, label, value } ) {
	return (
		<div className="flex items-start gap-3">
			<div className="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
				<Icon className="h-4 w-4" strokeWidth={ 2 } />
			</div>
			<div className="min-w-0">
				<p className="text-xs font-medium uppercase tracking-wide text-slate-400">{ label }</p>
				<p className="mt-0.5 break-words text-sm font-medium text-slate-800">{ value }</p>
			</div>
		</div>
	);
}

export function Bookings( { initialSelectedId } ) {
	const [ bookings, setBookings ] = useState( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const [ services, setServices ] = useState( [] );

	const [ status, setStatus ] = useState( '' );
	const [ serviceId, setServiceId ] = useState( '' );
	const [ dateFrom, setDateFrom ] = useState( '' );
	const [ dateTo, setDateTo ] = useState( '' );
	const [ searchInput, setSearchInput ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ perPage, setPerPage ] = useState( 25 );

	const [ offer, setOffer ] = useState( emptyOffer );
	const [ toast, setToast ] = useState( '' );

	const [ selectedId, setSelectedId ] = useState( initialSelectedId || null );
	const [ detail, setDetail ] = useState( null );
	const [ detailLoading, setDetailLoading ] = useState( false );

	useEffect( () => {
		const id = setTimeout( () => setSearch( searchInput.trim() ), 400 );
		return () => clearTimeout( id );
	}, [ searchInput ] );

	useEffect( () => {
		setPage( 1 );
	}, [ status, serviceId, dateFrom, dateTo, search ] );

	useEffect( () => {
		apiClient.get( '/admin/services' ).then( setServices ).catch( () => {} );
	}, [] );

	function load() {
		setLoading( true );
		const params = new URLSearchParams( {
			page,
			per_page: perPage,
			...( status && { status } ),
			...( serviceId && { service_id: serviceId } ),
			...( dateFrom && { date_from: dateFrom } ),
			...( dateTo && { date_to: dateTo } ),
			...( search && { search } ),
		} );

		apiClient
			.get( `/admin/bookings?${ params.toString() }` )
			.then( ( data ) => {
				setBookings( data.items );
				setTotal( data.total );
			} )
			.catch( ( e ) => setError( e.message ) )
			.finally( () => setLoading( false ) );
	}

	useEffect( load, [ status, serviceId, dateFrom, dateTo, search, page, perPage ] );

	function notify( message ) {
		setToast( message );
		setTimeout( () => setToast( '' ), 3000 );
	}

	function changeStatus( id, next ) {
		apiClient
			.put( `/admin/bookings/${ id }/status`, { status: next } )
			.then( () => {
				notify( 'Booking updated.' );
				load();
			} )
			.catch( ( e ) => setError( e.message ) );
	}

	function removeBooking( booking ) {
		if ( ! window.confirm( `Delete the booking for ${ booking.customer_name || 'this customer' }? This can't be undone.` ) ) {
			return;
		}

		apiClient
			.del( `/admin/bookings/${ booking.id }` )
			.then( () => {
				notify( 'Booking deleted.' );
				load();
			} )
			.catch( ( e ) => setError( e.message ) );
	}

	function openOffer( booking ) {
		setOffer( { open: true, booking, price: '', note: '', saving: false } );
	}

	function submitOffer( e ) {
		e.preventDefault();
		setOffer( ( o ) => ( { ...o, saving: true } ) );

		apiClient
			.post( `/admin/bookings/${ offer.booking.id }/offer`, {
				price: offer.price ? parseFloat( offer.price ) : 0,
				admin_note: offer.note,
			} )
			.then( () => {
				notify( 'Offer sent.' );
				setOffer( emptyOffer );
				load();
			} )
			.catch( ( e ) => {
				setError( e.message );
				setOffer( ( o ) => ( { ...o, saving: false } ) );
			} );
	}

	function loadDetail() {
		if ( ! selectedId ) {
			return;
		}

		setDetailLoading( true );
		apiClient
			.get( `/admin/bookings/${ selectedId }` )
			.then( setDetail )
			.catch( ( e ) => setError( e.message ) )
			.finally( () => setDetailLoading( false ) );
	}

	useEffect( loadDetail, [ selectedId ] );

	function closeDetail() {
		setSelectedId( null );
		setDetail( null );
	}

	function changeDetailStatus( next ) {
		apiClient
			.put( `/admin/bookings/${ selectedId }/status`, { status: next } )
			.then( () => {
				notify( 'Booking updated.' );
				loadDetail();
				load();
			} )
			.catch( ( e ) => setError( e.message ) );
	}

	function removeDetail() {
		if ( ! window.confirm( `Delete the booking for ${ detail.customer_name || 'this customer' }? This can't be undone.` ) ) {
			return;
		}

		apiClient
			.del( `/admin/bookings/${ selectedId }` )
			.then( () => {
				notify( 'Booking deleted.' );
				closeDetail();
				load();
			} )
			.catch( ( e ) => setError( e.message ) );
	}

	const totalPages = Math.max( 1, Math.ceil( total / perPage ) );

	return (
		<div className="space-y-6">
			{ selectedId ? (
				<button
					onClick={ closeDetail }
					className="inline-flex items-center gap-1.5 text-sm font-medium text-brand-600 transition-colors hover:text-brand-700"
				>
					<ArrowLeft className="h-4 w-4" />
					Back to Bookings
				</button>
			) : (
				<PageHeader title="Bookings" description="Every appointment booked through your site, most recent first." />
			) }

			{ error && <Card className="border-rose-200 bg-rose-50 text-sm text-rose-700">{ error }</Card> }

			{ selectedId ? (
				detailLoading || ! detail ? (
					<div className="space-y-6">
						<Card>
							<Skeleton className="h-16 w-full" />
						</Card>
						<div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
							<Card className="lg:col-span-2">
								<Skeleton className="h-48 w-full" />
							</Card>
							<Card>
								<Skeleton className="h-48 w-full" />
							</Card>
						</div>
					</div>
				) : (
					<div className="space-y-6">
						<Card className="overflow-hidden bg-brand-gradient text-white">
							<div className="flex flex-wrap items-center justify-between gap-6">
								<div className="flex items-center gap-4">
									<span className="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-white/15 text-lg font-semibold ring-2 ring-white/25">
										{ initials( detail.customer_name ) }
									</span>
									<div>
										<h2 className="text-xl font-semibold text-white">{ detail.customer_name || 'Unknown customer' }</h2>
										<p className="mt-1 inline-flex items-center gap-1.5 text-xs font-medium text-brand-100">
											<Hash className="h-3 w-3" />
											{ detail.uuid.slice( 0, 8 ).toUpperCase() }
											<span className="text-brand-200">·</span>
											{ detail.service_name || 'Unknown service' }
										</p>
									</div>
								</div>

								<div className="text-right">
									<Badge tone={ statusTone( detail.status ) } dot className={`bg-white/15 text-sm text-white ${ detail.status === 'completed' ? '!bg-emerald-50 ' : '' }`}>
										{ detail.status.replace( '_', ' ' ) }
									</Badge>
									<p className="mt-2 text-2xl font-semibold">{ formatPrice( detail ) }</p>
								</div>
							</div>
						</Card>

						<div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
							<div className="space-y-6 lg:col-span-2">
								<Card>
									<SectionTitle title="Customer Information" />
									<div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
										<InfoRow
											icon={ Mail }
											label="Email"
											value={
												detail.customer_email ? (
													<a href={ `mailto:${ detail.customer_email }` } className="text-brand-600 hover:underline">
														{ detail.customer_email }
													</a>
												) : (
													'—'
												)
											}
										/>
										<InfoRow
											icon={ Phone }
											label="Phone"
											value={
												detail.customer_phone ? (
													<a href={ `tel:${ detail.customer_phone }` } className="text-brand-600 hover:underline">
														{ detail.customer_phone }
													</a>
												) : (
													'—'
												)
											}
										/>
									</div>
								</Card>
								<Card>
									<SectionTitle title="Booking Details" />
									<div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
										<InfoRow icon={ Tag } label="Service" value={ detail.service_name || '—' } />
										<InfoRow icon={ CalendarDays } label="Date" value={ formatDate( detail.starts_at.slice( 0, 10 ) ) } />
										<InfoRow icon={ Clock3 } label="Time Slot" value={ `${ formatTime( detail.starts_at.slice( 11, 16 ) ) } – ${ formatTime( detail.ends_at.slice( 11, 16 ) ) }` } />
									</div>
									{ detail.notes && (
										<div className="mt-5 border-t border-slate-100 pt-5">
											<InfoRow icon={ MessageSquare } label="Customer notes" value={ detail.notes } />
										</div>
									) }
									{ detail.admin_notes && (
										<div className="mt-5 border-t border-slate-100 pt-5">
											<InfoRow icon={ StickyNote } label="Admin note (internal)" value={ detail.admin_notes } />
										</div>
									) }
								</Card>
							</div>
							<div className="space-y-6">
								<Card>
									<SectionTitle title="Timeline" />
									<ol>
										<li className="relative pb-5 pl-6 last:pb-0">
											<span className="absolute left-0 top-1 h-3 w-3 rounded-full bg-brand-500 ring-4 ring-brand-50" />
											{ 'pending' !== detail.status && <span className="absolute left-[5px] top-4 h-full w-px bg-slate-200" /> }
											<p className="text-sm font-semibold text-slate-800">Booking created</p>
											<p className="text-xs text-slate-400">{ formatDateTime( detail.created_at ) }</p>
										</li>
										{ 'pending' !== detail.status && (
											<li className="relative pl-6">
												<span className="absolute left-0 top-1 h-3 w-3 rounded-full bg-emerald-500 ring-4 ring-emerald-50" />
												<p className="text-sm font-semibold capitalize text-slate-800">{ detail.status.replace( '_', ' ' ) }</p>
												<p className="text-xs text-slate-400">{ formatDateTime( detail.updated_at ) }</p>
											</li>
										) }
									</ol>
								</Card>
								<Card>
									<SectionTitle title="Actions" />
									<div className="space-y-2">
										{ 'pending' === detail.status && (
											<>
												<Button className="w-full justify-center" icon={ Send } onClick={ () => openOffer( detail ) }>
													Send Offer
												</Button>
												<Button className="w-full justify-center" variant="success" icon={ Check } onClick={ () => changeDetailStatus( 'confirmed' ) }>
													Accept
												</Button>
												<Button className="w-full justify-center" variant="danger" icon={ XIcon } onClick={ () => changeDetailStatus( 'declined' ) }>
													Decline
												</Button>
											</>
										) }
										{ 'offer_sent' === detail.status && (
											<>
												<Badge tone="info" className="mb-1 w-full justify-center py-2 normal-case">Awaiting the customer's response</Badge>
												<Button className="w-full justify-center" variant="danger" icon={ Ban } onClick={ () => changeDetailStatus( 'cancelled' ) }>
													Cancel Offer
												</Button>
											</>
										) }
										{ 'confirmed' === detail.status && (
											<>
												<Button className="w-full justify-center" variant="success" icon={ CheckCircle2 } onClick={ () => changeDetailStatus( 'completed' ) }>
													Mark Completed
												</Button>
												<Button className="w-full justify-center" variant="warning" icon={ UserX } onClick={ () => changeDetailStatus( 'no_show' ) }>
													Mark No-show
												</Button>
												<Button className="w-full justify-center" variant="danger" icon={ Ban } onClick={ () => changeDetailStatus( 'cancelled' ) }>
													Cancel Booking
												</Button>
											</>
										) }
										{ [ 'declined', 'cancelled', 'completed', 'no_show', 'expired' ].includes( detail.status ) && (
											<p className="pb-1 text-xs text-slate-400">This booking is { detail.status.replace( '_', ' ' ) } — no further status changes are available.</p>
										) }
										<Button className="w-full justify-center" variant="danger" icon={ Trash2 } onClick={ removeDetail }>
											Delete Booking
										</Button>
									</div>
								</Card>
							</div>
						</div>
					</div>
				)
			) : (
				<>
					<Card className="bg-slate-50/60">
				<div className="grid grid-cols-4 gap-3">
					<Select className="w-auto min-w-[10rem]" value={ status } onChange={ ( e ) => setStatus( e.target.value ) }>
						<option value="">All Statuses</option>
						{ STATUS_OPTIONS.map( ( s ) => (
							<option key={ s } value={ s }>
								{ s.replace( '_', ' ' ) }
							</option>
						) ) }
					</Select>
					<Select className="w-auto min-w-[10rem]" value={ serviceId } onChange={ ( e ) => setServiceId( e.target.value ) }>
						<option value="">All Services</option>
						{ services.map( ( s ) => (
							<option key={ s.id } value={ s.id }>
								{ s.name }
							</option>
						) ) }
					</Select>
					<Input type="date" className="w-auto" value={ dateFrom } onChange={ ( e ) => setDateFrom( e.target.value ) } />
					<Input type="date" className="w-auto" value={ dateTo } onChange={ ( e ) => setDateTo( e.target.value ) } />
					<div className="relative min-w-full w-full flex-1">
						<Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
						<Input
							className="!pl-9 h-[50px]"
							placeholder="Search name or email..."
							value={ searchInput }
							onChange={ ( e ) => setSearchInput( e.target.value ) }
						/>
					</div>
				</div>
			</Card>

			<Card padded={ false }>
				{ loading ? (
					<div className="space-y-3 p-6">
						{ [ ...Array( 4 ) ].map( ( _, i ) => (
							<Skeleton key={ i } className="h-12 w-full" />
						) ) }
					</div>
				) : bookings.length === 0 ? (
					<div className="p-2">
						<EmptyState
							icon={ CalendarX2 }
							title="No bookings yet"
							description="Once a customer books through your widget, it'll show up here."
						/>
					</div>
				) : (
					<table>
						<thead>
							<tr className="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
								<th className="px-6 py-3.5">Date</th>
								<th className="px-6 py-3.5">Name</th>
								<th className="px-6 py-3.5">Service</th>
								<th className="px-6 py-3.5">Status</th>
								<th className="px-6 py-3.5">Price</th>
								<th className="px-6 py-3.5">Created</th>
								<th className="px-6 py-3.5 text-right">Actions</th>
							</tr>
						</thead>
						<tbody>
							{ bookings.map( ( booking ) => (
								<tr
									key={ booking.id }
									onClick={ () => setSelectedId( booking.id ) }
									className="cursor-pointer border-b border-slate-50 text-sm transition-colors hover:bg-slate-50/60 last:border-0"
								>
									<td className="px-6 py-3.5 text-slate-600">{ booking.starts_at.slice( 0, 10 ) }</td>
									<td className="px-6 py-3.5">
										<div className="flex items-center gap-2.5">
											<span className={ `flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold ${ avatarTone( booking.customer_name || '' ) }` }>
												{ initials( booking.customer_name ) }
											</span>
											<div>
												<p className="font-medium text-slate-800">{ booking.customer_name || 'Unknown' }</p>
												<p className="inline-flex items-center gap-1 text-xs text-slate-400">
													<Hash className="h-3 w-3" />
													{ booking.uuid.slice( 0, 8 ).toUpperCase() }
												</p>
											</div>
										</div>
									</td>
									<td className="px-6 py-3.5 text-slate-600">{ booking.service_name || '—' }</td>
									<td className="px-6 py-3.5">
										<Badge tone={ statusTone( booking.status ) } dot>
											{ booking.status.replace( '_', ' ' ) }
										</Badge>
									</td>
									<td className="px-6 py-3.5 font-medium text-slate-700">{ formatPrice( booking ) }</td>
									<td className="px-6 py-3.5 text-slate-500">{ booking.created_at }</td>
									<td className="px-6 py-3.5" onClick={ ( e ) => e.stopPropagation() }>
										<div className="flex items-center justify-end gap-1.5">
											{ 'pending' === booking.status && (
												<>
													<Button size="sm" variant="secondary" icon={ Send } onClick={ () => openOffer( booking ) }>
														Send Offer
													</Button>
													<Button size="sm" icon={ Check } onClick={ () => changeStatus( booking.id, 'confirmed' ) }>
														Accept
													</Button>
													<Button size="sm" variant="danger" icon={ XIcon } onClick={ () => changeStatus( booking.id, 'declined' ) }>
														Decline
													</Button>
												</>
											) }
											{ 'offer_sent' === booking.status && (
												<>
													<Badge tone="info" className="normal-case">Awaiting response</Badge>
													<Button size="sm" variant="danger" icon={ XIcon } onClick={ () => changeStatus( booking.id, 'cancelled' ) }>
														Cancel
													</Button>
												</>
											) }
										<IconButton icon={ Trash2 } tone="danger" onClick={ () => removeBooking( booking ) } aria-label="Delete booking" />
										</div>
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				) }
			</Card>

			<div className="flex flex-wrap items-center justify-between gap-3">
				<p className="text-sm text-slate-500">
					{ total } booking{ 1 === total ? '' : 's' } total
				</p>
				<div className="flex items-center gap-3">
					<Select
						className="w-auto py-1.5 text-xs"
						value={ perPage }
						onChange={ ( e ) => {
							setPerPage( Number( e.target.value ) );
							setPage( 1 );
						} }
					>
						{ PER_PAGE_OPTIONS.map( ( n ) => (
							<option key={ n } value={ n }>
								{ n } per page
							</option>
						) ) }
					</Select>
					<div className="flex items-center gap-1">
						<IconButton icon={ ChevronLeft } onClick={ () => setPage( ( p ) => Math.max( 1, p - 1 ) ) } disabled={ page <= 1 } aria-label="Previous page" />
						<span className="flex h-8 min-w-[2rem] items-center justify-center rounded-lg bg-brand-gradient px-2 text-xs font-semibold text-white">
							{ page }
						</span>
						<IconButton icon={ ChevronRight } onClick={ () => setPage( ( p ) => Math.min( totalPages, p + 1 ) ) } disabled={ page >= totalPages } aria-label="Next page" />
					</div>
				</div>
			</div>
				</>
			) }

			<Modal
				open={ offer.open }
				onClose={ () => setOffer( emptyOffer ) }
				title="Send Offer"
				footer={
					<>
						<Button type="button" variant="secondary" onClick={ () => setOffer( emptyOffer ) }>
							Cancel
						</Button>
						<Button type="submit" form="appointiva-send-offer" loading={ offer.saving }>
							Send Offer
						</Button>
					</>
				}
			>
				{ offer.booking && (
					<form id="appointiva-send-offer" onSubmit={ submitOffer } className="space-y-4">
						<div className="rounded-lg bg-slate-50 p-4 text-sm text-slate-700">
							<p><strong>Customer:</strong> { offer.booking.customer_name || 'Unknown' }</p>
							<p><strong>Service:</strong> { offer.booking.service_name || '—' }</p>
							<p><strong>Date:</strong> { offer.booking.starts_at.slice( 0, 10 ) }</p>
						</div>
						<Field label={ `Price (${ currencySymbol( offer.booking.currency ) })` } hint="Leave empty to send an offer without a price.">
							<Input
								type="number"
								step="0.01"
								min="0"
								placeholder="0.00"
								value={ offer.price }
								onChange={ ( e ) => setOffer( ( o ) => ( { ...o, price: e.target.value } ) ) }
							/>
						</Field>
						<Field label="Admin note (optional)" hint="Internal — not visible to the customer.">
							<Textarea
								rows={ 3 }
								placeholder="Internal note, not visible to customer..."
								value={ offer.note }
								onChange={ ( e ) => setOffer( ( o ) => ( { ...o, note: e.target.value } ) ) }
							/>
						</Field>
					</form>
				) }
			</Modal>

			<Toast message={ toast } />
		</div>
	);
}
