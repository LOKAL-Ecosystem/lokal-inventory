<!DOCTYPE html>
<html lang="en" data-theme="lokal">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lokal Inventory - Design System Showcase</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-base-200 text-base-content min-h-screen antialiased">
    <div class="drawer lg:drawer-open">
        <input id="my-drawer-4" type="checkbox" class="drawer-toggle" />
        
        <div class="drawer-content flex flex-col min-h-screen">
            <!-- Navbar -->
            <nav class="navbar w-full bg-base-100 border-b border-base-300 px-4 flex justify-between items-center sticky top-0 z-30">
                <div class="flex items-center gap-3">
                    <label for="my-drawer-4" aria-label="open sidebar" class="btn btn-square btn-ghost lg:hidden">
                        <i data-lucide="menu" class="w-5 h-5"></i>
                    </label>
                    <span class="font-bold text-lg text-neutral flex items-center gap-2">
                        <i data-lucide="package" class="w-5 h-5 text-primary"></i>
                        Lokal Inventory
                    </span>
                </div>
                <div class="flex items-center gap-3">
                    <button class="btn btn-sm btn-ghost btn-circle">
                        <i data-lucide="bell" class="w-4 h-4"></i>
                    </button>
                    <div class="avatar placeholder">
                        <div class="bg-neutral text-neutral-content rounded-full w-8 h-8 flex items-center justify-center font-semibold text-xs">
                            US
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Main Page Content -->
            <main class="p-6 space-y-6 flex-1 max-w-7xl w-full mx-auto">
                <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-neutral">Design System Showcase</h1>
                        <p class="text-sm text-gray-500">Lokal theme color palette, DaisyUI v5, Lucide icons, and SweetAlert2 integration.</p>
                    </div>
                    <button onclick="triggerAlert()" class="btn btn-primary text-white flex items-center gap-2 shadow-xs">
                        <i data-lucide="sparkles" class="w-4 h-4"></i>
                        Test SweetAlert2
                    </button>
                </header>

                <!-- Color Palette Cards -->
                <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                    <div class="p-4 rounded-lg bg-primary text-primary-content shadow-xs">
                        <div class="text-xs uppercase font-semibold opacity-80">Primary</div>
                        <div class="text-lg font-bold">#4285F4</div>
                    </div>
                    <div class="p-4 rounded-lg bg-success text-success-content shadow-xs">
                        <div class="text-xs uppercase font-semibold opacity-80">Success</div>
                        <div class="text-lg font-bold">#0F9D58</div>
                    </div>
                    <div class="p-4 rounded-lg bg-warning text-warning-content shadow-xs">
                        <div class="text-xs uppercase font-semibold opacity-80">Warning</div>
                        <div class="text-lg font-bold">#F4B400</div>
                    </div>
                    <div class="p-4 rounded-lg bg-error text-error-content shadow-xs">
                        <div class="text-xs uppercase font-semibold opacity-80">Error</div>
                        <div class="text-lg font-bold">#DB4437</div>
                    </div>
                    <div class="p-4 rounded-lg bg-neutral text-neutral-content shadow-xs col-span-2 sm:col-span-1">
                        <div class="text-xs uppercase font-semibold opacity-80">Neutral</div>
                        <div class="text-lg font-bold">#202124</div>
                    </div>
                </section>

                <!-- Form Controls & Components -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Crisp Inputs & Switches -->
                    <div class="card bg-base-100 border border-base-300 p-6 space-y-4 shadow-xs">
                        <h2 class="text-lg font-bold text-neutral flex items-center gap-2">
                            <i data-lucide="sliders" class="w-5 h-5 text-primary"></i>
                            Form Components
                        </h2>
                        
                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-gray-600">Product Name</label>
                            <input type="text" placeholder="Enter product name..." class="input w-full px-3 py-2 text-sm rounded-md" />
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-gray-600">Category Select</label>
                            <select class="select w-full px-3 py-2 text-sm rounded-md">
                                <option disabled selected>Select category</option>
                                <option>Electronics</option>
                                <option>Groceries</option>
                                <option>Apparel</option>
                            </select>
                        </div>

                        <div class="flex items-center justify-between pt-2">
                            <span class="text-sm font-medium text-neutral">Active Stock Status</span>
                            <input type="checkbox" class="toggle" checked />
                        </div>
                    </div>

                    <!-- Buttons & Alerts Showcase -->
                    <div class="card bg-base-100 border border-base-300 p-6 space-y-4 shadow-xs">
                        <h2 class="text-lg font-bold text-neutral flex items-center gap-2">
                            <i data-lucide="layers" class="w-5 h-5 text-primary"></i>
                            Buttons & Badges
                        </h2>
                        
                        <div class="flex flex-wrap gap-2">
                            <button class="btn btn-primary text-white">Primary</button>
                            <button class="btn btn-success text-white">Success</button>
                            <button class="btn btn-warning text-neutral">Warning</button>
                            <button class="btn btn-error text-white">Error</button>
                            <button class="btn btn-neutral text-white">Neutral</button>
                        </div>

                        <div class="flex flex-wrap gap-2 pt-2">
                            <span class="badge badge-primary">Primary</span>
                            <span class="badge badge-success text-white">In Stock</span>
                            <span class="badge badge-warning text-neutral">Low Stock</span>
                            <span class="badge badge-error text-white">Out of Stock</span>
                        </div>
                    </div>
                </div>
            </main>
        </div>

        <!-- Sidebar -->
        <div class="drawer-side z-40 border-r border-base-300">
            <label for="my-drawer-4" aria-label="close sidebar" class="drawer-overlay"></label>
            <div class="flex min-h-full flex-col w-64 bg-base-100 text-base-content p-4">
                <div class="flex items-center gap-2 px-2 py-4 border-b border-base-300 mb-4">
                    <i data-lucide="boxes" class="w-6 h-6 text-primary"></i>
                    <span class="font-bold text-lg text-neutral">Lokal Admin</span>
                </div>
                <ul class="menu w-full space-y-1">
                    <li>
                        <a class="active bg-primary/10 text-primary font-medium flex items-center gap-3">
                            <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center gap-3 hover:bg-base-200">
                            <i data-lucide="package-search" class="w-4 h-4"></i>
                            Inventory
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center gap-3 hover:bg-base-200">
                            <i data-lucide="settings" class="w-4 h-4"></i>
                            Settings
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        function triggerAlert() {
            if (window.Swal) {
                window.Swal.fire({
                    title: 'Lokal Design System',
                    text: 'SweetAlert2 configured with primary color #4285F4 and custom Lokal styling!',
                    icon: 'success',
                    confirmButtonText: 'Great!'
                });
            }
        }
    </script>
</body>

</html>