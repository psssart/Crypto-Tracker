<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Password reset</title>
</head>
<body style="margin: 0; padding: 0; background-image: url('{{ asset('images/app-main-theme.png') }}'); background-size: cover; background-position: center; font-family: Arial, sans-serif;">

<div style="background-color: rgba(255, 255, 255, 0.9); padding: 40px; max-width: 600px; margin: 60px auto; border-radius: 12px; box-shadow: 0 0 15px rgba(0,0,0,0.2); text-align: center;">
    <h1 style="color: #222;">Hey, {{ $user->name ?? 'User' }}!</h1>
    <p style="font-size: 16px; color: #444;">
        You are receiving this email because a request has been made to reset the password for your account.
    </p>
    <a href="{{ $url }}" style="display: inline-block; padding: 12px 24px; margin-top: 20px; background-color: #28a745; color: #fff; text-decoration: none; border-radius: 6px; font-weight: bold;">
        Reset password
    </a>
    <p style="margin-top: 30px; font-size: 14px; color: #666;">
        If you did not request a password change, simply ignore this email.
    </p>
    <hr style="margin: 30px 0; border: none; border-top: 1px solid #ccc;">
    <p style="font-size: 12px; color: #aaa;">
        Thank you,<br>CryptoTracker team
    </p>
</div>

</body>
</html>
