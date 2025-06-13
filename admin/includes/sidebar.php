<div class="w-64 bg-white shadow-sm">
    <div class="h-16 flex items-center px-6">
        <h1 class="text-xl font-bold text-gray-900">Aquanest Admin</h1>
    </div>
    
    <nav class="mt-4">
        <a href="index.php" 
           class="flex items-center px-6 py-3 text-sm <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-50' ?>">
            <i class="fas fa-home w-5"></i>
            <span class="ml-3">Dashboard</span>
        </a>
        
        <a href="orders.php" 
           class="flex items-center px-6 py-3 text-sm <?= basename($_SERVER['PHP_SELF']) === 'orders.php' ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-50' ?>">
            <i class="fas fa-shopping-cart w-5"></i>
            <span class="ml-3">Orders</span>
        </a>
        
        <a href="products.php" 
           class="flex items-center px-6 py-3 text-sm <?= basename($_SERVER['PHP_SELF']) === 'products.php' ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-50' ?>">
            <i class="fas fa-box w-5"></i>
            <span class="ml-3">Products</span>
        </a>
        
        <a href="subscriptions.php" 
           class="flex items-center px-6 py-3 text-sm <?= basename($_SERVER['PHP_SELF']) === 'subscriptions.php' ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-50' ?>">
            <i class="fas fa-sync w-5"></i>
            <span class="ml-3">Subscriptions</span>
        </a>
        
        <a href="couriers.php" 
           class="flex items-center px-6 py-3 text-sm <?= basename($_SERVER['PHP_SELF']) === 'couriers.php' ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-50' ?>">
            <i class="fas fa-truck w-5"></i>
            <span class="ml-3">Couriers</span>
        </a>
        
        <a href="customers.php" 
           class="flex items-center px-6 py-3 text-sm <?= basename($_SERVER['PHP_SELF']) === 'customers.php' ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-50' ?>">
            <i class="fas fa-users w-5"></i>
            <span class="ml-3">Customers</span>
        </a>
        
        <a href="reports.php" 
           class="flex items-center px-6 py-3 text-sm <?= basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-50' ?>">
            <i class="fas fa-chart-bar w-5"></i>
            <span class="ml-3">Reports</span>
        </a>
        
        <a href="settings.php" 
           class="flex items-center px-6 py-3 text-sm <?= basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-50' ?>">
            <i class="fas fa-cog w-5"></i>
            <span class="ml-3">Settings</span>
        </a>
        
        <a href="?action=logout" 
           class="flex items-center px-6 py-3 text-sm text-red-600 hover:bg-red-50">
            <i class="fas fa-sign-out-alt w-5"></i>
            <span class="ml-3">Logout</span>
        </a>
    </nav>
</div>
