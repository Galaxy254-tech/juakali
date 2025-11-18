'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth, useRoleAuth } from '@/hooks/useAuth';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
  Package,
  ShoppingCart,
  Truck,
  DollarSign,
  TrendingUp,
  Building,
  Users,
  CheckCircle,
  Clock,
  AlertTriangle,
  BarChart3,
  Plus
} from 'lucide-react';

export default function SupplierDashboardPage() {
  const router = useRouter();
  const { user } = useAuth();
  const { hasAccess } = useRoleAuth(['supplier']);

  useEffect(() => {
    if (!user || user.role !== 'supplier') {
      router.push('/dashboard');
    }
  }, [user, router]);

  if (!user || user.role !== 'supplier' || !hasAccess) {
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
        <h1 className="text-3xl font-bold text-emerald-900">Supplier Dashboard</h1>
        <p className="text-emerald-700 mt-2">Manage your products, orders, and business operations</p>
      </div>

      {/* Business Stats */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <Card className="border-emerald-200">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium text-emerald-700">Total Orders</CardTitle>
            <ShoppingCart className="h-4 w-4 text-blue-600" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-emerald-900">156</div>
            <p className="text-xs text-emerald-600 mt-1">+18.2% this month</p>
          </CardContent>
        </Card>

        <Card className="border-emerald-200">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium text-emerald-700">Revenue</CardTitle>
            <DollarSign className="h-4 w-4 text-green-600" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-emerald-900">KES 245,000</div>
            <p className="text-xs text-emerald-600 mt-1">+22.5% this month</p>
          </CardContent>
        </Card>

        <Card className="border-emerald-200">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium text-emerald-700">Active Products</CardTitle>
            <Package className="h-4 w-4 text-purple-600" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-emerald-900">89</div>
            <p className="text-xs text-emerald-600 mt-1">5 new products</p>
          </CardContent>
        </Card>

        <Card className="border-emerald-200">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium text-emerald-700">Pending Deliveries</CardTitle>
            <Truck className="h-4 w-4 text-orange-600" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-emerald-900">12</div>
            <p className="text-xs text-emerald-600 mt-1">-8.3% from last week</p>
          </CardContent>
        </Card>
      </div>

      {/* Quick Actions */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <Card className="border-emerald-200">
          <CardHeader>
            <CardTitle className="text-emerald-900">Add New Product</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-emerald-700 mb-4">Expand your catalog with new products</p>
            <Button
              onClick={() => router.push('/dashboard/products/add')}
              className="w-full bg-emerald-600 hover:bg-emerald-700"
            >
              <Plus className="w-4 h-4 mr-2" />
              Add Product
            </Button>
          </CardContent>
        </Card>

        <Card className="border-emerald-200">
          <CardHeader>
            <CardTitle className="text-emerald-900">Manage Orders</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-emerald-700 mb-4">Process and track customer orders</p>
            <Button
              onClick={() => router.push('/dashboard/orders')}
              variant="outline"
              className="w-full border-emerald-200 hover:bg-emerald-50"
            >
              <ShoppingCart className="w-4 h-4 mr-2" />
              View Orders
            </Button>
          </CardContent>
        </Card>

        <Card className="border-emerald-200">
          <CardHeader>
            <CardTitle className="text-emerald-900">Inventory Management</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-emerald-700 mb-4">Monitor stock levels and restock items</p>
            <Button
              onClick={() => router.push('/dashboard/inventory')}
              variant="outline"
              className="w-full border-emerald-200 hover:bg-emerald-50"
            >
              <Building className="w-4 h-4 mr-2" />
              Manage Inventory
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
            {[
              { id: '#12345', customer: 'Retailer Store #234', amount: 15000, status: 'processing', date: '2 hours ago' },
              { id: '#12346', customer: 'SuperMart Kenya', amount: 22000, status: 'shipped', date: '4 hours ago' },
              { id: '#12347', customer: 'City Electronics', amount: 35000, status: 'delivered', date: '1 day ago' },
              { id: '#12348', customer: 'Quick Shop Ltd', amount: 18000, status: 'processing', date: '2 days ago' },
            ].map((order, index) => (
              <div key={index} className="flex items-center justify-between p-4 border border-emerald-200 rounded-lg">
                <div className="flex items-center space-x-4">
                  <div className="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center">
                    <ShoppingCart className="w-5 h-5 text-emerald-600" />
                  </div>
                  <div>
                    <p className="font-medium text-emerald-900">Order {order.id}</p>
                    <p className="text-sm text-emerald-700">{order.customer}</p>
                    <p className="text-xs text-emerald-600">{order.date}</p>
                  </div>
                </div>
                <div className="text-right">
                  <p className="font-semibold text-emerald-900">KES {order.amount.toLocaleString()}</p>
                  <Badge className={
                    order.status === 'delivered' ? 'bg-green-100 text-green-800' :
                    order.status === 'shipped' ? 'bg-blue-100 text-blue-800' :
                    order.status === 'processing' ? 'bg-yellow-100 text-yellow-800' :
                    'bg-gray-100 text-gray-800'
                  }>
                    {order.status.charAt(0).toUpperCase() + order.status.slice(1)}
                  </Badge>
                </div>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* Performance Metrics */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <Card className="border-emerald-200">
          <CardHeader>
            <CardTitle className="text-emerald-900">Sales Performance</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              <div className="flex justify-between items-center">
                <span className="text-emerald-700">Daily Average</span>
                <span className="font-semibold text-emerald-900">KES 8,167</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-emerald-700">Best Selling Category</span>
                <span className="font-semibold text-emerald-900">Electronics</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-emerald-700">Average Order Value</span>
                <span className="font-semibold text-emerald-900">KES 1,571</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-emerald-700">Customer Rating</span>
                <div className="flex items-center space-x-1">
                  <span className="font-semibold text-emerald-900">4.8</span>
                  <div className="flex">
                    {[...Array(5)].map((_, i) => (
                      <svg key={i} className={`w-4 h-4 ${i < 4 ? 'text-yellow-400' : 'text-gray-300'}`} fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                      </svg>
                    ))}
                  </div>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card className="border-emerald-200">
          <CardHeader>
            <CardTitle className="text-emerald-900">Top Products</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-3">
              {[
                { name: 'Samsung Galaxy A54', units: 45, revenue: 'KES 2,025,000' },
                { name: 'HP Laptop 15', units: 32, revenue: 'KES 1,440,000' },
                { name: 'Sony Headphones', units: 28, revenue: 'KES 840,000' },
                { name: 'iPhone Cable', units: 89, revenue: 'KES 267,000' },
              ].map((product, index) => (
                <div key={index} className="flex items-center justify-between p-2 bg-emerald-50 rounded">
                  <div>
                    <p className="text-sm font-medium text-emerald-900">{product.name}</p>
                    <p className="text-xs text-emerald-600">{product.units} units sold</p>
                  </div>
                  <p className="text-sm font-semibold text-emerald-900">{product.revenue}</p>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Low Stock Alerts */}
      <Card className="border-emerald-200">
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle className="text-emerald-900">Low Stock Alerts</CardTitle>
          <Badge className="bg-orange-100 text-orange-800">3 Items</Badge>
        </CardHeader>
        <CardContent>
          <div className="space-y-3">
            {[
              { product: 'iPhone 15 Pro Case', current: 5, min: 10, urgency: 'high' },
              { product: 'USB-C Cable 2m', current: 12, min: 20, urgency: 'medium' },
              { product: 'Wireless Mouse', current: 8, min: 15, urgency: 'medium' },
            ].map((item, index) => (
              <div key={index} className="flex items-center justify-between p-3 border border-emerald-200 rounded-lg">
                <div className="flex items-center space-x-3">
                  {item.urgency === 'high' ? (
                    <AlertTriangle className="w-5 h-5 text-red-600" />
                  ) : (
                    <Clock className="w-5 h-5 text-yellow-600" />
                  )}
                  <div>
                    <p className="font-medium text-emerald-900">{item.product}</p>
                    <p className="text-sm text-emerald-700">
                      Stock: {item.current} / Min: {item.min}
                    </p>
                  </div>
                </div>
                <Button size="sm" variant="outline" className="border-emerald-200 hover:bg-emerald-50">
                  Restock
                </Button>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* Delivery Schedule */}
      <Card className="border-emerald-200">
        <CardHeader>
          <CardTitle className="text-emerald-900">Today's Deliveries</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-3">
            {[
              { order: '#12346', customer: 'SuperMart Kenya', time: '10:00 AM', status: 'ready', items: 15 },
              { order: '#12349', customer: 'Quick Shop Ltd', time: '2:00 PM', status: 'preparing', items: 8 },
              { order: '#12350', customer: 'City Electronics', time: '4:00 PM', status: 'ready', items: 12 },
            ].map((delivery, index) => (
              <div key={index} className="flex items-center justify-between p-3 bg-emerald-50 rounded-lg">
                <div className="flex items-center space-x-3">
                  <Truck className="w-5 h-5 text-emerald-600" />
                  <div>
                    <p className="font-medium text-emerald-900">{delivery.order}</p>
                    <p className="text-sm text-emerald-700">{delivery.customer}</p>
                    <p className="text-xs text-emerald-600">{delivery.time} • {delivery.items} items</p>
                  </div>
                </div>
                <Badge className={
                  delivery.status === 'ready' ? 'bg-green-100 text-green-800' :
                  'bg-yellow-100 text-yellow-800'
                }>
                  {delivery.status === 'ready' ? 'Ready for Pickup' : 'Preparing'}
                </Badge>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}