"use client"

import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { ArrowRight, TrendingUp, Zap, Shield } from "lucide-react"
import Link from "next/link"

export default function Home() {
  return (
    <main className="flex flex-col min-h-screen">
      {/* Navigation */}
      <nav className="sticky top-0 z-50 w-full border-b border-emerald-200 bg-gradient-to-r from-emerald-50 to-emerald-100 backdrop-blur-sm">
        <div className="container mx-auto px-4 py-4 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <div className="w-8 h-8 rounded-full bg-gradient-to-br from-emerald-500 to-emerald-600" />
            <span className="font-bold text-emerald-900">JuaKali Lend</span>
          </div>
          <div className="flex items-center gap-4">
            <Link href="/auth/login">
              <Button variant="ghost" className="text-emerald-700 hover:text-emerald-900">
                Sign In
              </Button>
            </Link>
            <Link href="/auth/register">
              <Button className="bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700">
                Get Started
              </Button>
            </Link>
          </div>
        </div>
      </nav>

      {/* Hero Section */}
      <section className="flex-1 bg-gradient-to-br from-emerald-50 via-white to-emerald-50 py-20">
        <div className="container mx-auto px-4 text-center">
          <h1 className="text-5xl md:text-6xl font-bold text-emerald-900 mb-6 text-balance">
            Revolutionizing Microfinance in East Africa
          </h1>
          <p className="text-xl text-emerald-700 mb-8 max-w-2xl mx-auto text-balance">
            Connect retailers with lenders and suppliers through our innovative platform. Access goods on credit, build
            your credit score, and grow your business.
          </p>
          <div className="flex flex-col sm:flex-row gap-4 justify-center">
            <Link href="/auth/register?role=retailer">
              <Button
                size="lg"
                className="gap-2 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700"
              >
                For Retailers <ArrowRight className="w-4 h-4" />
              </Button>
            </Link>
            <Link href="/auth/register?role=lender">
              <Button
                size="lg"
                variant="outline"
                className="gap-2 border-emerald-300 text-emerald-700 hover:bg-emerald-50 bg-transparent"
              >
                For Lenders <ArrowRight className="w-4 h-4" />
              </Button>
            </Link>
          </div>
        </div>
      </section>

      {/* Features Section */}
      <section className="py-20 bg-white">
        <div className="container mx-auto px-4">
          <h2 className="text-4xl font-bold text-emerald-900 mb-12 text-center">Why Choose JuaKali Lend?</h2>
          <div className="grid md:grid-cols-3 gap-8">
            <Card className="border-emerald-200 hover:shadow-lg transition-shadow">
              <CardHeader>
                <Zap className="w-8 h-8 text-emerald-600 mb-2" />
                <CardTitle className="text-emerald-900">Fast Approvals</CardTitle>
              </CardHeader>
              <CardContent>
                <p className="text-emerald-700">
                  Get approved for goods credit within 24 hours with our AI-powered credit scoring system.
                </p>
              </CardContent>
            </Card>

            <Card className="border-emerald-200 hover:shadow-lg transition-shadow">
              <CardHeader>
                <Shield className="w-8 h-8 text-emerald-600 mb-2" />
                <CardTitle className="text-emerald-900">Secure Transactions</CardTitle>
              </CardHeader>
              <CardContent>
                <p className="text-emerald-700">
                  Bank-grade security with blockchain integration for transparent and verifiable transactions.
                </p>
              </CardContent>
            </Card>

            <Card className="border-emerald-200 hover:shadow-lg transition-shadow">
              <CardHeader>
                <TrendingUp className="w-8 h-8 text-emerald-600 mb-2" />
                <CardTitle className="text-emerald-900">Build Credit</CardTitle>
              </CardHeader>
              <CardContent>
                <p className="text-emerald-700">
                  Every transaction builds your credit score, unlocking higher credit limits and better terms.
                </p>
              </CardContent>
            </Card>
          </div>
        </div>
      </section>

      {/* Stats Section */}
      <section className="py-20 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white">
        <div className="container mx-auto px-4">
          <div className="grid md:grid-cols-4 gap-8 text-center">
            <div>
              <div className="text-4xl font-bold mb-2">50K+</div>
              <p className="text-emerald-100">Active Users</p>
            </div>
            <div>
              <div className="text-4xl font-bold mb-2">$250M</div>
              <p className="text-emerald-100">Total Lent</p>
            </div>
            <div>
              <div className="text-4xl font-bold mb-2">95%</div>
              <p className="text-emerald-100">Repayment Rate</p>
            </div>
            <div>
              <div className="text-4xl font-bold mb-2">24/7</div>
              <p className="text-emerald-100">Support Available</p>
            </div>
          </div>
        </div>
      </section>

      {/* How It Works */}
      <section className="py-20 bg-white">
        <div className="container mx-auto px-4">
          <h2 className="text-4xl font-bold text-emerald-900 mb-12 text-center">How It Works</h2>
          <div className="grid md:grid-cols-4 gap-4">
            <div className="text-center">
              <div className="w-12 h-12 rounded-full bg-gradient-to-br from-emerald-500 to-emerald-600 text-white flex items-center justify-center mx-auto mb-4 font-bold text-lg">
                1
              </div>
              <h3 className="font-semibold text-emerald-900 mb-2">Register</h3>
              <p className="text-emerald-700">Sign up as a retailer, lender, or supplier with basic information.</p>
            </div>
            <div className="text-center">
              <div className="w-12 h-12 rounded-full bg-gradient-to-br from-emerald-500 to-emerald-600 text-white flex items-center justify-center mx-auto mb-4 font-bold text-lg">
                2
              </div>
              <h3 className="font-semibold text-emerald-900 mb-2">Verify KYC</h3>
              <p className="text-emerald-700">Complete identity verification through our secure KYC process.</p>
            </div>
            <div className="text-center">
              <div className="w-12 h-12 rounded-full bg-gradient-to-br from-emerald-500 to-emerald-600 text-white flex items-center justify-center mx-auto mb-4 font-bold text-lg">
                3
              </div>
              <h3 className="font-semibold text-emerald-900 mb-2">Browse Goods</h3>
              <p className="text-emerald-700">Access thousands of products from verified suppliers.</p>
            </div>
            <div className="text-center">
              <div className="w-12 h-12 rounded-full bg-gradient-to-br from-emerald-500 to-emerald-600 text-white flex items-center justify-center mx-auto mb-4 font-bold text-lg">
                4
              </div>
              <h3 className="font-semibold text-emerald-900 mb-2">Get Credit</h3>
              <p className="text-emerald-700">Get instant credit approval and start building credit.</p>
            </div>
          </div>
        </div>
      </section>

      {/* CTA Section */}
      <section className="py-20 bg-gradient-to-br from-emerald-50 to-emerald-100">
        <div className="container mx-auto px-4 text-center">
          <h2 className="text-4xl font-bold text-emerald-900 mb-6">Ready to Get Started?</h2>
          <p className="text-xl text-emerald-700 mb-8 max-w-2xl mx-auto">
            Join thousands of retailers already accessing goods on credit and building their credit scores.
          </p>
          <Link href="/auth/register">
            <Button
              size="lg"
              className="gap-2 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700"
            >
              Create Your Account <ArrowRight className="w-4 h-4" />
            </Button>
          </Link>
        </div>
      </section>

      {/* Footer */}
      <footer className="border-t border-emerald-200 bg-white py-12">
        <div className="container mx-auto px-4">
          <div className="grid md:grid-cols-4 gap-8 mb-8">
            <div>
              <h3 className="font-semibold text-emerald-900 mb-4">JuaKali Lend</h3>
              <ul className="space-y-2 text-sm text-emerald-700">
                <li>
                  <Link href="#" className="hover:text-emerald-900">
                    About Us
                  </Link>
                </li>
                <li>
                  <Link href="#" className="hover:text-emerald-900">
                    Blog
                  </Link>
                </li>
                <li>
                  <Link href="#" className="hover:text-emerald-900">
                    Careers
                  </Link>
                </li>
              </ul>
            </div>
            <div>
              <h3 className="font-semibold text-emerald-900 mb-4">Product</h3>
              <ul className="space-y-2 text-sm text-emerald-700">
                <li>
                  <Link href="#" className="hover:text-emerald-900">
                    For Retailers
                  </Link>
                </li>
                <li>
                  <Link href="#" className="hover:text-emerald-900">
                    For Lenders
                  </Link>
                </li>
                <li>
                  <Link href="#" className="hover:text-emerald-900">
                    For Suppliers
                  </Link>
                </li>
              </ul>
            </div>
            <div>
              <h3 className="font-semibold text-emerald-900 mb-4">Resources</h3>
              <ul className="space-y-2 text-sm text-emerald-700">
                <li>
                  <Link href="#" className="hover:text-emerald-900">
                    Documentation
                  </Link>
                </li>
                <li>
                  <Link href="#" className="hover:text-emerald-900">
                    FAQ
                  </Link>
                </li>
                <li>
                  <Link href="#" className="hover:text-emerald-900">
                    Support
                  </Link>
                </li>
              </ul>
            </div>
            <div>
              <h3 className="font-semibold text-emerald-900 mb-4">Legal</h3>
              <ul className="space-y-2 text-sm text-emerald-700">
                <li>
                  <Link href="#" className="hover:text-emerald-900">
                    Privacy Policy
                  </Link>
                </li>
                <li>
                  <Link href="#" className="hover:text-emerald-900">
                    Terms of Service
                  </Link>
                </li>
                <li>
                  <Link href="#" className="hover:text-emerald-900">
                    Contact
                  </Link>
                </li>
              </ul>
            </div>
          </div>
          <div className="border-t border-emerald-200 pt-8 text-center text-sm text-emerald-700">
            <p>&copy; 2025 JuaKali Lend. All rights reserved.</p>
          </div>
        </div>
      </footer>
    </main>
  )
}
