import axios, { AxiosInstance, AxiosResponse } from 'axios';

// API base configuration
const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';

// Types for API responses
export interface User {
  id: string;
  email: string;
  role: 'retailer' | 'lender' | 'supplier' | 'admin';
  name?: string;
  created_at: string;
}

export interface LoginCredentials {
  email: string;
  password: string;
}

export interface LoginResponse {
  token: string;
  user_id: string;
  role: string;
}

export interface RegisterData {
  email: string;
  password: string;
  role: 'retailer' | 'lender' | 'supplier';
  name?: string;
  phone?: string;
}

export interface ApiResponse<T = any> {
  success?: boolean;
  data?: T;
  error?: string;
  message?: string;
}

// API error class
export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
    public response?: any
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

// Create axios instance with interceptors
const createApiInstance = (): AxiosInstance => {
  const instance = axios.create({
    baseURL: API_BASE_URL,
    timeout: 10000,
    headers: {
      'Content-Type': 'application/json',
    },
  });

  // Request interceptor - Add auth token
  instance.interceptors.request.use(
    (config) => {
      const token = getAuthToken();
      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      }

      // Log request in development
      if (process.env.NODE_ENV === 'development') {
        console.log(`🚀 API Request: ${config.method?.toUpperCase()} ${config.url}`);
      }

      return config;
    },
    (error) => {
      console.error('❌ Request error:', error);
      return Promise.reject(error);
    }
  );

  // Response interceptor - Handle errors and responses
  instance.interceptors.response.use(
    (response: AxiosResponse) => {
      // Log response in development
      if (process.env.NODE_ENV === 'development') {
        console.log(`✅ API Response: ${response.status} ${response.config.url}`);
      }

      return response;
    },
    (error) => {
      // Log error in development
      if (process.env.NODE_ENV === 'development') {
        console.error(`❌ API Error: ${error.response?.status} ${error.config?.url}`);
      }

      // Handle common error scenarios
      if (error.response?.status === 401) {
        // Token expired - Clear storage and redirect to login
        clearAuthToken();
        if (typeof window !== 'undefined') {
          window.location.href = '/auth/login';
        }
      }

      const message = error.response?.data?.error || error.message || 'Network error occurred';
      const status = error.response?.status || 500;

      throw new ApiError(message, status, error.response?.data);
    }
  );

  return instance;
};

// Auth token management
export const getAuthToken = (): string | null => {
  if (typeof window === 'undefined') return null;
  return localStorage.getItem('auth_token');
};

export const setAuthToken = (token: string): void => {
  if (typeof window === 'undefined') return;
  localStorage.setItem('auth_token', token);
};

export const clearAuthToken = (): void => {
  if (typeof window === 'undefined') return;
  localStorage.removeItem('auth_token');
  localStorage.removeItem('user_data');
};

// User data management
export const getUserData = (): User | null => {
  if (typeof window === 'undefined') return null;
  const userData = localStorage.getItem('user_data');
  return userData ? JSON.parse(userData) : null;
};

export const setUserData = (user: User): void => {
  if (typeof window === 'undefined') return;
  localStorage.setItem('user_data', JSON.stringify(user));
};

// API instance
const api = createApiInstance();

// Auth API functions
export const authApi = {
  login: async (credentials: LoginCredentials): Promise<LoginResponse> => {
    const response = await api.post<ApiResponse<LoginResponse>>('/users/login.php', credentials);

    if (response.data.error) {
      throw new ApiError(response.data.error, 400);
    }

    const loginData = response.data;
    setAuthToken(loginData.token);

    // Fetch user data after login
    const userData = await usersApi.getById(loginData.user_id);
    setUserData(userData);

    return loginData;
  },

  register: async (userData: RegisterData): Promise<ApiResponse> => {
    const response = await api.post<ApiResponse>('/users/register.php', userData);

    if (response.data.error) {
      throw new ApiError(response.data.error, 400);
    }

    return response.data;
  },

  logout: async (): Promise<void> => {
    try {
      await api.post('/users/logout.php');
    } catch (error) {
      // Continue with logout even if server call fails
      console.warn('Logout API call failed:', error);
    } finally {
      clearAuthToken();
    }
  },

  forgotPassword: async (email: string): Promise<ApiResponse> => {
    const response = await api.post<ApiResponse>('/users/forgot-password.php', { email });

    if (response.data.error) {
      throw new ApiError(response.data.error, 400);
    }

    return response.data;
  },

  resetPassword: async (token: string, password: string): Promise<ApiResponse> => {
    const response = await api.post<ApiResponse>('/users/reset-password.php', { token, password });

    if (response.data.error) {
      throw new ApiError(response.data.error, 400);
    }

    return response.data;
  },
};

