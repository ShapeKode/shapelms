/* eslint-disable no-console */
/**
 * End-to-end smoke test for ShapeLMS.
 *
 * Usage:
 *   npm i -D playwright && npx playwright install chromium
 *   SHAPELMS_URL=http://localhost:10160 SHAPELMS_ADMIN=admin:pass SHAPELMS_STUDENT=student:pass SHAPELMS_COURSE_ID=138 node tests/e2e/smoke.js
 *
 * Expects a published course with slug "getting-started-demo" (the bundled demo course)
 * and writes screenshots to tests/e2e/shots/.
 */
const { chromium } = require( 'playwright' );
const path = require( 'path' );

const BASE = ( process.env.SHAPELMS_URL || 'http://localhost:10160' ).replace( /\/$/, '' );
const [ ADMIN_USER, ADMIN_PASS ] = ( process.env.SHAPELMS_ADMIN || 'slms_admin:Admin#Pass123' ).split( ':' );
const [ STUDENT_USER, STUDENT_PASS ] = ( process.env.SHAPELMS_STUDENT || 'slms_student:Student#Pass123' ).split( ':' );
const COURSE_ID = parseInt( process.env.SHAPELMS_COURSE_ID || '138', 10 );
const OUT = path.join( __dirname, 'shots' );
require( 'fs' ).mkdirSync( OUT, { recursive: true } );

const problems = [];

async function login( page, user, pass ) {
	await page.goto( `${ BASE }/wp-login.php` );
	await page.fill( '#user_login', user );
	await page.fill( '#user_pass', pass );
	await Promise.all( [ page.waitForNavigation(), page.click( '#wp-submit' ) ] );
}

function watch( page, label ) {
	page.on( 'dialog', ( d ) => {
		if ( d.type() === 'beforeunload' ) {
			problems.push( `[${ label }] unexpected beforeunload prompt on ${ page.url().replace( BASE, '' ) }` );
		}
		d.accept();
	} );
	page.on( 'console', ( msg ) => {
		if ( msg.type() === 'error' ) {
			const t = msg.text();
			if ( /favicon|net::ERR_|gravatar|googleapis|youtube|ERR_BLOCKED_BY_CLIENT/.test( t ) ) {
				return;
			}
			problems.push( `[${ label }] console.error: ${ t.slice( 0, 300 ) }` );
		}
	} );
	page.on( 'pageerror', ( err ) => problems.push( `[${ label }] pageerror: ${ String( err ).slice( 0, 300 ) }` ) );
	page.on( 'response', ( res ) => {
		const u = res.url();
		if ( res.status() >= 400 && ! /favicon|gravatar|youtube|googleapis/.test( u ) ) {
			problems.push( `[${ label }] HTTP ${ res.status() } ${ u.replace( BASE, '' ) }` );
		}
	} );
}

async function shot( page, name ) {
	await page.screenshot( { path: path.join( OUT, `${ name }.png` ), fullPage: true } );
	console.log( 'shot', name );
}

