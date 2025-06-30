<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Email verification</title>
</head>
<body style="margin: 0; padding: 0; background-image: url('{{ asset('images/app-main-theme.png') }}'); background-size: cover; background-position: center; font-family: Arial, sans-serif;">

<div style="background-color: rgba(255, 255, 255, 0.9); padding: 40px; max-width: 600px; margin: 60px auto; border-radius: 12px; box-shadow: 0 0 15px rgba(0,0,0,0.2); text-align: center;">
    <h1 style="color: #222;">Welcome, {{ $username }}!</h1>
    <p style="font-size: 16px; color: #444;">
        Thank you for registration! Please verify your email to continue using our service.
    </p>
    <a href="{{ $url }}" style="display: inline-block; padding: 12px 24px; margin-top: 20px; background-color: green; color: #fff; text-decoration: none; border-radius: 6px; font-weight: bold;">
        Verify email
    </a>
    <p style="margin-top: 30px; font-size: 12px; color: #888;">
        If you have not registered, simply ignore this email.
    </p>
</div>
</body>
</html>