// Users API functions
export const usersApi = {
  getById: async (id: string): Promise<User> => {
    const response = await api.get<ApiResponse<User>>(`/users/profile.php?id=${id}`);

    if (response.data.error) {
      throw new ApiError(response.data.error, 404);
    }

    return response.data.data!;
  },

  updateProfile: async (userData: Partial<User>): Promise<ApiResponse<User>> => {
    const response = await api.put<ApiResponse<User>>('/users/profile.php', userData);

    if (response.data.error) {
      throw new ApiError(response.data.error, 400);
    }

    // Update stored user data
    const updatedUser = { ...getUserData(), ...response.data.data };
    setUserData(updatedUser!);

    return response.data;
  },

  changePassword: async (currentPassword: string, newPassword: string): Promise<ApiResponse> => {
    const response = await api.put<ApiResponse>('/users/change-password.php', {
      current_password: currentPassword,
      new_password: newPassword,
    });

    if (response.data.error) {
      throw new ApiError(response.data.error, 400);
    }

    return response.data;
  },
};

// Products API functions
export const productsApi = {
  getAll: async (page = 1, limit = 20, category?: string): Promise<ApiResponse> => {
    const params = new URLSearchParams({
      page: page.toString(),
      limit: limit.toString(),
    });

    if (category) {
      params.append('category', category);
    }

    const response = await api.get<ApiResponse>(`/products/list.php?${params}`);
    return response.data;
  },

  getById: async (id: string): Promise<ApiResponse> => {
    const response = await api.get<ApiResponse>(`/products/view.php?id=${id}`);

    if (response.data.error) {
      throw new ApiError(response.data.error, 404);
    }

    return response.data;
  },

  search: async (query: string, filters?: any): Promise<ApiResponse> => {
    const response = await api.post<ApiResponse>('/products/search.php', {
      query,
      ...filters,
    });

    return response.data;
  },
};

// Loans API functions
export const loansApi = {
  getApplications: async (status?: string): Promise<ApiResponse> => {
    const params = status ? `?status=${status}` : '';
    const response = await api.get<ApiResponse>(`/loans/applications.php${params}`);
    return response.data;
  },

  applyForLoan: async (loanData: any): Promise<ApiResponse> => {
    const response = await api.post<ApiResponse>('/loans/apply.php', loanData);

    if (response.data.error) {
      throw new ApiError(response.data.error, 400);
    }

    return response.data;
  },

  getLoanDetails: async (id: string): Promise<ApiResponse> => {
    const response = await api.get<ApiResponse>(`/loans/details.php?id=${id}`);

    if (response.data.error) {
      throw new ApiError(response.data.error, 404);
    }

    return response.data;
  },

  makeRepayment: async (loanId: string, amount: number, paymentMethod: string): Promise<ApiResponse> => {
    const response = await api.post<ApiResponse>('/loans/repay.php', {
      loan_id: loanId,
      amount,
      payment_method: paymentMethod,
    });

    if (response.data.error) {
      throw new ApiError(response.data.error, 400);
    }

    return response.data;
  },
};

// Orders API functions
export const ordersApi = {
  getAll: async (status?: string): Promise<ApiResponse> => {
    const params = status ? `?status=${status}` : '';
    const response = await api.get<ApiResponse>(`/orders/list.php${params}`);
    return response.data;
  },

  createOrder: async (orderData: any): Promise<ApiResponse> => {
    const response = await api.post<ApiResponse>('/orders/create.php', orderData);

    if (response.data.error) {
      throw new ApiError(response.data.error, 400);
    }

    return response.data;
  },

  getOrderDetails: async (id: string): Promise<ApiResponse> => {
    const response = await api.get<ApiResponse>(`/orders/details.php?id=${id}`);

    if (response.data.error) {
      throw new ApiError(response.data.error, 404);
    }

    return response.data;
  },

  updateOrderStatus: async (id: string, status: string): Promise<ApiResponse> => {
    const response = await api.put<ApiResponse>(`/orders/status.php`, {
      order_id: id,
      status,
    });

    if (response.data.error) {
      throw new ApiError(response.data.error, 400);
    }

    return response.data;
  },
};

