import { useEffect, useState } from 'react';
import { Loader2, ImagePlus, X } from 'lucide-react';

/**
 * Small, shared UI primitives used across every admin screen. Keeping these
 * in one place is what makes the eight settings screens look like one
 * product instead of eight separately-styled forms.
 */

export function PageHeader( { eyebrow, title, description, action } ) {
	return (
		<div className="mb-8 flex flex-wrap items-start justify-between gap-4">
			<div>
				{ eyebrow && (
					<p className="mb-1 text-xs font-semibold uppercase tracking-wider text-brand-600">{ eyebrow }</p>
				) }
				<h1 className="text-2xl font-semibold tracking-tight text-slate-900">{ title }</h1>
				{ description && <p className="mt-1.5 max-w-2xl text-sm text-slate-500">{ description }</p> }
			</div>
			{ action && <div className="shrink-0">{ action }</div> }
		</div>
	);
}

export function Card( { children, className = '', padded = true } ) {
	return (
		<div className={ `rounded-2xl border border-slate-200/80 bg-white shadow-soft ${ padded ? 'p-6' : '' } ${ className }` }>
			{ children }
		</div>
	);
}

export function SectionTitle( { title, description, className = '' } ) {
	return (
		<div className={ `mb-5 ${ className }` }>
			<h2 className="text-base font-semibold text-slate-900">{ title }</h2>
			{ description && <p className="mt-1 text-sm text-slate-500">{ description }</p> }
		</div>
	);
}

const buttonVariants = {
	primary:
		'bg-brand-gradient text-white shadow-soft hover:brightness-110 active:brightness-95 disabled:opacity-60 disabled:cursor-not-allowed',
	secondary:
		'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50 active:bg-slate-100 disabled:opacity-60 disabled:cursor-not-allowed',
	ghost: 'text-slate-500 hover:bg-slate-100 hover:text-slate-800',
	danger: 'bg-rose-600 text-white shadow-soft hover:bg-rose-700 active:bg-rose-800 disabled:opacity-60 disabled:cursor-not-allowed',
	success: 'bg-emerald-600 text-white shadow-soft hover:bg-emerald-700 active:bg-emerald-800 disabled:opacity-60 disabled:cursor-not-allowed',
	warning: 'bg-amber-500 text-white shadow-soft hover:bg-amber-600 active:bg-amber-700 disabled:opacity-60 disabled:cursor-not-allowed',
};

const buttonSizes = {
	sm: 'px-3 py-1.5 text-xs gap-1.5',
	md: 'px-4 py-2.5 text-sm gap-2',
};

export function Button( { variant = 'primary', size = 'md', icon: Icon, loading = false, className = '', children, ...props } ) {
	return (
		<button
			className={ `inline-flex items-center justify-center rounded-lg font-medium transition-all duration-150 ${ buttonVariants[ variant ] } ${ buttonSizes[ size ] } ${ className }` }
			disabled={ loading || props.disabled }
			{ ...props }
		>
			{ loading ? <Loader2 className="h-4 w-4 animate-spin" /> : Icon && <Icon className="h-4 w-4" strokeWidth={ 2.25 } /> }
			{ children }
		</button>
	);
}

export function IconButton( { icon: Icon, tone = 'slate', className = '', ...props } ) {
	const tones = {
		slate: 'text-slate-400 hover:bg-slate-100 hover:text-slate-700',
		brand: 'text-slate-400 hover:bg-brand-50 hover:text-brand-600',
		danger: 'text-slate-400 hover:bg-rose-50 hover:text-rose-600',
	};

	return (
		<button
			className={ `inline-flex h-8 w-8 items-center justify-center rounded-lg transition-colors duration-150 disabled:opacity-40 disabled:pointer-events-none ${ tones[ tone ] } ${ className }` }
			{ ...props }
		>
			<Icon className="h-4 w-4" strokeWidth={ 2.25 } />
		</button>
	);
}

export function Field( { label, hint, children, className = '', horizontal = false } ) {
	return (
		<label className={ `block ${ horizontal ? 'flex items-center gap-3' : 'space-y-1.5' } ${ className }` }>
			{ label && <span className="text-sm font-medium text-slate-700">{ label }</span> }
			{ children }
			{ hint && <span className="block text-xs text-slate-400">{ hint }</span> }
		</label>
	);
}

