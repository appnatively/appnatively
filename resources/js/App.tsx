import { AppNatively } from './components/AppNatively';
import { useAdminSidebarLayout } from '@wpmvc/admin-sidebar';

export default function App() {
	const { left, top } = useAdminSidebarLayout();

	return (
		<div
			className="connection-page-wrapper w-full h-full min-h-screen"
			style={ { paddingTop: top, paddingLeft: left } }
		>
			<div className="w-full">
				<AppNatively />
			</div>
		</div>
	);
}
