import React from "react";
import { 
    LayoutDashboard, 
    Smartphone, 
    Bell, 
    Settings, 
    Search, 
    Plus, 
    CheckCircle2, 
    Clock, 
    ChevronRight,
    ArrowUpRight,
    Users,
    Activity,
    Zap,
    Globe
} from "lucide-react";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle, CardFooter } from "@/components/ui/card";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Progress } from "@/components/ui/progress";
import { Separator } from "@/components/ui/separator";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Switch } from "@/components/ui/switch";

export default function App() {
    return (
        <div className="flex h-screen bg-background text-foreground overflow-hidden">
            {/* Sidebar */}
            <aside className="w-16 lg:w-64 border-r bg-card flex flex-col transition-all duration-300 ease-in-out">
                <div className="h-16 flex items-center px-4 mb-6">
                    <div className="w-8 h-8 rounded-lg bg-primary flex items-center justify-center shrink-0">
                        <Zap className="text-primary-foreground size-5 fill-primary-foreground" />
                    </div>
                    <span className="ml-3 font-bold text-xl lg:block hidden tracking-tight">AppNatively</span>
                </div>

                <nav className="flex-1 px-3 space-y-1">
                    <NavItem icon={<LayoutDashboard size={20} />} label="Dashboard" active />
                    <NavItem icon={<Smartphone size={20} />} label="Mobile Apps" />
                    <NavItem icon={<Activity size={20} />} label="Analytics" />
                    <NavItem icon={<Globe size={20} />} label="Deployments" />
                    <Separator className="my-4 mx-2" />
                    <NavItem icon={<Bell size={20} />} label="Notifications" badge="3" />
                    <NavItem icon={<Settings size={20} />} label="Settings" />
                </nav>

                <div className="p-4 border-t">
                    <div className="flex items-center">
                        <Avatar className="size-8">
                            <AvatarImage src="https://github.com/shadcn.png" />
                            <AvatarFallback>AD</AvatarFallback>
                        </Avatar>
                        <div className="ml-3 lg:block hidden overflow-hidden">
                            <p className="text-sm font-medium leading-none truncate">Admin User</p>
                            <p className="text-xs text-muted-foreground truncate">admin@appnatively.com</p>
                        </div>
                    </div>
                </div>
            </aside>

            {/* Main Content */}
            <main className="flex-1 flex flex-col min-w-0 bg-muted/30">
                {/* Top Header */}
                <header className="h-16 border-b bg-card flex items-center justify-between px-8 z-10">
                    <div className="flex items-center flex-1 max-w-md">
                        <div className="relative w-full">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground size-4" />
                            <Input 
                                placeholder="Search apps, builds, users..." 
                                className="pl-9 h-9 bg-muted/50 border-none focus-visible:ring-1"
                            />
                        </div>
                    </div>
                    <div className="flex items-center space-x-4">
                        <Button variant="ghost" size="icon" className="relative text-muted-foreground">
                            <Bell size={18} />
                            <span className="absolute top-2 right-2 size-2 bg-destructive rounded-full border-2 border-background"></span>
                        </Button>
                        <Button size="sm" className="hidden sm:flex gap-2 font-medium shadow-lg shadow-primary/20">
                            <Plus size={16} />
                            New App
                        </Button>
                    </div>
                </header>

                {/* Dashboard Scroll Area */}
                <ScrollArea className="flex-1">
                    <div className="p-8 max-w-7xl mx-auto space-y-8">
                        {/* Welcome Heading */}
                        <div className="flex flex-col md:flex-row md:items-end justify-between gap-4">
                            <div>
                                <h1 className="text-3xl font-bold tracking-tight">Workspace Overview</h1>
                                <p className="text-muted-foreground mt-1 text-lg">Manage your app ecosystem and monitor build health.</p>
                            </div>
                            <Tabs defaultValue="overview" className="w-[300px]">
                                <TabsList className="grid w-full grid-cols-2">
                                    <TabsTrigger value="overview">Overview</TabsTrigger>
                                    <TabsTrigger value="builds">Builds</TabsTrigger>
                                </TabsList>
                            </Tabs>
                        </div>

                        {/* Top Stats */}
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <StatCard 
                                title="Active Users" 
                                value="24,512" 
                                change="+12.5%" 
                                icon={<Users className="size-4 text-primary" />} 
                            />
                            <StatCard 
                                title="App Store Rating" 
                                value="4.8" 
                                change="+0.2 this month" 
                                icon={<Smartphone className="size-4 text-primary" />} 
                            />
                            <StatCard 
                                title="Build Success Rate" 
                                value="99.2%" 
                                change="Stable" 
                                icon={<CheckCircle2 className="size-4 text-emerald-500" />} 
                            />
                            <StatCard 
                                title="Active Instances" 
                                value="156" 
                                change="+4 since yesterday" 
                                icon={<Activity className="size-4 text-primary" />} 
                            />
                        </div>

                        {/* Main Layout Grid */}
                        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            {/* Left: Recent Activity */}
                            <Card className="lg:col-span-2 border-none shadow-sm shadow-muted/50">
                                <CardHeader className="flex flex-row items-center justify-between pb-2">
                                    <div className="space-y-1">
                                        <CardTitle className="text-xl">Recent Build Activity</CardTitle>
                                        <CardDescription>Real-time status of your CI/CD pipelines.</CardDescription>
                                    </div>
                                    <Button variant="outline" size="sm" className="font-medium">View All</Button>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-6 mt-4">
                                        <ActivityItem 
                                            title="Production iOS Build" 
                                            app="AppNatively Pro" 
                                            status="Success" 
                                            time="14m ago"
                                            success
                                        />
                                        <ActivityItem 
                                            title="Beta Android V2.4" 
                                            app="Ecommerce Client" 
                                            status="Building" 
                                            time="Active now"
                                            progress={65}
                                        />
                                        <ActivityItem 
                                            title="Hotfix: Version 1.0.4" 
                                            app="AppNatively Pro" 
                                            status="Queued" 
                                            time="32m ago"
                                        />
                                        <ActivityItem 
                                            title="Development Sandbox" 
                                            app="Test App v9" 
                                            status="Failed" 
                                            time="1h ago"
                                            failed
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Right Side: Quick Config & Health */}
                            <div className="space-y-6">
                                <Card className="border-none shadow-sm">
                                    <CardHeader className="pb-3">
                                        <CardTitle className="text-lg">System Health</CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-6">
                                        <div className="space-y-2">
                                            <div className="flex items-center justify-between text-sm">
                                                <span className="text-muted-foreground">API Load</span>
                                                <span className="font-medium">Normal</span>
                                            </div>
                                            <Progress value={35} className="h-1.5" />
                                        </div>
                                        <div className="space-y-2">
                                            <div className="flex items-center justify-between text-sm">
                                                <span className="text-muted-foreground">Cloud Storage</span>
                                                <span className="font-medium text-destructive">82% Capacity</span>
                                            </div>
                                            <Progress value={82} className="h-1.5 bg-muted" />
                                        </div>
                                    </CardContent>
                                    <CardFooter className="pt-0">
                                        <Button variant="ghost" size="sm" className="w-full justify-between group">
                                            System Logs
                                            <ChevronRight size={14} className="group-hover:translate-x-1 transition-transform" />
                                        </Button>
                                    </CardFooter>
                                </Card>

                                <Card className="border-none shadow-sm overflow-hidden">
                                    <div className="bg-primary/5 p-6 space-y-4">
                                        <CardTitle className="text-lg">Quick Access</CardTitle>
                                        <div className="space-y-4">
                                            <div className="flex items-center justify-between">
                                                <div className="space-y-0.5">
                                                    <Label className="text-sm font-medium">Automatic Backups</Label>
                                                    <p className="text-xs text-muted-foreground">Sync to cloud daily</p>
                                                </div>
                                                <Switch defaultChecked />
                                            </div>
                                            <div className="flex items-center justify-between">
                                                <div className="space-y-0.5">
                                                    <Label className="text-sm font-medium">Auto-push Beta</Label>
                                                    <p className="text-xs text-muted-foreground">Push successful builds</p>
                                                </div>
                                                <Switch />
                                            </div>
                                            <Separator className="bg-primary/10" />
                                            <Button className="w-full bg-primary hover:bg-primary/90">
                                                Run Full Diagnostic
                                            </Button>
                                        </div>
                                    </div>
                                </Card>
                            </div>
                        </div>
                    </div>
                </ScrollArea>
            </main>
        </div>
    );
}

function NavItem({ icon, label, active = false, badge }: { icon: React.ReactNode, label: string, active?: boolean, badge?: string }) {
    return (
        <a 
            href="#" 
            className={`
                flex items-center h-10 px-3 rounded-md transition-all duration-200 group
                ${active 
                    ? "bg-primary text-primary-foreground shadow-md shadow-primary/20" 
                    : "text-muted-foreground hover:bg-muted hover:text-foreground"
                }
            `}
        >
            <span className="shrink-0">{icon}</span>
            <span className="ml-3 lg:block hidden text-sm font-medium flex-1 truncate">{label}</span>
            {badge && (
                <Badge variant={active ? "secondary" : "default"} className="ml-auto size-5 rounded-full p-0 flex items-center justify-center text-[10px] lg:flex hidden">
                    {badge}
                </Badge>
            )}
        </a>
    );
}

function StatCard({ title, value, change, icon }: { title: string, value: string, change: string, icon: React.ReactNode }) {
    return (
        <Card className="border-none shadow-sm shadow-muted/60 transition-all hover:shadow-md hover:shadow-primary/5 cursor-pointer group">
            <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
                <CardTitle className="text-xs font-medium text-muted-foreground uppercase tracking-wider">{title}</CardTitle>
                <div className="p-2 bg-muted/50 rounded-lg group-hover:bg-primary/10 transition-colors">
                    {icon}
                </div>
            </CardHeader>
            <CardContent>
                <div className="text-2xl font-bold">{value}</div>
                <div className="flex items-center mt-1">
                    <span className={`text-[10px] font-medium ${change.startsWith('+') ? 'text-emerald-500' : change === 'Stable' ? 'text-blue-500' : 'text-muted-foreground'}`}>
                        {change}
                    </span>
                    <ArrowUpRight size={10} className={`ml-1 ${change.startsWith('+') ? 'text-emerald-500' : 'hidden'}`} />
                </div>
            </CardContent>
        </Card>
    );
}

