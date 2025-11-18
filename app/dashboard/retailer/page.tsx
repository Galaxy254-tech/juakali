'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth, useRoleAuth } from '@/hooks/useAuth';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
  ShoppingBag,
  CreditCard,
  Package,
  TrendingUp,
  Clock,
  CheckCircle,
  AlertTriangle,
  DollarSign,
  BarChart3,
  Target
} from 'lucide-react';

export default function RetailerDashboardPage() {
  const router = useRouter();
  const { user } = useAuth();
  const { hasAccess } = useRoleAuth(['retailer']);

  useEffect(() => {
    if (!user || user.role !== 'retailer') {
      router.push('/dashboard');
    }
  }, [user, router]);

  if (!user || user.role !== 'retailer' || !hasAccess) {
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
        <h1 className="text-3xl font-bold text-emerald-900">Retailer Dashboard</h1>
        <p className="text-emerald-700 mt-2">Manage your orders, credit, and business operations</p>
      </div>

      {/* Quick Stats */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <Card className="border-emerald-200">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium text-emerald-700">Available Credit</CardTitle>
            <CreditCard className="h-4 w-4 text-blue-600" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-emerald-900">KES 45,000</div>
            <p className="text-xs text-emerald-600 mt-1">+12% from last month</p>
          </CardContent>
        </Card>

        <Card className="border-emerald-200">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium text-emerald-700">Active Orders</CardTitle>
            <ShoppingBag className="h-4 w-4 text-emerald-600" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-emerald-900">12</div>
            <p className="text-xs text-emerald-600 mt-1">3 pending delivery</p>
          </CardContent>
        </Card>

        <Card className="border-emerald-200">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium text-emerald-700">Credit Score</CardTitle>
            <TrendingUp className="h-4 w-4 text-green-600" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-emerald-900">750</div>
            <p className="text-xs text-emerald-600 mt-1">Excellent rating</p>
          </CardContent>
        </Card>

        <Card className="border-emerald-200">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium text-emerald-700">Monthly Spend</CardTitle>
            <DollarSign className="h-4 w-4 text-purple-600" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-emerald-900">KES 28,500</div>
            <p className="text-xs text-emerald-600 mt-1">-5% from last month</p>
          </CardContent>
        </Card>
      </div>

      {/* Quick Actions */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <Card className="border-emerald-200">
          <CardHeader>
            <CardTitle className="text-emerald-900">Browse Products</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-emerald-700 mb-4">Discover thousands of products from verified suppliers</p>
            <Button
              onClick={() => router.push('/dashboard/products')}
              className="w-full bg-emerald-600 hover:bg-emerald-700"
            >
              <ShoppingBag className="w-4 h-4 mr-2" />
              Start Shopping
            </Button>
          </CardContent>
        </Card>

        <Card className="border-emerald-200">
          <CardHeader>
            <CardTitle className="text-emerald-900">Apply for Credit</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-emerald-700 mb-4">Increase your credit limit for more purchasing power</p>
            <Button
              onClick={() => router.push('/dashboard/credit')}
              variant="outline"
              className="w-full border-emerald-200 hover:bg-emerald-50"
            >
              <CreditCard className="w-4 h-4 mr-2" />
              Apply Now
            </Button>
          </CardContent>
        </Card>

        <Card className="border-emerald-200">
          <CardHeader>
            <CardTitle className="text-emerald-900">Complete KYC</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-emerald-700 mb-4">Complete verification to unlock higher credit limits</p>
            <Button
              onClick={() => router.push('/dashboard/kyc')}
              variant="outline"
              className="w-full border-emerald-200 hover:bg-emerald-50"
            >
              <CheckCircle className="w-4 h-4 mr-2" />
              Verify Identity
            </Button>
          </CardContent>
        </Card>
      </div>

      {/* Recent Orders */}
      <Card className="border-emerald-200">
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle className="text-emerald-900">Recent Orders</CardTitle>
          <Button variant="outline" size="sm" className="border-emerald-200 hover:bg-emerald-50">
            View All Orders
          </Button>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            {[1, 2, 3].map((order) => (
              <div key={order} className="flex items-center justify-between p-4 border border-emerald-200 rounded-lg">
                <div className="flex items-center space-x-4">
                  <div className="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center">
                    <Package className="w-5 h-5 text-emerald-600" />
                  </div>
                  <div>
                    <p className="font-medium text-emerald-900">Order #{1000 + order}</p>
                    <p className="text-sm text-emerald-700">Electronics Supplies Ltd</p>
                  </div>
                </div>
                <div className="text-right">
                  <p className="font-semibold text-emerald-900">KES {(8000 + order * 1500).toLocaleString()}</p>
                  <Badge className={
                    order === 1 ? 'bg-green-100 text-green-800' :
                    order === 2 ? 'bg-yellow-100 text-yellow-800' :
                    'bg-blue-100 text-blue-800'
                  }>
                    {order === 1 ? 'Delivered' : order === 2 ? 'In Transit' : 'Processing'}
                  </Badge>
                </div>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* Credit Overview */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <Card className="border-emerald-200">
          <CardHeader>
            <CardTitle className="text-emerald-900">Credit Overview</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              <div className="flex justify-between items-center">
                <span className="text-emerald-700">Total Credit Limit</span>
                <span className="font-semibold text-emerald-900">KES 50,000</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-emerald-700">Used Credit</span>
                <span className="font-semibold text-emerald-900">KES 5,000</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-emerald-700">Available Credit</span>
                <span className="font-semibold text-emerald-900">KES 45,000</span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2">
                <div className="bg-emerald-600 h-2 rounded-full" style={{ width: '10%' }}></div>
              </div>
              <p className="text-xs text-emerald-600 text-center">10% of credit limit used</p>
            </div>
          </CardContent>
        </Card>

        <Card className="border-emerald-200">
          <CardHeader>
            <CardTitle className="text-emerald-900">Payment Schedule</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-3">
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-2">
                  <Clock className="w-4 h-4 text-yellow-600" />
                  <span className="text-sm text-emerald-700">Next Payment Due</span>
                </div>
                <span className="font-semibold text-emerald-900">Dec 15, 2025</span>
              </div>
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-2">
                  <DollarSign className="w-4 h-4 text-blue-600" />
                  <span className="text-sm text-emerald-700">Amount Due</span>
                </div>
                <span className="font-semibold text-emerald-900">KES 2,500</span>
              </div>
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-2">
                  <CheckCircle className="w-4 h-4 text-green-600" />
                  <span className="text-sm text-emerald-700">Payment History</span>
                </div>
                <span className="font-semibold text-emerald-900">100% On Time</span>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}