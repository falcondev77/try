<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/functions.php';
bootstrapSettings();

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo h(SITE_NAME); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body.landing {
            background: #0a0a0a;
            color: #f5f5f5;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .landing-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 28px 48px;
            position: relative;
            z-index: 10;
        }

        .landing-logo {
            font-size: 18px;
            font-weight: 600;
            letter-spacing: -0.3px;
            color: #f5f5f5;
            text-decoration: none;
        }

        .landing-nav-links {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .btn-nav-login {
            display: inline-block;
            padding: 10px 22px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            color: #a3a3a3;
            transition: color 0.2s;
        }

        .btn-nav-login:hover {
            color: #f5f5f5;
        }

        .btn-nav-register {
            display: inline-block;
            padding: 10px 22px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            background: #f5f5f5;
            color: #0a0a0a;
            transition: background 0.2s, transform 0.15s;
        }

        .btn-nav-register:hover {
            background: #e5e5e5;
            transform: translateY(-1px);
        }

        .landing-hero {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 80px 24px 120px;
            position: relative;
        }

        .landing-hero::before {
            content: '';
            position: absolute;
            top: -100px;
            left: 50%;
            transform: translateX(-50%);
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.08) 0%, transparent 70%);
            pointer-events: none;
        }

        .landing-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 100px;
            padding: 6px 16px;
            font-size: 13px;
            color: #a3a3a3;
            margin-bottom: 40px;
            letter-spacing: 0.2px;
        }

        .landing-badge-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #22c55e;
            animation: pulse-dot 2s infinite;
        }

        @keyframes pulse-dot {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        .landing-headline {
            font-size: clamp(42px, 7vw, 80px);
            font-weight: 700;
            line-height: 1.08;
            letter-spacing: -2px;
            margin: 0 0 24px;
            color: #f5f5f5;
            max-width: 800px;
        }

        .landing-headline span {
            color: #3b82f6;
        }

        .landing-sub {
            font-size: clamp(16px, 2vw, 19px);
            color: #737373;
            max-width: 480px;
            line-height: 1.65;
            margin: 0 0 56px;
            font-weight: 400;
        }

        .landing-cta {
            display: flex;
            gap: 14px;
            align-items: center;
            flex-wrap: wrap;
            justify-content: center;
        }

        .btn-primary-lg {
            display: inline-block;
            padding: 16px 36px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            background: #3b82f6;
            color: #fff;
            letter-spacing: -0.1px;
            transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
            box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.4);
        }

        .btn-primary-lg:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(59, 130, 246, 0.3);
        }

        .btn-secondary-lg {
            display: inline-block;
            padding: 16px 36px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 500;
            text-decoration: none;
            background: transparent;
            color: #a3a3a3;
            border: 1px solid rgba(255, 255, 255, 0.12);
            transition: border-color 0.2s, color 0.2s, transform 0.15s;
            letter-spacing: -0.1px;
        }

        .btn-secondary-lg:hover {
            border-color: rgba(255, 255, 255, 0.3);
            color: #f5f5f5;
            transform: translateY(-2px);
        }

        .landing-features {
            display: flex;
            gap: 32px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 96px;
            padding: 0 24px 80px;
        }

        .feature-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            text-align: center;
            max-width: 180px;
        }

        .feature-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .feature-label {
            font-size: 13px;
            color: #737373;
            font-weight: 400;
            line-height: 1.5;
        }

        .feature-title {
            font-size: 14px;
            font-weight: 600;
            color: #d4d4d4;
        }

        .landing-divider {
            width: 1px;
            height: 60px;
            background: rgba(255, 255, 255, 0.08);
            align-self: center;
        }

        @media (max-width: 640px) {
            .landing-nav {
                padding: 20px 24px;
            }
            .landing-divider {
                display: none;
            }
            .btn-primary-lg,
            .btn-secondary-lg {
                width: 100%;
                text-align: center;
            }
            .landing-cta {
                flex-direction: column;
                width: 100%;
                max-width: 320px;
            }
        }
    </style>
</head>
<body class="landing">

    <nav class="landing-nav">
        <a href="index.php" class="landing-logo"><?php echo h(SITE_NAME); ?></a>
        <div class="landing-nav-links">
            <a href="login.php" class="btn-nav-login">Accedi</a>
            <a href="register.php" class="btn-nav-register">Registrati</a>
        </div>
    </nav>

    <section class="landing-hero">
        <div class="landing-badge">
            <span class="landing-badge-dot"></span>
            Piattaforma di cashback con link affiliati
        </div>

        <h1 class="landing-headline">
            Guadagna dai<br><span>tuoi acquisti</span>
        </h1>

        <p class="landing-sub">
            Converti ogni link Amazon in un'opportunità. Accumula punti, ottieni premi e trasforma lo shopping in un vantaggio concreto.
        </p>

        <div class="landing-cta">
            <a href="register.php" class="btn-primary-lg">Inizia gratuitamente</a>
            <a href="login.php" class="btn-secondary-lg">Hai già un account?</a>
        </div>
    </section>

    <div class="landing-features">
        <div class="feature-item">
            <div class="feature-icon">&#128279;</div>
            <div class="feature-title">Incolla il link</div>
            <div class="feature-label">Copia qualsiasi link Amazon, corto o lungo</div>
        </div>
        <div class="landing-divider"></div>
        <div class="feature-item">
            <div class="feature-icon">&#10024;</div>
            <div class="feature-title">Converti</div>
            <div class="feature-label">Il sistema aggiunge automaticamente il tag affiliato</div>
        </div>
        <div class="landing-divider"></div>
        <div class="feature-item">
            <div class="feature-icon">&#127381;</div>
            <div class="feature-title">Accumula punti</div>
            <div class="feature-label">Ogni acquisto ti porta punti da riscattare</div>
        </div>
        <div class="landing-divider"></div>
        <div class="feature-item">
            <div class="feature-icon">&#127873;</div>
            <div class="feature-title">Ottieni premi</div>
            <div class="feature-label">Riscatta i tuoi punti in premi e sconti</div>
        </div>
    </div>

</body>
</html>
