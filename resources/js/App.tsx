import ConnectionPanel from "./components/ConnectionPanel";

export default function App() {
    return (
        <div className="flex flex-col items-center justify-center p-8 bg-muted/20 min-h-[500px] rounded-xl border border-border/50 shadow-sm!">
            <div className="w-full max-w-2xl animate-in fade-in slide-in-from-bottom-4 duration-500">
                <ConnectionPanel />
            </div>
        </div>
    )
}