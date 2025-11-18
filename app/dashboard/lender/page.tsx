'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth, useRoleAuth } from '@/hooks/useAuth';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
  DollarSign,
  TrendingUp,
  Target,
  Users,
  CheckCircle,
  Clock,
  AlertTriangle,
  BarChart3,
  PiggyBank,
  Award
} from 'lucide-react';

export default function LenderDashboardPage() {
  const router = useRouter();
  const { user } = useAuth();
  const { hasAccess } = useRoleAuth(['lender']);

  useEffect(() => {
    if (!user || user.role !== 'lender') {
      router.push('/dashboard');
    }
  }, [user, router]);

  if (!user || user.role !== 'lender' || !hasAccess) {
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
        <h1 className="text-3xl font-bold text-emerald-900">Lender Dashboard</h1>
        <p className="text-emerald-700 mt-2">Manage your loan portfolio and track your investments</p>
      </div>

      {/* Investment Stats */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <Card className="border-emerald-200">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium text-emerald-700">Total Invested</CardTitle>
            <DollarSign className="h-4 w-4 text-green-600" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-emerald-900">KES 850,000</div>
            <p className="text-xs text-emerald-600 mt-1">+15.3% this month</p>
          </CardContent>
        </Card>

        <Card className="border-emerald-200">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium text-emerald-700">Active Loans</CardTitle>
            <Target className="h-4 w-4 text-blue-600" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-emerald-900">45</div>
            <p className="text-xs text-emerald-600 mt-1">8 new this month</p>
          </CardContent>
        </Card>

        <Card className="border-emerald-200">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium text-emerald-700">Average Return</CardTitle>
            <TrendingUp className="h-4 w-4 text-purple-600" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-emerald-900">12.5%</div>
            <p className="text-xs text-emerald-600 mt-1">Annual percentage rate</p>
          </CardContent>
        </Card>

        <Card className="border-emerald-200">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium text-emerald-700">Repayment Rate</CardTitle>
            <CheckCircle className="h-4 w-4 text-emerald-600" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-emerald-900">96.5%</div>
            <p className="text-xs text-emerald-600 mt-1">+1.2% improvement</p>
          </CardContent>
        </Card>
      </div>

      {/* Quick Actions */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <Card className="border-emerald-200">
          <CardHeader>
            <CardTitle className="text-emerald-900">Review Applications</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-emerald-700 mb-4">5 new loan applications waiting for your review</p>
            <Button
              onClick={() => router.push('/dashboard/loans?filter=pending')}
              className="w-full bg-emerald-600 hover:bg-emerald-700"
            >
              <Target className="w-4 h-4 mr-2" />
              Review Applications
            </Button>
          </CardContent>
        </Card>

        <Card className="border-emerald-200">
          <CardHeader>
            <CardTitle className="text-emerald-900">Portfolio Analysis</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-emerald-700 mb-4">Detailed analysis of your investment performance</p>
            <Button
              onClick={() => router.push('/dashboard/portfolio')}
              variant="outline"
              className="w-full border-emerald-200 hover:bg-emerald-50"
            >
              <BarChart3 className="w-4 h-4 mr-2" />
              View Portfolio
            </Button>
          </CardContent>
        </Card>

        <Card className="border-emerald-200">
          <CardHeader>
            <CardTitle className="text-emerald-900">Auto-Invest Setup</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-emerald-700 mb-4">Configure automated investing based on your criteria</p>
            <Button
              onClick={() => router.push('/dashboard/settings/auto-invest')}
              variant="outline"
              className="w-full border-emerald-200 hover:bg-emerald-50"
            >
              <PiggyBank className="w-4 h-4 mr-2" />
              Configure Auto-Invest
            </Button>
          </CardContent>
        </Card>
      </div>

      {/* Recent Loan Applications */}
      <Card className="border-emerald-200">
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle className="text-emerald-900">Recent Loan Applications</CardTitle>
          <Button variant="outline" size="sm" className="border-emerald-200 hover:bg-emerald-50">
            View All Applications
          </Button>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            {[
              { name: 'John Kamau', business: 'Retail Store', amount: 25000, score: 720, risk: 'Low', status: 'pending' },
              { name: 'Mary Wanjiku', business: 'Electronics Shop', amount: 35000, score: 680, risk: 'Medium', status: 'pending' },
              { name: 'Peter Ochieng', business: 'Supermarket', amount: 50000, score: 750, risk: 'Low', status: 'reviewing' },
            ].map((application, index) => (
              <div key={index} className="flex items-center justify-between p-4 border border-emerald-200 rounded-lg">
                <div className="flex items-center space-x-4">
                  <div className="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center">
                    <Users className="w-5 h-5 text-emerald-600" />
                  </div>
                  <div>
                    <p className="font-medium text-emerald-900">{application.name}</p>
                    <p className="text-sm text-emerald-700">{application.business}</p>
                    <div className="flex items-center space-x-2 mt-1">
                      <Badge className={
                        application.risk === 'Low' ? 'bg-green-100 text-green-800' :
                        application.risk === 'Medium' ? 'bg-yellow-100 text-yellow-800' :
                        'bg-red-100 text-red-800'
                      }>
                        {application.risk} Risk
                      </Badge>
                      <Badge className="bg-blue-100 text-blue-800">
                        Score: {application.score}
                      </Badge>
                    </div>
                  </div>
                </div>
                <div className="text-right">
                  <p className="font-semibold text-emerald-900">KES {application.amount.toLocaleString()}</p>
                  <Badge className={
                    application.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                    'bg-blue-100 text-blue-800'
                  }>
                    {application.status === 'pending' ? 'Pending Review' : 'Under Review'}
                  </Badge>
                </div>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* Performance Overview */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <Card className="border-emerald-200">
          <CardHeader>
            <CardTitle className="text-emerald-900">Monthly Performance</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              <div className="flex justify-between items-center">
                <span className="text-emerald-700">Interest Earned</span>
                <span className="font-semibold text-emerald-900">KES 12,500</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-emerald-700">Principal Repaid</span>
                <span className="font-semibold text-emerald-900">KES 85,000</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-emerald-700">New Investments</span>
                <span className="font-semibold text-emerald-900">KES 45,000</span>
              </div>
              <div className="flex justify-between items-center pt-2 border-t border-emerald-200">
                <span className="font-medium text-emerald-900">Net Return</span>
                <span className="font-bold text-green-600">+KES 12,500</span>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card className="border-emerald-200">
          <CardHeader>
            <CardTitle className="text-emerald-900">Portfolio Distribution</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              <div className="flex justify-between items-center">
                <div className="flex items-center space-x-2">
                  <div className="w-3 h-3 bg-blue-500 rounded-full"></div>
                  <span className="text-sm text-emerald-700">Retail Loans</span>
                </div>
                <span className="font-semibold text-emerald-900">65%</span>
              </div>
              <div className="flex justify-between items-center">
                <div className="flex items-center space-x-2">
                  <div className="w-3 h-3 bg-green-500 rounded-full"></div>
                  <span className="text-sm text-emerald-700">Supplier Loans</span>
                </div>
                <span className="font-semibold text-emerald-900">25%</span>
              </div>
              <div className="flex justify-between items-center">
                <div className="flex items-center space-x-2">
                  <div className="w-3 h-3 bg-purple-500 rounded-full"></div>
                  <span className="text-sm text-emerald-700">Expansion Loans</span>
                </div>
                <span className="font-semibold text-emerald-900">10%</span>
              </div>
              <div className="pt-2 border-t border-emerald-200">
                <div className="flex items-center justify-between">
                  <span className="text-sm text-emerald-700">Diversification Score</span>
                  <div className="flex items-center space-x-1">
                    <Award className="w-4 h-4 text-yellow-500" />
                    <span className="font-semibold text-emerald-900">Good</span>
                  </div>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Upcoming Payments */}
      <Card className="border-emerald-200">
        <CardHeader>
          <CardTitle className="text-emerald-900">Upcoming Repayments</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-3">
            {[
              { borrower: 'John Kamau', amount: 2500, date: 'Dec 20, 2025', type: 'principal + interest' },
              { borrower: 'Mary Wanjiku', amount: 3200, date: 'Dec 22, 2025', type: 'interest only' },
              { borrower: 'Peter Ochieng', amount: 4500, date: 'Dec 25, 2025', type: 'principal + interest' },
            ].map((payment, index) => (
              <div key={index} className="flex items-center justify-between p-3 bg-emerald-50 rounded-lg">
                <div className="flex items-center space-x-3">
                  <Clock className="w-4 h-4 text-emerald-600" />
                  <div>
                    <p className="font-medium text-emerald-900">{payment.borrower}</p>
                    <p className="text-xs text-emerald-700">{payment.type}</p>
                  </div>
                </div>
                <div className="text-right">
                  <p className="font-semibold text-emerald-900">KES {payment.amount.toLocaleString()}</p>
                  <p className="text-xs text-emerald-600">{payment.date}</p>
                </div>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}