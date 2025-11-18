'use client';

import { useState } from 'react';
import { useRouter, usePathname } from 'next/navigation';
import Link from 'next/link';
import { useAuth, useRoleAuth } from '@/hooks/useAuth';
import { NotificationsProvider } from '@/hooks/useNotifications';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetTrigger } from '@/components/ui/sheet';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger
} from '@/components/ui/dropdown-menu';
import { Badge } from '@/components/ui/badge';
import { NotificationButton } from '@/components/dashboard/NotificationCenter';
import {
  Menu,
  X,
  Home,
  Package,
  DollarSign,
  Truck,
  Users,
  Settings,
  LogOut,
  Bell,
  TrendingUp,
  CreditCard,
  FileText,
  BarChart3,
  User,
  ShoppingBag,
  Building2,
  Shield
} from 'lucide-react';

interface DashboardLayoutProps {
  children: React.ReactNode;
}

interface NavItem {
  title: string;
  href: string;
  icon: React.ReactNode;
  badge?: string;
  roles?: string[];
}

const navigationItems: NavItem[] = [
  {
    title: 'Dashboard',
    href: '/dashboard',
    icon: <Home className="w-5 h-5" />,
  },
  {
    title: 'Orders',
    href: '/dashboard/orders',
    icon: <Package className="w-5 h-5" />,
    roles: ['retailer', 'supplier', 'admin'],
  },
  {
    title: 'Products',
    href: '/dashboard/products',
    icon: <ShoppingBag className="w-5 h-5" />,
    roles: ['retailer', 'supplier', 'admin'],
  },
  {
    title: 'Credit',
    href: '/dashboard/credit',
    icon: <CreditCard className="w-5 h-5" />,
    roles: ['retailer', 'lender'],
  },
  {
    title: 'Loans',
    href: '/dashboard/loans',
    icon: <DollarSign className="w-5 h-5" />,
    roles: ['retailer', 'lender', 'admin'],
  },
  {
    title: 'Portfolio',
    href: '/dashboard/portfolio',
    icon: <TrendingUp className="w-5 h-5" />,
    roles: ['lender'],
  },
  {
    title: 'Inventory',
    href: '/dashboard/inventory',
    icon: <Building2 className="w-5 h-5" />,
    roles: ['supplier'],
  },
  {
    title: 'Users',
    href: '/dashboard/users',
    icon: <Users className="w-5 h-5" />,
    roles: ['admin'],
  },
  {
    title: 'Analytics',
    href: '/dashboard/analytics',
    icon: <BarChart3 className="w-5 h-5" />,
    roles: ['admin', 'lender'],
  },
  {
    title: 'Reports',
    href: '/dashboard/reports',
    icon: <FileText className="w-5 h-5" />,
    roles: ['admin'],
  },
  {
    title: 'Settings',
    href: '/dashboard/settings',
    icon: <Settings className="w-5 h-5" />,
  },
];

