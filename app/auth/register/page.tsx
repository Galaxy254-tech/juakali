'use client';

import { useState, useEffect, Suspense } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import Link from 'next/link';
import { useForm, FormProvider } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Progress } from '@/components/ui/progress';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Checkbox } from '@/components/ui/checkbox';
import { Eye, EyeOff, Loader2, AlertCircle, User, Store, DollarSign, Truck, ArrowLeft, ArrowRight } from 'lucide-react';
import { useAuth } from '@/hooks/useAuth';
import { RegisterData } from '@/lib/api';

// Form validation schema
const registerSchema = z.object({
  firstName: z.string().min(2, 'First name must be at least 2 characters'),
  lastName: z.string().min(2, 'Last name must be at least 2 characters'),
  email: z.string().email('Please enter a valid email address'),
  phone: z.string().min(10, 'Phone number must be at least 10 digits'),
  role: z.enum(['retailer', 'lender', 'supplier'], {
    required_error: 'Please select a role',
  }),
  businessName: z.string().min(2, 'Business name must be at least 2 characters'),
  password: z.string()
    .min(8, 'Password must be at least 8 characters')
    .regex(/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/, 'Password must contain at least one uppercase letter, one lowercase letter, and one number'),
  confirmPassword: z.string().min(1, 'Please confirm your password'),
  agreeToTerms: z.boolean().refine((val) => val === true, 'You must agree to the terms and conditions'),
  agreeToPrivacy: z.boolean().refine((val) => val === true, 'You must agree to the privacy policy'),
}).refine((data) => data.password === data.confirmPassword, {
  message: 'Passwords do not match',
  path: ['confirmPassword'],
});

type RegisterFormData = z.infer<typeof registerSchema>;

interface StepConfig {
  title: string;
  description: string;
  fields: (keyof RegisterFormData)[];
}

const steps: StepConfig[] = [
  {
    title: 'Personal Information',
    description: 'Tell us about yourself',
    fields: ['firstName', 'lastName', 'email', 'phone'],
  },
  {
    title: 'Account Type',
    description: 'Choose your role in the ecosystem',
    fields: ['role'],
  },
  {
    title: 'Business Details',
    description: 'Tell us about your business',
    fields: ['businessName'],
  },
  {
    title: 'Security',
    description: 'Create a secure password',
    fields: ['password', 'confirmPassword'],
  },
  {
    title: 'Terms & Conditions',
    description: 'Review and accept our terms',
    fields: ['agreeToTerms', 'agreeToPrivacy'],
  },
];