const inputBase =
	'w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 shadow-sm transition-shadow duration-150 focus:border-brand-400 focus:ring-4 focus:ring-brand-100 disabled:bg-slate-50 disabled:text-slate-400';

export function Input( { className = '', ...props } ) {
	return <input className={ `${ inputBase } ${ className }` } { ...props } />;
}

export function Select( { className = '', children, ...props } ) {
	return (
		<select className={ `${ inputBase } appearance-none bg-[url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="%2394a3b8"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>')] bg-[length:1.1rem] bg-[right_0.6rem_center] bg-no-repeat pr-9 ${ className }` } { ...props }>
			{ children }
		</select>
	);
}

export function Textarea( { className = '', ...props } ) {
	return <textarea className={ `${ inputBase } resize-y ${ className }` } { ...props } />;
}

export function MediaPicker( { value, onChange, buttonLabel = 'Select Image', className = '' } ) {
	const [ previewUrl, setPreviewUrl ] = useState( null );

	useEffect( () => {
		if ( ! value || ! window.wp?.media ) {
			setPreviewUrl( null );
			return;
		}

		const attachment = window.wp.media.attachment( value );
		attachment.fetch().then( () => setPreviewUrl( attachment.get( 'url' ) ) );
	}, [ value ] );

	function openPicker() {
		if ( ! window.wp?.media ) {
			return;
		}

		const frame = window.wp.media( { title: buttonLabel, multiple: false, library: { type: 'image' } } );

		frame.on( 'select', () => {
			const attachment = frame.state().get( 'selection' ).first().toJSON();
			setPreviewUrl( attachment.url );
			onChange( attachment.id );
		} );

		frame.open();
	}

	return (
		<div className={ `flex items-center gap-4 ${ className }` }>
			{ previewUrl ? (
				<img src={ previewUrl } alt="" className="h-16 w-16 rounded-lg border border-slate-200 object-cover" />
			) : (
				<div className="flex h-16 w-16 items-center justify-center rounded-lg border border-dashed border-slate-200 bg-slate-50 text-slate-300">
					<ImagePlus className="h-6 w-6" strokeWidth={ 1.5 } />
				</div>
			) }
			<div className="flex items-center gap-2">
				<Button type="button" variant="secondary" size="sm" onClick={ openPicker }>
					{ previewUrl ? 'Change image' : buttonLabel }
				</Button>
				{ previewUrl && (
					<IconButton icon={ X } tone="danger" onClick={ () => { setPreviewUrl( null ); onChange( 0 ); } } aria-label="Remove image" />
				) }
			</div>
		</div>
	);
}

export function Checkbox( { label, description, className = '', ...props } ) {
	return (
		<label className={ `flex cursor-pointer items-start gap-3 ${ className }` }>
			<span className="relative mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center">
				<input type="checkbox" className="peer sr-only" { ...props } />
				<span className="absolute inset-0 rounded-md border-2 border-slate-300 bg-white transition-colors peer-checked:border-brand-600 peer-checked:bg-brand-600 peer-focus-visible:ring-4 peer-focus-visible:ring-brand-100" />
				<svg className="relative hidden h-3 w-3 text-white peer-checked:block" viewBox="0 0 12 12" fill="none">
					<path d="M2 6.2 4.8 9 10 3" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
				</svg>
			</span>
			{ ( label || description ) && (
				<span>
					{ label && <span className="block text-sm font-medium text-slate-700">{ label }</span> }
					{ description && <span className="block text-xs text-slate-500">{ description }</span> }
				</span>
			) }
		</label>
	);
}

