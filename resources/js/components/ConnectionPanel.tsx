import { useState, useEffect } from 'react';
import apiFetch from '@wordpress/api-fetch';
import { Card, CardContent, CardDescription, CardHeader, CardTitle, CardFooter } from './ui/card';
import { Button } from './ui/button';
import { Badge } from './ui/badge';
import { Globe, AlertCircle, ExternalLink, ArrowLeft, Trash2, LayoutDashboard } from 'lucide-react';

declare const appnativelyData: {
    restUrl: string;
    nonce: string;
    siteUrl: string;
};

// Configure apiFetch
apiFetch.use( apiFetch.createNonceMiddleware( appnativelyData.nonce ) );

export function ConnectionPanel() {
    const [ connections, setConnections ] = useState<any[]>( [] );
    const [ status, setStatus ] = useState<string>( 'loading' );
    const [ selectedAppId, setSelectedAppId ] = useState<string | null>( null );
    const [ isLoading, setIsLoading ] = useState( false );
    const [ isChecking, setIsChecking ] = useState( true );
    const [ error, setError ] = useState<string | null>( null );

    const platformUrl = 'https://local.appnatively.com';

    useEffect( () => {
        handleCheckConnection();
    }, [] );

    const handleCheckConnection = async () => {
        setIsChecking( true );
        setError( null );
        try {
            const data: any = await apiFetch( {
                path: 'appnatively/v1/connection/status',
            } );
            
            if ( data.connections && data.connections.length > 0 ) {
                setConnections( data.connections );
                setStatus( 'connected' );
            } else {
                setConnections( [] );
                setStatus( 'disconnected' );
            }
        } catch ( err: any ) {
            setError( 'Failed to check connection status' );
        } finally {
            setIsChecking( false );
        }
    };

    const handleDisconnect = async ( appId?: string ) => {
        setIsLoading( true );
        try {
            const path = appId ? `appnatively/v1/connection/disconnect?appId=${appId}` : 'appnatively/v1/connection/disconnect';
            await apiFetch( {
                path: path,
                method: 'DELETE',
            } );
            
            if ( appId === selectedAppId ) {
                setSelectedAppId( null );
            }
            
            handleCheckConnection();
        } catch ( err: any ) {
            setError( 'Failed to disconnect application' );
        } finally {
            setIsLoading( false );
        }
    };

    const handleConnect = async () => {
        setIsLoading( true );
        setError( null );

        try {
            const data: any = await apiFetch( {
                path: 'appnatively/v1/connection/init',
                method: 'POST',
            } );

            if ( data.success && data.redirectUrl ) {
                window.open( data.redirectUrl, '_blank' );
            } else {
                setError( data.message || 'Failed to initiate connection' );
            }
        } catch ( err: any ) {
            setError( err.message || 'An unexpected error occurred' );
        } finally {
            setIsLoading( false );
        }
    };

    // --- RENDER: Loading ---
    if ( isChecking ) {
        return (
            <div className="flex items-center justify-center p-12">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
            </div>
        );
    }

    // --- RENDER: Embedded Mode (Iframe) ---
    // if ( status === 'connected' && selectedAppId ) {
        const path = `/studio/app/${selectedAppId}/overview`;
        const embedUrl = `${ platformUrl }${ path }?source=wordpress&siteUrl=${ encodeURIComponent( appnativelyData.siteUrl ) }`;
        
        return (
            <div className="connection-embed-container w-full h-[calc(100vh-32px)] flex flex-col bg-background">
                {/* <div className="flex items-center justify-between p-2 border-b border-border bg-muted/30">
                    <Button variant="ghost" size="sm" onClick={() => setSelectedAppId( null )} className="gap-2">
                        <ArrowLeft className="w-4 h-4" />
                        Back to Connections
                    </Button>
                    <div className="flex items-center gap-2">
                        <Badge variant="outline" className="bg-background">
                            App ID: {selectedAppId}
                        </Badge>
                    </div>
                </div> */}
                <div className="flex-1 w-full bg-background overflow-hidden relative">
                    <iframe 
                        src={"https://local.appnatively.com/new-dashboard"}
                        // src={embedUrl}
                        className="w-full h-full border-0"
                        title="AppNatively Studio"
                    />
                </div>
            </div>
        );
    // }

    // --- RENDER: Dashboard Mode (List) ---
    if ( status === 'connected' ) {
        return (
            <div className="w-full space-y-8 animate-in fade-in duration-500">
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-3xl font-bold tracking-tight">AppNatively Dashboard</h2>
                        <p className="text-muted-foreground">Manage your connected applications</p>
                    </div>
                    <Button onClick={handleConnect} disabled={isLoading} className="gap-2 shadow-sm!">
                        <ExternalLink className="w-4 h-4" />
                        Connect Another App
                    </Button>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {connections.map( ( app ) => (
                        <Card key={app.appId} className="border border-border/50 shadow-sm hover:shadow-md transition-all group overflow-hidden">
                            <CardHeader className="pb-4">
                                <div className="flex items-start justify-between">
                                    <div className="flex items-center gap-3">
                                        <div className="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center">
                                            <Globe className="w-5 h-5 text-primary" />
                                        </div>
                                        <div>
                                            <CardTitle className="text-lg font-bold truncate max-w-[150px]">
                                                {app.appName || 'Unnamed App'}
                                            </CardTitle>
                                            <CardDescription className="text-xs">
                                                ID: {app.appId}
                                            </CardDescription>
                                        </div>
                                    </div>
                                    <Badge variant="secondary" className="bg-green-500/10 text-green-600 border-green-500/20">
                                        Active
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent className="pb-6">
                                <div className="space-y-3">
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground">Platform:</span>
                                        <span className="font-medium">Studio</span>
                                    </div>
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground">Site:</span>
                                        <span className="font-medium truncate max-w-[120px]">{appnativelyData.siteUrl}</span>
                                    </div>
                                </div>
                            </CardContent>
                            <CardFooter className="bg-muted/30 border-t border-border/50 p-4 gap-2">
                                <Button 
                                    variant="default" 
                                    className="flex-1 gap-2" 
                                    onClick={() => setSelectedAppId( app.appId )}
                                >
                                    <LayoutDashboard className="w-4 h-4" />
                                    Enter Studio
                                </Button>
                                <Button 
                                    variant="ghost" 
                                    size="icon" 
                                    className="text-muted-foreground hover:text-destructive transition-colors"
                                    onClick={() => handleDisconnect( app.appId )}
                                    disabled={isLoading}
                                >
                                    <Trash2 className="w-4 h-4" />
                                </Button>
                            </CardFooter>
                        </Card>
                    ) )}
                </div>

                {error && (
                    <div className="bg-destructive/10 border border-destructive/20 text-destructive px-4 py-3 rounded-lg flex items-center gap-3">
                        <AlertCircle className="w-5 h-5" />
                        <p className="text-sm font-medium">{error}</p>
                    </div>
                )}
            </div>
        );
    }

    // --- RENDER: Disconnected State ---
    return (
        <Card className="w-full max-w-2xl border border-border shadow-sm mx-auto">
            <CardHeader>
                <div className="flex items-center justify-between">
                    <div>
                        <CardTitle className="text-2xl font-bold flex items-center gap-2">
                            <Globe className="w-6 h-6 text-primary" />
                            AppNatively Connection
                        </CardTitle>
                        <CardDescription>
                            Link your WordPress site to the platform to start building your app
                        </CardDescription>
                    </div>
                    <Badge variant="secondary" className="px-3 py-1">Disconnected</Badge>
                </div>
            </CardHeader>
            <CardContent className="space-y-6">
                {error && (
                    <div className="bg-destructive/10 border border-destructive/20 text-destructive px-4 py-3 rounded-lg flex items-center gap-3">
                        <AlertCircle className="w-5 h-5" />
                        <p className="text-sm font-medium">{error}</p>
                    </div>
                )}

                <div className="p-6 bg-muted/30 rounded-2xl border border-border/50 flex flex-col items-center text-center space-y-4">
                    <div className="w-16 h-16 rounded-full bg-primary/10 flex items-center justify-center">
                        <Globe className="w-8 h-8 text-primary" />
                    </div>
                    <div>
                        <p className="text-lg font-bold">{appnativelyData.siteUrl}</p>
                        <p className="text-sm text-muted-foreground mt-1 px-4">
                            Connect this site to your AppNatively workspace to sync content and trigger mobile builds.
                        </p>
                    </div>
                </div>
            </CardContent>
            <CardFooter className="flex flex-col gap-4">
                <div className="w-full flex flex-col gap-3">
                    <Button 
                        className="w-full py-6 text-lg font-bold shadow-lg hover:shadow-xl transition-all" 
                        onClick={handleConnect} 
                        disabled={isLoading}
                    >
                        {isLoading ? 'Connecting...' : 'Connect to AppNatively'}
                        <ExternalLink className="w-4 h-4 ml-2" />
                    </Button>
                    <Button 
                        variant="ghost" 
                        size="sm"
                        className="text-muted-foreground hover:text-foreground"
                        onClick={handleCheckConnection}
                        disabled={isChecking || isLoading}
                    >
                        {isChecking ? 'Checking...' : 'Already connected? Click to refresh'}
                    </Button>
                </div>
            </CardFooter>
        </Card>
    );
}
