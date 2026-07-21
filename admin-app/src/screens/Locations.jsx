import { useState } from 'react';
import { MapPin, Pencil, Check, X } from 'lucide-react';
import { useResource } from '../api/useResource';
import { Card, PageHeader, Field, Input, Button, IconButton, Badge, Skeleton } from '../components/ui';

export function Locations() {
	const { items, loading, error, update } = useResource( 'locations' );
	const [ editingId, setEditingId ] = useState( null );
	const [ form, setForm ] = useState( { name: '', address: '', phone: '' } );
	const [ saving, setSaving ] = useState( false );

	function edit( location ) {
		setEditingId( location.id );
		setForm( { name: location.name, address: location.address || '', phone: location.phone || '' } );
	}

	function submit( e ) {
		e.preventDefault();
		setSaving( true );
		update( editingId, form ).finally( () => {
			setSaving( false );
			setEditingId( null );
		} );
	}

	return (
		<div className="space-y-6">
			<PageHeader title="Locations" description="Where appointments happen. Appointiva Pro adds support for multiple locations." />

			{ error && <Card className="border-rose-200 bg-rose-50 text-sm text-rose-700">{ error }</Card> }

			{ loading ? (
				<Skeleton className="h-24 w-full" />
			) : (
				<div className="space-y-3">
					{ items.map( ( location ) =>
						editingId === location.id ? (
							<Card key={ location.id }>
								<form onSubmit={ submit } className="grid grid-cols-1 gap-4 md:grid-cols-4">
									<Field label="Name">
										<Input value={ form.name } onChange={ ( e ) => setForm( { ...form, name: e.target.value } ) } />
									</Field>
									<Field label="Address" className="md:col-span-2">
										<Input value={ form.address } onChange={ ( e ) => setForm( { ...form, address: e.target.value } ) } />
									</Field>
									<Field label="Phone">
										<Input value={ form.phone } onChange={ ( e ) => setForm( { ...form, phone: e.target.value } ) } />
									</Field>
									<div className="flex gap-2 md:col-span-4">
										<Button type="submit" icon={ Check } loading={ saving }>
											Save
										</Button>
										<Button type="button" variant="ghost" icon={ X } onClick={ () => setEditingId( null ) }>
											Cancel
										</Button>
									</div>
								</form>
							</Card>
						) : (
							<Card key={ location.id } className="flex items-center gap-4">
								<div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
									<MapPin className="h-5 w-5" strokeWidth={ 2 } />
								</div>
								<div className="min-w-0 flex-1">
									<div className="flex items-center gap-2">
										<p className="font-semibold text-slate-800">{ location.name }</p>
										{ !! location.is_default && <Badge tone="brand">Default</Badge> }
									</div>
									<p className="truncate text-sm text-slate-400">{ location.address || 'No address set' }</p>
								</div>
								<IconButton icon={ Pencil } tone="brand" onClick={ () => edit( location ) } aria-label="Edit location" />
							</Card>
						)
					) }
				</div>
			) }
		</div>
	);
}
