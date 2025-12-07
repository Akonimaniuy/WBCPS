<!-- Forgot Password Modal Container -->
<div id="forgotPasswordModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center p-4 z-50 hidden">
    <div class="bg-white text-gray-900 rounded-xl p-8 max-w-sm w-full relative shadow-2xl">
        <!-- Close Button -->
        <button id="closeForgotPasswordModal" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>

        <div id="fpStep1">
            <h2 class="text-2xl font-bold text-center mb-2">Forgot Password</h2>
            <p class="text-center text-gray-600 mb-6">Enter your email to receive a reset code.</p>
            <div id="fpError1" class="p-3 mb-4 rounded-lg text-sm bg-red-100 text-red-700 hidden"></div>
            <form id="forgotPasswordForm" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" name="email" id="fp_email" class="form-input" placeholder="example@gmail.com" required>
                </div>
                <button type="submit" id="sendOtpBtn" class="w-full bg-yellow-400 text-gray-900 px-4 py-3 font-bold rounded-lg hover:bg-yellow-500 shadow-md">Send Code</button>
            </form>
        </div>

        <div id="fpStep2" class="hidden">
            <h2 class="text-2xl font-bold text-center mb-2">Verify Code</h2>
            <p class="text-center text-gray-600 mb-6">A 6-digit code was sent to your email.</p>
            <div id="fpError2" class="p-3 mb-4 rounded-lg text-sm bg-red-100 text-red-700 hidden"></div>
            <form id="verifyOtpForm" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700">OTP Code</label>
                    <input type="text" name="otp" id="fp_otp" class="form-input" placeholder="••••••" required maxlength="6">
                </div>
                <button type="submit" id="verifyOtpBtn" class="w-full bg-yellow-400 text-gray-900 px-4 py-3 font-bold rounded-lg hover:bg-yellow-500 shadow-md">Verify</button>
            </form>
        </div>

        <div id="fpStep3" class="hidden">
            <h2 class="text-2xl font-bold text-center mb-2">Reset Password</h2>
            <p class="text-center text-gray-600 mb-6">Enter your new password.</p>
            <div id="fpError3" class="p-3 mb-4 rounded-lg text-sm bg-red-100 text-red-700 hidden"></div>
            <form id="resetPasswordForm" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700">New Password</label>
                    <input type="password" name="password" class="form-input" placeholder="••••••••" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                    <input type="password" name="password_confirm" class="form-input" placeholder="••••••••" required>
                </div>
                <button type="submit" id="resetPasswordBtn" class="w-full bg-yellow-400 text-gray-900 px-4 py-3 font-bold rounded-lg hover:bg-yellow-500 shadow-md">Reset Password</button>
            </form>
        </div>

        <div id="fpSuccess" class="hidden text-center">
            <h2 class="text-2xl font-bold text-green-600 mb-4">Success!</h2>
            <p class="text-gray-700 mb-6">Your password has been reset. You can now log in with your new password.</p>
            <button id="fpReturnToLoginBtn" class="w-full bg-gray-800 text-white px-4 py-3 font-bold rounded-lg hover:bg-gray-900 shadow-md">Return to Login</button>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const forgotPasswordModal = document.getElementById('forgotPasswordModal');
    const closeForgotPasswordModal = document.getElementById('closeForgotPasswordModal');

    const fpStep1 = document.getElementById('fpStep1');
    const fpStep2 = document.getElementById('fpStep2');
    const fpStep3 = document.getElementById('fpStep3');
    const fpSuccess = document.getElementById('fpSuccess');

    const forgotPasswordForm = document.getElementById('forgotPasswordForm');
    const verifyOtpForm = document.getElementById('verifyOtpForm');
    const resetPasswordForm = document.getElementById('resetPasswordForm');

    const sendOtpBtn = document.getElementById('sendOtpBtn');
    const verifyOtpBtn = document.getElementById('verifyOtpBtn');
    const resetPasswordBtn = document.getElementById('resetPasswordBtn');
    const fpReturnToLoginBtn = document.getElementById('fpReturnToLoginBtn');

    const fpError1 = document.getElementById('fpError1');
    const fpError2 = document.getElementById('fpError2');
    const fpError3 = document.getElementById('fpError3');

    const showFpError = (step, message) => {
        const errorDiv = document.getElementById(`fpError${step}`);
        errorDiv.textContent = message;
        errorDiv.classList.remove('hidden');
    };

    const hideFpErrors = () => {
        fpError1.classList.add('hidden');
        fpError2.classList.add('hidden');
        fpError3.classList.add('hidden');
    };

    const switchFpStep = (step) => {
        hideFpErrors();
        [fpStep1, fpStep2, fpStep3, fpSuccess].forEach(s => s.classList.add('hidden'));
        document.getElementById(`fpStep${step}`)?.classList.remove('hidden');
        if (step === 'Success') fpSuccess.classList.remove('hidden');
    };

    if (closeForgotPasswordModal) {
        closeForgotPasswordModal.addEventListener('click', () => forgotPasswordModal.classList.add('hidden'));
    }

    // Handle Send OTP
    forgotPasswordForm.addEventListener('submit', (e) => {
        e.preventDefault();
        sendOtpBtn.disabled = true;
        sendOtpBtn.textContent = 'Sending...';
        const formData = new FormData(forgotPasswordForm);
        formData.append('ajax', 'true');

        fetch('send_otp.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    switchFpStep(2);
                } else {
                    showFpError(1, data.message || 'An unknown error occurred.');
                }
            }).finally(() => {
                sendOtpBtn.disabled = false;
                sendOtpBtn.textContent = 'Send Code';
            });
    });

    // Handle Verify OTP
    verifyOtpForm.addEventListener('submit', (e) => {
        e.preventDefault();
        verifyOtpBtn.disabled = true;
        verifyOtpBtn.textContent = 'Verifying...';
        const formData = new FormData(verifyOtpForm);
        formData.append('action', 'verify_otp');
        formData.append('ajax', 'true');

        fetch('update_password.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    switchFpStep(3);
                } else {
                    showFpError(2, data.message || 'An unknown error occurred.');
                }
            }).finally(() => {
                verifyOtpBtn.disabled = false;
                verifyOtpBtn.textContent = 'Verify';
            });
    });

    // Handle Reset Password
    resetPasswordForm.addEventListener('submit', (e) => {
        e.preventDefault();
        resetPasswordBtn.disabled = true;
        resetPasswordBtn.textContent = 'Resetting...';
        const formData = new FormData(resetPasswordForm);
        formData.append('action', 'reset_password');
        formData.append('ajax', 'true');

        fetch('update_password.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    switchFpStep('Success');
                } else {
                    showFpError(3, data.message || 'An unknown error occurred.');
                }
            }).finally(() => {
                resetPasswordBtn.disabled = false;
                resetPasswordBtn.textContent = 'Reset Password';
            });
    });

    fpReturnToLoginBtn.addEventListener('click', () => {
        forgotPasswordModal.classList.add('hidden');
        document.getElementById('authModal').classList.remove('hidden');
        switchFpStep(1); // Reset for next time
    });
});
</script>