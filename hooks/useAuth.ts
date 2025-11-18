'use client';

import { createContext, useContext, useEffect, useState, ReactNode } from 'react';
import { useRouter } from 'next/navigation';
import { authApi, getUserData, setUserData, User, LoginCredentials, RegisterData } from '@/lib/api';

interface AuthContextType {
  user: User | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  login: (credentials: LoginCredentials) => Promise<void>;
  register: (userData: RegisterData) => Promise<void>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
}

interface AuthProviderProps {
  children: ReactNode;
}

// Create context
const AuthContext = createContext<AuthContextType | undefined>(undefined);

// Custom hook to use auth context
export const useAuth = () => {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};

// Auth Provider component
export const AuthProvider = ({ children }: AuthProviderProps) => {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const router = useRouter();

  const isAuthenticated = !!user;

  // Check if user is authenticated on mount
  useEffect(() => {
    checkAuthStatus();
  }, []);

  const checkAuthStatus = async () => {
    try {
      const userData = getUserData();
      if (userData) {
        // Verify token is still valid by fetching fresh user data
        const freshUserData = await authApi.login({
          email: userData.email,
          password: '', // We'll need to implement a token validation endpoint
        });

        setUser(userData);
      }
    } catch (error) {
      // Token is invalid or expired
      console.warn('Authentication check failed:', error);
      clearAuthState();
    } finally {
      setIsLoading(false);
    }
  };

  const clearAuthState = () => {
    setUser(null);
    if (typeof window !== 'undefined') {
      localStorage.removeItem('auth_token');
      localStorage.removeItem('user_data');
    }
  };

  const login = async (credentials: LoginCredentials) => {
    setIsLoading(true);
    try {
      const response = await authApi.login(credentials);
      const userData = getUserData();

      if (userData) {
        setUser(userData);

        // Redirect based on user role
        switch (userData.role) {
          case 'retailer':
            router.push('/dashboard/retailer');
            break;
          case 'lender':
            router.push('/dashboard/lender');
            break;
          case 'supplier':
            router.push('/dashboard/supplier');
            break;
          case 'admin':
            router.push('/dashboard/admin');
            break;
          default:
            router.push('/dashboard');
        }
      }
    } catch (error) {
      console.error('Login failed:', error);
      throw error;
    } finally {
      setIsLoading(false);
    }
  };

  const register = async (userData: RegisterData) => {
    setIsLoading(true);
    try {
      await authApi.register(userData);

      // After successful registration, redirect to login
      router.push('/auth/login?message=Registration successful. Please login.');
    } catch (error) {
      console.error('Registration failed:', error);
      throw error;
    } finally {
      setIsLoading(false);
    }
  };

  const logout = async () => {
    setIsLoading(true);
    try {
      await authApi.logout();
      clearAuthState();
      router.push('/');
    } catch (error) {
      console.error('Logout failed:', error);
      // Even if logout API fails, clear local state
      clearAuthState();
      router.push('/');
    } finally {
      setIsLoading(false);
    }
  };

  const refreshUser = async () => {
    if (!user) return;

    try {
      // We'll need to implement a refresh user endpoint
      // For now, just update local state
      const userData = getUserData();
      if (userData) {
        setUser(userData);
      }
    } catch (error) {
      console.error('Failed to refresh user data:', error);
    }
  };

  const value: AuthContextType = {
    user,
    isLoading,
    isAuthenticated,
    login,
    register,
    logout,
    refreshUser,
  };

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
};

// Higher-order component for protecting routes
export const withAuth = <P extends object>(Component: React.ComponentType<P>) => {
  return function ProtectedComponent(props: P) {
    const { isAuthenticated, isLoading } = useAuth();
    const router = useRouter();

    useEffect(() => {
      if (!isLoading && !isAuthenticated) {
        router.push('/auth/login');
      }
    }, [isAuthenticated, isLoading, router]);

    if (isLoading) {
      return (
        <div className="min-h-screen flex items-center justify-center">
          <div className="animate-spin rounded-full h-32 w-32 border-b-2 border-emerald-600"></div>
        </div>
      );
    }

    if (!isAuthenticated) {
      return null; // Will redirect
    }

    return <Component {...props} />;
  };
};

// Hook for role-based access control
export const useRoleAuth = (allowedRoles: string[]) => {
  const { user, isAuthenticated } = useAuth();

  if (!isAuthenticated || !user) {
    return { hasAccess: false, reason: 'not_authenticated' };
  }

  const hasAccess = allowedRoles.includes(user.role);

  if (!hasAccess) {
    return { hasAccess: false, reason: 'insufficient_role' };
  }

  return { hasAccess: true, reason: null };
};

// Hook for checking if user is a specific role
export const useIsRole = (role: string) => {
  const { user } = useAuth();
  return user?.role === role;
};

// Hook for getting user-specific dashboard URL
export const useDashboardUrl = () => {
  const { user } = useAuth();

  if (!user) return '/dashboard';

  switch (user.role) {
    case 'retailer':
      return '/dashboard/retailer';
    case 'lender':
      return '/dashboard/lender';
    case 'supplier':
      return '/dashboard/supplier';
    case 'admin':
      return '/dashboard/admin';
    default:
      return '/dashboard';
  }
};

export default useAuth;