export default function DashboardLayout({ children }: DashboardLayoutProps) {
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const router = useRouter();
  const pathname = usePathname();
  const { user, logout } = useAuth();

  // Check if user has access to current route
  const { hasAccess } = useRoleAuth(['retailer', 'lender', 'supplier', 'admin']);

  if (!user || !hasAccess) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="animate-spin rounded-full h-32 w-32 border-b-2 border-emerald-600"></div>
      </div>
    );
  }

  const filteredNavItems = navigationItems.filter(item => {
    if (!item.roles) return true;
    return item.roles.includes(user.role);
  });

  const getRoleBadgeColor = (role: string) => {
    switch (role) {
      case 'retailer':
        return 'bg-blue-100 text-blue-800';
      case 'lender':
        return 'bg-green-100 text-green-800';
      case 'supplier':
        return 'bg-orange-100 text-orange-800';
      case 'admin':
        return 'bg-purple-100 text-purple-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  const getRoleLabel = (role: string) => {
    switch (role) {
      case 'retailer':
        return 'Retailer';
      case 'lender':
        return 'Lender';
      case 'supplier':
        return 'Supplier';
      case 'admin':
        return 'Administrator';
      default:
        return 'User';
    }
  };

  const handleLogout = async () => {
    await logout();
    router.push('/');
  };

  const Sidebar = ({ mobile = false }: { mobile?: boolean }) => (
    <div className={`flex flex-col h-full ${mobile ? 'px-4 py-6' : 'px-6 py-8'}`}>
      {/* Logo */}
      <div className="flex items-center gap-3 mb-8">
        <div className="w-10 h-10 rounded-full bg-gradient-to-br from-emerald-500 to-emerald-600 flex-shrink-0" />
        <div>
          <div className="font-bold text-emerald-900 text-lg">JuaKali Lend</div>
          <Badge className={`text-xs ${getRoleBadgeColor(user.role)}`}>
            {getRoleLabel(user.role)}
          </Badge>
        </div>
      </div>

      {/* User Info */}
      <div className="mb-8 p-4 bg-emerald-50 rounded-lg">
        <div className="flex items-center gap-3 mb-2">
          <Avatar className="w-8 h-8">
            <AvatarImage src="" />
            <AvatarFallback>
              {user.name ? user.name.substring(0, 2).toUpperCase() : user.email.substring(0, 2).toUpperCase()}
            </AvatarFallback>
          </Avatar>
          <div className="flex-1 min-w-0">
            <p className="text-sm font-medium text-emerald-900 truncate">
              {user.name || 'User'}
            </p>
            <p className="text-xs text-emerald-600 truncate">
              {user.email}
            </p>
          </div>
        </div>
      </div>

      {/* Navigation */}
      <nav className="flex-1 space-y-2">
        {filteredNavItems.map((item) => (
          <Link
            key={item.href}
            href={item.href}
            className={`flex items-center gap-3 px-3 py-2 rounded-lg transition-colors ${
              pathname === item.href
                ? 'bg-emerald-100 text-emerald-900 font-medium'
                : 'text-emerald-700 hover:bg-emerald-50 hover:text-emerald-900'
            }`}
            onClick={() => mobile && setSidebarOpen(false)}
          >
            {item.icon}
            <span className="flex-1">{item.title}</span>
            {item.badge && (
              <Badge variant="secondary" className="text-xs">
                {item.badge}
              </Badge>
            )}
          </Link>
        ))}
      </nav>

      {/* Footer */}
      <div className="mt-auto pt-6 border-t border-emerald-200">
        <div className="text-xs text-emerald-600 text-center">
          © 2025 JuaKali Lend
        </div>
      </div>
    </div>
  );

  return (
    <NotificationsProvider>
      <div className="min-h-screen bg-gray-50 flex">
        {/* Mobile Sidebar */}
        <Sheet open={sidebarOpen} onOpenChange={setSidebarOpen}>
          <SheetContent side="left" className="w-80 p-0">
            <Sidebar mobile />
          </SheetContent>
        </Sheet>

        {/* Desktop Sidebar */}
        <div className="hidden lg:flex lg:flex-shrink-0">
          <div className="w-80 bg-white border-r border-emerald-200">
            <Sidebar />
          </div>
        </div>

        {/* Main Content */}
        <div className="flex-1 flex flex-col min-w-0">
        {/* Top Navigation */}
        <header className="bg-white border-b border-emerald-200 px-4 sm:px-6 lg:px-8">
          <div className="flex items-center justify-between h-16">
            <div className="flex items-center">
              {/* Mobile menu button */}
              <Sheet open={sidebarOpen} onOpenChange={setSidebarOpen}>
                <SheetTrigger asChild>
                  <Button variant="ghost" size="sm" className="lg:hidden">
                    <Menu className="w-5 h-5" />
                  </Button>
                </SheetTrigger>
              </Sheet>

              {/* Breadcrumb or Page Title */}
              <div className="ml-4 lg:ml-0">
                <h1 className="text-lg font-semibold text-emerald-900">
                  {filteredNavItems.find(item => item.href === pathname)?.title || 'Dashboard'}
                </h1>
              </div>
            </div>

            <div className="flex items-center gap-4">
              {/* Notifications */}
              <NotificationButton />

              {/* User Menu */}
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="ghost" className="relative h-8 w-8 rounded-full">
                    <Avatar className="h-8 w-8">
                      <AvatarImage src="" />
                      <AvatarFallback>
                        {user.name ? user.name.substring(0, 2).toUpperCase() : user.email.substring(0, 2).toUpperCase()}
                      </AvatarFallback>
                    </Avatar>
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent className="w-56" align="end" forceMount>
                  <DropdownMenuLabel className="font-normal">
                    <div className="flex flex-col space-y-1">
                      <p className="text-sm font-medium leading-none">
                        {user.name || 'User'}
                      </p>
                      <p className="text-xs leading-none text-muted-foreground">
                        {user.email}
                      </p>
                    </div>
                  </DropdownMenuLabel>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem asChild>
                    <Link href="/dashboard/profile" className="flex items-center gap-2">
                      <User className="w-4 h-4" />
                      Profile
                    </Link>
                  </DropdownMenuItem>
                  <DropdownMenuItem asChild>
                    <Link href="/dashboard/settings" className="flex items-center gap-2">
                      <Settings className="w-4 h-4" />
                      Settings
                    </Link>
                  </DropdownMenuItem>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem onClick={handleLogout} className="flex items-center gap-2 text-red-600">
                    <LogOut className="w-4 h-4" />
                    Log out
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
          </div>
        </header>

        {/* Page Content */}
        <main className="flex-1 overflow-auto">
          <div className="p-4 sm:p-6 lg:p-8">
            {children}
          </div>
        </main>
      </div>
    </div>
    </NotificationsProvider>
  );
}