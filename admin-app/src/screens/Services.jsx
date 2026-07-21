import { useEffect, useMemo, useState } from 'react';
import { Plus, Pencil, Trash2, X, ListChecks, Clock3 } from 'lucide-react';
import { useResource } from '../api/useResource';
import { Card, PageHeader, SectionTitle, Field, Input, Textarea, Button, IconButton, Badge, Switch, Radio, MediaPicker, EmptyState, Skeleton } from '../components/ui';

const empty = {
	name: '',
	description: '',
	image_id: 0,
	status: 'active',
	min_lead_days: 0,
	price: '0.00',
	currency: 'USD',
	booking_mode: 'timeslot',
	available_from: '09:00',
	available_until: '17:00',
	duration_minutes: 30,
	buffer_after_minutes: 0,
};

function toTimeInputValue( value ) {
	return value ? value.slice( 0, 5 ) : '';
}

function slotsPerDay( form ) {
	if ( 'timeslot' !== form.booking_mode || ! form.available_from || ! form.available_until ) {
		return null;
	}

	const [ fromH, fromM ] = form.available_from.split( ':' ).map( Number );
	const [ untilH, untilM ] = form.available_until.split( ':' ).map( Number );
	const windowMinutes = ( untilH * 60 + untilM ) - ( fromH * 60 + fromM );
	const slotLength = ( parseInt( form.duration_minutes, 10 ) || 0 ) + ( parseInt( form.buffer_after_minutes, 10 ) || 0 );

	if ( windowMinutes <= 0 || slotLength <= 0 ) {
		return 0;
	}

	return Math.floor( windowMinutes / slotLength );
}

function ServiceThumbnail( { imageId } ) {
	const [ url, setUrl ] = useState( null );

	useEffect( () => {
		if ( ! imageId || ! window.wp?.media ) {
			setUrl( null );
			return;
		}

		const attachment = window.wp.media.attachment( imageId );
		attachment.fetch().then( () => setUrl( attachment.get( 'url' ) ) );
	}, [ imageId ] );

	if ( ! url ) {
		return null;
	}

	return <img src={ url } alt="" className="h-10 w-10 shrink-0 rounded-lg border border-slate-200 object-cover" />;
}

