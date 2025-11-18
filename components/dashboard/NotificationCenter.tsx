'use client';

import { useState } from 'react';
import { Bell, X, Check, CheckCircle, Info, AlertTriangle, AlertCircle, Clock } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { useNotifications } from '@/hooks/useNotifications';
import { Notification } from '@/hooks/useNotifications';

interface NotificationCenterProps {
  isOpen: boolean;
  onClose: () => void;
}

export function NotificationCenter({ isOpen, onClose }: NotificationCenterProps) {
  const {
    notifications,
    unreadCount,
    isLoading,
    markAsRead,
    markAllAsRead,
    refreshNotifications,
  } = useNotifications();

  const [filter, setFilter] = useState<'all' | 'unread'>('all');

  const filteredNotifications = notifications.filter(notification =>
    filter === 'all' || (filter === 'unread' && !notification.read)
  );

  const getNotificationIcon = (type: string) => {
    switch (type) {
      case 'success':
        return <CheckCircle className="w-4 h-4 text-green-600" />;
      case 'warning':
        return <AlertTriangle className="w-4 h-4 text-yellow-600" />;
      case 'error':
        return <AlertCircle className="w-4 h-4 text-red-600" />;
      default:
        return <Info className="w-4 h-4 text-blue-600" />;
    }
  };

  const getNotificationColor = (type: string) => {
    switch (type) {
      case 'success':
        return 'border-green-200 bg-green-50';
      case 'warning':
        return 'border-yellow-200 bg-yellow-50';
      case 'error':
        return 'border-red-200 bg-red-50';
      default:
        return 'border-blue-200 bg-blue-50';
    }
  };

  const formatTime = (timestamp: string) => {
    const date = new Date(timestamp);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins} min ago`;
    if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
    return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
  };

  const handleNotificationClick = async (notification: Notification) => {
    if (!notification.read) {
      await markAsRead(notification.id);
    }

    if (notification.action_url) {
      window.location.href = notification.action_url;
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-end pt-16 pr-4">
      {/* Backdrop */}
      <div
        className="absolute inset-0 bg-black/20"
        onClick={onClose}
      />

      {/* Notifications Panel */}
      <Card className="relative w-full max-w-md max-h-[80vh] bg-white shadow-xl border-emerald-200">
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-4">
          <CardTitle className="text-emerald-900 flex items-center gap-2">
            <Bell className="w-5 h-5" />
            Notifications
            {unreadCount > 0 && (
              <Badge className="bg-red-100 text-red-800">{unreadCount}</Badge>
            )}
          </CardTitle>
          <div className="flex items-center gap-2">
            <Button
              variant="ghost"
              size="sm"
              onClick={refreshNotifications}
              disabled={isLoading}
              className="text-emerald-600 hover:text-emerald-800"
            >
              {isLoading ? '...' : '↻'}
            </Button>
            <Button
              variant="ghost"
              size="sm"
              onClick={onClose}
              className="text-emerald-600 hover:text-emerald-800"
            >
              <X className="w-4 h-4" />
            </Button>
          </div>
        </CardHeader>

        <CardContent className="p-0">
          {/* Filter Tabs */}
          <div className="flex border-b border-emerald-200">
            <button
              className={`flex-1 px-4 py-2 text-sm font-medium transition-colors ${
                filter === 'all'
                  ? 'text-emerald-900 border-b-2 border-emerald-600'
                  : 'text-emerald-700 hover:text-emerald-900'
              }`}
              onClick={() => setFilter('all')}
            >
              All ({notifications.length})
            </button>
            <button
              className={`flex-1 px-4 py-2 text-sm font-medium transition-colors ${
                filter === 'unread'
                  ? 'text-emerald-900 border-b-2 border-emerald-600'
                  : 'text-emerald-700 hover:text-emerald-900'
              }`}
              onClick={() => setFilter('unread')}
            >
              Unread ({unreadCount})
            </button>
          </div>

          {/* Mark All as Read */}
          {unreadCount > 0 && (
            <div className="px-4 py-2 border-b border-emerald-200">
              <Button
                variant="ghost"
                size="sm"
                onClick={markAllAsRead}
                className="text-emerald-600 hover:text-emerald-800"
              >
                <Check className="w-4 h-4 mr-2" />
                Mark all as read
              </Button>
            </div>
          )}

          {/* Notifications List */}
          <ScrollArea className="h-96">
            {isLoading ? (
              <div className="flex items-center justify-center h-32">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-600"></div>
              </div>
            ) : filteredNotifications.length === 0 ? (
              <div className="flex flex-col items-center justify-center h-32 text-emerald-600">
                <Bell className="w-8 h-8 mb-2 opacity-50" />
                <p className="text-sm">No notifications</p>
              </div>
            ) : (
              <div className="space-y-0">
                {filteredNotifications.map((notification) => (
                  <div key={notification.id}>
                    <div
                      className={`p-4 cursor-pointer transition-colors hover:bg-emerald-50 ${getNotificationColor(
                        notification.type
                      )} ${!notification.read ? 'border-l-4 border-l-emerald-600' : ''}`}
                      onClick={() => handleNotificationClick(notification)}
                    >
                      <div className="flex items-start space-x-3">
                        <div className="flex-shrink-0 mt-1">
                          {getNotificationIcon(notification.type)}
                        </div>
                        <div className="flex-1 min-w-0">
                          <div className="flex items-center justify-between">
                            <p className={`text-sm ${!notification.read ? 'font-semibold' : 'font-medium'} text-emerald-900`}>
                              {notification.title}
                            </p>
                            {!notification.read && (
                              <div className="w-2 h-2 bg-emerald-600 rounded-full flex-shrink-0"></div>
                            )}
                          </div>
                          <p className="text-sm text-emerald-700 mt-1">{notification.message}</p>
                          <div className="flex items-center justify-between mt-2">
                            <div className="flex items-center text-xs text-emerald-600">
                              <Clock className="w-3 h-3 mr-1" />
                              {formatTime(notification.created_at)}
                            </div>
                            {notification.action_text && (
                              <Badge variant="outline" className="text-xs border-emerald-200 text-emerald-700">
                                {notification.action_text}
                              </Badge>
                            )}
                          </div>
                        </div>
                      </div>
                    </div>
                    {notification.id !== filteredNotifications[filteredNotifications.length - 1]?.id && (
                      <Separator className="bg-emerald-200" />
                    )}
                  </div>
                ))}
              </div>
            )}
          </ScrollArea>
        </CardContent>
      </Card>
    </div>
  );
}

// Notification Button Component
export function NotificationButton() {
  const [isOpen, setIsOpen] = useState(false);
  const { unreadCount } = useNotifications();

  return (
    <>
      <Button
        variant="ghost"
        size="sm"
        onClick={() => setIsOpen(true)}
        className="relative"
      >
        <Bell className="w-5 h-5 text-emerald-700" />
        {unreadCount > 0 && (
          <span className="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center">
            {unreadCount > 99 ? '99+' : unreadCount}
          </span>
        )}
      </Button>
      <NotificationCenter isOpen={isOpen} onClose={() => setIsOpen(false)} />
    </>
  );
}

export default NotificationCenter;