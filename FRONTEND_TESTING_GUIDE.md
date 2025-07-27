# Frontend Authentication Testing Guide

## 🧪 How to Test the Vue.js Authentication System

### **Step 1: Access the Application**
- Open your browser and go to: `http://localhost:5173/`
- You should see the home page with authentication status

### **Step 2: Test Login**
1. Click "Login to Test Authentication" or go to: `http://localhost:5173/login`
2. You'll see the login form with demo credentials pre-filled
3. Use one of these accounts:

#### **Admin Login:**
- **Email:** `admin@cod.test`
- **Password:** `password`
- **Expected Result:** Redirected to `/admin/dashboard`

#### **Affiliate Login:**
- **Email:** `affiliate@cod.test`
- **Password:** `password`
- **Expected Result:** Redirected to `/affiliate/dashboard`

### **Step 3: Test Dashboards**

#### **Admin Dashboard Features:**
- ✅ Welcome message with user name
- ✅ Statistics cards (Affiliates, Orders, Revenue, Pending)
- ✅ Quick action buttons (disabled based on permissions)
- ✅ User information display
- ✅ Logout button

#### **Affiliate Dashboard Features:**
- ✅ Welcome message with user name
- ✅ Personal statistics (Orders, Commissions, Earnings)
- ✅ Quick action buttons (disabled based on permissions)
- ✅ Recent orders table
- ✅ Account information
- ✅ Logout button

### **Step 4: Test Role-Based Access**

#### **As Admin:**
1. Try accessing: `http://localhost:5173/affiliate/dashboard`
2. **Expected:** Should be redirected to `/unauthorized`

#### **As Affiliate:**
1. Try accessing: `http://localhost:5173/admin/dashboard`
2. **Expected:** Should be redirected to `/unauthorized`

### **Step 5: Test Navigation**

#### **Navigation Menu:**
- ✅ Home link works
- ✅ Login link (when not authenticated)
- ✅ Admin section (when logged in as admin)
- ✅ Affiliate section (when logged in as affiliate)

#### **User Profile Dropdown:**
- ✅ Shows correct user name and role
- ✅ Profile link
- ✅ Settings link
- ✅ Logout functionality

### **Step 6: Test Authentication Persistence**
1. Login with any account
2. Refresh the page (F5)
3. **Expected:** Should remain logged in and stay on the same page

### **Step 7: Test Logout**
1. Click the "Logout" button in the dashboard or user profile
2. **Expected:** Redirected to `/login` page
3. **Expected:** Authentication status cleared

### **Step 8: Test Route Protection**
1. Logout completely
2. Try to access: `http://localhost:5173/admin/dashboard`
3. **Expected:** Redirected to `/login`

### **Step 9: Test Language Switching**
1. Login with any account
2. Click the language switcher (🌐) in the navbar
3. Switch between English, French, and Arabic
4. **Expected:** Interface language changes, authentication persists

### **Step 10: Test Error Handling**
1. Go to login page
2. Enter wrong credentials: `wrong@email.com` / `wrongpassword`
3. **Expected:** Error message displayed
4. **Expected:** Form remains accessible for retry

## 🔍 **What to Look For:**

### **✅ Working Features:**
- Login form accepts demo credentials
- Successful login redirects to appropriate dashboard
- Dashboard shows user-specific content
- Role-based access control works
- Navigation updates based on authentication status
- Logout clears authentication and redirects
- Route protection prevents unauthorized access
- Authentication persists across page refreshes
- Language switching works with authentication

### **❌ Common Issues to Check:**
- White screen → Check browser console for JavaScript errors
- Login doesn't work → Check network tab for API call failures
- Wrong redirects → Check route configuration
- Missing translations → Check language files
- Permission errors → Check user roles and permissions

## 🛠️ **Troubleshooting:**

### **If you see a white screen:**
1. Open browser developer tools (F12)
2. Check the Console tab for errors
3. Look for route-related errors or missing components

### **If login doesn't work:**
1. Check the Network tab in developer tools
2. Verify the API call to `/api/auth/login` returns 200
3. Check if Laravel server is running on `http://127.0.0.1:8000`

### **If redirects don't work:**
1. Check browser console for router errors
2. Verify the route exists in the application
3. Check authentication store state

## 📱 **Mobile Testing:**
- Test on mobile devices or browser mobile view
- Verify responsive design works
- Check touch interactions for login and navigation

## 🎯 **Success Criteria:**
- ✅ Can login with demo credentials
- ✅ Dashboards load without errors
- ✅ Role-based access control works
- ✅ Navigation reflects authentication status
- ✅ Logout works properly
- ✅ Route protection prevents unauthorized access
- ✅ Authentication persists across refreshes
- ✅ No JavaScript errors in console

Your Vue.js authentication system is working correctly if all these tests pass! 🚀