// Payments API functions
export const paymentsApi = {
  getMethods: async (): Promise<ApiResponse> => {
    const response = await api.get<ApiResponse>('/payments/methods.php');
    return response.data;
  },

  initiatePayment: async (paymentData: any): Promise<ApiResponse> => {
    const response = await api.post<ApiResponse>('/payments/initiate.php', paymentData);

    if (response.data.error) {
      throw new ApiError(response.data.error, 400);
    }

    return response.data;
  },

  getPaymentHistory: async (page = 1, limit = 20): Promise<ApiResponse> => {
    const response = await api.get<ApiResponse>(`/payments/history.php?page=${page}&limit=${limit}`);
    return response.data;
  },
};

// KYC API functions
export const kycApi = {
  getDocuments: async (): Promise<ApiResponse> => {
    const response = await api.get<ApiResponse>('/kyc/documents.php');
    return response.data;
  },

  uploadDocument: async (documentType: string, file: File): Promise<ApiResponse> => {
    const formData = new FormData();
    formData.append('document_type', documentType);
    formData.append('document', file);

    const response = await api.post<ApiResponse>('/kyc/upload.php', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });

    if (response.data.error) {
      throw new ApiError(response.data.error, 400);
    }

    return response.data;
  },

  getStatus: async (): Promise<ApiResponse> => {
    const response = await api.get<ApiResponse>('/kyc/status.php');
    return response.data;
  },
};

// Notifications API functions
export const notificationsApi = {
  getUnreadCount: async (): Promise<ApiResponse> => {
    const response = await api.get<ApiResponse>('/notifications/unread-count.php');
    return response.data;
  },

  getAll: async (page = 1, limit = 20): Promise<ApiResponse> => {
    const response = await api.get<ApiResponse>(`/notifications/list.php?page=${page}&limit=${limit}`);
    return response.data;
  },

  markAsRead: async (notificationId: string): Promise<ApiResponse> => {
    const response = await api.put<ApiResponse>('/notifications/read.php', {
      notification_id: notificationId,
    });

    if (response.data.error) {
      throw new ApiError(response.data.error, 400);
    }

    return response.data;
  },

  markAllAsRead: async (): Promise<ApiResponse> => {
    const response = await api.put<ApiResponse>('/notifications/read-all.php');

    if (response.data.error) {
      throw new ApiError(response.data.error, 400);
    }

    return response.data;
  },
};

// Analytics API functions
export const analyticsApi = {
  getDashboardStats: async (): Promise<ApiResponse> => {
    const response = await api.get<ApiResponse>('/analytics/dashboard.php');
    return response.data;
  },

  getCreditScore: async (): Promise<ApiResponse> => {
    const response = await api.get<ApiResponse>('/analytics/credit-score.php');
    return response.data;
  },

  getFinancialSummary: async (period = 'month'): Promise<ApiResponse> => {
    const response = await api.get<ApiResponse>(`/analytics/financial-summary.php?period=${period}`);
    return response.data;
  },

  getAdvancedAnalytics: async (type: string, period: string = 'month', startDate?: string, endDate?: string): Promise<ApiResponse> => {
    const params = new URLSearchParams({
      type,
      period,
    });

    if (startDate) params.append('start_date', startDate);
    if (endDate) params.append('end_date', endDate);

    const response = await api.get<ApiResponse>(`/analytics/advanced.php?${params}`);
    return response.data;
  },

  getUserBehaviorAnalytics: async (userId?: string): Promise<ApiResponse> => {
    const params = userId ? `?user_id=${userId}` : '';
    const response = await api.get<ApiResponse>(`/analytics/user-behavior.php${params}`);
    return response.data;
  },

  getRiskAssessment: async (userId?: string): Promise<ApiResponse> => {
    const params = userId ? `?user_id=${userId}` : '';
    const response = await api.get<ApiResponse>(`/analytics/risk-assessment.php${params}`);
    return response.data;
  },

  getPerformanceMetrics: async (period: string = 'month'): Promise<ApiResponse> => {
    const response = await api.get<ApiResponse>(`/analytics/performance-metrics.php?period=${period}`);
    return response.data;
  },

  getPredictiveAnalytics: async (modelType: string): Promise<ApiResponse> => {
    const response = await api.get<ApiResponse>(`/analytics/predictive.php?model=${modelType}`);
    return response.data;
  },
};

export default api;