/**
 * ShapeLMS front-end script.
 *
 * Vanilla JS, no dependencies. Configuration is read from data attributes on
 * the nearest [data-rest-url] root; nothing is inlined in the page.
 *
 * @package ShapeLMS
 */
( function () {
	'use strict';

	var __ = window.wp && window.wp.i18n ? window.wp.i18n.__ : function ( s ) {
		return s;
	};
	var sprintf = window.wp && window.wp.i18n ? window.wp.i18n.sprintf : function ( s ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return s.replace( /%(\d+\$)?[sd]/g, function () {
			return args[ i++ ];
		} );
	};

	/* ------------------------------------------------------------------ */
	/* Helpers                                                             */
	/* ------------------------------------------------------------------ */

	function config( el ) {
		var root = el && el.closest ? el.closest( '[data-rest-url]' ) : null;
		root = root || document.querySelector( '[data-rest-url]' );
		if ( ! root ) {
			return {};
		}
		var data = {};
		Array.prototype.forEach.call( root.attributes, function ( attr ) {
			if ( attr.name.indexOf( 'data-' ) === 0 ) {
				data[ attr.name.slice( 5 ).replace( /-([a-z])/g, function ( m, c ) {
					return c.toUpperCase();
				} ) ] = attr.value;
			}
		} );
		return data;
	}

	function api( el, path, options ) {
		var cfg = config( el );
		options = options || {};
		var headers = { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.restNonce || '' };
		var init = { method: options.method || 'GET', headers: headers, credentials: 'same-origin' };
		if ( options.body ) {
			init.body = JSON.stringify( options.body );
		}
		return fetch( ( cfg.restUrl || '' ) + path, init ).then( function ( response ) {
			return response.json().then( function ( json ) {
				if ( ! response.ok ) {
					var err = new Error( json && json.message ? json.message : __( 'Something went wrong.', 'shapelms' ) );
					err.code = json && json.code;
					err.status = response.status;
					throw err;
				}
				return json;
			} );
		} );
	}

	function el( tag, attrs, children ) {
		var node = document.createElement( tag );
		Object.keys( attrs || {} ).forEach( function ( key ) {
			if ( key === 'class' ) {
				node.className = attrs[ key ];
			} else if ( key === 'text' ) {
				node.textContent = attrs[ key ];
			} else if ( key === 'html' ) {
				node.innerHTML = attrs[ key ];
			} else if ( key.indexOf( 'on' ) === 0 ) {
				node.addEventListener( key.slice( 2 ), attrs[ key ] );
			} else if ( attrs[ key ] !== null && attrs[ key ] !== false ) {
				node.setAttribute( key, attrs[ key ] );
			}
		} );
		( children || [] ).forEach( function ( child ) {
			if ( child ) {
				node.appendChild( typeof child === 'string' ? document.createTextNode( child ) : child );
			}
		} );
		return node;
	}

	function message( node, text, type ) {
		if ( ! node ) {
			return;
		}
		node.textContent = text || '';
		node.className = node.className.replace( /\bis-(error|success)\b/g, '' ).trim();
		if ( type ) {
			node.classList.add( 'is-' + type );
		}
	}

	function formatTime( seconds ) {
		var m = Math.floor( seconds / 60 );
		var s = seconds % 60;
		return ( m < 10 ? '0' : '' ) + m + ':' + ( s < 10 ? '0' : '' ) + s;
	}

	/* ------------------------------------------------------------------ */
	/* Progress bars                                                       */
	/* ------------------------------------------------------------------ */

	function initProgress( scope ) {
		( scope || document ).querySelectorAll( '.shapelms-progress__bar[data-percent]' ).forEach( function ( bar ) {
			bar.style.width = Math.max( 0, Math.min( 100, parseInt( bar.getAttribute( 'data-percent' ), 10 ) || 0 ) ) + '%';
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Enroll (free)                                                       */
	/* ------------------------------------------------------------------ */

	function initEnroll() {
		document.querySelectorAll( '[data-shapelms-enroll]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var courseId = parseInt( button.getAttribute( 'data-shapelms-enroll' ), 10 );
				var box = button.closest( '.shapelms-enroll-box' ) || button.parentNode;
				var msg = box.querySelector( '.shapelms-enroll-box__message' );
				var cfg = config( button );

				if ( cfg.loggedIn !== '1' ) {
					window.location.href = cfg.loginUrl;
					return;
				}

				button.disabled = true;
				button.classList.add( 'is-loading' );
				message( msg, '' );

				api( button, 'enrollments', { method: 'POST', body: { course_id: courseId } } )
					.then( function ( res ) {
						message( msg, __( 'You are enrolled! Redirecting…', 'shapelms' ), 'success' );
						window.location.href = res.redirect || window.location.href;
					} )
					.catch( function ( err ) {
						button.disabled = false;
						button.classList.remove( 'is-loading' );
						message( msg, err.message, 'error' );
					} );
			} );
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Learn page                                                          */
	/* ------------------------------------------------------------------ */

	function initLearn() {
		var root = document.getElementById( 'shapelms-learn' );
		if ( ! root ) {
			return;
		}

		var toggle = root.querySelector( '[data-shapelms-toggle-sidebar]' );
		if ( toggle ) {
			var stored = null;
			try {
				stored = window.localStorage.getItem( 'shapelms-sidebar' );
			} catch ( e ) {
				stored = null;
			}
			if ( stored === 'closed' || ( stored === null && window.innerWidth < 960 ) ) {
				root.classList.add( 'is-sidebar-closed' );
				toggle.setAttribute( 'aria-expanded', 'false' );
			}
			toggle.addEventListener( 'click', function () {
				var closed = root.classList.toggle( 'is-sidebar-closed' );
				toggle.setAttribute( 'aria-expanded', closed ? 'false' : 'true' );
				try {
					window.localStorage.setItem( 'shapelms-sidebar', closed ? 'closed' : 'open' );
				} catch ( e ) {
					// Ignore.
				}
			} );
		}

		var current = root.querySelector( '.shapelms-learn__item.is-current' );
		if ( current && current.scrollIntoView ) {
			current.scrollIntoView( { block: 'center' } );
		}

		root.querySelectorAll( '[data-shapelms-complete]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var lessonId = parseInt( button.getAttribute( 'data-shapelms-complete' ), 10 );
				button.disabled = true;
				button.classList.add( 'is-loading' );

				api( root, 'progress/complete', { method: 'POST', body: { lesson_id: lessonId } } )
					.then( function ( res ) {
						button.classList.remove( 'is-loading' );
						button.classList.add( 'is-done' );
						button.textContent = __( 'Completed', 'shapelms' );
						updateProgressUi( root, res, lessonId );

						if ( res.completed ) {
							window.location.href = res.course_url;
							return;
						}
						if ( root.getAttribute( 'data-autoplay' ) === '1' && res.next_url ) {
							window.location.href = res.next_url;
						}
					} )
					.catch( function ( err ) {
						button.disabled = false;
						button.classList.remove( 'is-loading' );
						window.alert( err.message );
					} );
			} );
		} );

		document.addEventListener( 'keydown', function ( e ) {
			if ( e.target && ( e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable ) ) {
				return;
			}
			var nextLink = root.querySelector( '.shapelms-learn__footer a[href]:last-child' );
			var prevLink = root.querySelector( '.shapelms-learn__footer a[href]:first-child' );
			if ( e.key === 'n' && nextLink ) {
				nextLink.click();
			} else if ( e.key === 'p' && prevLink ) {
				prevLink.click();
			}
		} );
	}

	function updateProgressUi( root, summary, itemId ) {
		var bar = root.querySelector( '.shapelms-progress__bar' );
		var text = root.querySelector( '[data-shapelms-progress-text]' );
		if ( bar ) {
			bar.style.width = summary.percent + '%';
			var pb = bar.parentNode;
			if ( pb ) {
				pb.setAttribute( 'aria-valuenow', summary.percent );
			}
		}
		if ( text ) {
			text.textContent = sprintf( __( '%d%% complete', 'shapelms' ), summary.percent );
		}
		var item = root.querySelector( '.shapelms-learn__item[data-item-id="' + itemId + '"]' );
		if ( item ) {
			item.classList.add( 'is-done' );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Quiz player                                                         */
	/* ------------------------------------------------------------------ */

	function initQuiz() {
		var quiz = document.getElementById( 'shapelms-quiz' );
		if ( ! quiz ) {
			return;
		}

		var learn = document.getElementById( 'shapelms-learn' ) || quiz;
		var quizId = parseInt( quiz.getAttribute( 'data-quiz-id' ), 10 );
		var intro = quiz.querySelector( '[data-shapelms-quiz-intro]' );
		var player = quiz.querySelector( '[data-shapelms-quiz-player]' );
		var result = quiz.querySelector( '[data-shapelms-quiz-result]' );
		var form = quiz.querySelector( '[data-shapelms-quiz-form]' );
		var counter = quiz.querySelector( '[data-shapelms-quiz-counter]' );
		var timer = quiz.querySelector( '[data-shapelms-quiz-timer]' );
		var msg = quiz.querySelector( '[data-shapelms-quiz-message]' );
		var prevBtn = quiz.querySelector( '[data-shapelms-quiz-prev]' );
		var nextBtn = quiz.querySelector( '[data-shapelms-quiz-next]' );
		var submitBtn = quiz.querySelector( '[data-shapelms-quiz-submit]' );
		var startBtn = quiz.querySelector( '[data-shapelms-quiz-start]' );
		var history = quiz.querySelector( '[data-shapelms-quiz-history]' );

		var state = { attempt: null, questions: [], settings: {}, page: 0, perPage: 1, answers: {}, timerId: null };

		function loadOverview() {
			api( learn, 'quizzes/' + quizId ).then( function ( data ) {
				var left = quiz.querySelector( '[data-shapelms-quiz-attempts]' );
				if ( left && data.attempts_left >= 0 ) {
					left.textContent = sprintf( __( '%1$d of %2$d left', 'shapelms' ), data.attempts_left, data.settings.attempts_allowed );
				}
				if ( startBtn && data.attempts_left === 0 ) {
					startBtn.disabled = true;
					startBtn.textContent = __( 'No attempts left', 'shapelms' );
				}
				if ( startBtn && data.open_attempt_id ) {
					startBtn.textContent = __( 'Resume quiz', 'shapelms' );
				}
				renderHistory( data.history || [] );
			} ).catch( function () {
				// Guests or non-enrolled: overview is not available.
			} );
		}

		function renderHistory( rows ) {
			if ( ! history || ! rows.length ) {
				return;
			}
			var tbody = history.querySelector( 'tbody' );
			tbody.innerHTML = '';
			rows.forEach( function ( row ) {
				var view = el( 'button', { type: 'button', class: 'shapelms-link', text: __( 'Review', 'shapelms' ), onclick: function () {
					showResult( row.id );
				} } );
				tbody.appendChild( el( 'tr', {}, [
					el( 'td', { text: '#' + row.attempt_number } ),
					el( 'td', { text: row.percentage + '%' } ),
					el( 'td', {}, [ el( 'span', { class: 'shapelms-tag ' + ( row.passed ? 'shapelms-tag--success' : 'shapelms-tag--danger' ), text: row.passed ? __( 'Passed', 'shapelms' ) : __( 'Failed', 'shapelms' ) } ) ] ),
					el( 'td', { text: row.finished_at ? new Date( row.finished_at.replace( ' ', 'T' ) + 'Z' ).toLocaleDateString() : '' } ),
					el( 'td', {}, [ view ] )
				] ) );
			} );
			history.hidden = false;
		}

		function start() {
			startBtn.disabled = true;
			message( msg, '' );
			api( learn, 'quizzes/' + quizId + '/start', { method: 'POST' } ).then( function ( data ) {
				state.attempt = data.attempt;
				state.questions = data.questions;
				state.settings = data.settings;
				state.perPage = data.settings.questions_per_page > 0 ? data.settings.questions_per_page : 1;
				state.page = 0;
				state.answers = {};
				data.questions.forEach( function ( q ) {
					if ( q.answer !== null && q.answer !== undefined ) {
						state.answers[ q.id ] = q.answer;
					}
				} );
				intro.hidden = true;
				result.hidden = true;
				player.hidden = false;
				startTimer( data );
				renderPage();
				player.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			} ).catch( function ( err ) {
				startBtn.disabled = false;
				message( msg, err.message, 'error' );
			} );
		}

		function startTimer( data ) {
			stopTimer();
			if ( ! data.attempt.expires_at ) {
				timer.hidden = true;
				return;
			}
			var expires = Date.parse( data.attempt.expires_at.replace( ' ', 'T' ) + 'Z' );
			var offset = Date.now() - ( data.server_time * 1000 );
			timer.hidden = false;
			function tick() {
				var remaining = Math.max( 0, Math.round( ( expires - ( Date.now() - offset ) ) / 1000 ) );
				timer.textContent = formatTime( remaining );
				timer.classList.toggle( 'is-warning', remaining <= 60 );
				if ( remaining <= 0 ) {
					stopTimer();
					submit( true );
				}
			}
			tick();
			state.timerId = window.setInterval( tick, 1000 );
		}

		function stopTimer() {
			if ( state.timerId ) {
				window.clearInterval( state.timerId );
				state.timerId = null;
			}
		}

		function pageCount() {
			return Math.ceil( state.questions.length / state.perPage );
		}

		function renderPage() {
			form.innerHTML = '';
			var startIndex = state.page * state.perPage;
			var slice = state.questions.slice( startIndex, startIndex + state.perPage );

			slice.forEach( function ( q, i ) {
				form.appendChild( renderQuestion( q, startIndex + i ) );
			} );

			counter.textContent = state.perPage === 1
				? sprintf( __( 'Question %1$d of %2$d', 'shapelms' ), state.page + 1, state.questions.length )
				: sprintf( __( 'Page %1$d of %2$d', 'shapelms' ), state.page + 1, pageCount() );

			prevBtn.hidden = state.page === 0;
			nextBtn.hidden = state.page >= pageCount() - 1;
			submitBtn.hidden = state.page < pageCount() - 1;
		}

		function renderQuestion( q, index ) {
			var fieldset = el( 'fieldset', { class: 'shapelms-question shapelms-question--' + q.type, 'data-question-id': q.id } );
			fieldset.appendChild( el( 'legend', { class: 'shapelms-question__title' }, [
				el( 'span', { class: 'shapelms-question__number', text: ( index + 1 ) + '.' } ),
				' ' + q.title,
				el( 'span', { class: 'shapelms-question__points', text: ' ' + sprintf( __( '(%s pts)', 'shapelms' ), q.points ) } )
			] ) );
			if ( q.description ) {
				fieldset.appendChild( el( 'div', { class: 'shapelms-question__description shapelms-prose', html: q.description } ) );
			}

			var answer = state.answers[ q.id ];
			var name = 'q_' + q.id;

			if ( q.type === 'single_choice' || q.type === 'true_false' || q.type === 'multiple_choice' ) {
				var multi = q.type === 'multiple_choice';
				( q.data.options || [] ).forEach( function ( opt ) {
					var checked = multi ? Array.isArray( answer ) && answer.indexOf( opt.id ) !== -1 : answer === opt.id;
					var input = el( 'input', { type: multi ? 'checkbox' : 'radio', name: name, value: opt.id } );
					input.checked = checked;
					input.addEventListener( 'change', function () {
						if ( multi ) {
							state.answers[ q.id ] = Array.prototype.map.call( fieldset.querySelectorAll( 'input:checked' ), function ( i ) {
								return i.value;
							} );
						} else {
							state.answers[ q.id ] = opt.id;
						}
						saveAnswer( q, fieldset );
					} );
					var label = el( 'label', { class: 'shapelms-option' }, [ input, el( 'span', { class: 'shapelms-option__text', html: opt.text } ) ] );
					if ( opt.image ) {
						label.appendChild( el( 'img', { src: opt.image, alt: '', class: 'shapelms-option__image' } ) );
					}
					fieldset.appendChild( label );
				} );
			} else if ( q.type === 'short_answer' ) {
				var input = el( 'input', { type: 'text', class: 'shapelms-input', name: name, maxlength: q.data.max_length || 200, value: answer || '' } );
				input.addEventListener( 'change', function () {
					state.answers[ q.id ] = input.value;
					saveAnswer( q, fieldset );
				} );
				fieldset.appendChild( input );
			} else if ( q.type === 'fill_blank' ) {
				var wrap = el( 'div', { class: 'shapelms-blanks' } );
				var values = Array.isArray( answer ) ? answer : [];
				( q.data.parts || [] ).forEach( function ( part, i ) {
					wrap.appendChild( el( 'span', { html: part } ) );
					if ( i < q.data.blanks ) {
						var blank = el( 'input', { type: 'text', class: 'shapelms-input shapelms-input--blank', name: name + '_' + i, value: values[ i ] || '', 'aria-label': sprintf( __( 'Blank %d', 'shapelms' ), i + 1 ) } );
						blank.addEventListener( 'change', function () {
							var list = Array.prototype.map.call( wrap.querySelectorAll( 'input' ), function ( b ) {
								return b.value;
							} );
							state.answers[ q.id ] = list;
							saveAnswer( q, fieldset );
						} );
						wrap.appendChild( blank );
					}
				} );
				fieldset.appendChild( wrap );
			}

			fieldset.appendChild( el( 'div', { class: 'shapelms-question__feedback', 'aria-live': 'polite' } ) );
			return fieldset;
		}

		function saveAnswer( q, fieldset ) {
			api( learn, 'quizzes/attempts/' + state.attempt.id + '/answer', { method: 'POST', body: { question_id: q.id, answer: state.answers[ q.id ] } } )
				.then( function ( res ) {
					if ( state.settings.feedback_mode === 'instant' && typeof res.is_correct !== 'undefined' ) {
						var box = fieldset.querySelector( '.shapelms-question__feedback' );
						box.className = 'shapelms-question__feedback is-' + ( res.is_correct ? 'correct' : 'incorrect' );
						box.innerHTML = '';
						box.appendChild( el( 'strong', { text: res.is_correct ? __( 'Correct!', 'shapelms' ) : __( 'Not quite.', 'shapelms' ) } ) );
						if ( res.explanation ) {
							box.appendChild( el( 'div', { html: res.explanation } ) );
						}
					}
				} )
				.catch( function ( err ) {
					message( msg, err.message, 'error' );
					if ( err.code === 'shapelms_attempt_expired' || err.code === 'shapelms_attempt_closed' ) {
						showResult( state.attempt.id );
					}
				} );
		}

		function submit( auto ) {
			if ( ! auto ) {
				var unanswered = state.questions.filter( function ( q ) {
					var a = state.answers[ q.id ];
					return a === undefined || a === null || a === '' || ( Array.isArray( a ) && ! a.length );
				} ).length;
				if ( unanswered > 0 && ! window.confirm( sprintf( __( 'You have %d unanswered question(s). Submit anyway?', 'shapelms' ), unanswered ) ) ) {
					return;
				}
			}
			stopTimer();
			submitBtn.disabled = true;
			api( learn, 'quizzes/attempts/' + state.attempt.id + '/submit', { method: 'POST', body: { answers: state.answers } } )
				.then( function ( data ) {
					submitBtn.disabled = false;
					renderResult( data );
				} )
				.catch( function ( err ) {
					submitBtn.disabled = false;
					message( msg, err.message, 'error' );
				} );
		}

		function showResult( attemptId ) {
			api( learn, 'quizzes/attempts/' + attemptId ).then( renderResult ).catch( function ( err ) {
				message( msg, err.message, 'error' );
			} );
		}

		function renderResult( data ) {
			player.hidden = true;
			intro.hidden = true;
			result.hidden = false;
			result.innerHTML = '';

			var passed = data.passed;
			result.appendChild( el( 'div', { class: 'shapelms-result ' + ( passed ? 'is-passed' : 'is-failed' ) }, [
				el( 'div', { class: 'shapelms-result__score', text: data.percentage + '%' } ),
				el( 'h2', { text: passed ? __( 'Congratulations, you passed!', 'shapelms' ) : __( 'You did not pass this time.', 'shapelms' ) } ),
				el( 'p', { class: 'shapelms-muted', text: sprintf( __( 'Score: %1$s of %2$s points. Pass mark: %3$d%%.', 'shapelms' ), data.score, data.max_score, data.settings.pass_mark ) } )
			] ) );

			if ( data.questions && data.questions.length ) {
				var list = el( 'div', { class: 'shapelms-review' } );
				data.questions.forEach( function ( q, i ) {
					var item = el( 'div', { class: 'shapelms-review__item is-' + ( q.is_correct ? 'correct' : 'incorrect' ) } );
					item.appendChild( el( 'h3', { text: ( i + 1 ) + '. ' + q.title } ) );
					item.appendChild( el( 'p', { class: 'shapelms-muted', text: sprintf( __( 'Your answer: %s', 'shapelms' ), formatAnswer( q, q.answer ) ) } ) );
					if ( q.correct ) {
						item.appendChild( el( 'p', { text: sprintf( __( 'Correct answer: %s', 'shapelms' ), formatAnswer( q, q.correct ) ) } ) );
					}
					item.appendChild( el( 'p', { class: 'shapelms-review__points', text: sprintf( __( '%1$s / %2$s points', 'shapelms' ), q.points, q.max_points ) } ) );
					if ( q.explanation ) {
						item.appendChild( el( 'div', { class: 'shapelms-review__explanation shapelms-prose', html: q.explanation } ) );
					}
					list.appendChild( item );
				} );
				result.appendChild( list );
			}

			var actions = el( 'div', { class: 'shapelms-result__actions' } );
			actions.appendChild( el( 'button', { type: 'button', class: 'shapelms-btn shapelms-btn--secondary', text: __( 'Back to quiz', 'shapelms' ), onclick: function () {
				result.hidden = true;
				intro.hidden = false;
				loadOverview();
			} } ) );
			var nextUrl = learn.getAttribute( 'data-next-url' );
			if ( nextUrl ) {
				actions.appendChild( el( 'a', { class: 'shapelms-btn shapelms-btn--primary', href: nextUrl, text: __( 'Next lesson', 'shapelms' ) } ) );
			}
			result.appendChild( actions );

			if ( learn !== quiz ) {
				api( learn, 'courses/' + learn.getAttribute( 'data-course-id' ) + '/progress' ).then( function ( summary ) {
					updateProgressUi( learn, summary, quizId );
				} ).catch( function () {} );
			}

			result.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		}

		function formatAnswer( q, value ) {
			if ( value === null || value === undefined || value === '' ) {
				return __( '(no answer)', 'shapelms' );
			}
			var options = ( q.data && q.data.options ) || [];
			var list = Array.isArray( value ) ? value : [ value ];
			return list.map( function ( v ) {
				var opt = options.filter( function ( o ) {
					return o.id === v;
				} )[ 0 ];
				if ( opt ) {
					var tmp = document.createElement( 'div' );
					tmp.innerHTML = opt.text;
					return tmp.textContent;
				}
				return String( v );
			} ).join( ', ' );
		}

		if ( startBtn ) {
			startBtn.addEventListener( 'click', start );
		}
		prevBtn.addEventListener( 'click', function () {
			state.page = Math.max( 0, state.page - 1 );
			renderPage();
		} );
		nextBtn.addEventListener( 'click', function () {
			state.page = Math.min( pageCount() - 1, state.page + 1 );
			renderPage();
		} );
		submitBtn.addEventListener( 'click', function () {
			submit( false );
		} );
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
		} );

		if ( config( learn ).loggedIn === '1' ) {
			loadOverview();
		}
	}

	/* ------------------------------------------------------------------ */
	/* Generic REST forms (reviews, Q&A, instructor application, profile) */
	/* ------------------------------------------------------------------ */

	function initForms() {
		document.querySelectorAll( 'form[data-shapelms-form]' ).forEach( function ( form ) {
			form.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				var path = form.getAttribute( 'data-shapelms-form' );
				var method = form.getAttribute( 'data-method' ) || 'POST';
				var msg = form.querySelector( '[data-shapelms-form-message]' );
				var button = form.querySelector( '[type="submit"]' );
				var body = {};

				Array.prototype.forEach.call( form.elements, function ( field ) {
					if ( ! field.name || field.disabled ) {
						return;
					}
					if ( ( field.type === 'checkbox' || field.type === 'radio' ) && ! field.checked ) {
						return;
					}
					if ( field.name.slice( -2 ) === '[]' ) {
						var key = field.name.slice( 0, -2 );
						body[ key ] = body[ key ] || [];
						body[ key ].push( field.value );
					} else {
						body[ field.name ] = field.type === 'checkbox' ? true : field.value;
					}
				} );

				if ( button ) {
					button.disabled = true;
				}
				message( msg, '' );

				api( form, path, { method: method, body: body } )
					.then( function ( res ) {
						message( msg, form.getAttribute( 'data-success' ) || __( 'Saved.', 'shapelms' ), 'success' );
						if ( form.getAttribute( 'data-reload' ) === '1' ) {
							window.location.reload();
							return;
						}
						if ( res && res.redirect ) {
							window.location.href = res.redirect;
							return;
						}
						if ( form.getAttribute( 'data-reset' ) === '1' ) {
							form.reset();
						}
						form.dispatchEvent( new CustomEvent( 'shapelms:submitted', { detail: res } ) );
					} )
					.catch( function ( err ) {
						message( msg, err.message, 'error' );
					} )
					.then( function () {
						if ( button ) {
							button.disabled = false;
						}
					} );
			} );
		} );

		document.querySelectorAll( '.shapelms-stars' ).forEach( function ( stars ) {
			var inputs = stars.querySelectorAll( 'input' );
			function paint() {
				var value = 0;
				inputs.forEach( function ( input ) {
					if ( input.checked ) {
						value = parseInt( input.value, 10 );
					}
				} );
				stars.querySelectorAll( 'label' ).forEach( function ( label ) {
					label.classList.toggle( 'is-on', parseInt( label.getAttribute( 'data-value' ), 10 ) <= value );
				} );
			}
			inputs.forEach( function ( input ) {
				input.addEventListener( 'change', paint );
			} );
			paint();
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Action buttons (wishlist, delete, etc.): data-shapelms-action       */
	/* ------------------------------------------------------------------ */

	function initActions() {
		document.addEventListener( 'click', function ( e ) {
			var button = e.target.closest ? e.target.closest( '[data-shapelms-action]' ) : null;
			if ( ! button ) {
				return;
			}
			e.preventDefault();

			var cfg = config( button );
			if ( cfg.loggedIn !== '1' && button.getAttribute( 'data-public' ) !== '1' ) {
				window.location.href = cfg.loginUrl;
				return;
			}
			if ( button.getAttribute( 'data-confirm' ) && ! window.confirm( button.getAttribute( 'data-confirm' ) ) ) {
				return;
			}

			var body = {};
			try {
				body = JSON.parse( button.getAttribute( 'data-body' ) || '{}' );
			} catch ( err ) {
				body = {};
			}

			button.disabled = true;
			api( button, button.getAttribute( 'data-shapelms-action' ), { method: button.getAttribute( 'data-method' ) || 'POST', body: body } )
				.then( function ( res ) {
					button.disabled = false;
					if ( button.getAttribute( 'data-toggle-class' ) ) {
						button.classList.toggle( button.getAttribute( 'data-toggle-class' ), !! ( res && res.active ) );
						var onText = button.getAttribute( 'data-on-text' );
						var offText = button.getAttribute( 'data-off-text' );
						var label = button.querySelector( '.shapelms-action__label' ) || button;
						if ( onText && offText ) {
							label.textContent = res && res.active ? onText : offText;
						}
						button.setAttribute( 'aria-pressed', res && res.active ? 'true' : 'false' );
					}
					if ( button.getAttribute( 'data-reload' ) === '1' ) {
						window.location.reload();
					}
					if ( button.getAttribute( 'data-remove' ) ) {
						var target = button.closest( button.getAttribute( 'data-remove' ) );
						if ( target ) {
							target.remove();
						}
					}
				} )
				.catch( function ( err ) {
					button.disabled = false;
					window.alert( err.message );
				} );
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Dashboard tabs                                                      */
	/* ------------------------------------------------------------------ */

	function initTabs() {
		document.querySelectorAll( '[data-shapelms-tabs]' ).forEach( function ( tabs ) {
			var links = tabs.querySelectorAll( '[data-tab]' );
			var panels = tabs.querySelectorAll( '[data-panel]' );
			function activate( id, push ) {
				links.forEach( function ( link ) {
					var on = link.getAttribute( 'data-tab' ) === id;
					link.classList.toggle( 'is-active', on );
					link.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				} );
				panels.forEach( function ( panel ) {
					panel.hidden = panel.getAttribute( 'data-panel' ) !== id;
				} );
				if ( push && window.history.replaceState ) {
					var url = new URL( window.location.href );
					url.searchParams.set( 'tab', id );
					window.history.replaceState( null, '', url.toString() );
				}
			}
			links.forEach( function ( link ) {
				link.addEventListener( 'click', function ( e ) {
					e.preventDefault();
					activate( link.getAttribute( 'data-tab' ), true );
				} );
			} );
			var initial = new URL( window.location.href ).searchParams.get( 'tab' );
			var exists = initial && tabs.querySelector( '[data-tab="' + initial + '"]' );
			activate( exists ? initial : ( links[ 0 ] ? links[ 0 ].getAttribute( 'data-tab' ) : '' ), false );
		} );
	}

	/* ------------------------------------------------------------------ */
	/* Boot                                                                */
	/* ------------------------------------------------------------------ */

	function boot() {
		initProgress();
		initEnroll();
		initLearn();
		initQuiz();
		initForms();
		initActions();
		initTabs();
		document.dispatchEvent( new CustomEvent( 'shapelms:ready', { detail: { api: api, config: config, el: el, message: message } } ) );
	}

	window.shapelms = { api: api, config: config, el: el, message: message, initProgress: initProgress };

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );
