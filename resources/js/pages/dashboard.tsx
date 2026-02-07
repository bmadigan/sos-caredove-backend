import { Head, useForm, usePage } from '@inertiajs/react';
import { Users, Smartphone, Bell, AlertTriangle } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import SosController from '@/actions/App/Http/Controllers/SosController';
import type { BreadcrumbItem, SharedData } from '@/types';

type DashboardUser = {
    id: string;
    name: string;
    email: string;
    has_device_token: boolean;
    created_at: string;
};

type DashboardSosAlert = {
    id: string;
    triggered_by_name: string;
    recipient_count: number;
    created_at: string;
};

type DashboardStats = {
    totalUsers: number;
    devicesRegistered: number;
    alertsToday: number;
};

type DashboardProps = {
    users: DashboardUser[];
    sosAlerts: DashboardSosAlert[];
    stats: DashboardStats;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function StatCard({ title, value, icon: Icon }: { title: string; value: number; icon: React.ElementType }) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
                <CardDescription>{title}</CardDescription>
                <Icon className="text-muted-foreground h-4 w-4" />
            </CardHeader>
            <CardContent>
                <div className="text-2xl font-bold">{value}</div>
            </CardContent>
        </Card>
    );
}

function SendSosDialog({ users }: { users: DashboardUser[] }) {
    const [open, setOpen] = useState(false);
    const eligibleUsers = users.filter((u) => u.has_device_token);
    const form = useForm({ recipient_ids: [] as string[] });

    const toggleRecipient = (userId: string) => {
        const current = form.data.recipient_ids;
        if (current.includes(userId)) {
            form.setData('recipient_ids', current.filter((id) => id !== userId));
        } else {
            form.setData('recipient_ids', [...current, userId]);
        }
    };

    const toggleAll = () => {
        if (form.data.recipient_ids.length === eligibleUsers.length) {
            form.setData('recipient_ids', []);
        } else {
            form.setData('recipient_ids', eligibleUsers.map((u) => u.id));
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(SosController.store().url, {
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="destructive" size="lg">
                    <AlertTriangle className="mr-2 h-4 w-4" />
                    Send SOS Alert
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={handleSubmit}>
                    <DialogHeader>
                        <DialogTitle>Send SOS Alert</DialogTitle>
                        <DialogDescription>
                            Select team members to receive the SOS push notification.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="py-4">
                        {eligibleUsers.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No team members have registered devices yet.</p>
                        ) : (
                            <div className="space-y-3">
                                <label className="flex items-center gap-2 rounded-md border p-3">
                                    <Checkbox
                                        checked={form.data.recipient_ids.length === eligibleUsers.length}
                                        onCheckedChange={toggleAll}
                                    />
                                    <span className="text-sm font-medium">Select All ({eligibleUsers.length})</span>
                                </label>
                                <div className="max-h-60 space-y-2 overflow-y-auto">
                                    {eligibleUsers.map((user) => (
                                        <label key={user.id} className="flex items-center gap-2 rounded-md border p-3">
                                            <Checkbox
                                                checked={form.data.recipient_ids.includes(user.id)}
                                                onCheckedChange={() => toggleRecipient(user.id)}
                                            />
                                            <div>
                                                <div className="text-sm font-medium">{user.name}</div>
                                                <div className="text-muted-foreground text-xs">{user.email}</div>
                                            </div>
                                        </label>
                                    ))}
                                </div>
                            </div>
                        )}
                        {form.errors.recipient_ids && (
                            <p className="mt-2 text-sm text-red-600">{form.errors.recipient_ids}</p>
                        )}
                        {form.errors.sos && (
                            <p className="mt-2 text-sm text-red-600">{form.errors.sos}</p>
                        )}
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={form.processing || form.data.recipient_ids.length === 0}
                        >
                            {form.processing ? 'Sending...' : 'Send SOS'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Dashboard({ users, sosAlerts, stats }: DashboardProps) {
    const { flash } = usePage<SharedData & { flash?: { success?: string } }>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                {/* Header with SOS button */}
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">Overview</h2>
                    <SendSosDialog users={users} />
                </div>

                {/* Flash success message */}
                {flash?.success && (
                    <div className="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200">
                        {flash.success}
                    </div>
                )}

                {/* Stats cards */}
                <div className="grid gap-4 md:grid-cols-3">
                    <StatCard title="Team Members" value={stats.totalUsers} icon={Users} />
                    <StatCard title="Devices Registered" value={stats.devicesRegistered} icon={Smartphone} />
                    <StatCard title="Alerts Today" value={stats.alertsToday} icon={Bell} />
                </div>

                {/* SOS Alert History */}
                <Card>
                    <CardHeader>
                        <CardTitle>SOS Alert History</CardTitle>
                        <CardDescription>Recent alerts sent to team members</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {sosAlerts.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No SOS alerts have been sent yet.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="pb-2 text-left font-medium">Triggered By</th>
                                            <th className="pb-2 text-left font-medium">Recipients</th>
                                            <th className="pb-2 text-left font-medium">Date/Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {sosAlerts.map((alert) => (
                                            <tr key={alert.id} className="border-b last:border-0">
                                                <td className="py-3">{alert.triggered_by_name}</td>
                                                <td className="py-3">
                                                    <Badge variant="secondary">{alert.recipient_count} people</Badge>
                                                </td>
                                                <td className="text-muted-foreground py-3">{formatDate(alert.created_at)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Team Members */}
                <Card>
                    <CardHeader>
                        <CardTitle>Team Members</CardTitle>
                        <CardDescription>Members and their device registration status</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b">
                                        <th className="pb-2 text-left font-medium">Name</th>
                                        <th className="pb-2 text-left font-medium">Email</th>
                                        <th className="pb-2 text-left font-medium">Device</th>
                                        <th className="pb-2 text-left font-medium">Joined</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {users.map((user) => (
                                        <tr key={user.id} className="border-b last:border-0">
                                            <td className="py-3 font-medium">{user.name}</td>
                                            <td className="text-muted-foreground py-3">{user.email}</td>
                                            <td className="py-3">
                                                {user.has_device_token ? (
                                                    <Badge className="bg-green-600">Registered</Badge>
                                                ) : (
                                                    <Badge variant="secondary">Not registered</Badge>
                                                )}
                                            </td>
                                            <td className="text-muted-foreground py-3">{formatDate(user.created_at)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
