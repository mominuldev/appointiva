import * as React from 'react';
import * as ReactDOM from 'react-dom';
import { createRoot } from 'react-dom/client';
import { appointivaAdminRegistry } from './slot-fill/registry';
import { App } from './App';
import './index.css';

// Expose the single React/ReactDOM instance this bundle carries so Pro (and
// any other separately-built extension bundle) resolves against THIS copy
// instead of bundling its own — two independent React copies rendering into
// one tree breaks hooks in ways that are miserable to debug. This mirrors
// how WordPress's block editor shares one React instance via `wp.element`
// for every extension, rather than each plugin bundling its own.
appointivaAdminRegistry.React = React;
appointivaAdminRegistry.ReactDOM = ReactDOM;

const container = document.getElementById( 'appointiva-admin-root' );

if ( container ) {
	createRoot( container ).render( <App /> );
}