function RegisterPageContent() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { register: registerUser, isLoading, isAuthenticated } = useAuth();
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [currentStep, setCurrentStep] = useState(0);
  const [completedSteps, setCompletedSteps] = useState<Set<number>>(new Set());

  const methods = useForm<RegisterFormData>({
    resolver: zodResolver(registerSchema),
    mode: 'onChange',
  });

  const {
    watch,
    trigger,
    formState: { errors, isValid },
  } = methods;

  const watchedValues = watch();

  // Redirect if already authenticated
  useEffect(() => {
    if (isAuthenticated) {
      router.push('/dashboard');
    }
  }, [isAuthenticated, router]);

  // Set role from URL params
  useEffect(() => {
    const roleParam = searchParams.get('role');
    if (roleParam && ['retailer', 'lender', 'supplier'].includes(roleParam)) {
      methods.setValue('role', roleParam as 'retailer' | 'lender' | 'supplier');
    }
  }, [searchParams, methods]);

  // Validate step and update completed steps
  useEffect(() => {
    const validateCurrentStep = async () => {
      const currentStepConfig = steps[currentStep];
      const fieldsToValidate = currentStepConfig.fields;

      let isValidStep = true;
      for (const field of fieldsToValidate) {
        const isFieldValid = await trigger(field);
        if (!isFieldValid) {
          isValidStep = false;
          break;
        }
      }

      if (isValidStep) {
        setCompletedSteps((prev) => new Set(prev).add(currentStep));
      } else {
        setCompletedSteps((prev) => {
          const newSet = new Set(prev);
          newSet.delete(currentStep);
          return newSet;
        });
      }
    };

    validateCurrentStep();
  }, [watchedValues, currentStep, trigger]);

  const nextStep = async () => {
    const currentStepConfig = steps[currentStep];
    const fieldsToValidate = currentStepConfig.fields;

    let isValidStep = true;
    for (const field of fieldsToValidate) {
      const isFieldValid = await trigger(field);
      if (!isFieldValid) {
        isValidStep = false;
        break;
      }
    }

    if (isValidStep && currentStep < steps.length - 1) {
      setCurrentStep(currentStep + 1);
    }
  };

  const prevStep = () => {
    if (currentStep > 0) {
      setCurrentStep(currentStep - 1);
    }
  };

  const onSubmit = async (data: RegisterFormData) => {
    try {
      setError(null);
      const registerData: RegisterData = {
        firstName: data.firstName,
        lastName: data.lastName,
        email: data.email,
        phone: data.phone,
        role: data.role,
        businessName: data.businessName,
        password: data.password,
      };

      await registerUser(registerData);
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'Registration failed';
      setError(errorMessage);
    }
  };

  const getRoleIcon = (role: string) => {
    switch (role) {
      case 'retailer':
        return <Store className="w-6 h-6" />;
      case 'lender':
        return <DollarSign className="w-6 h-6" />;
      case 'supplier':
        return <Truck className="w-6 h-6" />;
      default:
        return <User className="w-6 h-6" />;
    }
  };

  const getRoleDescription = (role: string) => {
    switch (role) {
      case 'retailer':
        return 'Access goods on credit and build your business';
      case 'lender':
        return 'Provide loans and earn competitive returns';
      case 'supplier':
        return 'Sell products to verified retailers';
      default:
        return '';
    }
  };

  if (isAuthenticated) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <Loader2 className="h-8 w-8 animate-spin text-emerald-600" />
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-emerald-50 via-white to-emerald-50 flex items-center justify-center px-4 py-12">
      <div className="w-full max-w-2xl">
          {/* Logo and Header */}
          <div className="text-center mb-8">
            <div className="flex items-center justify-center gap-2 mb-4">
              <div className="w-10 h-10 rounded-full bg-gradient-to-br from-emerald-500 to-emerald-600" />
              <span className="font-bold text-2xl text-emerald-900">JuaKali Lend</span>
            </div>
            <h1 className="text-3xl font-bold text-emerald-900 mb-2">Create Your Account</h1>
            <p className="text-emerald-700">Join thousands of businesses growing with JuaKali Lend</p>
          </div>

        {/* Progress Indicator */}
        <div className="mb-8">
          <div className="flex items-center justify-between mb-4">
            {steps.map((step, index) => (
              <div key={index} className="flex items-center">
                <div
                  className={`w-10 h-10 rounded-full flex items-center justify-center text-sm font-semibold transition-colors ${
                    completedSteps.has(index)
                      ? 'bg-emerald-600 text-white'
                      : index === currentStep
                      ? 'bg-emerald-100 text-emerald-600 border-2 border-emerald-600'
                      : 'bg-gray-100 text-gray-500 border-2 border-gray-300'
                  }`}
                >
                  {completedSteps.has(index) ? (
                    <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                      <path
                        fillRule="evenodd"
                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                        clipRule="evenodd"
                      />
                    </svg>
                  ) : (
                    index + 1
                  )}
                </div>
                {index < steps.length - 1 && (
                  <div
                    className={`w-full h-1 mx-2 transition-colors ${
                      completedSteps.has(index) ? 'bg-emerald-600' : 'bg-gray-300'
                    }`}
                  />
                )}
              </div>
            ))}
          </div>
          <div className="text-center">
            <h3 className="text-lg font-semibold text-emerald-900">{steps[currentStep].title}</h3>
            <p className="text-emerald-700">{steps[currentStep].description}</p>
          </div>
        </div>

        {/* Error Message */}
        {error && (
          <Alert className="mb-6 border-red-200 bg-red-50">
            <AlertCircle className="h-4 w-4 text-red-600" />
            <AlertDescription className="text-red-800">
              {error}
            </AlertDescription>
          </Alert>
        )}

        {/* Registration Form */}
        <Card className="border-emerald-200 shadow-lg">
          <CardContent className="p-6">
            <FormProvider {...methods}>
              <form onSubmit={methods.handleSubmit(onSubmit)} className="space-y-6">
                {/* Step 1: Personal Information */}
                {currentStep === 0 && (
                  <div className="space-y-4">
                    <div className="grid md:grid-cols-2 gap-4">
                      <div className="space-y-2">
                        <Label htmlFor="firstName" className="text-emerald-900 font-medium">
                          First Name
                        </Label>
                        <Input
                          id="firstName"
                          placeholder="John"
                          className="border-emerald-200 focus:border-emerald-400 focus:ring-emerald-400"
                          {...methods.register('firstName')}
                          disabled={isLoading}
                        />
                        {errors.firstName && (
                          <p className="text-sm text-red-600">{errors.firstName.message}</p>
                        )}
                      </div>

                      <div className="space-y-2">
                        <Label htmlFor="lastName" className="text-emerald-900 font-medium">
                          Last Name
                        </Label>
                        <Input
                          id="lastName"
                          placeholder="Doe"
                          className="border-emerald-200 focus:border-emerald-400 focus:ring-emerald-400"
                          {...methods.register('lastName')}
                          disabled={isLoading}
                        />
                        {errors.lastName && (
                          <p className="text-sm text-red-600">{errors.lastName.message}</p>
                        )}
                      </div>
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="email" className="text-emerald-900 font-medium">
                        Email Address
                      </Label>
                      <Input
                        id="email"
                        type="email"
                        placeholder="john.doe@example.com"
                        className="border-emerald-200 focus:border-emerald-400 focus:ring-emerald-400"
                        {...methods.register('email')}
                        disabled={isLoading}
                      />
                      {errors.email && (
                        <p className="text-sm text-red-600">{errors.email.message}</p>
                      )}
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="phone" className="text-emerald-900 font-medium">
                        Phone Number
                      </Label>
                      <Input
                        id="phone"
                        type="tel"
                        placeholder="+254 712 345 678"
                        className="border-emerald-200 focus:border-emerald-400 focus:ring-emerald-400"
                        {...methods.register('phone')}
                        disabled={isLoading}
                      />
                      {errors.phone && (
                        <p className="text-sm text-red-600">{errors.phone.message}</p>
                      )}
                    </div>
                  </div>
                )}

                {/* Step 2: Account Type */}
                {currentStep === 1 && (
                  <div className="space-y-6">
                    <RadioGroup
                      value={watchedValues.role}
                      onValueChange={(value) => methods.setValue('role', value as 'retailer' | 'lender' | 'supplier')}
                      className="space-y-4"
                      disabled={isLoading}
                    >
                      <div className="flex items-center space-x-4 p-4 border rounded-lg border-emerald-200 hover:bg-emerald-50 transition-colors">
                        <RadioGroupItem value="retailer" id="retailer" />
                        <div className="flex items-center space-x-3 flex-1">
                          {getRoleIcon('retailer')}
                          <div>
                            <Label htmlFor="retailer" className="font-semibold text-emerald-900 cursor-pointer">
                              Retailer
                            </Label>
                            <p className="text-sm text-emerald-700">{getRoleDescription('retailer')}</p>
                          </div>
                        </div>
                      </div>

                      <div className="flex items-center space-x-4 p-4 border rounded-lg border-emerald-200 hover:bg-emerald-50 transition-colors">
                        <RadioGroupItem value="lender" id="lender" />
                        <div className="flex items-center space-x-3 flex-1">
                          {getRoleIcon('lender')}
                          <div>
                            <Label htmlFor="lender" className="font-semibold text-emerald-900 cursor-pointer">
                              Lender
                            </Label>
                            <p className="text-sm text-emerald-700">{getRoleDescription('lender')}</p>
                          </div>
                        </div>
                      </div>

                      <div className="flex items-center space-x-4 p-4 border rounded-lg border-emerald-200 hover:bg-emerald-50 transition-colors">
                        <RadioGroupItem value="supplier" id="supplier" />
                        <div className="flex items-center space-x-3 flex-1">
                          {getRoleIcon('supplier')}
                          <div>
                            <Label htmlFor="supplier" className="font-semibold text-emerald-900 cursor-pointer">
                              Supplier
                            </Label>
                            <p className="text-sm text-emerald-700">{getRoleDescription('supplier')}</p>
                          </div>
                        </div>
                      </div>
                    </RadioGroup>

                    {errors.role && (
                      <p className="text-sm text-red-600">{errors.role.message}</p>
                    )}
                  </div>
                )}

                {/* Step 3: Business Details */}
                {currentStep === 2 && (
                  <div className="space-y-4">
                    <div className="space-y-2">
                      <Label htmlFor="businessName" className="text-emerald-900 font-medium">
                        Business Name
                      </Label>
                      <Input
                        id="businessName"
                        placeholder="JuaKali Enterprises Ltd"
                        className="border-emerald-200 focus:border-emerald-400 focus:ring-emerald-400"
                        {...methods.register('businessName')}
                        disabled={isLoading}
                      />
                      {errors.businessName && (
                        <p className="text-sm text-red-600">{errors.businessName.message}</p>
                      )}
                    </div>

                    {/* Additional business fields could go here */}
                    <div className="bg-emerald-50 p-4 rounded-lg">
                      <p className="text-sm text-emerald-700">
                        <strong>Next:</strong> You'll be able to add more business details after registration, including business registration, physical address, and KYC documents.
                      </p>
                    </div>
                  </div>
                )}

                {/* Step 4: Security */}
                {currentStep === 3 && (
                  <div className="space-y-4">
                    <div className="space-y-2">
                      <Label htmlFor="password" className="text-emerald-900 font-medium">
                        Password
                      </Label>
                      <div className="relative">
                        <Input
                          id="password"
                          type={showPassword ? 'text' : 'password'}
                          placeholder="Create a strong password"
                          className="border-emerald-200 focus:border-emerald-400 focus:ring-emerald-400 pr-10"
                          {...methods.register('password')}
                          disabled={isLoading}
                        />
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          className="absolute right-0 top-0 h-full px-3 py-2 hover:bg-transparent"
                          onClick={() => setShowPassword(!showPassword)}
                          disabled={isLoading}
                        >
                          {showPassword ? (
                            <EyeOff className="h-4 w-4 text-emerald-600" />
                          ) : (
                            <Eye className="h-4 w-4 text-emerald-600" />
                          )}
                        </Button>
                      </div>
                      {errors.password && (
                        <p className="text-sm text-red-600">{errors.password.message}</p>
                      )}
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="confirmPassword" className="text-emerald-900 font-medium">
                        Confirm Password
                      </Label>
                      <div className="relative">
                        <Input
                          id="confirmPassword"
                          type={showConfirmPassword ? 'text' : 'password'}
                          placeholder="Confirm your password"
                          className="border-emerald-200 focus:border-emerald-400 focus:ring-emerald-400 pr-10"
                          {...methods.register('confirmPassword')}
                          disabled={isLoading}
                        />
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          className="absolute right-0 top-0 h-full px-3 py-2 hover:bg-transparent"
                          onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                          disabled={isLoading}
                        >
                          {showConfirmPassword ? (
                            <EyeOff className="h-4 w-4 text-emerald-600" />
                          ) : (
                            <Eye className="h-4 w-4 text-emerald-600" />
                          )}
                        </Button>
                      </div>
                      {errors.confirmPassword && (
                        <p className="text-sm text-red-600">{errors.confirmPassword.message}</p>
                      )}
                    </div>

                    <div className="bg-emerald-50 p-4 rounded-lg">
                      <p className="text-sm text-emerald-700 font-medium mb-2">Password Requirements:</p>
                      <ul className="text-sm text-emerald-600 space-y-1">
                        <li>• At least 8 characters long</li>
                        <li>• Contains uppercase and lowercase letters</li>
                        <li>• Contains at least one number</li>
                        <li>• Should not be easily guessable</li>
                      </ul>
                    </div>
                  </div>
                )}

                {/* Step 5: Terms & Conditions */}
                {currentStep === 4 && (
                  <div className="space-y-6">
                    <div className="bg-emerald-50 p-6 rounded-lg space-y-4">
                      <h4 className="font-semibold text-emerald-900">Terms & Conditions</h4>
                      <div className="text-sm text-emerald-700 max-h-40 overflow-y-auto space-y-2">
                        <p>
                          By creating an account with JuaKali Lend, you agree to our terms of service and privacy policy.
                          These terms govern your use of our platform and outline your rights and responsibilities.
                        </p>
                        <p>
                          Important points:
                        </p>
                        <ul className="list-disc pl-5 space-y-1">
                          <li>You must provide accurate information</li>
                          <li>You are responsible for maintaining account security</li>
                          <li>All transactions are subject to verification</li>
                          <li>You must comply with applicable laws and regulations</li>
                          <li>Service fees and interest rates apply</li>
                        </ul>
                      </div>
                    </div>

                    <div className="space-y-4">
                      <div className="flex items-start space-x-3">
                        <Checkbox
                          id="agreeToTerms"
                          checked={watchedValues.agreeToTerms}
                          onCheckedChange={(checked) => methods.setValue('agreeToTerms', checked as boolean)}
                          disabled={isLoading}
                        />
                        <div className="space-y-1">
                          <Label htmlFor="agreeToTerms" className="text-sm text-emerald-900 cursor-pointer">
                            I agree to the Terms and Conditions
                          </Label>
                          {errors.agreeToTerms && (
                            <p className="text-sm text-red-600">{errors.agreeToTerms.message}</p>
                          )}
                        </div>
                      </div>

                      <div className="flex items-start space-x-3">
                        <Checkbox
                          id="agreeToPrivacy"
                          checked={watchedValues.agreeToPrivacy}
                          onCheckedChange={(checked) => methods.setValue('agreeToPrivacy', checked as boolean)}
                          disabled={isLoading}
                        />
                        <div className="space-y-1">
                          <Label htmlFor="agreeToPrivacy" className="text-sm text-emerald-900 cursor-pointer">
                            I agree to the Privacy Policy
                          </Label>
                          {errors.agreeToPrivacy && (
                            <p className="text-sm text-red-600">{errors.agreeToPrivacy.message}</p>
                          )}
                        </div>
                      </div>
                    </div>
                  </div>
                )}

                {/* Navigation Buttons */}
                <div className="flex justify-between pt-6 border-t border-emerald-200">
                  <Button
                    type="button"
                    variant="outline"
                    onClick={prevStep}
                    disabled={currentStep === 0 || isLoading}
                    className="border-emerald-200 hover:bg-emerald-50"
                  >
                    <ArrowLeft className="w-4 h-4 mr-2" />
                    Previous
                  </Button>

                  {currentStep === steps.length - 1 ? (
                    <Button
                      type="submit"
                      className="bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700"
                      disabled={isLoading || !isValid}
                    >
                      {isLoading ? (
                        <>
                          <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                          Creating Account...
                        </>
                      ) : (
                        'Create Account'
                      )}
                    </Button>
                  ) : (
                    <Button
                      type="button"
                      onClick={nextStep}
                      disabled={!completedSteps.has(currentStep) || isLoading}
                      className="bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700"
                    >
                      Next
                      <ArrowRight className="w-4 h-4 ml-2" />
                    </Button>
                  )}
                </div>
              </form>
            </FormProvider>
          </CardContent>
        </Card>

        {/* Sign In Link */}
        <div className="text-center mt-6">
          <p className="text-emerald-700">
            Already have an account?{' '}
            <Link
              href="/auth/login"
              className="font-semibold text-emerald-600 hover:text-emerald-800 transition-colors"
            >
              Sign in here
            </Link>
          </p>
        </div>
      </div>
    </div>
  );
}

export default function RegisterPage() {
  return (
    <Suspense fallback={
      <div className="min-h-screen bg-gradient-to-br from-emerald-50 via-white to-emerald-50 flex items-center justify-center px-4 py-12">
        <div className="w-full max-w-2xl">
          <div className="text-center mb-8">
            <div className="flex items-center justify-center gap-2 mb-4">
              <div className="w-10 h-10 rounded-full bg-gradient-to-br from-emerald-500 to-emerald-600" />
              <span className="font-bold text-2xl text-emerald-900">JuaKali Lend</span>
            </div>
            <h1 className="text-3xl font-bold text-emerald-900 mb-2">Create Your Account</h1>
            <p className="text-emerald-700">Join thousands of businesses growing with JuaKali Lend</p>
          </div>
          <div className="flex items-center justify-center">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-600"></div>
          </div>
        </div>
      </div>
    }>
      <RegisterPageContent />
    </Suspense>
  );
}