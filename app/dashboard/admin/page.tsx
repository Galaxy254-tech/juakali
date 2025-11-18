'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth, useRoleAuth } from '@/hooks/useAuth';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
  Users,
  DollarSign,
  TrendingUp,
  Target,
  AlertTriangle,
  CheckCircle,
  Clock,
  BarChart3,
  FileText,
  Shield,
  Settings,
  Activity,
  CreditCard,
  Building,
  Eye
} from 'lucide-react';

export default function AdminDashboardPage() {
  const router = useRouter();
  const { user } = useAuth();
  const { hasAccess } = useRoleAuth(['admin']);

  useEffect(() => {
    if (!user || user.role !== 'admin') {
      router.push('/dashboard');
    }
  }, [user, router]);

  if (!user || user.role !== 'admin' || !hasAccess) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="animate-spin rounded-full h-32 w-32 border-b-2 border-emerald-600"></div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-3xl font-bold text-emerald-900">Administrator Dashboard</h1>
        <p className="text-emerald-700 mt-2">Monitor and manage the entire JuaKali Lend platform</p>
      </div>

      {/* Platform Overview Stats */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <Card className="border-emerald-200">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium text-emerald-700">Total Users</CardTitle>
            <Users className="h-4 w-4 text-blue-600" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-emerald-900">12,456</div>
            <p className="text-xs text-emerald-600 mt-1">+25.3% this month</p>
          </CardContent>
        </Card>

        <Card className="border-emerald-200">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium text-emerald-700">Platform Volume</CardTitle>
            <DollarSign className="h-4 w-4 text-green-600" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-emerald-900">KES 8.5M</div>
            <p className="text-xs text-emerald-600 mt-1">+18.7% this month</p>
          </CardContent>
        </Card>

        <Card className="border-emerald-200">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium text-emerald-700">Active Loans</CardTitle>
            <Target className="h-4 w-4 text-purple-600" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-emerald-900">1,245</div>
            <p className="text-xs text-emerald-600 mt-1">+12.5% current period</p>
          </CardContent>
        </Card>

        <Card className="border-emerald-200">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium text-emerald-700">Default Rate</CardTitle>
            <TrendingUp className="h-4 w-4 text-emerald-600" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-emerald-900">3.5%</div>
            <p className="text-xs text-emerald-600 mt-1">-0.8% improvement</p>
          </CardContent>
        </Card>
      </div>

      {/* Admin Actions */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <Card className="border-emerald-200">
          <CardHeader>
            <CardTitle className="text-emerald-900 flex items-center gap-2">
              <Users className="w-5 h-5" />
              User Management
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-emerald-700 mb-4">Manage user accounts and permissions</p>
            <Button
              onClick={() => router.push('/dashboard/users')}
              className="w-full bg-emerald-600 hover:bg-emerald-700"
            >
              Manage Users
            </Button>
          </CardContent>
        </Card>

        <Card className="border-emerald-200">
          <CardHeader>
            <CardTitle className="text-emerald-900 flex items-center gap-2">
              <CreditCard className="w-5 h-5" />
              Loan Oversight
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-emerald-700 mb-4">Monitor and approve high-value loans</p>
            <Button
              onClick={() => router.push('/dashboard/loans')}
              className="w-full bg-emerald-600 hover:bg-emerald-700"
            >
              Review Loans
            </Button>
          </CardContent>
        </Card>

        <Card className="border-emerald-200">
          <CardHeader>
            <CardTitle className="text-emerald-900 flex items-center gap-2">
              <BarChart3 className="w-5 h-5" />
              Analytics
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-emerald-700 mb-4">View platform analytics and insights</p>
            <Button
              onClick={() => router.push('/dashboard/analytics')}
              className="w-full bg-emerald-600 hover:bg-emerald-700"
            >
              View Analytics
            </Button>
          </CardContent>
        </Card>

        <Card className="border-emerald-200">
          <CardHeader>
            <CardTitle className="text-emerald-900 flex items-center gap-2">
              <FileText className="w-5 h-5" />
              Reports
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-emerald-700 mb-4">Generate compliance and financial reports</p>
            <Button
              onClick={() => router.push('/dashboard/reports')}
              className="w-full bg-emerald-600 hover:bg-emerald-700"
            >
              Generate Reports
            </Button>
          </CardContent>
        </Card>
      </div>

      {/* System Alerts */}
      <Card className="border-emerald-200">
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle className="text-emerald-900">System Alerts</CardTitle>
          <Badge className="bg-red-100 text-red-800">3 Critical</Badge>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            {[
              { type: 'critical', title: 'Payment Gateway Issue', description: 'M-Pesa integration experiencing delays', time: '2 hours ago' },
              { type: 'warning', title: 'High Volume Transactions', description: 'Unusual spike in loan applications', time: '4 hours ago' },
              { type: 'info', title: 'Scheduled Maintenance', description: 'System backup scheduled for tonight 11 PM', time: '6 hours ago' },
              { type: 'critical', title: 'Security Alert', description: 'Multiple failed login attempts detected', time: '8 hours ago' },
              { type: 'warning', title: 'Low Balance Alert', description: 'System reserve account below threshold', time: '1 day ago' },
            ].map((alert, index) => (
              <div key={index} className="flex items-start space-x-4 p-4 border border-emerald-200 rounded-lg">
                <div className={`w-2 h-2 rounded-full mt-2 flex-shrink-0 ${
                  alert.type === 'critical' ? 'bg-red-500' :
                  alert.type === 'warning' ? 'bg-yellow-500' :
                  'bg-blue-500'
                }`}></div>
                <div className="flex-1">
                  <div className="flex items-center justify-between">
                    <p className="font-medium text-emerald-900">{alert.title}</p>
                    <Badge className={
                      alert.type === 'critical' ? 'bg-red-100 text-red-800' :
                      alert.type === 'warning' ? 'bg-yellow-100 text-yellow-800' :
                      'bg-blue-100 text-blue-800'
                    }>
                      {alert.type.charAt(0).toUpperCase() + alert.type.slice(1)}
                    </Badge>
                  </div>
                  <p className="text-sm text-emerald-700 mt-1">{alert.description}</p>
                  <p className="text-xs text-emerald-600 mt-2">{alert.time}</p>
                </div>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* User Distribution */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <Card className="border-emerald-200">
          <CardHeader>
            <CardTitle className="text-emerald-900">User Distribution</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              <div className="flex justify-between items-center">
                <div className="flex items-center space-x-2">
                  <div className="w-3 h-3 bg-blue-500 rounded-full"></div>
                  <span className="text-sm text-emerald-700">Retailers</span>
                </div>
                <span className="font-semibold text-emerald-900">8,234 (66%)</span>
              </div>
              <div className="flex justify-between items-center">
                <div className="flex items-center space-x-2">
                  <div className="w-3 h-3 bg-green-500 rounded-full"></div>
                  <span className="text-sm text-emerald-700">Lenders</span>
                </div>
                <span className="font-semibold text-emerald-900">2,156 (17%)</span>
              </div>
              <div className="flex justify-between items-center">
                <div className="flex items-center space-x-2">
                  <div className="w-3 h-3 bg-orange-500 rounded-full"></div>
                  <span className="text-sm text-emerald-700">Suppliers</span>
                </div>
                <span className="font-semibold text-emerald-900">1,845 (15%)</span>
              </div>
              <div className="flex justify-between items-center">
                <div className="flex items-center space-x-2">
                  <div className="w-3 h-3 bg-purple-500 rounded-full"></div>
                  <span className="text-sm text-emerald-700">Admins</span>
                </div>
                <span className="font-semibold text-emerald-900">221 (2%)</span>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card className="border-emerald-200">
          <CardHeader>
            <CardTitle className="text-emerald-900">Recent Activity</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-3">
              {[
                { action: 'New User Registration', user: 'John Kamau (Retailer)', time: '5 mins ago', icon: <Users className="w-4 h-4" /> },
                { action: 'High Value Loan Approved', user: 'KES 500,000 - Mary Wanjiku', time: '15 mins ago', icon: <CreditCard className="w-4 h-4" /> },
                { action: 'KYC Verification Completed', user: 'Peter Ochieng (Supplier)', time: '1 hour ago', icon: <Shield className="w-4 h-4" /> },
                { action: 'System Backup Completed', user: 'Automated Process', time: '2 hours ago', icon: <Activity className="w-4 h-4" /> },
                { action: 'New Partnership', user: 'Electronics Kenya Ltd', time: '3 hours ago', icon: <Building className="w-4 h-4" /> },
              ].map((activity, index) => (
                <div key={index} className="flex items-center space-x-3 p-2">
                  <div className="w-8 h-8 bg-emerald-100 rounded-full flex items-center justify-center text-emerald-600">
                    {activity.icon}
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium text-emerald-900 truncate">{activity.action}</p>
                    <p className="text-xs text-emerald-700 truncate">{activity.user}</p>
                  </div>
                  <p className="text-xs text-emerald-600 whitespace-nowrap">{activity.time}</p>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Financial Overview */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <Card className="border-emerald-200">
          <CardHeader>
            <CardTitle className="text-emerald-900">Revenue Streams</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-3">
              <div className="flex justify-between items-center">
                <span className="text-sm text-emerald-700">Transaction Fees</span>
                <span className="font-semibold text-emerald-900">KES 425,000</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-sm text-emerald-700">Interest Revenue</span>
                <span className="font-semibold text-emerald-900">KES 1,250,000</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-sm text-emerald-700">Subscription Fees</span>
                <span className="font-semibold text-emerald-900">KES 185,000</span>
              </div>
              <div className="flex justify-between items-center pt-2 border-t border-emerald-200">
                <span className="font-medium text-emerald-900">Total Revenue</span>
                <span className="font-bold text-green-600">KES 1,860,000</span>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card className="border-emerald-200">
          <CardHeader>
            <CardTitle className="text-emerald-900">System Health</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-3">
              <div className="flex justify-between items-center">
                <span className="text-sm text-emerald-700">API Response Time</span>
                <span className="font-semibold text-green-600">145ms</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-sm text-emerald-700">Database Load</span>
                <span className="font-semibold text-yellow-600">67%</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-sm text-emerald-700">Server Uptime</span>
                <span className="font-semibold text-green-600">99.8%</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-sm text-emerald-700">Active Connections</span>
                <span className="font-semibold text-emerald-900">1,234</span>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card className="border-emerald-200">
          <CardHeader>
            <CardTitle className="text-emerald-900">Compliance Status</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-3">
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-2">
                  <CheckCircle className="w-4 h-4 text-green-600" />
                  <span className="text-sm text-emerald-700">KYC Compliance</span>
                </div>
                <span className="font-semibold text-green-600">98.5%</span>
              </div>
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-2">
                  <CheckCircle className="w-4 h-4 text-green-600" />
                  <span className="text-sm text-emerald-700">AML Screening</span>
                </div>
                <span className="font-semibold text-green-600">100%</span>
              </div>
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-2">
                  <AlertTriangle className="w-4 h-4 text-yellow-600" />
                  <span className="text-sm text-emerald-700">Data Privacy</span>
                </div>
                <span className="font-semibold text-yellow-600">In Review</span>
              </div>
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-2">
                  <CheckCircle className="w-4 h-4 text-green-600" />
                  <span className="text-sm text-emerald-700">Financial Audit</span>
                </div>
                <span className="font-semibold text-green-600">Passed</span>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}