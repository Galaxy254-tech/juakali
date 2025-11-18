'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth, useRoleAuth } from '@/hooks/useAuth';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
  TrendingUp,
  TrendingDown,
  DollarSign,
  Package,
  Users,
  CreditCard,
  ArrowUpRight,
  ArrowDownRight,
  Calendar,
  Clock,
  CheckCircle,
  AlertCircle,
  BarChart3,
  ShoppingCart,
  Building,
  Target
} from 'lucide-react';

interface StatCard {
  title: string;
  value: string;
  change: number;
  changeLabel: string;
  icon: React.ReactNode;
  color: string;
}

interface RecentActivity {
  id: string;
  type: 'order' | 'payment' | 'loan' | 'kyc';
  title: string;
  description: string;
  amount?: string;
  status: 'completed' | 'pending' | 'failed';
  timestamp: string;
}

export default function DashboardPage() {
  const router = useRouter();
  const { user } = useAuth();
  const { hasAccess } = useRoleAuth(['retailer', 'lender', 'supplier', 'admin']);
  const [stats, setStats] = useState<StatCard[]>([]);
  const [recentActivities, setRecentActivities] = useState<RecentActivity[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!user) {
      router.push('/auth/login');
      return;
    }

    // Load dashboard data based on user role
    loadDashboardData();
  }, [user, router]);

  const loadDashboardData = async () => {
    setLoading(true);
    try {
      // This would normally call the actual API endpoints
      // For now, we'll use mock data based on the user role
      const mockData = getMockDataForRole(user!.role);
      setStats(mockData.stats);
      setRecentActivities(mockData.activities);
    } catch (error) {
      console.error('Failed to load dashboard data:', error);
    } finally {
      setLoading(false);
    }
  };

  const getMockDataForRole = (role: string) => {
    switch (role) {
      case 'retailer':
        return {
          stats: [
            {
              title: 'Available Credit',
              value: 'KES 45,000',
              change: 12.5,
              changeLabel: 'from last month',
              icon: <CreditCard className="w-5 h-5" />,
              color: 'bg-blue-500'
            },
            {
              title: 'Active Orders',
              value: '23',
              change: -5.2,
              changeLabel: 'from last week',
              icon: <ShoppingCart className="w-5 h-5" />,
              color: 'bg-emerald-500'
            },
            {
              title: 'Credit Score',
              value: '750',
              change: 8.1,
              changeLabel: 'improvement',
              icon: <TrendingUp className="w-5 h-5" />,
              color: 'bg-purple-500'
            },
            {
              title: 'Pending Payments',
              value: 'KES 12,500',
              change: 0,
              changeLabel: 'due this week',
              icon: <DollarSign className="w-5 h-5" />,
              color: 'bg-orange-500'
            }
          ],
          activities: [
            {
              id: '1',
              type: 'order',
              title: 'Order #12345',
              description: 'Electronics Supplies Ltd',
              amount: 'KES 8,500',
              status: 'completed',
              timestamp: '2 hours ago'
            },
            {
              id: '2',
              type: 'payment',
              title: 'Payment Received',
              description: 'Credit facility payment',
              amount: 'KES 2,000',
              status: 'completed',
              timestamp: '5 hours ago'
            },
            {
              id: '3',
              type: 'loan',
              title: 'Credit Application',
              description: 'Increased credit limit request',
              status: 'pending',
              timestamp: '1 day ago'
            }
          ]
        };

      case 'lender':
        return {
          stats: [
            {
              title: 'Total Invested',
              value: 'KES 850,000',
              change: 15.3,
              changeLabel: 'this month',
              icon: <DollarSign className="w-5 h-5" />,
              color: 'bg-green-500'
            },
            {
              title: 'Active Loans',
              value: '45',
              change: 8.7,
              changeLabel: 'new this month',
              icon: <Target className="w-5 h-5" />,
              color: 'bg-blue-500'
            },
            {
              title: 'Returns',
              value: '12.5%',
              change: 2.1,
              changeLabel: 'APR',
              icon: <TrendingUp className="w-5 h-5" />,
              color: 'bg-purple-500'
            },
            {
              title: 'Repayment Rate',
              value: '96.5%',
              change: 1.2,
              changeLabel: 'improvement',
              icon: <CheckCircle className="w-5 h-5" />,
              color: 'bg-emerald-500'
            }
          ],
          activities: [
            {
              id: '1',
              type: 'loan',
              title: 'New Loan Funded',
              description: 'John Kamau - Retail goods',
              amount: 'KES 25,000',
              status: 'completed',
              timestamp: '3 hours ago'
            },
            {
              id: '2',
              type: 'payment',
              title: 'Payment Received',
              description: 'Loan #89234 repayment',
              amount: 'KES 3,500',
              status: 'completed',
              timestamp: '6 hours ago'
            },
            {
              id: '3',
              type: 'loan',
              title: 'Loan Application',
              description: 'Mary Wanjiku - Expansion loan',
              status: 'pending',
              timestamp: '2 days ago'
            }
          ]
        };

      case 'supplier':
        return {
          stats: [
            {
              title: 'Total Orders',
              value: '156',
              change: 18.2,
              changeLabel: 'this month',
              icon: <Package className="w-5 h-5" />,
              color: 'bg-blue-500'
            },
            {
              title: 'Revenue',
              value: 'KES 245,000',
              change: 22.5,
              changeLabel: 'this month',
              icon: <DollarSign className="w-5 h-5" />,
              color: 'bg-green-500'
            },
            {
              title: 'Active Products',
              value: '89',
              change: 5.0,
              changeLabel: 'new products',
              icon: <Building className="w-5 h-5" />,
              color: 'bg-purple-500'
            },
            {
              title: 'Pending Deliveries',
              value: '12',
              change: -8.3,
              changeLabel: 'from last week',
              icon: <Clock className="w-5 h-5" />,
              color: 'bg-orange-500'
            }
          ],
          activities: [
            {
              id: '1',
              type: 'order',
              title: 'New Order Received',
              description: 'Retailer Store #234',
              amount: 'KES 15,000',
              status: 'completed',
              timestamp: '1 hour ago'
            },
            {
              id: '2',
              type: 'order',
              title: 'Order Shipped',
              description: 'Electronics order #567',
              amount: 'KES 22,000',
              status: 'completed',
              timestamp: '4 hours ago'
            },
            {
              id: '3',
              type: 'order',
              title: 'Order Processing',
              description: 'Bulk order from SuperMart',
              status: 'pending',
              timestamp: '1 day ago'
            }
          ]
        };

      case 'admin':
        return {
          stats: [
            {
              title: 'Total Users',
              value: '12,456',
              change: 25.3,
              changeLabel: 'this month',
              icon: <Users className="w-5 h-5" />,
              color: 'bg-blue-500'
            },
            {
              title: 'Platform Volume',
              value: 'KES 8.5M',
              change: 18.7,
              changeLabel: 'this month',
              icon: <BarChart3 className="w-5 h-5" />,
              color: 'bg-green-500'
            },
            {
              title: 'Active Loans',
              value: '1,245',
              change: 12.5,
              changeLabel: 'current period',
              icon: <Target className="w-5 h-5" />,
              color: 'bg-purple-500'
            },
            {
              title: 'Default Rate',
              value: '3.5%',
              change: -0.8,
              changeLabel: 'improvement',
              icon: <TrendingDown className="w-5 h-5" />,
              color: 'bg-emerald-500'
            }
          ],
          activities: [
            {
              id: '1',
              type: 'kyc',
              title: 'KYC Verification',
              description: 'New user verification completed',
              status: 'completed',
              timestamp: '2 hours ago'
            },
            {
              id: '2',
              type: 'loan',
              title: 'High Value Loan',
              description: 'KES 500,000 loan approved',
              amount: 'KES 500,000',
              status: 'completed',
              timestamp: '5 hours ago'
            },
            {
              id: '3',
              type: 'payment',
              title: 'Gateway Issue',
              description: 'M-Pesa integration error',
              status: 'failed',
              timestamp: '6 hours ago'
            }
          ]
        };

      default:
        return { stats: [], activities: [] };
    }
  };

  const getActivityIcon = (type: string) => {
    switch (type) {
      case 'order':
        return <Package className="w-4 h-4" />;
      case 'payment':
        return <DollarSign className="w-4 h-4" />;
      case 'loan':
        return <CreditCard className="w-4 h-4" />;
      case 'kyc':
        return <Users className="w-4 h-4" />;
      default:
        return <AlertCircle className="w-4 h-4" />;
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'completed':
        return 'bg-green-100 text-green-800';
      case 'pending':
        return 'bg-yellow-100 text-yellow-800';
      case 'failed':
        return 'bg-red-100 text-red-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  if (!user || !hasAccess) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="animate-spin rounded-full h-32 w-32 border-b-2 border-emerald-600"></div>
      </div>
    );
  }

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="animate-spin rounded-full h-32 w-32 border-b-2 border-emerald-600"></div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Welcome Header */}
      <div>
        <h1 className="text-3xl font-bold text-emerald-900">
          Welcome back, {user.name || 'User'}!
        </h1>
        <p className="text-emerald-700 mt-2">
          Here's what's happening with your {user.role === 'retailer' ? 'business' : user.role === 'lender' ? 'portfolio' : user.role === 'supplier' ? 'store' : 'platform'} today.
        </p>
      </div>

      {/* Stats Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {stats.map((stat, index) => (
          <Card key={index} className="border-emerald-200">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium text-emerald-700">
                {stat.title}
              </CardTitle>
              <div className={`p-2 rounded-md ${stat.color} bg-opacity-10`}>
                <div className={`${stat.color.replace('bg-', 'text-')} bg-opacity-100`}>
                  {stat.icon}
                </div>
              </div>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-emerald-900">{stat.value}</div>
              <div className="flex items-center space-x-1 text-xs text-emerald-600 mt-1">
                {stat.change > 0 ? (
                  <ArrowUpRight className="w-3 h-3 text-green-600" />
                ) : stat.change < 0 ? (
                  <ArrowDownRight className="w-3 h-3 text-red-600" />
                ) : null}
                <span className={stat.change > 0 ? 'text-green-600' : stat.change < 0 ? 'text-red-600' : 'text-emerald-600'}>
                  {stat.change !== 0 ? `${Math.abs(stat.change)}%` : ''}
                </span>
                <span>{stat.changeLabel}</span>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      {/* Quick Actions */}
      <Card className="border-emerald-200">
        <CardHeader>
          <CardTitle className="text-emerald-900">Quick Actions</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            {user.role === 'retailer' && (
              <>
                <Button
                  onClick={() => router.push('/dashboard/products')}
                  className="flex flex-col items-center gap-2 h-auto py-4"
                >
                  <Package className="w-6 h-6" />
                  <span>Browse Products</span>
                </Button>
                <Button
                  onClick={() => router.push('/dashboard/credit')}
                  variant="outline"
                  className="flex flex-col items-center gap-2 h-auto py-4 border-emerald-200 hover:bg-emerald-50"
                >
                  <CreditCard className="w-6 h-6" />
                  <span>Apply for Credit</span>
                </Button>
                <Button
                  onClick={() => router.push('/dashboard/orders')}
                  variant="outline"
                  className="flex flex-col items-center gap-2 h-auto py-4 border-emerald-200 hover:bg-emerald-50"
                >
                  <ShoppingCart className="w-6 h-6" />
                  <span>View Orders</span>
                </Button>
                <Button
                  onClick={() => router.push('/dashboard/settings')}
                  variant="outline"
                  className="flex flex-col items-center gap-2 h-auto py-4 border-emerald-200 hover:bg-emerald-50"
                >
                  <Users className="w-6 h-6" />
                  <span>Complete KYC</span>
                </Button>
              </>
            )}

            {user.role === 'lender' && (
              <>
                <Button
                  onClick={() => router.push('/dashboard/loans')}
                  className="flex flex-col items-center gap-2 h-auto py-4"
                >
                  <Target className="w-6 h-6" />
                  <span>Review Applications</span>
                </Button>
                <Button
                  onClick={() => router.push('/dashboard/portfolio')}
                  variant="outline"
                  className="flex flex-col items-center gap-2 h-auto py-4 border-emerald-200 hover:bg-emerald-50"
                >
                  <BarChart3 className="w-6 h-6" />
                  <span>Portfolio Analysis</span>
                </Button>
                <Button
                  onClick={() => router.push('/dashboard/analytics')}
                  variant="outline"
                  className="flex flex-col items-center gap-2 h-auto py-4 border-emerald-200 hover:bg-emerald-50"
                >
                  <TrendingUp className="w-6 h-6" />
                  <span>View Analytics</span>
                </Button>
                <Button
                  onClick={() => router.push('/dashboard/settings')}
                  variant="outline"
                  className="flex flex-col items-center gap-2 h-auto py-4 border-emerald-200 hover:bg-emerald-50"
                >
                  <DollarSign className="w-6 h-6" />
                  <span>Payment Settings</span>
                </Button>
              </>
            )}

            {user.role === 'supplier' && (
              <>
                <Button
                  onClick={() => router.push('/dashboard/products')}
                  className="flex flex-col items-center gap-2 h-auto py-4"
                >
                  <Package className="w-6 h-6" />
                  <span>Add Products</span>
                </Button>
                <Button
                  onClick={() => router.push('/dashboard/orders')}
                  variant="outline"
                  className="flex flex-col items-center gap-2 h-auto py-4 border-emerald-200 hover:bg-emerald-50"
                >
                  <ShoppingCart className="w-6 h-6" />
                  <span>Manage Orders</span>
                </Button>
                <Button
                  onClick={() => router.push('/dashboard/inventory')}
                  variant="outline"
                  className="flex flex-col items-center gap-2 h-auto py-4 border-emerald-200 hover:bg-emerald-50"
                >
                  <Building className="w-6 h-6" />
                  <span>Inventory</span>
                </Button>
                <Button
                  onClick={() => router.push('/dashboard/analytics')}
                  variant="outline"
                  className="flex flex-col items-center gap-2 h-auto py-4 border-emerald-200 hover:bg-emerald-50"
                >
                  <BarChart3 className="w-6 h-6" />
                  <span>Sales Analytics</span>
                </Button>
              </>
            )}

            {user.role === 'admin' && (
              <>
                <Button
                  onClick={() => router.push('/dashboard/users')}
                  className="flex flex-col items-center gap-2 h-auto py-4"
                >
                  <Users className="w-6 h-6" />
                  <span>Manage Users</span>
                </Button>
                <Button
                  onClick={() => router.push('/dashboard/reports')}
                  variant="outline"
                  className="flex flex-col items-center gap-2 h-auto py-4 border-emerald-200 hover:bg-emerald-50"
                >
                  <BarChart3 className="w-6 h-6" />
                  <span>View Reports</span>
                </Button>
                <Button
                  onClick={() => router.push('/dashboard/loans')}
                  variant="outline"
                  className="flex flex-col items-center gap-2 h-auto py-4 border-emerald-200 hover:bg-emerald-50"
                >
                  <CreditCard className="w-6 h-6" />
                  <span>Loan Management</span>
                </Button>
                <Button
                  onClick={() => router.push('/dashboard/analytics')}
                  variant="outline"
                  className="flex flex-col items-center gap-2 h-auto py-4 border-emerald-200 hover:bg-emerald-50"
                >
                  <TrendingUp className="w-6 h-6" />
                  <span>Platform Analytics</span>
                </Button>
              </>
            )}
          </div>
        </CardContent>
      </Card>

      {/* Recent Activity */}
      <Card className="border-emerald-200">
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle className="text-emerald-900">Recent Activity</CardTitle>
          <Button variant="outline" size="sm" className="border-emerald-200 hover:bg-emerald-50">
            View All
          </Button>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            {recentActivities.map((activity) => (
              <div key={activity.id} className="flex items-center space-x-4">
                <div className="flex-shrink-0">
                  <div className="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center text-emerald-600">
                    {getActivityIcon(activity.type)}
                  </div>
                </div>
                <div className="flex-1 min-w-0">
                  <div className="flex items-center justify-between">
                    <p className="text-sm font-medium text-emerald-900 truncate">
                      {activity.title}
                    </p>
                    <Badge className={`text-xs ${getStatusColor(activity.status)}`}>
                      {activity.status}
                    </Badge>
                  </div>
                  <p className="text-sm text-emerald-700">{activity.description}</p>
                  {activity.amount && (
                    <p className="text-sm font-semibold text-emerald-900">{activity.amount}</p>
                  )}
                  <div className="flex items-center mt-1 text-xs text-emerald-600">
                    <Clock className="w-3 h-3 mr-1" />
                    {activity.timestamp}
                  </div>
                </div>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}