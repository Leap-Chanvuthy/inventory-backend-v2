<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Password Reset Successful</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            color: #333;
            background-color: #fff;
        }

        .container {
            margin: 0 auto;
            width: 100%;
            max-width: 600px;
            padding: 20px;
            line-height: 1.8;
        }

        .header {
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        .header a {
            font-size: 1.4em;
            color: #000;
            text-decoration: none;
            font-weight: 600;
        }

        .content {
            margin-top: 20px;
        }

        .success-message {
            font-size: 1.2em;
            font-weight: bold;
            color: #28a745;
            text-align: center;
        }

        .footer {
            margin-top: 40px;
            color: #aaa;
            font-size: 0.8em;
            text-align: center;
            line-height: 1.5;
        }

        .email-info {
            color: #666;
            font-weight: 400;
            font-size: 13px;
            line-height: 18px;
            padding-bottom: 6px;
        }

        .email-info a {
            text-decoration: none;
            color: #00bc69;
        }

        hr {
            border: none;
            border-top: 0.5px solid #ddd;
            margin: 40px 0;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <a>Password Reset Successful</a>
        </div>

        <div class="content">
            <p><strong>Dear {{ $user->name ?? 'User' }},</strong></p>

            <p>We’re just letting you know that your password has been successfully reset for your <strong>Inventory App</strong> account associated with <strong>{{ $user->email }}</strong>.</p>

            <p class="success-message">
                ✅ Your password has been updated.
            </p>

            <p>If you did not request this change or believe an unauthorized person has accessed your account, please contact our support team immediately.</p>

            <p>Thank you for using <strong>Inventory App</strong>!</p>

            <p>Best regards, <br>
        </div>

        <hr />

        <div class="footer">
            <p>This email can't receive replies.</p>
            <p>
                For more information, visit our <strong>Help Center</strong> or contact support.
            </p>
        </div>
    </div>

    <div style="text-align: center">
        <div class="email-info">
            &copy; {{ now()->year }}. All rights reserved.
        </div>
    </div>
</body>

</html>
