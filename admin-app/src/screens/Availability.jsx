import { useEffect, useState } from 'react';
import { Save, CheckCircle2 } from 'lucide-react';
import { apiClient } from '../api/client';
import { Card, PageHeader, SectionTitle, Input, Button, Switch } from '../components/ui';

const DAYS = [ 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ];

function defaultWeek() {
	return DAYS.map( ( _, day_of_week ) => ( {
		day_of_week,
		is_available: day_of_week >= 1 && day_of_week <= 5,
		start_time: '09:00',
		end_time: '17:00',
	} ) );
}

export function Availability() {
	const [ week, setWeek ] = useState( defaultWeek() );
	const [ saving, setSaving ] = useState( false );
	const [ saved, setSaved ] = useState( false );
	const [ message, setMessage ] = useState( '' );

	useEffect( () => {
		apiClient.get( '/admin/availability/recurring' ).then( ( rows ) => {
			if ( rows && rows.length ) {
				setWeek( rows );
			}
		} );
	}, [] );

	function updateDay( index, patch ) {
		setWeek( ( current ) => current.map( ( day, i ) => ( i === index ? { ...day, ...patch } : day ) ) );
	}

	function save() {
		setSaving( true );
		setMessage( '' );
		setSaved( false );
		apiClient
			.put( '/admin/availability/recurring', { week } )
			.then( () => setSaved( true ) )
			.catch( ( e ) => setMessage( e.message ) )
			.finally( () => setSaving( false ) );
	}

	const activeDays = week.filter( ( d ) => d.is_available ).length;

	return (
		<div className="space-y-6">
			<PageHeader
				title="Availability"
				description="Set the weekly hours customers can book. Date-specific overrides and time off are managed from the Bookings calendar."
				action={
					<Button icon={ saved ? CheckCircle2 : Save } loading={ saving } onClick={ save }>
						{ saved ? 'Saved' : 'Save availability' }
					</Button>
				}
			/>

			{ message && <Card className="border-rose-200 bg-rose-50 text-sm text-rose-700">{ message }</Card> }

			<Card padded={ false }>
				<div className="flex items-center justify-between border-b border-slate-100 px-6 py-4">
					<SectionTitle title="Weekly hours" description={ `${ activeDays } of 7 days open for booking` } className="mb-0" />
				</div>
				<div className="divide-y divide-slate-50">
					{ week.map( ( day, index ) => (
						<div key={ day.day_of_week } className={ `flex flex-wrap items-center gap-4 px-6 py-4 transition-colors ${ day.is_available ? '' : 'opacity-50' }` }>
							<Switch
								checked={ day.is_available }
								onChange={ ( e ) => updateDay( index, { is_available: e.target.checked } ) }
								className="w-40"
								label={ DAYS[ day.day_of_week ] }
							/>
							<div className="flex items-center gap-2">
								<Input
									type="time"
									className="w-32"
									disabled={ ! day.is_available }
									value={ day.start_time }
									onChange={ ( e ) => updateDay( index, { start_time: e.target.value } ) }
								/>
								<span className="text-sm text-slate-400">to</span>
								<Input
									type="time"
									className="w-32"
									disabled={ ! day.is_available }
									value={ day.end_time }
									onChange={ ( e ) => updateDay( index, { end_time: e.target.value } ) }
								/>
							</div>
						</div>
					) ) }
				</div>
			</Card>
		</div>
	);
}
