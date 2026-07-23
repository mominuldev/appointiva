/**
 * Appointiva booking widget — dependency-free vanilla JS.
 *
 * Hydrates every [data-appointiva-widget] container found on the page.
 * Two-step form: Service, date & time (step 1) → Contact details (step 2).
 */
( function () {
	'use strict';

	var cfg = window.AppointivaWidgetConfig || {};
	var i18n = cfg.i18n || {};
	// get_locale() returns WP-style underscore tags (e.g. "en_US"); the Intl/Date
	// APIs require BCP 47 hyphenated tags and throw a RangeError otherwise.
	var jsLocale = ( cfg.locale || '' ).replace( /_/g, '-' );

	function t( key, fallback ) {
		return i18n[ key ] || fallback || key;
	}

	function el( tag, attrs, children ) {
		var node = document.createElement( tag );
		attrs = attrs || {};
		Object.keys( attrs ).forEach( function ( key ) {
			var value = attrs[ key ];
			if ( key === 'text' ) {
				node.textContent = value;
			} else if ( key.indexOf( 'on' ) === 0 && typeof value === 'function' ) {
				node.addEventListener( key.slice( 2 ).toLowerCase(), value );
			} else if ( typeof value === 'boolean' ) {
				// Boolean HTML attributes (disabled, required, …) are "on" merely by being
				// present — setAttribute( key, false ) still renders disabled="false", which
				// the DOM treats as disabled. Only add the attribute when true.
				if ( value ) {
					node.setAttribute( key, key );
				}
			} else {
				node.setAttribute( key, value );
			}
		} );
		( children || [] ).forEach( function ( child ) {
			if ( child ) {
				node.appendChild( child );
			}
		} );
		return node;
	}

	function apiGet( path ) {
		return fetch( cfg.restUrl + path, { credentials: 'omit' } ).then( function ( res ) {
			if ( ! res.ok ) {
				throw new Error( 'request_failed' );
			}
			return res.json();
		} );
	}

	function apiPost( path, body ) {
		return fetch( cfg.restUrl + path, {
			method: 'POST',
			credentials: 'omit',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify( body ),
		} ).then( function ( res ) {
			return res.json().then( function ( json ) {
				if ( ! res.ok ) {
					var err = new Error( json.message || 'request_failed' );
					throw err;
				}
				return json;
			} );
		} );
	}

	function formatDateLabel( isoDate ) {
		try {
			return new Date( isoDate + 'T00:00:00' ).toLocaleDateString( jsLocale || undefined, {
				weekday: 'short',
				month: 'short',
				day: 'numeric',
			} );
		} catch ( e ) {
			return isoDate;
		}
	}

	function formatTimeLabel( isoDateTime ) {
		try {
			return new Date( isoDateTime ).toLocaleTimeString( jsLocale || undefined, {
				hour: 'numeric',
				minute: '2-digit',
			} );
		} catch ( e ) {
			return isoDateTime;
		}
	}

	function formatPrice( price ) {
		var amount = parseFloat( price );
		if ( ! amount ) {
			return '';
		}
		var formatted = amount.toFixed( 2 );
		var symbol = cfg.currencySymbol || '';
		return 'after' === cfg.currencyPosition ? formatted + symbol : symbol + formatted;
	}

	// SVG elements need their own namespace — document.createElement() would produce an
	// inert HTMLUnknownElement instead of a real, renderable <svg>/<circle>/<path>.
	function createSuccessIcon() {
		var svgNS = 'http://www.w3.org/2000/svg';

		var svg = document.createElementNS( svgNS, 'svg' );
		svg.setAttribute( 'class', 'appointiva-widget__success-icon' );
		svg.setAttribute( 'viewBox', '0 0 52 52' );
		svg.setAttribute( 'aria-hidden', 'true' );

		var circle = document.createElementNS( svgNS, 'circle' );
		circle.setAttribute( 'class', 'appointiva-widget__success-icon-circle' );
		circle.setAttribute( 'cx', '26' );
		circle.setAttribute( 'cy', '26' );
		circle.setAttribute( 'r', '24' );
		circle.setAttribute( 'fill', 'none' );

		var check = document.createElementNS( svgNS, 'path' );
		check.setAttribute( 'class', 'appointiva-widget__success-icon-check' );
		check.setAttribute( 'fill', 'none' );
		check.setAttribute( 'd', 'M14 27l7.2 7.2L38 17.6' );

		svg.appendChild( circle );
		svg.appendChild( check );
		return svg;
	}

	function Widget( root ) {
		this.root = root;
		this.presetServiceId = parseInt( root.getAttribute( 'data-service-id' ) || '0', 10 );
		this.state = {
			currentStep: 1,
			services: [],
			serviceId: this.presetServiceId || null,
			service: null,
			slotsByDate: {},
			availableDates: [],
			slotsLoading: false,
			selectedDate: null,
			selectedSlot: null,
			calendarMonth: new Date(),
			submitting: false,
		};
		this.init();
	}

	Widget.prototype.init = function () {
		this.root.innerHTML = '';
		this.root.setAttribute( 'dir', cfg.isRtl ? 'rtl' : 'ltr' );

		this.liveRegion = el( 'div', { class: 'appointiva-widget__status', role: 'status', 'aria-live': 'polite' } );
		this.progress = el( 'div', { class: 'appointiva-widget__progress' } );
		this.body = el( 'div', { class: 'appointiva-widget__body' } );
		this.footer = el( 'div', { class: 'appointiva-widget__footer' } );

		this.root.appendChild( this.liveRegion );
		this.root.appendChild( this.progress );
		this.root.appendChild( this.body );
		this.root.appendChild( this.footer );

		if ( this.presetServiceId ) {
			this.loadServiceAndSlots();
		} else {
			this.loadServices();
		}
	};

	Widget.prototype.announce = function ( message ) {
		this.liveRegion.textContent = message;
	};

	Widget.prototype.renderProgress = function () {
		this.progress.innerHTML = '';
		var self = this;
		var steps = [
			{ n: 1, label: t( 'stepBooking', 'Choose a time' ) },
			{ n: 2, label: t( 'stepDetails', 'Your details' ) },
		];

		steps.forEach( function ( step, index ) {
			var state = self.state.currentStep === step.n ? 'current' : ( self.state.currentStep > step.n ? 'done' : 'upcoming' );
			var stepEl = el( 'div', { class: 'appointiva-widget__progress-step appointiva-widget__progress-step--' + state }, [
				el( 'span', { class: 'appointiva-widget__progress-badge', text: 'done' === state ? '✓' : String( step.n ) } ),
				el( 'span', { class: 'appointiva-widget__progress-label', text: step.label } ),
			] );
			self.progress.appendChild( stepEl );

			if ( index < steps.length - 1 ) {
				self.progress.appendChild( el( 'div', { class: 'appointiva-widget__progress-line' + ( self.state.currentStep > step.n ? ' appointiva-widget__progress-line--done' : '' ) } ) );
			}
		} );
	};

	Widget.prototype.loadServices = function () {
		var self = this;
		this.body.innerHTML = '';
		this.body.appendChild( el( 'p', { text: t( 'loading', 'Loading…' ) } ) );

		apiGet( '/services' + ( cfg.locale ? '?lang=' + encodeURIComponent( cfg.locale ) : '' ) )
			.then( function ( services ) {
				self.state.services = services;
				self.renderStep1();
			} )
			.catch( function () {
				self.body.innerHTML = '';
				self.body.appendChild( el( 'p', { class: 'appointiva-widget__error', text: t( 'loadError', 'Something went wrong. Please try again.' ) } ) );
			} );
	};

	Widget.prototype.loadServiceAndSlots = function () {
		var self = this;
		this.body.innerHTML = '';
		this.body.appendChild( el( 'p', { text: t( 'loading', 'Loading…' ) } ) );

		// Load service details first
		apiGet( '/services' + ( cfg.locale ? '?lang=' + encodeURIComponent( cfg.locale ) : '' ) )
			.then( function ( services ) {
				self.state.services = services;
				var service = services.find( function ( s ) { return Number( s.id ) === self.presetServiceId; } );
				if ( service ) {
					self.state.serviceId = Number( service.id );
					self.state.service = service;
					return self.loadSlots();
				} else {
					throw new Error( 'Service not found' );
				}
			} )
			.catch( function () {
				self.body.innerHTML = '';
				self.body.appendChild( el( 'p', { class: 'appointiva-widget__error', text: t( 'loadError', 'Something went wrong. Please try again.' ) } ) );
			} );
	};

	Widget.prototype.loadSlots = function () {
		var self = this;

		// First time a preset-service widget loads slots, step 1's layout (calendar
		// + time slot containers) doesn't exist yet — build it before rendering into it.
		if ( ! this.calendarContainer ) {
			this.renderStep1();
		}

		this.state.slotsLoading = true;
		this.renderCalendar();

		var from = new Date();
		var to = new Date();
		to.setDate( to.getDate() + 14 );

		var fromStr = from.toISOString().slice( 0, 10 );
		var toStr = to.toISOString().slice( 0, 10 );

		return apiGet( '/slots?service_id=' + this.state.serviceId + '&from=' + fromStr + '&to=' + toStr )
			.then( function ( slots ) {
				self.state.slotsByDate = self.groupByDate( slots );
				self.state.availableDates = Object.keys( self.state.slotsByDate );
				self.state.slotsLoading = false;

				// Get service details if not already loaded
				if ( ! self.state.service && self.state.serviceId ) {
					self.state.service = self.state.services.find( function ( s ) { return Number( s.id ) === self.state.serviceId; } );
				}

				self.renderCalendar();
				self.renderTimeSlots();
				self.renderFooter();
			} )
			.catch( function () {
				self.state.slotsLoading = false;
				self.calendarContainer.innerHTML = '';
				self.calendarContainer.appendChild( el( 'p', { class: 'appointiva-widget__error', text: t( 'loadError', 'Something went wrong. Please try again.' ) } ) );
			} );
	};

	Widget.prototype.groupByDate = function ( slots ) {
		var grouped = {};
		slots.forEach( function ( slot ) {
			var date = slot.start.slice( 0, 10 );
			grouped[ date ] = grouped[ date ] || [];
			grouped[ date ].push( slot );
		} );
		return grouped;
	};

	// Step 1: Service, calendar, and (once a date is picked) its time slots.
	Widget.prototype.renderStep1 = function () {
		var self = this;
		this.state.currentStep = 1;
		this.body.innerHTML = '';

		// Service selector
		if ( ! this.presetServiceId && this.state.services.length > 0 ) {
			var label = el( 'label', { for: 'appointiva-service', text: t( 'selectService', 'Select a service' ) } );
			var select = el( 'select', { id: 'appointiva-service', name: 'service_id', required: 'required' } );

			select.appendChild( el( 'option', { value: '', text: t( 'selectServicePlaceholder', 'Please select a service' ) } ) );

			this.state.services.forEach( function ( service ) {
				var price = formatPrice( service.price );
				var text = service.name + ' — ' + service.duration_minutes + ' min' + ( price ? ' · ' + price : '' );
				var option = el( 'option', { value: service.id, text: text } );
				if ( Number( service.id ) === self.state.serviceId ) {
					option.setAttribute( 'selected', 'selected' );
				}
				select.appendChild( option );
			} );

			select.addEventListener( 'change', function () {
				self.state.serviceId = parseInt( select.value, 10 ) || null;
				self.state.service = self.state.services.find( function ( s ) { return Number( s.id ) === self.state.serviceId; } );
				self.state.selectedDate = null;
				self.state.selectedSlot = null;

				if ( self.state.serviceId ) {
					self.loadSlots();
				} else {
					self.state.slotsByDate = {};
					self.state.availableDates = [];
					self.renderCalendar();
					self.renderTimeSlots();
					self.renderFooter();
				}
			} );

			this.body.appendChild( el( 'div', { class: 'appointiva-widget__field' }, [ label, select ] ) );
		}

		var layout = el( 'div', { class: 'appointiva-widget__booking-layout' } );
		this.calendarContainer = el( 'div', { class: 'appointiva-widget__calendar-container' } );
		this.timeSlotContainer = el( 'div', { class: 'appointiva-widget__timeslots-container' } );
		layout.appendChild( this.calendarContainer );
		layout.appendChild( this.timeSlotContainer );
		this.body.appendChild( layout );

		this.renderCalendar();
		this.renderTimeSlots();
		this.renderProgress();
		this.renderFooter();
	};

	Widget.prototype.renderCalendar = function () {
		var self = this;
		this.calendarContainer.innerHTML = '';

		if ( ! this.state.serviceId ) {
			this.calendarContainer.appendChild( el( 'p', { class: 'appointiva-widget__text-muted', text: t( 'selectServiceFirst', 'Please select a service to see available dates.' ) } ) );
			return;
		}

		if ( this.state.slotsLoading ) {
			this.calendarContainer.appendChild( el( 'div', { class: 'appointiva-widget__skeleton', 'aria-hidden': 'true' } ) );
			return;
		}

		if ( this.state.availableDates.length === 0 ) {
			this.calendarContainer.appendChild( el( 'p', { class: 'appointiva-widget__text-muted', text: t( 'noAvailability', 'No availability in the next two weeks.' ) } ) );
			return;
		}

		var year = this.state.calendarMonth.getFullYear();
		var month = this.state.calendarMonth.getMonth();

		// Calendar header with month navigation
		var header = el( 'div', { class: 'appointiva-widget__calendar-header' } );

		var prevBtn = el( 'button', {
			type: 'button',
			class: 'appointiva-widget__calendar-nav',
			text: '‹',
			onClick: function () {
				self.state.calendarMonth.setMonth( self.state.calendarMonth.getMonth() - 1 );
				self.renderCalendar();
			}
		} );

		var monthLabel = el( 'div', { class: 'appointiva-widget__calendar-month' }, [
			el( 'strong', { text: this.state.calendarMonth.toLocaleDateString( jsLocale || undefined, { month: 'long', year: 'numeric' } ) } )
		] );

		var nextBtn = el( 'button', {
			type: 'button',
			class: 'appointiva-widget__calendar-nav',
			text: '›',
			onClick: function () {
				self.state.calendarMonth.setMonth( self.state.calendarMonth.getMonth() + 1 );
				self.renderCalendar();
			}
		} );

		header.appendChild( prevBtn );
		header.appendChild( monthLabel );
		header.appendChild( nextBtn );
		this.calendarContainer.appendChild( header );

		// Calendar grid
		var grid = el( 'div', { class: 'appointiva-widget__calendar-grid' } );

		// Day headers
		var dayNames = [ 'Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa' ];
		if ( jsLocale === 'en-GB' || jsLocale.indexOf( 'en-GB' ) === 0 ) {
			dayNames = [ 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su' ];
		}
		dayNames.forEach( function ( dayName ) {
			grid.appendChild( el( 'div', { class: 'appointiva-widget__calendar-day-header', text: dayName } ) );
		} );

		// Calculate first day and days in month
		var firstDay = new Date( year, month, 1 );
		var lastDay = new Date( year, month + 1, 0 );
		var startDay = firstDay.getDay();
		if ( jsLocale === 'en-GB' || jsLocale.indexOf( 'en-GB' ) === 0 ) {
			startDay = startDay === 0 ? 6 : startDay - 1; // Monday-first
		}

		// Empty cells before first day
		for ( var i = 0; i < startDay; i++ ) {
			grid.appendChild( el( 'div', { class: 'appointiva-widget__calendar-day-empty' } ) );
		}

		// Day cells
		for ( var day = 1; day <= lastDay.getDate(); day++ ) {
			var dateStr = year + '-' + String( month + 1 ).padStart( 2, '0' ) + '-' + String( day ).padStart( 2, '0' );
			var hasSlots = this.state.availableDates.indexOf( dateStr ) !== -1;
			var isSelected = this.state.selectedDate === dateStr;

			var dayCell = el( 'button', {
				type: 'button',
				class: 'appointiva-widget__calendar-day' + ( hasSlots ? ' appointiva-widget__calendar-day--available' : '' ) + ( isSelected ? ' appointiva-widget__calendar-day--selected' : '' ),
				text: String( day ),
				disabled: ! hasSlots,
				onClick: ( function ( date ) {
					return function () {
						self.state.selectedDate = date;
						self.state.selectedSlot = null;
						self.renderCalendar();
						self.renderTimeSlots();
						self.renderFooter();
					};
				} )( dateStr )
			} );

			grid.appendChild( dayCell );
		}

		this.calendarContainer.appendChild( grid );
	};

	// Renders the time slot panel below the calendar for the currently selected date.
	Widget.prototype.renderTimeSlots = function () {
		var self = this;
		this.timeSlotContainer.innerHTML = '';
		this.timeSlotContainer.classList.remove( 'is-visible' );

		if ( ! this.state.selectedDate || ! this.state.slotsByDate[ this.state.selectedDate ] ) {
			return;
		}

		var slots = this.state.slotsByDate[ this.state.selectedDate ];
		var isTimeslot = this.state.service && this.state.service.booking_mode === 'timeslot';

		this.timeSlotContainer.appendChild(
			el( 'div', { class: 'appointiva-widget__timeslots-heading' }, [
				el( 'span', { class: 'appointiva-widget__timeslots-label', text: t( 'selectTime', 'Select a time' ) } ),
				el( 'span', { class: 'appointiva-widget__timeslots-date', text: formatDateLabel( this.state.selectedDate ) } ),
			] )
		);

		if ( isTimeslot ) {
			if ( slots.length === 0 ) {
				this.timeSlotContainer.appendChild( el( 'p', { class: 'appointiva-widget__text-muted', text: t( 'noSlots', 'No available times in this range.' ) } ) );
			} else {
				var timeGrid = el( 'div', { class: 'appointiva-widget__time-grid' } );

				slots.forEach( function ( slot ) {
					var isSelected = self.state.selectedSlot && self.state.selectedSlot.start === slot.start;
					timeGrid.appendChild( el( 'button', {
						type: 'button',
						class: 'appointiva-widget__time-slot' + ( isSelected ? ' appointiva-widget__time-slot--selected' : '' ),
						text: formatTimeLabel( slot.start ),
						onClick: function () {
							self.state.selectedSlot = slot;
							self.renderTimeSlots();
							self.renderFooter();
						}
					} ) );
				} );

				this.timeSlotContainer.appendChild( timeGrid );
			}
		} else {
			// For day-based booking, auto-select the (single) day slot.
			if ( ! this.state.selectedSlot && slots.length > 0 ) {
				this.state.selectedSlot = slots[ 0 ];
			}
			this.timeSlotContainer.appendChild( el( 'p', { class: 'appointiva-widget__info', text: t( 'dayBookingSelected', 'Full day booking selected.' ) } ) );
		}

		// Deferred so the browser paints the panel first, letting the CSS
		// transition on `.is-visible` actually animate instead of snapping in.
		requestAnimationFrame( function () {
			self.timeSlotContainer.classList.add( 'is-visible' );
		} );
	};

	// Step 2: Contact form
	Widget.prototype.renderStep2 = function () {
		var self = this;
		this.state.currentStep = 2;
		this.body.innerHTML = '';

		// Booking summary
		var servicePrice = this.state.service ? formatPrice( this.state.service.price ) : '';
		var summary = el( 'div', { class: 'appointiva-widget__summary' });
		summary.appendChild( el( 'strong', { text: t( 'bookingSummary', 'Booking Summary' ) } ) );
		summary.appendChild( el( 'p', { text: this.state.service ? this.state.service.name + ( servicePrice ? ' — ' + servicePrice : '' ) : '' } ) );
		summary.appendChild( el( 'p', { text: formatDateLabel( this.state.selectedDate ) } ) );
		if ( this.state.selectedSlot ) {
			summary.appendChild( el( 'p', { text: formatTimeLabel( this.state.selectedSlot.start ) } ) );
		}
		this.body.appendChild( summary );

		// Contact form
		var firstName = el( 'input', { type: 'text', id: 'appointiva-first-name', name: 'first_name', required: 'required', autocomplete: 'given-name' } );
		var lastName = el( 'input', { type: 'text', id: 'appointiva-last-name', name: 'last_name', autocomplete: 'family-name' } );
		var email = el( 'input', { type: 'email', id: 'appointiva-email', name: 'email', required: 'required', autocomplete: 'email' } );
		var phone = el( 'input', { type: 'tel', id: 'appointiva-phone', name: 'phone', autocomplete: 'tel' } );
		var notes = el( 'textarea', { id: 'appointiva-notes', name: 'notes', rows: '3' } );
		var consent = el( 'input', { type: 'checkbox', id: 'appointiva-consent', name: 'gdpr_consent', required: 'required' } );
		var honeypot = el( 'input', { type: 'text', name: 'website', tabindex: '-1', autocomplete: 'off', class: 'appointiva-widget__honeypot', 'aria-hidden': 'true' } );

		function field( labelText, input ) {
			return el( 'div', { class: 'appointiva-widget__field' }, [ el( 'label', { for: input.id, text: labelText } ), input ] );
		}

		var form = el(
			'form',
			{
				class: 'appointiva-widget__form',
				onSubmit: function ( e ) {
					e.preventDefault();
					self.submitBooking( { firstName: firstName, lastName: lastName, email: email, phone: phone, notes: notes, consent: consent, honeypot: honeypot } );
				},
			},
			[
				field( t( 'firstName', 'First name' ), firstName ),
				field( t( 'lastName', 'Last name' ), lastName ),
				field( t( 'email', 'Email' ), email ),
				field( t( 'phone', 'Phone' ), phone ),
				field( t( 'notes', 'Notes' ), notes ),
				el( 'div', { class: 'appointiva-widget__field appointiva-widget__field--checkbox' }, [
					consent,
					el( 'label', { for: 'appointiva-consent', text: t( 'consent', 'I agree to the storage of my details to process this booking.' ) } ),
				] ),
				honeypot,
			]
		);

		this.body.appendChild( form );
		this.renderProgress();
		this.renderFooter();
	};

	// Footer navigation
	Widget.prototype.renderFooter = function () {
		var self = this;
		this.footer.innerHTML = '';

		var canGoBack = this.state.currentStep > 1;
		var canContinue = false;

		if ( this.state.currentStep === 1 ) {
			var isTimeslot = this.state.service && this.state.service.booking_mode === 'timeslot';
			canContinue = !! ( this.state.serviceId && this.state.selectedDate && ( isTimeslot ? this.state.selectedSlot : true ) );
		} else if ( this.state.currentStep === 2 ) {
			canContinue = ! this.state.submitting;
		}

		var backBtn = el( 'button', {
			type: 'button',
			class: 'appointiva-widget__btn appointiva-widget__btn--secondary',
			text: t( 'back', 'Back' ),
			disabled: ! canGoBack,
			onClick: function () {
				if ( self.state.currentStep === 2 ) {
					self.renderStep1();
				}
			}
		} );

		var continueBtn = el( 'button', {
			type: 'button',
			class: 'appointiva-widget__btn appointiva-widget__btn--primary',
			text: this.state.currentStep === 2 ? ( this.state.submitting ? t( 'submitting', 'Booking...' ) : t( 'submit', 'Book appointment' ) ) : t( 'continue', 'Continue' ),
			disabled: ! canContinue,
			onClick: function () {
				if ( self.state.currentStep === 1 ) {
					self.renderStep2();
				} else if ( self.state.currentStep === 2 ) {
					// Trigger form submit
					var form = self.body.querySelector( '.appointiva-widget__form' );
					if ( form ) {
						form.dispatchEvent( new Event( 'submit', { bubbles: true, cancelable: true } ) );
					}
				}
			}
		} );

		var footerInner = el( 'div', { class: 'appointiva-widget__footer-inner' } );
		footerInner.appendChild( backBtn );
		footerInner.appendChild( continueBtn );
		this.footer.appendChild( footerInner );
	};

	Widget.prototype.submitBooking = function ( fields ) {
		var self = this;

		if ( ! this.state.selectedSlot ) {
			this.announce( t( 'selectTime', 'Select a time' ) );
			return;
		}

		this.state.submitting = true;
		this.renderFooter(); // Disables Continue and swaps its label to "Booking...".

		apiPost( '/bookings', {
			service_id: this.state.serviceId,
			starts_at: this.state.selectedSlot.start,
			first_name: fields.firstName.value,
			last_name: fields.lastName.value,
			email: fields.email.value,
			phone: fields.phone.value,
			notes: fields.notes.value,
			gdpr_consent: fields.consent.checked,
			website: fields.honeypot.value,
		} )
			.then( function () {
				self.progress.innerHTML = '';
				self.body.innerHTML = '';
				self.footer.innerHTML = '';

				var successEl = el( 'div', { class: 'appointiva-widget__success' } );
				successEl.appendChild( createSuccessIcon() );
				successEl.appendChild( el( 'p', { class: 'appointiva-widget__success-text', text: t( 'success', 'Your booking is confirmed. Check your email for details.' ) } ) );
				self.body.appendChild( successEl );

				self.announce( t( 'success', 'Your booking is confirmed. Check your email for details.' ) );
			} )
			.catch( function ( err ) {
				self.state.submitting = false;
				self.renderFooter();
				self.announce( err.message || t( 'loadError', 'Something went wrong. Please try again.' ) );
			} );
	};

	// Idempotent: a container is only ever hydrated once, so re-scanning the
	// page (or the same subtree) is always safe.
	function mount( root ) {
		if ( ! root || root.appointivaMounted ) {
			return;
		}
		root.appointivaMounted = true;
		new Widget( root );
	}

	function boot( context ) {
		var scope = context && context.querySelectorAll ? context : document;
		var roots = scope.querySelectorAll( '[data-appointiva-widget]' );
		Array.prototype.forEach.call( roots, mount );
	}

	// Public API. Free still auto-boots every container on DOMContentLoaded
	// exactly as before; this additionally lets containers injected *after*
	// initial load — AJAX, popups, or a page builder's live editor (e.g.
	// Appointiva Pro's Elementor widget) — be hydrated on demand. boot() accepts
	// an optional context element to limit the scan to one subtree.
	window.Appointiva = window.Appointiva || {};
	window.Appointiva.boot = boot;
	window.Appointiva.mount = mount;

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			boot();
		} );
	} else {
		boot();
	}
} )();