( async () => {
	const browser = await chromium.launch();

	// ---- Admin ----
	const admin = await browser.newContext( { viewport: { width: 1400, height: 900 } } );
	const a = await admin.newPage();
	watch( a, 'admin' );
	await login( a, ADMIN_USER, ADMIN_PASS );

	for ( const screen of [ 'shapelms', 'shapelms-courses', 'shapelms-students', 'shapelms-quizzes', 'shapelms-instructors', 'shapelms-orders', 'shapelms-reports', 'shapelms-modules', 'shapelms-settings', 'shapelms-tools' ] ) {
		await a.goto( `${ BASE }/wp-admin/admin.php?page=${ screen }` );
		await a.waitForTimeout( 1800 );
		const rendered = await a.evaluate( () => {
			const root = document.getElementById( 'shapelms-admin' );
			return root ? root.innerText.trim().slice( 0, 120 ) : 'NO ROOT';
		} );
		console.log( screen, '=>', rendered.replace( /\n/g, ' | ' ) );
		if ( ! rendered || rendered === 'NO ROOT' ) {
			problems.push( `[admin] ${ screen } did not render` );
		}
		await shot( a, `admin-${ screen }` );
	}

	// Settings: change a value and save.
	await a.goto( `${ BASE }/wp-admin/admin.php?page=shapelms-settings` );
	await a.waitForTimeout( 1500 );
	const perPage = a.locator( 'input[type="number"]' ).first();
	const current = parseInt( await perPage.inputValue(), 10 ) || 12;
	await perPage.fill( String( current === 12 ? 10 : 12 ) );
	await a.waitForTimeout( 400 );
	const saveBtn = a.locator( 'button:has-text("Save changes")' );
	console.log( 'save enabled after edit:', await saveBtn.isEnabled() );
	if ( await saveBtn.isEnabled() ) {
		await saveBtn.click();
		await a.waitForTimeout( 1500 );
		console.log( 'settings saved snackbar:', await a.locator( '.components-snackbar' ).count() );
	} else {
		problems.push( '[admin] settings: editing a number field did not enable Save' );
	}

	const courseStatus = async ( label ) => {
		const status = await a.evaluate( async ( id ) => {
			const r = await fetch( `/wp-json/shapelms/v1/courses/${ id }` );
			return ( await r.json() ).status;
		}, COURSE_ID );
		console.log( `course ${ COURSE_ID } status ${ label }:`, status );
		if ( status !== 'publish' ) {
			problems.push( `[admin] course ${ COURSE_ID } is "${ status }" ${ label } (expected publish)` );
		}
	};
	await courseStatus( 'before builder' );

	// ---- Builder ----
	await a.goto( `${ BASE }/wp-admin/post.php?post=${ COURSE_ID }&action=edit` );
	await a.waitForTimeout( 2500 );
	const takeOver = a.locator( '#post-lock-dialog a.button-primary:has-text("Take over")' );
	if ( await a.locator( '#post-lock-dialog' ).isVisible() && await takeOver.count() ) {
		console.log( 'post lock dialog shown — taking over' );
		await Promise.all( [ a.waitForNavigation(), takeOver.click() ] );
		await a.waitForTimeout( 2500 );
	}
	const builderText = await a.evaluate( () => ( document.getElementById( 'shapelms-builder' ) || {} ).innerText || 'NO ROOT' );
	console.log( 'builder =>', builderText.slice( 0, 160 ).replace( /\n/g, ' | ' ) );
	await shot( a, 'builder-basics' );
	await a.click( '.shapelms-builder__nav button:has-text("Curriculum")' );
	await a.waitForTimeout( 800 );
	await shot( a, 'builder-curriculum' );
	// Add a topic and a lesson, then open the lesson editor.
	await a.fill( '.shapelms-curriculum-builder__add input', 'Playwright Topic' );
	await a.click( 'button:has-text("Add topic")' );
	await a.waitForTimeout( 1200 );
	const topics = await a.locator( '.shapelms-curriculum-builder__topic' ).count();
	console.log( 'topics after add:', topics );
	const lastTopic = a.locator( '.shapelms-curriculum-builder__topic' ).last();
	await lastTopic.locator( 'button:has-text("+ Lesson")' ).click();
	await lastTopic.locator( 'input[placeholder="Lesson title"]' ).fill( 'Playwright Lesson' );
	await lastTopic.locator( 'button:has-text("Add")' ).first().click();
	await a.waitForTimeout( 2000 );
	const modal = a.locator( '.components-modal__frame' );
	console.log( 'lesson editor opened:', await modal.count() );
	await shot( a, 'builder-lesson-editor' );
	if ( await modal.count() ) {
		await modal.locator( 'button:has-text("Save & close")' ).click();
		await a.waitForTimeout( 1500 );
	}
	// Open the quiz editor.
	await a.locator( '.shapelms-curriculum-builder__item:has(.is-quiz) .shapelms-curriculum-builder__item-title' ).first().click();
	await a.waitForTimeout( 2000 );
	console.log( 'quiz editor questions:', await a.locator( '.shapelms-questions__item' ).count() );
	await shot( a, 'builder-quiz-editor' );
	await a.keyboard.press( 'Escape' );
	await a.waitForTimeout( 500 );
	await a.click( '.shapelms-builder__nav button:has-text("Pricing")' );
	await a.waitForTimeout( 600 );
	await shot( a, 'builder-pricing' );
	await a.waitForTimeout( 2000 );
	await courseStatus( 'after builder' );

	// ---- Student front end ----
	const student = await browser.newContext( { viewport: { width: 1280, height: 900 } } );
	const s = await student.newPage();
	watch( s, 'student' );
	await login( s, STUDENT_USER, STUDENT_PASS );

	await s.goto( `${ BASE }/courses/` );
	await shot( s, 'front-archive' );
	await s.goto( `${ BASE }/courses/getting-started-demo/` );
	await shot( s, 'front-course' );
	await s.goto( `${ BASE }/dashboard/` );
	await shot( s, 'front-dashboard' );
	await s.goto( `${ BASE }/courses/getting-started-demo/lessons/watching-a-video-lesson/` );
	await s.waitForTimeout( 800 );
	await shot( s, 'front-lesson' );
	// Mark complete.
	const completeBtn = s.locator( '[data-shapelms-complete]' );
	if ( await completeBtn.count() && ! ( await completeBtn.isDisabled() ) ) {
		await completeBtn.click();
		await s.waitForTimeout( 2500 );
		console.log( 'after mark complete url:', s.url().replace( BASE, '' ) );
	}
	// Quiz.
	await s.goto( `${ BASE }/courses/getting-started-demo/quizzes/demo-quiz/` );
	await s.waitForTimeout( 1200 );
	await shot( s, 'front-quiz-intro' );
	await s.click( '[data-shapelms-quiz-start]' );
	await s.waitForTimeout( 1500 );
	console.log( 'quiz counter:', await s.locator( '[data-shapelms-quiz-counter]' ).innerText() );
	await s.locator( '.shapelms-option input' ).first().check();
	await s.waitForTimeout( 1000 );
	console.log( 'instant feedback:', ( await s.locator( '.shapelms-question__feedback' ).innerText() ).slice( 0, 60 ) );
	await shot( s, 'front-quiz-question' );
	await s.click( '[data-shapelms-quiz-next]' );
	await s.waitForTimeout( 500 );
	await s.locator( '.shapelms-option input' ).nth( 0 ).check();
	await s.locator( '.shapelms-option input' ).nth( 1 ).check();
	await s.locator( '.shapelms-option input' ).nth( 2 ).check();
	await s.waitForTimeout( 800 );
	await s.click( '[data-shapelms-quiz-next]' );
	await s.locator( '.shapelms-option input' ).first().check();
	await s.waitForTimeout( 500 );
	await s.click( '[data-shapelms-quiz-next]' );
	await s.fill( '.shapelms-input', 'Mark as complete' );
	await s.locator( '.shapelms-input' ).press( 'Tab' );
	await s.waitForTimeout( 500 );
	await s.click( '[data-shapelms-quiz-next]' );
	await s.locator( '.shapelms-input--blank' ).nth( 0 ).fill( 'topics' );
	await s.locator( '.shapelms-input--blank' ).nth( 1 ).fill( 'quizzes' );
	await s.locator( '.shapelms-input--blank' ).nth( 1 ).press( 'Tab' );
	await s.waitForTimeout( 500 );
	await s.click( '[data-shapelms-quiz-submit]' );
	await s.waitForTimeout( 2500 );
	console.log( 'quiz result:', ( await s.locator( '.shapelms-result' ).innerText() ).replace( /\n/g, ' | ' ).slice( 0, 120 ) );
	await shot( s, 'front-quiz-result' );
	await s.goto( `${ BASE }/checkout-2/?course=${ COURSE_ID }` );
	await shot( s, 'front-checkout' );

	await browser.close();

	console.log( '\n==== PROBLEMS (' + problems.length + ') ====' );
	problems.forEach( ( p ) => console.log( p ) );
} )().catch( ( e ) => {
	console.error( 'SCRIPT FAILED:', e );
	process.exit( 1 );
} );
