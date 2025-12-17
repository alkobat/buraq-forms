<?php

declare(strict_types=1);

session_start();

$referenceCode = isset($_GET['ref']) ? trim($_GET['ref']) : '';

if (empty($referenceCode)) {
    header('Location: index.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تم إرسال الاستمارة بنجاح</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/forms.css" rel="stylesheet">
    
    <style>
        .confetti {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1000;
        }
        
        .confetti-piece {
            position: absolute;
            width: 10px;
            height: 10px;
            background: #667eea;
            animation: confetti-fall 3s linear forwards;
        }
        
        @keyframes confetti-fall {
            to {
                transform: translateY(100vh) rotate(360deg);
                opacity: 0;
            }
        }
    </style>
</head>
<body>
    <div class="confetti" id="confetti"></div>
    
    <header class="main-header">
        <div class="container">
            <h1>
                <i class="fas fa-check-circle"></i>
                نجاح الإرسال
            </h1>
        </div>
    </header>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card form-card fade-in">
                    <div class="card-body">
                        <div class="success-container">
                            <div class="success-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            
                            <h2 class="mb-4">تم إرسال الاستمارة بنجاح!</h2>
                            
                            <p class="lead">شكراً لك على ملء الاستمارة. تم حفظ بياناتك بنجاح.</p>
                            
                            <div class="alert alert-info mt-4">
                                <h5 class="mb-3">
                                    <i class="fas fa-barcode"></i>
                                    رمز المرجع الخاص بك
                                </h5>
                                <div class="reference-code" id="referenceCode">
                                    <?= htmlspecialchars($referenceCode) ?>
                                </div>
                                <p class="mb-0">
                                    احتفظ بهذا الرمز للرجوع إليه لاحقاً
                                </p>
                                <button type="button" 
                                        class="btn btn-primary mt-3" 
                                        onclick="copyReferenceCode()">
                                    <i class="fas fa-copy"></i>
                                    نسخ الرمز
                                </button>
                            </div>
                            
                            <div class="mt-4">
                                <p class="text-muted">
                                    <i class="fas fa-envelope"></i>
                                    سيتم إرسال نسخة من الرمز المرجعي إلى بريدك الإلكتروني
                                </p>
                            </div>
                            
                            <div class="d-flex gap-3 justify-content-center mt-5">
                                <a href="index.php" class="btn btn-primary">
                                    <i class="fas fa-list"></i>
                                    العودة للاستمارات
                                </a>
                                <button type="button" 
                                        class="btn btn-secondary" 
                                        onclick="window.print()">
                                    <i class="fas fa-print"></i>
                                    طباعة
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="text-center mt-5 mb-4">
        <p class="text-muted">
            &copy; <?= date('Y') ?> نظام تقييم الموظفين. جميع الحقوق محفوظة.
        </p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Create confetti animation
        function createConfetti() {
            const confettiContainer = document.getElementById('confetti');
            const colors = ['#667eea', '#764ba2', '#f093fb', '#4facfe', '#43e97b'];
            
            for (let i = 0; i < 50; i++) {
                const confetti = document.createElement('div');
                confetti.className = 'confetti-piece';
                confetti.style.left = Math.random() * 100 + '%';
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.animationDelay = Math.random() * 3 + 's';
                confetti.style.animationDuration = (Math.random() * 3 + 2) + 's';
                confettiContainer.appendChild(confetti);
            }
            
            setTimeout(() => {
                confettiContainer.innerHTML = '';
            }, 6000);
        }
        
        function copyReferenceCode() {
            const code = document.getElementById('referenceCode').textContent.trim();
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(code).then(() => {
                    alert('تم نسخ الرمز المرجعي بنجاح!');
                }).catch(err => {
                    fallbackCopy(code);
                });
            } else {
                fallbackCopy(code);
            }
        }
        
        function fallbackCopy(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            
            try {
                document.execCommand('copy');
                alert('تم نسخ الرمز المرجعي بنجاح!');
            } catch (err) {
                alert('فشل نسخ الرمز. يرجى نسخه يدوياً.');
            }
            
            document.body.removeChild(textarea);
        }
        
        // Start confetti animation on load
        window.addEventListener('DOMContentLoaded', createConfetti);
    </script>
</body>
</html>
