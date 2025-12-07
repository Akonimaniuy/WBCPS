<?php
// This file is designed to be included in other pages.
// It checks for an authentication message in the session.
$auth_message = $_SESSION['auth_message'] ?? '';
$auth_message_type = $_SESSION['auth_message_type'] ?? '';
$current_action = $_SESSION['auth_action'] ?? 'login';

// Clear the session variables after displaying them
unset($_SESSION['auth_message'], $_SESSION['auth_message_type'], $_SESSION['auth_action']);
?>

<!-- Modal Container -->
<div id="authModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center p-4 z-50 hidden">
    <div class="bg-white text-gray-900 rounded-xl p-8 max-w-sm w-full relative shadow-2xl">
        <!-- Close Button -->
        <button id="closeAuthModal" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
        
        <h2 class="text-2xl font-bold text-center mb-6">Career Pathway</h2>
        
        <!-- Message Display -->
        <?php if (!empty($auth_message)): ?>
            <div class="p-3 mb-4 rounded-lg text-sm
                <?php echo $auth_message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                <?php echo $auth_message; ?>
            </div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="flex justify-center mb-6 space-x-2 p-1 bg-gray-200 rounded-lg">
            <button id="loginTab" class="flex-1 px-4 py-2 rounded-lg font-medium">Login</button>
            <button id="registerTab" class="flex-1 px-4 py-2 rounded-lg font-medium">Register</button>
        </div>

        <!-- Login Form -->
         <form id="loginForm" class="space-y-6" action="login.php" method="POST">
            <input type="hidden" name="action" value="login">
            <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
            <div>
                <label class="block text-sm font-medium text-gray-700">Email</label>
                <input type="email" name="login_email" class="form-input" placeholder="example@gmail.com" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Password</label>
                <input type="password" name="login_password" class="form-input" placeholder="••••••••" required>
            </div>
            <div class="text-right text-sm">
                <button type="button" id="openForgotPasswordModal" class="font-medium text-yellow-600 hover:text-yellow-500">
                    Forgot Password?
                </button>
            </div>
            <button type="submit" class="w-full bg-yellow-400 text-gray-900 px-4 py-3 font-bold rounded-lg hover:bg-yellow-500 shadow-md">Login</button>
        </form>

        <!-- Register Form (Initially Hidden) -->
        <form id="registerForm" class="space-y-6 hidden" action="login.php" method="POST">
            <input type="hidden" name="action" value="register">
            <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
            <div>
                <label class="block text-sm font-medium text-gray-700">Name</label>
                <input type="text" name="register_name" class="form-input" placeholder="Name" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Email</label>
                <input type="email" name="register_email" class="form-input" placeholder="example@gmail.com" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Password</label>
                <input type="password" name="register_password" class="form-input" placeholder="••••••••" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Confirm Password</label>
                <input type="password" name="register_confirm_password" class="form-input" placeholder="••••••••" required>
            </div>
            <button type="submit" class="w-full bg-yellow-400 text-gray-900 px-4 py-3 font-bold rounded-lg hover:bg-yellow-500 shadow-md">Register</button>
        </form>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        const authModal = document.getElementById('authModal');
        const openAuthModalBtns = document.querySelectorAll('.openAuthModalBtn');
        const openForgotPasswordModalBtn = document.getElementById('openForgotPasswordModal');
        const closeAuthModalBtn = document.getElementById('closeAuthModal');

        const loginTab = document.getElementById('loginTab');
        const registerTab = document.getElementById('registerTab');
        const loginForm = document.getElementById('loginForm');
        const registerForm = document.getElementById('registerForm');

        const showLogin = () => {
            loginTab.classList.add('bg-gray-800', 'text-white');
            registerTab.classList.remove('bg-gray-800', 'text-white');
            loginForm.classList.remove('hidden');
            registerForm.classList.add('hidden');
        };

        const showRegister = () => {
            registerTab.classList.add('bg-gray-800', 'text-white');
            loginTab.classList.remove('bg-gray-800', 'text-white');
            registerForm.classList.remove('hidden');
            loginForm.classList.add('hidden');
        };

        loginTab.addEventListener('click', showLogin);
        registerTab.addEventListener('click', showRegister);

        openAuthModalBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                authModal.classList.remove('hidden');
            });
        });

        if (closeAuthModalBtn) {
            closeAuthModalBtn.addEventListener('click', () => {
                authModal.classList.add('hidden');
            });
        }

        if (openForgotPasswordModalBtn) {
            openForgotPasswordModalBtn.addEventListener('click', () => {
                authModal.classList.add('hidden');
                document.getElementById('forgotPasswordModal').classList.remove('hidden');
            });
        }

        // If a server-side error occurred, show the modal automatically.
        const currentAction = "<?php echo $current_action; ?>";
        const authMessage = "<?php echo $auth_message; ?>";

        if (authMessage) {
            authModal.classList.remove('hidden');
        }

        if (currentAction === 'register') {
            showRegister();
        } else {
            showLogin();
        }
    });
</script>
<style>
    .form-input {
        display: block; width: 100%; padding: 0.75rem 1rem; border-radius: 0.5rem;
        border-width: 2px; border-color: #D1D5DB; transition: all 0.3s;
    }
    .form-input:focus {
        outline: none; box-shadow: 0 0 0 2px #FACC15; border-color: transparent;
    }
</style>