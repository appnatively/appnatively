export function AppNatively() {
	const platformUrl = 'https://local.appnatively.com/studio';
	const embedUrl = `${ platformUrl }?source=wordpress`;

	return (
		<div className="connection-embed-container w-full h-[calc(100vh-32px)] flex flex-col bg-background">
			<div className="flex-1 w-full bg-background overflow-hidden relative">
				<iframe
					src={ embedUrl }
					className="w-full h-full border-0"
					title="AppNatively Studio"
				/>
			</div>
		</div>
	);
}
