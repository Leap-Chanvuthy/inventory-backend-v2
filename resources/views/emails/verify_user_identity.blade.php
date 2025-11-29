<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Account</title>
    <style>
        body {
            background-color: #f4f4f7;
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 0;
            color: #333;
        }
        .email-container {
            max-width: 600px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        .header {
            background: rgb(99, 79, 209);
            padding: 30px;
            text-align: center;
            color: #fff;
            font-size: 22px;
            font-weight: bold;
        }
        .content {
            padding: 30px;
            line-height: 1.6;
            font-size: 15px;
        }
        .button {
            display: inline-block;
            margin-top: 25px;
            padding: 12px 25px;
            background-color: #1a73e8;
            color: #fff !important;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
        }
        .password-box {
            background: #f1f5f9;
            padding: 12px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 15px;
            margin-top: 10px;
        }
        .footer {
            text-align: center;
            padding: 20px;
            color: #777;
            font-size: 13px;
        }
    </style>
</head>

<body>
<div class="email-container">

    <!-- Header -->
    <div class="header">
        {{ $appName }} – Account Verification
    </div>

    <!-- Content -->
    <div class="content">
        <p>Hello <strong>{{ $name }}</strong>,</p>

        <p>
            An administrator has created an account for you on <strong>{{ $appName }}</strong>.
            Before you can access the system, please verify your identity and set up your login.
        </p>

        <p>Your login email:</p>
        <div class="password-box">{{ $email }}</div>

        <p>Your temporary password:</p>
        <div class="password-box">{{ $temporaryPassword }}</div>

        <p>
            To complete your registration, click the button below to verify your account:
        </p>

        <p>If the button does not work, Please contact our support team. Please login via this link.</p>
        <p style="font-size: 13px; color: #555;">
            <a href="{{ $appFrontendUrl }}">{{ $appFrontendUrl }}</a>
        </p>

        <p style="margin-top: 25px;">
            If you did not expect this email, please ignore it.
        </p>

        <p>Thank you,<br>{{ $appName }} Team</p>
    </div>

    <!-- Footer -->
    <div class="footer">
        &copy; {{ date('Y') }} {{ $appName }}. All rights reserved.
    </div>

</div>
</body>
</html>
