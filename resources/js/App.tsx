import { ConnectionPanel } from "./components/ConnectionPanel";
import { useAdminSidebarLayout } from '@wpmvc/admin-sidebar';

export default function App() {
    const { left, top } = useAdminSidebarLayout();

    return (
        <div 
            className="connection-page-wrapper w-full h-full min-h-screen"
            style={{ paddingTop: top, paddingLeft: left }}
        >
            <div className="w-full">
            {/* <div className="w-full px-4"> */}
                <ConnectionPanel />
            </div>
        </div>
    );
}