import { useEffect, useState } from 'react';
import { Save, ShieldCheck, CheckCircle2 } from 'lucide-react';
import { apiClient } from '../api/client';
import { Card, PageHeader, SectionTitle, Field, Input, Switch, Button } from '../components/ui';

const PRESETS = {
	gmail: { label: 'Gmail', host: 'smtp.gmail.com', port: 587, encryption: 'tls' },
	outlook: { label: 'Outlook / M365', host: 'smtp.office365.com', port: 587, encryption: 'tls' },
	yahoo: { label: 'Yahoo Mail', host: 'smtp.mail.yahoo.com', port: 587, encryption: 'tls' },
	icloud: { label: 'iCloud Mail', host: 'smtp.mail.me.com', port: 587, encryption: 'tls' },
	zoho: { label: 'Zoho Mail', host: 'smtp.zoho.com', port: 587, encryption: 'tls' },
	custom: { label: 'Custom', host: '', port: 587, encryption: 'tls' },
};

const emptyForm = { enabled: false, preset: 'custom', host: '', port: 587, encryption: 'tls', username: '', password: '', from_email: '', from_name: '' };

export function Notifications() {
	const [ form, setForm ] = useState( emptyForm );
	const [ saving, setSaving ] = useState( false );
	const [ saved, setSaved ] = useState( false );
	const [ message, setMessage ] = useState( '' );

	useEffect( () => {
		apiClient.get( '/admin/settings/smtp' ).then( ( data ) => setForm( { ...emptyForm, ...data, password: '' } ) );
	}, [] );

	function applyPreset( preset ) {
		const p = PRESETS[ preset ];
		setForm( { ...form, preset, host: p.host, port: p.port, encryption: p.encryption } );
	}

	function save( e ) {
		e.preventDefault();
		setSaving( true );
		setSaved( false );
		apiClient
			.put( '/admin/settings/smtp', form )
			.then( () => setSaved( true ) )
			.catch( ( err ) => setMessage( err.message ) )
			.finally( () => setSaving( false ) );
	}

	return (
		<div className="space-y-6">
			<PageHeader title="Notifications" description="Control how confirmation and reminder emails are delivered." />

			{ message && <Card className="border-rose-200 bg-rose-50 text-sm text-rose-700">{ message }</Card> }

			<Card>
				<div className="mb-5 flex items-center justify-between">
					<SectionTitle title="SMTP delivery" description="Send booking emails through a real mailbox instead of the server default." className="mb-0" />
					<Switch checked={ form.enabled } onChange={ ( e ) => setForm( { ...form, enabled: e.target.checked } ) } />
				</div>

				<form onSubmit={ save } className={ `space-y-5 transition-opacity ${ form.enabled ? '' : 'pointer-events-none opacity-40' }` }>
					<div>
						<p className="mb-2 text-sm font-medium text-slate-700">Provider</p>
						<div className="flex flex-wrap gap-2">
							{ Object.entries( PRESETS ).map( ( [ key, preset ] ) => (
								<button
									type="button"
									key={ key }
									onClick={ () => applyPreset( key ) }
									className={ `rounded-full border px-3.5 py-1.5 text-xs font-medium transition-colors ${
										form.preset === key
											? 'border-brand-600 bg-brand-50 text-brand-700'
											: 'border-slate-200 text-slate-500 hover:border-slate-300 hover:text-slate-700'
									}` }
								>
									{ preset.label }
								</button>
							) ) }
						</div>
					</div>

					<div className="grid grid-cols-1 gap-4 md:grid-cols-2">
						<Field label="SMTP host">
							<Input value={ form.host } onChange={ ( e ) => setForm( { ...form, host: e.target.value } ) } />
						</Field>
						<Field label="Port">
							<Input type="number" value={ form.port } onChange={ ( e ) => setForm( { ...form, port: e.target.value } ) } />
						</Field>
						<Field label="Username">
							<Input value={ form.username } onChange={ ( e ) => setForm( { ...form, username: e.target.value } ) } />
						</Field>
						<Field label="Password" hint="Leave blank to keep the current password">
							<Input type="password" value={ form.password } onChange={ ( e ) => setForm( { ...form, password: e.target.value } ) } />
						</Field>
						<Field label="From email">
							<Input type="email" value={ form.from_email } onChange={ ( e ) => setForm( { ...form, from_email: e.target.value } ) } />
						</Field>
						<Field label="From name">
							<Input value={ form.from_name } onChange={ ( e ) => setForm( { ...form, from_name: e.target.value } ) } />
						</Field>
					</div>

					<div className="flex items-center gap-3 border-t border-slate-100 pt-5">
						<Button type="submit" icon={ saved ? CheckCircle2 : Save } loading={ saving }>
							{ saved ? 'Saved' : 'Save SMTP settings' }
						</Button>
						<p className="flex items-center gap-1.5 text-xs text-slate-400">
							<ShieldCheck className="h-3.5 w-3.5" />
							Credentials are encrypted (AES-256) before storage.
						</p>
					</div>
				</form>
			</Card>
		</div>
	);
}
