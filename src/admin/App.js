/**
 * Admin app router.
 */
import { SnackbarList } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import Dashboard from './screens/Dashboard';
import Courses from './screens/Courses';
import Students from './screens/Students';
import Quizzes from './screens/Quizzes';
import Instructors from './screens/Instructors';
import Orders from './screens/Orders';
import Reports from './screens/Reports';
import Modules from './screens/Modules';
import Settings from './screens/Settings';
import Tools from './screens/Tools';

const screens = {
	dashboard: Dashboard,
	courses: Courses,
	students: Students,
	quizzes: Quizzes,
	instructors: Instructors,
	orders: Orders,
	reports: Reports,
	modules: Modules,
	settings: Settings,
	tools: Tools,
};

function Snackbars() {
	const notices = useSelect( ( select ) => select( noticesStore ).getNotices(), [] );
	const { removeNotice } = useDispatch( noticesStore );
	const snackbars = notices.filter( ( n ) => n.type === 'snackbar' );
	return <SnackbarList notices={ snackbars } onRemove={ removeNotice } className="shapelms-snackbars" />;
}

export default function App( { screen, config } ) {
	const Screen = screens[ screen ] || Dashboard;
	return (
		<div className="shapelms-admin">
			<Screen config={ config } />
			<Snackbars />
		</div>
	);
}