export function Radio( { label, description, className = '', ...props } ) {
	return (
		<label className={ `flex cursor-pointer items-start gap-3 ${ className }` }>
			<span className="relative mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center">
				<input type="radio" className="peer sr-only" { ...props } />
				<span className="absolute inset-0 rounded-full border-2 border-slate-300 bg-white transition-colors peer-checked:border-brand-600 peer-focus-visible:ring-4 peer-focus-visible:ring-brand-100" />
				<span className="relative hidden h-2.5 w-2.5 rounded-full bg-brand-600 peer-checked:block" />
			</span>
			{ ( label || description ) && (
				<span>
					{ label && <span className="block text-sm font-medium text-slate-700">{ label }</span> }
					{ description && <span className="block text-xs text-slate-500">{ description }</span> }
				</span>
			) }
		</label>
	);
}

export function Switch( { checked, onChange, label, className = '' } ) {
	return (
		<label className={ `inline-flex cursor-pointer items-center gap-2.5 ${ className }` }>
			<span className="relative inline-flex h-6 w-11 shrink-0 items-center">
				<input type="checkbox" className="peer sr-only" checked={ checked } onChange={ onChange } />
				<span className="absolute inset-0 rounded-full bg-slate-200 transition-colors duration-150 peer-checked:bg-brand-gradient peer-focus-visible:ring-4 peer-focus-visible:ring-brand-100" />
				<span className="relative h-4.5 w-4.5 translate-x-1 rounded-full bg-white shadow transition-transform duration-150 peer-checked:translate-x-6" style={ { height: '1.125rem', width: '1.125rem' } } />
			</span>
			{ label && <span className="text-sm font-medium text-slate-700">{ label }</span> }
		</label>
	);
}

const badgeTones = {
	neutral: 'bg-slate-100 text-slate-600',
	brand: 'bg-brand-50 text-brand-700',
	success: 'bg-emerald-50 text-emerald-700',
	warning: 'bg-amber-50 text-amber-700',
	danger: 'bg-rose-50 text-rose-700',
	info: 'bg-sky-50 text-sky-700',
};

export function Badge( { tone = 'neutral', dot = false, children, className = '' } ) {
	return (
		<span className={ `inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium capitalize ${ badgeTones[ tone ] } ${ className }` }>
			{ dot && <span className="h-1.5 w-1.5 rounded-full bg-current" /> }
			{ children }
		</span>
	);
}

export function EmptyState( { icon: Icon, title, description, action } ) {
	return (
		<div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 px-6 py-14 text-center">
			{ Icon && (
				<div className="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-white text-brand-600 shadow-soft">
					<Icon className="h-6 w-6" strokeWidth={ 1.75 } />
				</div>
			) }
			<p className="text-sm font-semibold text-slate-800">{ title }</p>
			{ description && <p className="mt-1 max-w-sm text-sm text-slate-500">{ description }</p> }
			{ action && <div className="mt-5">{ action }</div> }
		</div>
	);
}

export function Skeleton( { className = '' } ) {
	return <div className={ `animate-pulse rounded-lg bg-slate-100 ${ className }` } />;
}

export function Modal( { open, onClose, title, description, children, footer } ) {
	if ( ! open ) {
		return null;
	}

	return (
		<div className="fixed inset-0 z-50 flex items-center justify-center p-4">
			<div className="absolute inset-0 bg-slate-900/40" onClick={ onClose } />
			<div className="appointiva-animate-in relative w-full max-w-md rounded-2xl border border-slate-200/80 bg-white p-6 shadow-popover">
				<div className="mb-5 flex items-start justify-between gap-4">
					<div>
						<h2 className="text-lg font-semibold text-slate-900">{ title }</h2>
						{ description && <p className="mt-1 text-sm text-slate-500">{ description }</p> }
					</div>
					<IconButton icon={ X } onClick={ onClose } aria-label="Close" />
				</div>
				<div className="space-y-4">{ children }</div>
				{ footer && <div className="mt-6 flex items-center justify-end gap-2 border-t border-slate-100 pt-5">{ footer }</div> }
			</div>
		</div>
	);
}

export function Toast( { message, tone = 'success' } ) {
	if ( ! message ) {
		return null;
	}

	const tones = {
		success: 'bg-emerald-600',
		error: 'bg-rose-600',
	};

	return (
		<div className={ `appointiva-animate-in fixed bottom-6 right-6 z-50 rounded-xl px-4 py-3 text-sm font-medium text-white shadow-popover ${ tones[ tone ] }` }>
			{ message }
		</div>
	);
}
