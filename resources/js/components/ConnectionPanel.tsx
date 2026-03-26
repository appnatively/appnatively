import { useState } from 'react';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { Card, CardContent, CardDescription, CardHeader, CardTitle, CardFooter } from './ui/card';
import { Button } from './ui/button';
import { Badge } from './ui/badge';
import { Globe, AlertCircle, CheckCircle2 } from 'lucide-react';

declare const appnativelyData: {
    restUrl: string;
    nonce: string;
    siteUrl: string;
    connectionStatus: 'connected' | 'disconnected' | 'pending';
    connectedApp: string;
};

// Configure apiFetch to use the nonce
apiFetch.use( apiFetch.createNonceMiddleware( appnativelyData.nonce ) );

export default function ConnectionPanel() {
    const [ status, setStatus ] = useState( appnativelyData.connectionStatus );
    const [ appName, setAppName ] = useState( appnativelyData.connectedApp );
    const [ isLoading, setIsLoading ] = useState( false );
    const [ error, setError ] = useState<string | null>( null );

    const handleConnect = async () => {
        setIsLoading( true );
        setError( null );

        try {
            // Initiate connection via REST API
            const data: any = await apiFetch( {
                path: 'appnatively/v1/connection/init',
                method: 'POST',
            } );

            if ( data.success && data.redirectUrl ) {
                window.location.href = data.redirectUrl;
            } else {
                setError( data.message || 'Failed to initiate connection' );
            }
        } catch ( err: any ) {
            setError( err.message || 'An unexpected error occurred' );
        } finally {
            setIsLoading( false );
        }
    };

    const handleDisconnect = async () => {
        if ( ! confirm( 'Are you sure you want to disconnect from AppNatively?' ) ) return;
        
        setIsLoading( true );
        try {
            await apiFetch( {
                path: 'appnatively/v1/connection/disconnect',
                method: 'DELETE',
            } );
            
            setStatus( 'disconnected' );
            setAppName( '' );
        } catch ( err: any ) {
            setError( 'Failed to disconnect' );
        } finally {
            setIsLoading( false );
        }
    };

    return (
        <Card className="w-full max-w-2xl border-2">
            <CardHeader>
                <div className="flex items-center justify-between">
                    <div>
                        <CardTitle className="text-2xl font-bold flex items-center gap-2">
                            <Globe className="w-6 h-6 text-primary" />
                            AppNatively Connection
                        </CardTitle>
                        <CardDescription>
                            Link your WordPress site to the AppNatively platform
                        </CardDescription>
                    </div>
                    <Badge variant={status === 'connected' ? 'default' : 'secondary'} className="px-3 py-1">
                        {status === 'connected' ? 'Connected' : 'Not Connected'}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent className="space-y-6">
                {error && (
                    <div className="bg-destructive/10 border border-destructive text-destructive px-4 py-3 rounded-lg flex items-center gap-3">
                        <AlertCircle className="w-5 h-5" />
                        <p className="text-sm font-medium">{error}</p>
                    </div>
                )}

                <div className="grid gap-4">
                    <div className="flex items-center justify-between p-4 bg-muted/50 rounded-xl border border-border/50">
                        <div className="flex items-center gap-4">
                            <div className="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center">
                                <Globe className="w-5 h-5 text-primary" />
                            </div>
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">Site URL</p>
                                <p className="text-base font-semibold">{appnativelyData.siteUrl}</p>
                            </div>
                        </div>
                    </div>

                    {status === 'connected' && (
                        <div className="flex items-center justify-between p-4 bg-primary/5 rounded-xl border border-primary/20">
                            <div className="flex items-center gap-4">
                                <div className="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center">
                                    <CheckCircle2 className="w-5 h-5 text-primary" />
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">Connected App</p>
                                    <p className="text-base font-semibold">{appName || 'My Awesome App'}</p>
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </CardContent>
            <CardFooter className="flex flex-col gap-4">
                {status === 'connected' ? (
                    <div className="w-full flex gap-3">
                        <Button 
                            variant="outline" 
                            className="flex-1" 
                            onClick={() => {
                                const dashboardUrl = addQueryArgs('https://local.appnatively.com', {
                                    source_site: appnativelyData.siteUrl
                                });
                                window.open(dashboardUrl, '_blank');
                            }}
                        >
                            Open Dashboard
                        </Button>
                        <Button variant="destructive" className="flex-1" onClick={handleDisconnect} disabled={isLoading}>
                            Disconnect
                        </Button>
                    </div>
                ) : (
                    <Button className="w-full py-6 text-lg font-bold shadow-lg hover:shadow-xl transition-all" onClick={handleConnect} disabled={isLoading}>
                        {isLoading ? 'Connecting...' : 'Connect to AppNatively'}
                    </Button>
                )}
                <p className="text-xs text-center text-muted-foreground">
                    By connecting, you allow AppNatively to access your site content via the REST API.
                </p>
            </CardFooter>
        </Card>
    );
}
