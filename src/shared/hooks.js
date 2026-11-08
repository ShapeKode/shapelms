/**
 * Shared hooks.
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { list, errorMessage } from './api';
import { config } from './config';

function sameQuery( a, b ) {
	const keys = new Set( [ ...Object.keys( a ), ...Object.keys( b ) ] );
	for ( const key of keys ) {
		if ( String( a[ key ] ?? '' ) !== String( b[ key ] ?? '' ) ) {
			return false;
		}
	}
	return true;
}

/**
 * Finds an unused preloaded response for this request and marks it used.
 */
function claimPreload( path, query ) {
	const entry = ( config().preload || [] ).find( ( p ) => p && ! p.used && p.path === path && sameQuery( p.query || {}, query ) );
	if ( ! entry ) {
		return null;
	}
	entry.used = true;
	return entry;
}

/**
 * Returns the raw server-preloaded response for a plain GET (once), or null.
 * Screens use it as their initial state so data shows on first paint.
 */
export function takePreloadedData( path, query = {} ) {
	const entry = claimPreload( path, query );
	return entry ? entry.data ?? null : null;
}

/**
 * Returns the server-preloaded first page for this list (once), or null.
 */
function takePreload( path, query ) {
	const preload = claimPreload( path, query );
	if ( ! preload ) {
		return null;
	}
	const json = preload.data;
	if ( Array.isArray( json ) ) {
		return { items: json, total: json.length, pages: 1 };
	}
	if ( ! json || ! Array.isArray( json.items ) ) {
		return null;
	}
	return { items: json.items, total: parseInt( json.total, 10 ) || 0, pages: parseInt( json.pages, 10 ) || 1, ...( json.counts ? { counts: json.counts } : {} ) };
}

const queryKey = ( query ) => JSON.stringify( Object.keys( query ).sort().map( ( key ) => [ key, String( query[ key ] ?? '' ) ] ) );

/**
 * Loads a paginated list with search/filters.
 *
 * While a request runs the previous rows stay on screen. Pages already seen
 * for a query come back from memory at once and are refreshed in the
 * background. `options.preview( items, query )` may filter/sort the rows
 * already loaded so a new filter shows something before the server answers.
 */
export function usePagedList( path, initialQuery = {}, options = {} ) {
	// Rows per page come from Settings > General > Pagination (matches the server preload).
	const [ query, setQueryState ] = useState( () => ( { page: 1, per_page: config().perPage || 20, ...initialQuery } ) );
	const preloaded = useRef( undefined );
	const cache = useRef( new Map() );
	if ( preloaded.current === undefined ) {
		preloaded.current = takePreload( path, query );
		if ( preloaded.current ) {
			cache.current.set( queryKey( query ), preloaded.current );
		}
	}
	const skipFirstLoad = useRef( !! preloaded.current );
	const [ data, setData ] = useState( () => preloaded.current || { items: [], total: 0, pages: 1 } );
	const [ loading, setLoading ] = useState( ! preloaded.current );
	const [ error, setError ] = useState( '' );
	const requestId = useRef( 0 );
	const previewRef = useRef( options.preview );
	previewRef.current = options.preview;

	const showInstantly = ( key ) => {
		const cached = cache.current.get( key );
		if ( cached ) {
			setData( cached );
			return;
		}
		if ( ! previewRef.current ) {
			return;
		}
		setData( ( prev ) => {
			// Every row seen so far, newest copy wins.
			const pool = new Map();
			[ ...cache.current.values(), prev ].forEach( ( result ) => result.items.forEach( ( item ) => pool.set( item.id, item ) ) );
			const rows = previewRef.current( [ ...pool.values() ], query );
			// Nothing matched locally: keep the old rows rather than flash "empty".
			return rows && rows.length ? { ...prev, items: rows.slice( 0, query.per_page || 20 ) } : prev;
		} );
	};

	const fetchList = useCallback( ( force ) => {
		const id = ++requestId.current;
		const key = queryKey( query );
		if ( force ) {
			// Something changed on the server; other cached filters are stale too.
			cache.current.clear();
		} else {
			showInstantly( key );
		}
		setLoading( true );
		setError( '' );
		list( path, query )
			.then( ( result ) => {
				cache.current.set( key, result );
				if ( id === requestId.current ) {
					setData( result );
					setLoading( false );
				}
			} )
			.catch( ( err ) => {
				if ( id === requestId.current ) {
					setError( errorMessage( err ) );
					setLoading( false );
				}
			} );
		// showInstantly only reads refs and the current query.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ path, query ] );

	const load = useCallback( () => fetchList( false ), [ fetchList ] );
	const reload = useCallback( () => fetchList( true ), [ fetchList ] );

	useEffect( () => {
		// The first page came from the server with the page; no request needed.
		if ( skipFirstLoad.current ) {
			skipFirstLoad.current = false;
			return;
		}
		load();
	}, [ load ] );

	const setQuery = useCallback( ( patch ) => {
		setQueryState( ( prev ) => ( { ...prev, page: 1, ...patch } ) );
	}, [] );

	const setPage = useCallback( ( page ) => {
		setQueryState( ( prev ) => ( { ...prev, page } ) );
	}, [] );

	const patchItem = useCallback( ( id, changes ) => {
		// Cached pages may hold this row under other filters; drop them.
		cache.current.clear();
		setData( ( prev ) => ( { ...prev, items: prev.items.map( ( item ) => ( item.id === id ? { ...item, ...changes } : item ) ) } ) );
	}, [] );

	return { ...data, query, setQuery, setPage, loading, error, reload, patchItem };
}

/**
 * Debounces a value.
 */
export function useDebounce( value, delay = 350 ) {
	const [ debounced, setDebounced ] = useState( value );
	useEffect( () => {
		const timer = setTimeout( () => setDebounced( value ), delay );
		return () => clearTimeout( timer );
	}, [ value, delay ] );
	return debounced;
}
