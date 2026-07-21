import { useEffect, useState } from 'react';
import { CreditCard, Wallet, Save, CheckCircle2 } from 'lucide-react';
import { apiClient } from '../api/client';
import { Card, PageHeader, SectionTitle, Field, Input, Switch, Button, Badge } from '../components/ui';

function GatewayCard( { icon: Icon, iconTone, title, subtitle, enabled, onToggle, configured, children, onSubmit, saving, saved } ) {
	return (
		<Card>
			<div className="mb-5 flex items-start justify-between">
				<div className="flex items-center gap-3">
					<div className={ `flex h-10 w-10 items-center justify-center rounded-xl text-white ${ iconTone }` }>
						<Icon className="h-5 w-5" strokeWidth={ 2 } />
					</div>
					<div>
						<div className="flex items-center gap-2">
							<h2 className="text-base font-semibold text-slate-900">{ title }</h2>
							<Badge tone={ configured ? 'success' : 'neutral' } dot>
								{ configured ? 'Connected' : 'Not connected' }
							</Badge>
						</div>
						<p className="text-sm text-slate-500">{ subtitle }</p>
					</div>
				</div>
				<Switch checked={ enabled } onChange={ onToggle } />
			</div>

			<form onSubmit={ onSubmit } className={ `space-y-4 transition-opacity ${ enabled ? '' : 'pointer-events-none opacity-40' }` }>
				{ children }
				<div className="border-t border-slate-100 pt-4">
					<Button type="submit" size="sm" icon={ saved ? CheckCircle2 : Save } loading={ saving }>
						{ saved ? 'Saved' : `Save ${ title } settings` }
					</Button>
				</div>
			</form>
		</Card>
	);
}

export function Payments() {
	const [ stripe, setStripe ] = useState( { enabled: false, publishable_key: '', secret_key: '', configured: false } );
	const [ paypal, setPaypal ] = useState( { enabled: false, sandbox: false, client_id: '', client_secret: '', configured: false } );
	const [ savingStripe, setSavingStripe ] = useState( false );
	const [ savingPaypal, setSavingPaypal ] = useState( false );
	const [ savedStripe, setSavedStripe ] = useState( false );
	const [ savedPaypal, setSavedPaypal ] = useState( false );
	const [ message, setMessage ] = useState( '' );

	useEffect( () => {
		apiClient.get( '/admin/settings/stripe' ).then( ( d ) => setStripe( ( s ) => ( { ...s, ...d, secret_key: '' } ) ) );
		apiClient.get( '/admin/settings/paypal' ).then( ( d ) => setPaypal( ( p ) => ( { ...p, ...d, client_secret: '' } ) ) );
	}, [] );

	function saveStripe( e ) {
		e.preventDefault();
		setSavingStripe( true );
		setSavedStripe( false );
		apiClient
			.put( '/admin/settings/stripe', stripe )
			.then( () => setSavedStripe( true ) )
			.catch( ( err ) => setMessage( err.message ) )
			.finally( () => setSavingStripe( false ) );
	}

	function savePaypal( e ) {
		e.preventDefault();
		setSavingPaypal( true );
		setSavedPaypal( false );
		apiClient
			.put( '/admin/settings/paypal', paypal )
			.then( () => setSavedPaypal( true ) )
			.catch( ( err ) => setMessage( err.message ) )
			.finally( () => setSavingPaypal( false ) );
	}

	return (
		<div className="space-y-6">
			<PageHeader title="Payments" description="Accept card and PayPal payments for bookings." />

			{ message && <Card className="border-rose-200 bg-rose-50 text-sm text-rose-700">{ message }</Card> }

			<GatewayCard
				icon={ CreditCard }
				iconTone="bg-gradient-to-br from-indigo-500 to-violet-600"
				title="Stripe"
				subtitle="Accept card payments"
				enabled={ stripe.enabled }
				onToggle={ ( e ) => setStripe( { ...stripe, enabled: e.target.checked } ) }
				configured={ stripe.configured }
				onSubmit={ saveStripe }
				saving={ savingStripe }
				saved={ savedStripe }
			>
				<div className="grid grid-cols-1 gap-4 md:grid-cols-2">
					<Field label="Publishable key">
						<Input value={ stripe.publishable_key } onChange={ ( e ) => setStripe( { ...stripe, publishable_key: e.target.value } ) } />
					</Field>
					<Field label="Secret key" hint="Leave blank to keep the current key">
						<Input type="password" value={ stripe.secret_key } onChange={ ( e ) => setStripe( { ...stripe, secret_key: e.target.value } ) } />
					</Field>
				</div>
			</GatewayCard>

			<GatewayCard
				icon={ Wallet }
				iconTone="bg-gradient-to-br from-sky-500 to-blue-600"
				title="PayPal"
				subtitle="Accept PayPal payments"
				enabled={ paypal.enabled }
				onToggle={ ( e ) => setPaypal( { ...paypal, enabled: e.target.checked } ) }
				configured={ paypal.configured }
				onSubmit={ savePaypal }
				saving={ savingPaypal }
				saved={ savedPaypal }
			>
				<div className="grid grid-cols-1 gap-4 md:grid-cols-2">
					<Field label="Client ID">
						<Input value={ paypal.client_id } onChange={ ( e ) => setPaypal( { ...paypal, client_id: e.target.value } ) } />
					</Field>
					<Field label="Client secret" hint="Leave blank to keep the current secret">
						<Input type="password" value={ paypal.client_secret } onChange={ ( e ) => setPaypal( { ...paypal, client_secret: e.target.value } ) } />
					</Field>
				</div>
				<label className="flex cursor-pointer items-center gap-2 text-sm text-slate-600">
					<input
						type="checkbox"
						className="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-400"
						checked={ paypal.sandbox }
						onChange={ ( e ) => setPaypal( { ...paypal, sandbox: e.target.checked } ) }
					/>
					Sandbox / testing mode
				</label>
			</GatewayCard>
		</div>
	);
}
