import { useEffect, useState } from 'react';
import { Save, CheckCircle2, AlertTriangle } from 'lucide-react';
import { apiClient } from '../api/client';
import { Slot } from '../slot-fill/Slot';
import { Card, PageHeader, SectionTitle, Field, Input, Select, Radio, Checkbox, Button } from '../components/ui';

const DATE_FORMATS = [ 'Y-m-d', 'm/d/Y', 'd/m/Y', 'd.m.Y', 'F j, Y', 'j F Y' ];

const MONTHS = [ 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' ];

function formatDatePreview( pattern, date ) {
	const day = date.getDate();
	const month = date.getMonth();
	const year = date.getFullYear();
	const pad = ( n ) => String( n ).padStart( 2, '0' );

	return pattern
		.replace( 'Y', year )
		.replace( 'F', MONTHS[ month ] )
		.replace( 'm', pad( month + 1 ) )
		.replace( 'd', pad( day ) )
		.replace( 'j', day );
}

const defaultForm = {
	reminder_lead_hours: 24,
	delete_data_on_uninstall: false,
	admin_email: '',
	date_format: 'Y-m-d',
	currency: 'USD',
	currency_symbol: '$',
	currency_position: 'before',
	theme_mode: 'auto',
};

export function Settings() {
	const [ form, setForm ] = useState( defaultForm );
	const [ saving, setSaving ] = useState( false );
	const [ saved, setSaved ] = useState( false );
	const [ message, setMessage ] = useState( '' );
	const today = new Date();

	useEffect( () => {
		apiClient.get( '/admin/settings/general' ).then( ( data ) => setForm( { ...defaultForm, ...data } ) );
	}, [] );

	function save( e ) {
		e.preventDefault();
		setSaving( true );
		setSaved( false );
		apiClient
			.put( '/admin/settings/general', form )
			.then( () => setSaved( true ) )
			.catch( ( err ) => setMessage( err.message ) )
			.finally( () => setSaving( false ) );
	}

	return (
		<div className="space-y-6">
			<PageHeader title="Settings" description="General booking behavior and data handling." />

			{ message && <Card className="border-rose-200 bg-rose-50 text-sm text-rose-700">{ message }</Card> }

			<Card>
				<SectionTitle title="General" description="Site-wide defaults for email, formatting, and appearance." />
				<form onSubmit={ save } className="space-y-5">
					<div className="grid grid-cols-1 gap-4 md:grid-cols-2">
						<Field label="Admin email" hint="Used as the sender for booking notification emails.">
							<Input
								type="email"
								value={ form.admin_email }
								onChange={ ( e ) => setForm( { ...form, admin_email: e.target.value } ) }
							/>
						</Field>

						<Field label="Date format">
							<Select value={ form.date_format } onChange={ ( e ) => setForm( { ...form, date_format: e.target.value } ) }>
								{ DATE_FORMATS.map( ( pattern ) => (
									<option key={ pattern } value={ pattern }>
										{ pattern } ({ formatDatePreview( pattern, today ) })
									</option>
								) ) }
							</Select>
						</Field>

						<Field label="Currency" hint="ISO 4217 currency code (e.g., EUR, USD, GBP).">
							<Input
								maxLength={ 3 }
								value={ form.currency }
								onChange={ ( e ) => setForm( { ...form, currency: e.target.value.toUpperCase() } ) }
							/>
						</Field>

						<Field label="Currency symbol">
							<Input
								value={ form.currency_symbol }
								onChange={ ( e ) => setForm( { ...form, currency_symbol: e.target.value } ) }
							/>
						</Field>

						<Field label="Currency position" className="md:col-span-2">
							<Select
								value={ form.currency_position }
								onChange={ ( e ) => setForm( { ...form, currency_position: e.target.value } ) }
							>
								<option value="before">Before amount ({ form.currency_symbol }100)</option>
								<option value="after">After amount (100{ form.currency_symbol })</option>
							</Select>
						</Field>
					</div>

					<div>
						<p className="mb-2 text-sm font-medium text-slate-700">Theme mode</p>
						<div className="space-y-2.5">
							<Radio
								name="theme_mode"
								label="Auto (follows device setting)"
								checked={ form.theme_mode === 'auto' }
								onChange={ () => setForm( { ...form, theme_mode: 'auto' } ) }
							/>
							<Radio
								name="theme_mode"
								label="Always Light"
								checked={ form.theme_mode === 'light' }
								onChange={ () => setForm( { ...form, theme_mode: 'light' } ) }
							/>
							<Radio
								name="theme_mode"
								label="Always Dark"
								checked={ form.theme_mode === 'dark' }
								onChange={ () => setForm( { ...form, theme_mode: 'dark' } ) }
							/>
						</div>
						<p className="mt-2 text-xs text-slate-400">Controls the calendar appearance and email templates.</p>
					</div>

					<Button type="submit" icon={ saved ? CheckCircle2 : Save } loading={ saving }>
						{ saved ? 'Saved' : 'Save settings' }
					</Button>
				</form>
			</Card>

			<Card>
				<SectionTitle title="Reminders" description="How far ahead of an appointment a reminder email goes out." />
				<form onSubmit={ save } className="space-y-5">
					<Field label="Reminder lead time (hours)" className="max-w-xs">
						<Input
							type="number"
							min="1"
							max="168"
							value={ form.reminder_lead_hours }
							onChange={ ( e ) => setForm( { ...form, reminder_lead_hours: e.target.value } ) }
						/>
					</Field>

					<Button type="submit" icon={ saved ? CheckCircle2 : Save } loading={ saving }>
						{ saved ? 'Saved' : 'Save settings' }
					</Button>
				</form>
			</Card>

			<Card className="border-amber-200 bg-amber-50/40">
				<div className="mb-4 flex items-center gap-2 text-amber-800">
					<AlertTriangle className="h-4.5 w-4.5" />
					<h2 className="text-base font-semibold">Danger zone</h2>
				</div>
				<Checkbox
					label="Delete all Appointiva data when the plugin is deleted"
					description="Off by default. Bookings, customers, and settings are kept so you can safely reinstall. Turning this on permanently deletes everything when you remove the plugin."
					checked={ form.delete_data_on_uninstall }
					onChange={ ( e ) => {
						const next = { ...form, delete_data_on_uninstall: e.target.checked };
						setForm( next );
						apiClient.put( '/admin/settings/general', next );
					} }
				/>
			</Card>

			<Slot name="appointiva-settings-tabs" />
		</div>
	);
}