function ActivityItem({ title, app, status, time, success, failed, progress }: { title: string, app: string, status: string, time: string, success?: boolean, failed?: boolean, progress?: number }) {
    return (
        <div className="flex items-center justify-between group hover:bg-muted/30 p-2 -mx-2 rounded-lg transition-colors cursor-pointer">
            <div className="flex items-center space-x-4 min-w-0">
                <div className={`size-10 rounded-full flex items-center justify-center shrink-0 ${
                    success ? 'bg-emerald-100/50' : 
                    failed ? 'bg-destructive/10' : 
                    progress ? 'bg-primary/10' : 'bg-muted/50'
                }`}>
                    {success ? <CheckCircle2 className="size-5 text-emerald-500" /> : 
                     failed ? <Zap className="size-5 text-destructive" /> : 
                     <Clock className="size-5 text-muted-foreground" />}
                </div>
                <div className="min-w-0">
                    <p className="text-sm font-semibold truncate leading-tight">{title}</p>
                    <div className="flex items-center text-xs text-muted-foreground mt-0.5">
                        <span className="font-medium text-primary/70">{app}</span>
                        <span className="mx-1.5 opacity-30">•</span>
                        <span>{time}</span>
                    </div>
                </div>
            </div>
            
            <div className="flex flex-col items-end gap-1.5 ml-4 shrink-0 px-2 min-w-[100px]">
                {progress ? (
                    <div className="w-full space-y-1">
                        <div className="flex justify-between text-[10px] font-medium">
                            <span>{status}</span>
                            <span>{progress}%</span>
                        </div>
                        <Progress value={progress} className="h-1" />
                    </div>
                ) : (
                    <Badge variant="outline" className={`text-[10px] py-0 px-2 h-5 ${success ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' : ''}`}>
                        {status}
                    </Badge>
                )}
            </div>
        </div>
    );
}