export function Services() {
	const { items, loading, error, create, update, remove } = useResource( 'services' );
	const [ form, setForm ] = useState( empty );
	const [ editingId, setEditingId ] = useState( null );
	const [ saving, setSaving ] = useState( false );

	const slots = useMemo( () => slotsPerDay( form ), [ form ] );

	function patch( fields ) {
		setForm( ( current ) => ( { ...current, ...fields } ) );
	}

	function submit( e ) {
		e.preventDefault();
		setSaving( true );
		const action = editingId ? update( editingId, form ) : create( form );
		action.finally( () => {
			setSaving( false );
			setForm( empty );
			setEditingId( null );
		} );
	}

	function edit( service ) {
		setEditingId( service.id );
		setForm( {
			name: service.name,
			description: service.description || '',
			image_id: service.image_id || 0,
			status: service.status || 'active',
			min_lead_days: service.min_lead_days ?? 0,
			price: service.price,
			currency: service.currency,
			booking_mode: service.booking_mode || 'timeslot',
			available_from: toTimeInputValue( service.available_from ) || '09:00',
			available_until: toTimeInputValue( service.available_until ) || '17:00',
			duration_minutes: service.duration_minutes,
			buffer_after_minutes: service.buffer_after_minutes ?? 0,
		} );
	}

	return (
		<div className="space-y-6">
			<PageHeader title="Services" description="What customers can book, how long it takes, and what it costs." />

			{ error && <Card className="border-rose-200 bg-rose-50 text-sm text-rose-700">{ error }</Card> }

			<Card>
				<SectionTitle
					title={ editingId ? 'Edit service' : 'Add a service' }
					description="Duration includes the customer-facing time — buffers can be tuned via availability."
				/>
				<form onSubmit={ submit } className="space-y-8">
					<div className="grid grid-cols-1 gap-4 md:grid-cols-4">
						<Field label="Service name" className="md:col-span-2">
							<Input placeholder="e.g. Haircut & style" required value={ form.name } onChange={ ( e ) => patch( { name: e.target.value } ) } />
						</Field>
						<Field label="Description" className="md:col-span-2">
							<Textarea rows={ 1 } placeholder="Optional — shown to customers while booking" value={ form.description } onChange={ ( e ) => patch( { description: e.target.value } ) } />
						</Field>
					</div>

					<div className="space-y-3 border-t border-slate-100 pt-6">
						<span className="block text-xs font-semibold uppercase tracking-wide text-slate-400">Image</span>
						<MediaPicker value={ form.image_id } onChange={ ( id ) => patch( { image_id: id } ) } />
						<Switch checked={ 'active' === form.status } onChange={ ( e ) => patch( { status: e.target.checked ? 'active' : 'inactive' } ) } label="Active" />
						<p className="text-xs text-slate-400">Service is visible to customers.</p>
					</div>

					<div className="border-t border-slate-100 pt-6">
						<SectionTitle title="Availability & Pricing" className="mb-4" />
						<div className="grid grid-cols-1 gap-4 md:grid-cols-2">
							<Field label="Minimum lead days" hint="How many days in advance must a booking be made.">
								<Input type="number" min="0" value={ form.min_lead_days } onChange={ ( e ) => patch( { min_lead_days: e.target.value } ) } />
							</Field>
							<Field label="Base price" hint="Base price for this service. Leave empty for no default price.">
								<div className="flex gap-2">
									<Input type="number" step="0.01" min="0" placeholder="0.00" value={ form.price } onChange={ ( e ) => patch( { price: e.target.value } ) } />
									<Input className="w-20" maxLength={ 3 } value={ form.currency } onChange={ ( e ) => patch( { currency: e.target.value.toUpperCase() } ) } />
								</div>
							</Field>
						</div>
					</div>

					<div className="border-t border-slate-100 pt-6">
						<SectionTitle title="Time Slots" className="mb-4" />

						<Field label="Booking mode" className="mb-4">
							<div className="space-y-2">
								<Radio
									name="booking_mode"
									label="Day — customer books a whole day"
									checked={ 'day' === form.booking_mode }
									onChange={ () => patch( { booking_mode: 'day' } ) }
								/>
								<Radio
									name="booking_mode"
									label="Timeslot — customer picks a time slot"
									checked={ 'timeslot' === form.booking_mode }
									onChange={ () => patch( { booking_mode: 'timeslot' } ) }
								/>
							</div>
						</Field>

						{ 'timeslot' === form.booking_mode && (
							<>
								<div className="grid grid-cols-1 gap-4 md:grid-cols-2">
									<Field label="Available from">
										<Input type="time" value={ form.available_from } onChange={ ( e ) => patch( { available_from: e.target.value } ) } />
									</Field>
									<Field label="Available until">
										<Input type="time" value={ form.available_until } onChange={ ( e ) => patch( { available_until: e.target.value } ) } />
									</Field>
									<Field label="Slot duration (minutes)" hint="How long each appointment lasts.">
										<Input type="number" min="5" required value={ form.duration_minutes } onChange={ ( e ) => patch( { duration_minutes: e.target.value } ) } />
									</Field>
									<Field label="Buffer between slots (minutes)" hint="Optional gap between two consecutive slots.">
										<Input type="number" min="0" value={ form.buffer_after_minutes } onChange={ ( e ) => patch( { buffer_after_minutes: e.target.value } ) } />
									</Field>
								</div>

								{ null !== slots && (
									<div className="mt-4 border-l-2 border-brand-500 bg-brand-50/50 px-4 py-2.5 text-sm text-slate-600">
										This configuration produces <strong>{ slots }</strong> slot{ 1 === slots ? '' : 's' } per day.
									</div>
								) }

								<div className="mt-3 rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600">
									<strong className="text-slate-800">Need different hours per weekday?</strong> Appointiva Pro ships a per-weekday availability editor (e.g. Mon/Wed/Fri 09-12 + 14-18, Tue/Thu only afternoons).
								</div>
							</>
						) }
					</div>

					<div className="flex items-center gap-2 border-t border-slate-100 pt-6">
						<Button type="submit" icon={ editingId ? Pencil : Plus } loading={ saving }>
							{ editingId ? 'Update service' : 'Add service' }
						</Button>
						{ editingId && (
							<Button
								type="button"
								variant="ghost"
								icon={ X }
								onClick={ () => {
									setEditingId( null );
									setForm( empty );
								} }
							>
								Cancel
							</Button>
						) }
					</div>
				</form>
			</Card>

			<Card padded={ false }>
				{ loading ? (
					<div className="space-y-3 p-6">
						{ [ ...Array( 3 ) ].map( ( _, i ) => (
							<Skeleton key={ i } className="h-12 w-full" />
						) ) }
					</div>
				) : items.length === 0 ? (
					<div className="p-2">
						<EmptyState icon={ ListChecks } title="No services yet" description="Add your first bookable service above to start taking appointments." />
					</div>
				) : (
					<table>
						<thead>
							<tr className="border-b border-slate-100 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
								<th className="px-6 py-3.5">Service</th>
								<th className="px-6 py-3.5">Duration</th>
								<th className="px-6 py-3.5">Price</th>
								<th className="px-6 py-3.5">Status</th>
								<th className="px-6 py-3.5 text-right">Actions</th>
							</tr>
						</thead>
						<tbody>
							{ items.map( ( service ) => (
								<tr key={ service.id } className="border-b border-slate-50 text-sm transition-colors hover:bg-slate-50/60 last:border-0">
									<td className="px-6 py-4">
										<div className="flex items-center gap-3">
											<ServiceThumbnail imageId={ service.image_id } />
											<div>
												<p className="font-medium text-slate-800">{ service.name }</p>
												{ service.description && <p className="mt-0.5 max-w-md truncate text-xs text-slate-400">{ service.description }</p> }
											</div>
										</div>
									</td>
									<td className="px-6 py-4">
										<Badge tone="info" className="normal-case">
											<Clock3 className="h-3 w-3" />
											{ service.duration_minutes } min
										</Badge>
									</td>
									<td className="px-6 py-4 font-medium text-slate-700">
										{ service.price } { service.currency }
									</td>
									<td className="px-6 py-4">
										<Badge tone={ 'active' === service.status ? 'success' : 'neutral' }>{ service.status }</Badge>
									</td>
									<td className="px-6 py-4">
										<div className="flex justify-end gap-1">
											<IconButton icon={ Pencil } tone="brand" onClick={ () => edit( service ) } aria-label="Edit service" />
											<IconButton icon={ Trash2 } tone="danger" onClick={ () => remove( service.id ) } aria-label="Delete service" />
										</div>
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				) }
			</Card>
		</div>
	);
}
