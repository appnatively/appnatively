import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import App from './App';

// import './app.scss';

domReady( () => {
	const container = document.querySelector(
		'.appnatively-root'
	) as HTMLElement | null;


	if ( ! container ) return;

	const root = createRoot( container );
	root.render( <App /> );
} );