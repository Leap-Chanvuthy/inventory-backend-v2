<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Inventory Reset Token</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            color: #333;
            background-color: #f9f9f9;
        }

        .purple {
            color: #605BFF;
        }

        .container {
            margin: 20px auto;
            width: 100%;
            max-width: 600px;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            line-height: 1.6;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .header {
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .header a {
            font-size: 1.4em;
            color: #000;
            text-decoration: none;
            font-weight: 600;
        }

        .content p {
            margin: 10px 0;
        }

        .code {
            display: inline-block;
            width: 100%;
            text-align: center;
            font-size: 32px;
            font-weight: 600;
            letter-spacing: 10px;
            color: #fff;
            background: linear-gradient(to right, #00bc69, #00bca8);
            padding: 15px 0;
            border-radius: 6px;
            margin: 20px 0;
        }

        .footer {
            color: #aaa;
            font-size: 0.8em;
            line-height: 1.4;
            font-weight: 300;
            margin-top: 20px;
        }

        .email-info {
            color: #666666;
            font-weight: 400;
            font-size: 13px;
            line-height: 18px;
            padding-bottom: 6px;
            text-align: center;
            margin-top: 10px;
        }

        .email-info a {
            text-decoration: none;
            color: #00bc69;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <!-- <img 
                src="{{'http://localhost:8000'}}/images/landscape-logo.png"
                alt="Loukdo Logo" 
                style="height: 60px; margin-bottom: 10px;"
            /> -->
            <a>Verify Your Account Identity</a>
        </div>

        <p><strong>Dear Valued User,</strong></p>

        <p>
            We have received a request to reset your password for your Loukdo account. For security purposes, please use the following reset linkk to reset your password:
        </p>

        <a
            href="{{ env('APP_FRONTEND_URL', 'http://localhost:5173') }}/auth/reset-password?token={{ $token }}&email={{ $email }}"
            target="_blank">
            Reset Password
        </a>

        <p style="font-size: 0.9em;">
            <strong>This reset token is valid for 60 minutes.</strong><br /><br />
            If you did not request a password reset, please disregard this email. Do not share this token with anyone.<br /><br />
            <strong>Thank you for using {{ config('app.name') }} App.</strong><br /><br />
            Best regards,<br />
            <strong>Inventory App</strong>
        </p>

        <hr style="border: none; border-top: 0.5px solid #ddd" />

        <div class="footer">
            <p>This email cannot receive replies.</p>
            <p>For more information about {{ config('app.name') }} and your account, visit <a href="#">Our Help Center</a>.</p>
        </div>
    </div>

    <div class="email-info">
        &copy; {{ now()->year }} {{ config('app.name') }}. All rights reserved.
    </div>
</body>

</html>