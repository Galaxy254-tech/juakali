'use client';

import { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import {
  LineChart,
  Line,
  AreaChart,
  Area,
  BarChart,
  Bar,
  PieChart,
  Pie,
  Cell,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  ResponsiveContainer
} from 'recharts';
import {
  TrendingUp,
  TrendingDown,
  BarChart3,
  PieChart as PieChartIcon,
  LineChart as LineChartIcon,
  Calendar,
  Download,
  RefreshCw
} from 'lucide-react';
import { analyticsApi } from '@/lib/api';
import { useAuth } from '@/hooks/useAuth';

interface AnalyticsData {
  time_series: Array<{ date: string; value: number; predicted?: number }>;
  growth_rates: { [key: string]: string };
  seasonal_patterns: { [key: string]: string };
  forecasting: Array<{ date: string; predicted_value: number; confidence_lower: number; confidence_upper: number }>;
}

interface AnalyticsChartProps {
  title: string;
  type: 'overview' | 'financial' | 'performance' | 'trends' | 'predictions';
  period?: string;
  showControls?: boolean;
}

const COLORS = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];

export function AnalyticsChart({ title, type, period = 'month', showControls = true }: AnalyticsChartProps) {
  const { user } = useAuth();
  const [data, setData] = useState<AnalyticsData | null>(null);
  const [loading, setLoading] = useState(true);
  const [selectedPeriod, setSelectedPeriod] = useState(period);
  const [chartType, setChartType] = useState<'line' | 'area' | 'bar'>('line');
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchAnalytics();
  }, [type, selectedPeriod]);

  const fetchAnalytics = async () => {
    setLoading(true);
    setError(null);

    try {
      const response = await analyticsApi.getAdvancedAnalytics(type, selectedPeriod);
      if (response.success && response.data) {
        setData(response.data);
      }
    } catch (err) {
      console.error('Failed to fetch analytics:', err);
      setError('Failed to load analytics data');
    } finally {
      setLoading(false);
    }
  };

  const handleExport = () => {
    if (!data) return;

    const csvContent = generateCSV(data);
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${title}_${selectedPeriod}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
  };

  const generateCSV = (data: AnalyticsData) => {
    let csv = 'Date,Value,Type\n';

    if (data.time_series) {
      data.time_series.forEach(item => {
        csv += `${item.date},${item.value},Actual\n`;
      });
    }

    if (data.forecasting) {
      data.forecasting.forEach(item => {
        csv += `${item.date},${item.predicted_value},Predicted\n`;
      });
    }

    return csv;
  };

  const getGrowthIndicator = (rate: string) => {
    const value = parseFloat(rate);
    if (value > 0) {
      return (
        <div className="flex items-center text-green-600">
          <TrendingUp className="w-4 h-4 mr-1" />
          <span className="text-sm font-medium">+{rate}</span>
        </div>
      );
    } else {
      return (
        <div className="flex items-center text-red-600">
          <TrendingDown className="w-4 h-4 mr-1" />
          <span className="text-sm font-medium">{rate}</span>
        </div>
      );
    }
  };

  if (loading) {
    return (
      <Card className="border-emerald-200">
        <CardHeader>
          <CardTitle className="text-emerald-900">{title}</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="flex items-center justify-center h-64">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-600"></div>
          </div>
        </CardContent>
      </Card>
    );
  }

  if (error || !data) {
    return (
      <Card className="border-emerald-200">
        <CardHeader>
          <CardTitle className="text-emerald-900">{title}</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="flex flex-col items-center justify-center h-64 text-emerald-600">
            <BarChart3 className="w-8 h-8 mb-2 opacity-50" />
            <p className="text-sm">{error || 'No data available'}</p>
            <Button onClick={fetchAnalytics} variant="outline" size="sm" className="mt-2">
              <RefreshCw className="w-4 h-4 mr-2" />
              Retry
            </Button>
          </div>
        </CardContent>
      </Card>
    );
  }

  const renderChart = () => {
    const chartData = type === 'predictions' && data.forecasting
      ? data.forecasting.map(item => ({
          ...item,
          actual: item.predicted_value,
          lower: item.confidence_lower,
          upper: item.confidence_upper
        }))
      : data.time_series;

    if (!chartData || chartData.length === 0) {
      return (
        <div className="flex items-center justify-center h-64 text-emerald-600">
          <p className="text-sm">No data available for the selected period</p>
        </div>
      );
    }

    switch (chartType) {
      case 'area':
        return (
          <ResponsiveContainer width="100%" height={300}>
            <AreaChart data={chartData}>
              <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
              <XAxis
                dataKey="date"
                stroke="#6b7280"
                tick={{ fontSize: 12 }}
                tickFormatter={(value) => new Date(value).toLocaleDateString()}
              />
              <YAxis stroke="#6b7280" tick={{ fontSize: 12 }} />
              <Tooltip
                contentStyle={{
                  backgroundColor: 'white',
                  border: '1px solid #d1d5db',
                  borderRadius: '8px'
                }}
                formatter={(value: any) => [`KES ${value.toLocaleString()}`, 'Value']}
              />
              <Area
                type="monotone"
                dataKey="value"
                stroke="#10b981"
                fill="#10b981"
                fillOpacity={0.3}
                strokeWidth={2}
              />
              {type === 'predictions' && (
                <>
                  <Area
                    type="monotone"
                    dataKey="upper"
                    stroke="#3b82f6"
                    fill="#3b82f6"
                    fillOpacity={0.1}
                    strokeWidth={0}
                  />
                  <Area
                    type="monotone"
                    dataKey="lower"
                    stroke="#3b82f6"
                    fill="#3b82f6"
                    fillOpacity={0.2}
                    strokeWidth={0}
                  />
                </>
              )}
            </AreaChart>
          </ResponsiveContainer>
        );

      case 'bar':
        return (
          <ResponsiveContainer width="100%" height={300}>
            <BarChart data={chartData}>
              <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
              <XAxis
                dataKey="date"
                stroke="#6b7280"
                tick={{ fontSize: 12 }}
                tickFormatter={(value) => new Date(value).toLocaleDateString()}
              />
              <YAxis stroke="#6b7280" tick={{ fontSize: 12 }} />
              <Tooltip
                contentStyle={{
                  backgroundColor: 'white',
                  border: '1px solid #d1d5db',
                  borderRadius: '8px'
                }}
                formatter={(value: any) => [`KES ${value.toLocaleString()}`, 'Value']}
              />
              <Bar dataKey="value" fill="#10b981" radius={[4, 4, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        );

      default:
        return (
          <ResponsiveContainer width="100%" height={300}>
            <LineChart data={chartData}>
              <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
              <XAxis
                dataKey="date"
                stroke="#6b7280"
                tick={{ fontSize: 12 }}
                tickFormatter={(value) => new Date(value).toLocaleDateString()}
              />
              <YAxis stroke="#6b7280" tick={{ fontSize: 12 }} />
              <Tooltip
                contentStyle={{
                  backgroundColor: 'white',
                  border: '1px solid #d1d5db',
                  borderRadius: '8px'
                }}
                formatter={(value: any) => [`KES ${value.toLocaleString()}`, 'Value']}
              />
              <Line
                type="monotone"
                dataKey="value"
                stroke="#10b981"
                strokeWidth={2}
                dot={{ fill: '#10b981', r: 4 }}
                activeDot={{ r: 6 }}
              />
              {type === 'predictions' && (
                <Line
                  type="monotone"
                  dataKey="predicted_value"
                  stroke="#3b82f6"
                  strokeWidth={2}
                  strokeDasharray="5 5"
                  dot={{ fill: '#3b82f6', r: 4 }}
                />
              )}
            </LineChart>
          </ResponsiveContainer>
        );
    }
  };

  return (
    <Card className="border-emerald-200">
      <CardHeader>
        <div className="flex items-center justify-between">
          <CardTitle className="text-emerald-900">{title}</CardTitle>
          <div className="flex items-center gap-2">
            {showControls && (
              <>
                <Select value={selectedPeriod} onValueChange={setSelectedPeriod}>
                  <SelectTrigger className="w-32">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="day">Today</SelectItem>
                    <SelectItem value="week">Week</SelectItem>
                    <SelectItem value="month">Month</SelectItem>
                    <SelectItem value="quarter">Quarter</SelectItem>
                    <SelectItem value="year">Year</SelectItem>
                  </SelectContent>
                </Select>

                <Select value={chartType} onValueChange={(value: any) => setChartType(value)}>
                  <SelectTrigger className="w-24">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="line">
                      <div className="flex items-center gap-2">
                        <LineChartIcon className="w-4 h-4" />
                        Line
                      </div>
                    </SelectItem>
                    <SelectItem value="area">
                      <div className="flex items-center gap-2">
                        <BarChart3 className="w-4 h-4" />
                        Area
                      </div>
                    </SelectItem>
                    <SelectItem value="bar">
                      <div className="flex items-center gap-2">
                        <BarChart3 className="w-4 h-4" />
                        Bar
                      </div>
                    </SelectItem>
                  </SelectContent>
                </Select>
              </>
            )}

            <Button variant="outline" size="sm" onClick={handleExport}>
              <Download className="w-4 h-4" />
            </Button>

            <Button variant="outline" size="sm" onClick={fetchAnalytics}>
              <RefreshCw className="w-4 h-4" />
            </Button>
          </div>
        </div>
      </CardHeader>

      <CardContent>
        {data.growth_rates && (
          <div className="mb-4 flex flex-wrap gap-4">
            {Object.entries(data.growth_rates).map(([key, value]) => (
              <div key={key} className="flex items-center justify-between bg-emerald-50 px-3 py-2 rounded-lg">
                <span className="text-sm text-emerald-700 capitalize">{key.replace('_', ' ')}</span>
                {getGrowthIndicator(value)}
              </div>
            ))}
          </div>
        )}

        {renderChart()}

        {data.seasonal_patterns && (
          <div className="mt-4 pt-4 border-t border-emerald-200">
            <h4 className="text-sm font-medium text-emerald-900 mb-2">Seasonal Patterns</h4>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              {Object.entries(data.seasonal_patterns).map(([key, value]) => (
                <div key={key} className="flex justify-between items-center">
                  <span className="text-sm text-emerald-700 capitalize">{key.replace('_', ' ')}</span>
                  <Badge variant="secondary" className="text-xs">
                    {value}
                  </Badge>
                </div>
              ))}
            </div>
          </div>
        )}
      </CardContent>
    </Card>
  );
}

export default AnalyticsChart;