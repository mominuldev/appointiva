import { useState } from 'react';
import { UserPlus, Trash2, Users } from 'lucide-react';
import { useResource } from '../api/useResource';
import { Card, PageHeader, SectionTitle, Field, Input, Button, IconButton, EmptyState, Skeleton } from '../components/ui';
import { initials, avatarTone } from '../lib/status';

const empty = { display_name: '', email: '', phone: '' };

export function Staff() {
	const { items, loading, error, create, remove } = useResource( 'staff' );
	const [ form, setForm ] = useState( empty );
	const [ saving, setSaving ] = useState( false );

	function submit( e ) {
		e.preventDefault();
		setSaving( true );
		create( form ).finally( () => {
			setSaving( false );
			setForm( empty );
		} );
	}

	return (
		<div className="space-y-6">
			<PageHeader
				title="Staff"
				description="The Free plugin books a single staff member per service. Add more staff now to prepare for Appointiva Pro's multi-staff scheduling."
			/>

			{ error && <Card className="border-rose-200 bg-rose-50 text-sm text-rose-700">{ error }</Card> }

			<Card>
				<SectionTitle title="Add a staff member" />
				<form onSubmit={ submit } className="grid grid-cols-1 gap-4 md:grid-cols-4">
					<Field label="Name">
						<Input required value={ form.display_name } onChange={ ( e ) => setForm( { ...form, display_name: e.target.value } ) } />
					</Field>
					<Field label="Email">
						<Input type="email" required value={ form.email } onChange={ ( e ) => setForm( { ...form, email: e.target.value } ) } />
					</Field>
					<Field label="Phone">
						<Input value={ form.phone } onChange={ ( e ) => setForm( { ...form, phone: e.target.value } ) } />
					</Field>
					<div className="flex items-end">
						<Button type="submit" icon={ UserPlus } loading={ saving } className="w-full justify-center">
							Add staff
						</Button>
					</div>
				</form>
			</Card>

			{ loading ? (
				<div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
					{ [ ...Array( 3 ) ].map( ( _, i ) => (
						<Skeleton key={ i } className="h-20 w-full" />
					) ) }
				</div>
			) : items.length === 0 ? (
				<Card padded={ false }>
					<div className="p-2">
						<EmptyState icon={ Users } title="No staff yet" description="Add the people who'll be fulfilling bookings." />
					</div>
				</Card>
			) : (
				<div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
					{ items.map( ( person ) => (
						<Card key={ person.id } className="flex items-center gap-3">
							<span className={ `flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-sm font-semibold ${ avatarTone( person.display_name ) }` }>
								{ initials( person.display_name ) }
							</span>
							<div className="min-w-0 flex-1">
								<p className="truncate text-sm font-semibold text-slate-800">{ person.display_name }</p>
								<p className="truncate text-xs text-slate-400">{ person.email }</p>
							</div>
							<IconButton icon={ Trash2 } tone="danger" onClick={ () => remove( person.id ) } aria-label="Remove staff member" />
						</Card>
					) ) }
				</div>
			) }
		</div>
	);
}
