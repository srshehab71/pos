<?php include 'includes/header.php'; ?>

<style>
    /* ================================
       PREMIUM LOGIN DESIGN
    ================================= */

    body {
        background:
            radial-gradient(circle at 10% 20%, rgba(124, 58, 237, 0.18), transparent 30%),
            radial-gradient(circle at 90% 80%, rgba(168, 85, 247, 0.18), transparent 30%),
            linear-gradient(135deg, #f7f4ff 0%, #eee8ff 50%, #f9f7ff 100%);
        min-height: 100vh;
    }

    .login-wrapper {
        min-height: calc(100vh - 120px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 30px 15px;
    }

    .login-card {
        width: 100%;
        max-width: 440px;
        background: rgba(255, 255, 255, 0.94);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.8);
        border-radius: 28px;
        padding: 38px;
        box-shadow:
            0 25px 70px rgba(76, 29, 149, 0.15),
            0 8px 25px rgba(0, 0, 0, 0.06);
    }

    .login-icon {
        width: 78px;
        height: 78px;
        margin: 0 auto 20px;
        border-radius: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 30px;
        background: linear-gradient(135deg, #7c3aed, #5b21b6);
        box-shadow:
            0 12px 30px rgba(124, 58, 237, 0.35);
    }

    .login-title {
        font-size: 28px;
        font-weight: 800;
        color: #24134f;
        margin-bottom: 7px;
    }

    .login-subtitle {
        color: #817798;
        font-size: 14px;
        margin-bottom: 30px;
    }

    .form-label {
        color: #3b2a5d;
        font-weight: 700;
        font-size: 14px;
        margin-bottom: 8px;
    }

    .input-group-custom {
        position: relative;
    }

    .input-icon {
        position: absolute;
        left: 17px;
        top: 50%;
        transform: translateY(-50%);
        color: #8b5cf6;
        z-index: 5;
        font-size: 15px;
    }

    .login-input {
        height: 52px;
        border-radius: 14px !important;
        border: 1px solid #e2dcef;
        background: #faf9ff;
        padding-left: 46px;
        color: #332255;
        font-size: 15px;
        transition: all 0.25s ease;
    }

    .login-input:focus {
        background: #fff;
        border-color: #8b5cf6;
        box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.12);
    }

    .login-input::placeholder {
        color: #aaa2bb;
    }

    .login-button {
        height: 54px;
        border: none;
        border-radius: 15px;
        width: 100%;
        color: #fff;
        font-size: 16px;
        font-weight: 800;
        letter-spacing: 0.2px;
        background: linear-gradient(135deg, #7c3aed, #6d28d9, #5b21b6);
        box-shadow: 0 12px 25px rgba(109, 40, 217, 0.28);
        transition: all 0.25s ease;
    }

    .login-button:hover {
        transform: translateY(-2px);
        color: #fff;
        box-shadow: 0 16px 30px rgba(109, 40, 217, 0.38);
    }

    .login-button:active {
        transform: translateY(0);
    }

    .login-footer-text {
        text-align: center;
        margin-top: 25px;
        color: #9188a2;
        font-size: 12px;
    }

    .security-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: #6d28d9;
        background: #f3edff;
        padding: 7px 13px;
        border-radius: 50px;
        font-size: 11px;
        font-weight: 700;
        margin-top: 15px;
    }

    /* ================================
       MOBILE
    ================================= */
    @media (max-width: 576px) {

        .login-wrapper {
            min-height: calc(100vh - 90px);
            padding: 20px 12px;
            align-items: center;
        }

        .login-card {
            padding: 28px 20px;
            border-radius: 23px;
            max-width: 100%;
        }

        .login-icon {
            width: 68px;
            height: 68px;
            border-radius: 20px;
            font-size: 26px;
            margin-bottom: 16px;
        }

        .login-title {
            font-size: 24px;
        }

        .login-subtitle {
            font-size: 13px;
            margin-bottom: 24px;
        }

        .login-input {
            height: 50px;
        }

        .login-button {
            height: 52px;
        }
    }
</style>

<div class="login-wrapper">

    <div class="login-card">

        <!-- Login Icon -->
        <div class="login-icon">
            <i class="fas fa-lock"></i>
        </div>

        <!-- Heading -->
        <div class="text-center">
            <h2 class="login-title">স্বাগতম 👋</h2>
            <p class="login-subtitle">
                আপনার অ্যাকাউন্টে প্রবেশ করতে লগইন করুন
            </p>
        </div>

        <!-- Login Form -->
        <form action="login_process.php" method="POST">

            <div class="mb-3">
                <label class="form-label">
                    <i class="fas fa-envelope me-1"></i> ইমেইল
                </label>

                <div class="input-group-custom">
                    <i class="fas fa-envelope input-icon"></i>

                    <input
                        type="email"
                        name="email"
                        class="form-control login-input"
                        placeholder="আপনার ইমেইল লিখুন"
                        required
                    >
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">
                    <i class="fas fa-key me-1"></i> পাসওয়ার্ড
                </label>

                <div class="input-group-custom">
                    <i class="fas fa-lock input-icon"></i>

                    <input
                        type="password"
                        name="password"
                        class="form-control login-input"
                        placeholder="আপনার পাসওয়ার্ড লিখুন"
                        required
                    >
                </div>
            </div>

            <button type="submit" class="login-button">
                <i class="fas fa-sign-in-alt me-2"></i>
                প্রবেশ করুন
            </button>

        </form>

        <div class="text-center">
            <div class="security-badge">
                <i class="fas fa-shield-alt"></i>
                নিরাপদ লগইন
            </div>
        </div>

        <div class="login-footer-text">
            আপনার তথ্য নিরাপদ ও সুরক্ষিত থাকবে
        </div>

    </div>

</div>

<?php include 'includes/footer.php'; ?>