/**
 * TinyMCE (classic editor) wrapped as a controlled-ish component.
 * Requires wp_enqueue_editor() on the screen.
 */
import { useEffect, useRef } from '@wordpress/element';

let counter = 0;

export default function ClassicEditor( { value, onChange, height = 260 } ) {
	const id = useRef( `shapelms-editor-${ ++counter }` );
	const latest = useRef( value );

	useEffect( () => {
		const editorId = id.current;
		if ( ! window.wp || ! window.wp.oldEditor ) {
			return undefined;
		}

		window.wp.oldEditor.initialize( editorId, {
			tinymce: {
				wpautop: true,
				height,
				toolbar1: 'formatselect,bold,italic,bullist,numlist,blockquote,link,unlink,wp_more,undo,redo',
				setup( editor ) {
					editor.on( 'change keyup paste input', () => {
						const content = editor.getContent();
						if ( content !== latest.current ) {
							latest.current = content;
							onChange( content );
						}
					} );
				},
			},
			quicktags: true,
			mediaButtons: true,
		} );

		return () => {
			window.wp.oldEditor.remove( editorId );
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	useEffect( () => {
		if ( value === latest.current ) {
			return;
		}
		latest.current = value;
		const editor = window.tinymce && window.tinymce.get( id.current );
		if ( editor ) {
			editor.setContent( value || '' );
		} else {
			const textarea = document.getElementById( id.current );
			if ( textarea ) {
				textarea.value = value || '';
			}
		}
	}, [ value ] );

	return (
		<div className="shapelms-classic-editor">
			<textarea id={ id.current } defaultValue={ value || '' } rows={ 10 } className="wp-editor-area" />
		</div>
	);
}
