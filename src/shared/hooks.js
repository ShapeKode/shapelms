/**
 * Shared hooks.
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { list, errorMessage } from './api';

/**
 * Loads a paginated list with search/filters.
 */
export function usePagedList( path, initialQuery = {} ) {
	const [ query, setQueryState ] = useState( { page: 1, per_page: 20, ...initialQuery } );
	const [ data, setData ] = useState( { items: [], total: 0, pages: 1 } );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const requestId = useRef( 0 );

	const load = useCallback( () => {
		const id = ++requestId.current;
		setLoading( true );
		setError( '' );
		list( path, query )
			.then( ( result ) => {
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
	}, [ path, query ] );

	useEffect( () => {
		load();
	}, [ load ] );

	const setQuery = useCallback( ( patch ) => {
		setQueryState( ( prev ) => ( { ...prev, page: 1, ...patch } ) );
	}, [] );

	const setPage = useCallback( ( page ) => {
		setQueryState( ( prev ) => ( { ...prev, page } ) );
	}, [] );

	return { ...data, query, setQuery, setPage, loading, error, reload: load };
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
